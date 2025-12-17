# M-Pesa Transaction Status Query Implementation Guide

## Overview

This document provides a complete implementation guide for adding a "Check Payment Status" feature to the M-Pesa payment gateway. This feature allows users to manually verify if their payment was successful even if the webhook callback failed.

---

## Table of Contents

1. [Why This Feature?](#why-this-feature)
2. [API Endpoint Details](#api-endpoint-details)
3. [Implementation Steps](#implementation-steps)
4. [Code Implementation](#code-implementation)
5. [User Interface](#user-interface)
6. [Testing Guide](#testing-guide)
7. [Error Handling](#error-handling)
8. [Security Considerations](#security-considerations)

---

## Why This Feature?

### Problem Scenarios

1. **Network Issues**: Customer completes M-Pesa payment, but webhook fails to reach server
2. **Server Downtime**: Payment callback arrives during server maintenance
3. **Customer Uncertainty**: Customer claims they paid but order shows as pending
4. **Webhook Failure**: Safaricom's callback service has intermittent issues

### Solution

Add a "Check Payment Status" button that:
- Queries M-Pesa directly for transaction status
- Updates order if payment was actually completed
- Provides real-time feedback to customer
- Creates an audit trail

---

## API Endpoint Details

### M-Pesa STK Push Query API

**Sandbox URL:**
```
https://sandbox.safaricom.co.ke/mpesa/stkpushquery/v1/query
```

**Production URL:**
```
https://api.safaricom.co.ke/mpesa/stkpushquery/v1/query
```

### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `BusinessShortCode` | String | Yes | Your M-Pesa shortcode |
| `Password` | String | Yes | Base64 encoded string (same as STK Push) |
| `Timestamp` | String | Yes | Current timestamp (YYYYMMDDHHmmss) |
| `CheckoutRequestID` | String | Yes | From original STK Push response |

### Request Headers

```
Authorization: Bearer {access_token}
Content-Type: application/json
```

### Sample Request Body

```json
{
  "BusinessShortCode": "174379",
  "Password": "MTc0Mzc5YmZiMjc5ZjlhYTliZGJjZjE1OGU5N2RkNzFhNDY3Y2QyZTBjODkzMDU5YjEwZjc4ZTZiNzJhZGExZWQyYzkxOTIwMjQxMjE3MTI1ODU0",
  "Timestamp": "20241217125854",
  "CheckoutRequestID": "ws_CO_17122025125853261113005226"
}
```

### Response (Success)

```json
{
  "ResponseCode": "0",
  "ResponseDescription": "The service request has been accepted successfully",
  "MerchantRequestID": "92643-47073138-2",
  "CheckoutRequestID": "ws_CO_17122025125853261113005226",
  "ResultCode": "0",
  "ResultDesc": "The service request is processed successfully."
}
```

### Response (Payment Cancelled)

```json
{
  "ResponseCode": "0",
  "ResponseDescription": "The service request has been accepted successfully",
  "MerchantRequestID": "92643-47073138-2",
  "CheckoutRequestID": "ws_CO_17122025125853261113005226",
  "ResultCode": "1032",
  "ResultDesc": "Request cancelled by user"
}
```

### Result Codes

| Code | Description | Action |
|------|-------------|--------|
| 0 | Success | Mark order as paid |
| 1032 | Cancelled by user | Keep pending, allow retry |
| 1037 | Timeout (user didn't enter PIN) | Keep pending, allow retry |
| 2001 | Invalid initiator | Configuration error |
| 1 | Insufficient balance | Keep pending |
| 1001 | Unable to lock subscriber | Technical issue, retry |

---

## Implementation Steps

### Step 1: Add Query Method to MpesaService

**File:** `mpesa/MpesaService.php`

Add a new method `query_transaction_status()`:

```php
public function query_transaction_status($checkoutRequestID, $credentials, $env)
{
    $consumerKey = $credentials['consumerKey'];
    $consumerSecret = $credentials['consumerSecret'];
    $shortCode = $credentials['shortCode'];
    $passkey = $credentials['passkey'];
    
    // Get access token
    $accessToken = $this->getAccessToken($consumerKey, $consumerSecret, $env);
    
    // Generate password and timestamp
    $timestamp = date('YmdHis');
    $password = base64_encode($shortCode . $passkey . $timestamp);
    
    // Prepare request data
    $data = array(
        'BusinessShortCode' => $shortCode,
        'Password' => $password,
        'Timestamp' => $timestamp,
        'CheckoutRequestID' => $checkoutRequestID
    );
    
    // Select endpoint based on environment
    $url = ($env == 'sandbox') 
        ? 'https://sandbox.safaricom.co.ke/mpesa/stkpushquery/v1/query'
        : 'https://api.safaricom.co.ke/mpesa/stkpushquery/v1/query';
    
    // Make API call
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Authorization: Bearer ' . $accessToken
    ));
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($curl, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($curl);
    
    if (curl_errno($curl)) {
        $error = curl_error($curl);
        curl_close($curl);
        error_log("cURL Error in query_transaction_status: " . $error);
        return json_encode(array(
            'errorCode' => 'CURL_ERROR',
            'errorMessage' => $error
        ));
    }
    
    curl_close($curl);
    
    return $response;
}
```

### Step 2: Add AJAX Handler in Gateway Class

**File:** `includes/wc-mpesa-gateway.php`

Add these actions in `__construct()`:

```php
// Add transaction status query handlers
add_action('wp_ajax_mpesa_check_payment_status', array($this, 'check_payment_status'));
add_action('wp_ajax_nopriv_mpesa_check_payment_status', array($this, 'check_payment_status'));
```

Add the handler method:

```php
public function check_payment_status()
{
    try {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mpesa_check_status')) {
            throw new Exception(__('Security check failed', 'woocommerce'));
        }
        
        // Get order ID
        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        if (!$order_id) {
            throw new Exception(__('Invalid order ID', 'woocommerce'));
        }
        
        // Get order
        $order = wc_get_order($order_id);
        if (!$order) {
            throw new Exception(__('Order not found', 'woocommerce'));
        }
        
        // Verify order belongs to current user (security)
        if (get_current_user_id() !== $order->get_customer_id() && !current_user_can('manage_woocommerce')) {
            throw new Exception(__('Unauthorized access', 'woocommerce'));
        }
        
        // Get CheckoutRequestID from order meta
        $checkoutRequestID = $order->get_meta('_checkout_request_id');
        if (empty($checkoutRequestID)) {
            throw new Exception(__('Payment request ID not found. Please try making a new payment.', 'woocommerce'));
        }
        
        error_log("M-Pesa: Checking status for order #{$order_id}, CheckoutRequestID: {$checkoutRequestID}");
        
        // Get gateway credentials
        $passkey = $this->get_option('passkey');
        $shortCode = $this->get_option('shortcode');
        $consumerKey = $this->get_option('key');
        $consumerSecret = $this->get_option('secret');
        $env = $this->get_option('env');
        
        $creds = array(
            'passkey' => $passkey,
            'shortCode' => $shortCode,
            'consumerKey' => $consumerKey,
            'consumerSecret' => $consumerSecret,
            'env' => $env
        );
        
        // Query transaction status
        require_once plugin_dir_path(__DIR__) . 'mpesa/MpesaService.php';
        $mpesa_service = new MpesaService();
        $response = $mpesa_service->query_transaction_status($checkoutRequestID, $creds, $env);
        
        error_log("M-Pesa Status Query Response: " . $response);
        
        $result = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception(__('Invalid response from M-Pesa', 'woocommerce'));
        }
        
        // Check for API errors
        if (isset($result['errorCode'])) {
            throw new Exception($result['errorMessage'] ?? __('Unknown error from M-Pesa', 'woocommerce'));
        }
        
        // Process result
        $resultCode = isset($result['ResultCode']) ? $result['ResultCode'] : -1;
        $resultDesc = isset($result['ResultDesc']) ? $result['ResultDesc'] : 'Unknown';
        
        error_log("M-Pesa: Status query result - Code: {$resultCode}, Desc: {$resultDesc}");
        
        if ($resultCode === '0' || $resultCode === 0) {
            // Payment was successful!
            $merchantRequestID = $result['MerchantRequestID'] ?? '';
            
            // Check if already completed
            if (in_array($order->get_status(), array('completed', 'processing'))) {
                wp_send_json_success(array(
                    'status' => 'already_completed',
                    'message' => __('This order has already been paid and processed.', 'woocommerce')
                ));
                return;
            }
            
            // Mark as complete
            $order->add_order_note(sprintf(
                __('Payment confirmed via status query. MerchantRequestID: %s', 'woocommerce'),
                $merchantRequestID
            ));
            $order->payment_complete($merchantRequestID);
            
            error_log("M-Pesa: Order #{$order_id} marked as paid via status query");
            
            wp_send_json_success(array(
                'status' => 'paid',
                'message' => __('Payment confirmed! Your order has been updated.', 'woocommerce'),
                'redirect' => $order->get_view_order_url()
            ));
            
        } else if ($resultCode === '1032' || $resultCode === 1032) {
            // Cancelled by user
            wp_send_json_success(array(
                'status' => 'cancelled',
                'message' => __('Payment was cancelled. Please try again.', 'woocommerce')
            ));
            
        } else if ($resultCode === '1037' || $resultCode === 1037) {
            // Timeout
            wp_send_json_success(array(
                'status' => 'timeout',
                'message' => __('Payment request timed out. Please try again.', 'woocommerce')
            ));
            
        } else {
            // Other error
            wp_send_json_success(array(
                'status' => 'failed',
                'message' => sprintf(__('Payment failed: %s', 'woocommerce'), $resultDesc)
            ));
        }
        
    } catch (Exception $e) {
        error_log("M-Pesa Status Check Error: " . $e->getMessage());
        wp_send_json_error(array(
            'message' => $e->getMessage()
        ));
    }
}
```

### Step 3: Update Template with Check Status Button

**File:** `templates/order-pay-mpesa.php`

Add after the order summary section:

```php
<!-- Check Payment Status Section -->
<div class="mpesa-status-check" style="margin-top: 20px; padding: 20px; background: #f8f9fa; border-radius: 8px; border: 1px solid #dee2e6;">
    <h4 style="margin: 0 0 10px 0; color: #333; font-size: 16px;">
        🔍 <?php _e('Already Paid?', 'woocommerce'); ?>
    </h4>
    <p style="margin: 0 0 15px 0; color: #666; font-size: 14px; line-height: 1.6;">
        <?php _e('If you completed the M-Pesa payment but your order is still pending, click the button below to check your payment status.', 'woocommerce'); ?>
    </p>
    <button 
        type="button" 
        id="mpesa-check-status-btn" 
        class="button" 
        style="background: #17a2b8; color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-weight: 600; transition: all 0.3s ease;"
        data-order-id="<?php echo esc_attr($order_id); ?>"
        data-nonce="<?php echo wp_create_nonce('mpesa_check_status'); ?>"
    >
        🔄 <?php _e('Check Payment Status', 'woocommerce'); ?>
    </button>
    <div id="mpesa-status-result" style="margin-top: 15px; display: none;"></div>
</div>
```

### Step 4: Add JavaScript for Check Status Button

**File:** `assets/mpesa-checkout.js`

Add at the end of the jQuery ready function:

```javascript
// Check payment status functionality
$('#mpesa-check-status-btn').on('click', function() {
    const btn = $(this);
    const orderId = btn.data('order-id');
    const nonce = btn.data('nonce');
    const resultDiv = $('#mpesa-status-result');
    
    // Disable button and show loading
    btn.prop('disabled', true).html('⏳ Checking...');
    resultDiv.hide();
    
    $.ajax({
        url: wc_checkout_params.ajax_url,
        type: 'POST',
        data: {
            action: 'mpesa_check_payment_status',
            order_id: orderId,
            nonce: nonce
        },
        success: function(response) {
            if (response.success) {
                const data = response.data;
                let messageClass = '';
                let icon = '';
                
                switch(data.status) {
                    case 'paid':
                        messageClass = 'success';
                        icon = '✅';
                        setTimeout(function() {
                            if (data.redirect) {
                                window.location.href = data.redirect;
                            }
                        }, 2000);
                        break;
                    case 'already_completed':
                        messageClass = 'info';
                        icon = 'ℹ️';
                        break;
                    case 'cancelled':
                    case 'timeout':
                    case 'failed':
                        messageClass = 'error';
                        icon = '❌';
                        break;
                    default:
                        messageClass = 'notice';
                        icon = 'ℹ️';
                }
                
                resultDiv.html(
                    '<div class="woocommerce-' + messageClass + '" style="padding: 12px; border-radius: 4px; margin: 0;">' +
                    icon + ' ' + data.message +
                    '</div>'
                ).fadeIn();
                
            } else {
                resultDiv.html(
                    '<div class="woocommerce-error" style="padding: 12px; border-radius: 4px; margin: 0;">' +
                    '❌ ' + (response.data.message || 'Failed to check status') +
                    '</div>'
                ).fadeIn();
            }
        },
        error: function() {
            resultDiv.html(
                '<div class="woocommerce-error" style="padding: 12px; border-radius: 4px; margin: 0;">' +
                '❌ Network error. Please try again.' +
                '</div>'
            ).fadeIn();
        },
        complete: function() {
            btn.prop('disabled', false).html('🔄 Check Payment Status');
        }
    });
});
```

### Step 5: Add CSS Styling

**File:** `assets/mpesa-payment-page.css`

Add:

```css
/* Check Payment Status Section */
.mpesa-status-check {
    transition: all 0.3s ease;
}

.mpesa-status-check:hover {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

#mpesa-check-status-btn {
    transition: all 0.3s ease;
}

#mpesa-check-status-btn:hover {
    background: #138496 !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(23, 162, 184, 0.3) !important;
}

#mpesa-check-status-btn:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none !important;
}

#mpesa-status-result .woocommerce-message,
#mpesa-status-result .woocommerce-error,
#mpesa-status-result .woocommerce-info {
    animation: slideInDown 0.3s ease-out;
}

@keyframes slideInDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
```

---

## User Interface

### Visual Design

```
┌────────────────────────────────────────────┐
│ ORDER SUMMARY                              │
│ Order Number: #1234                        │
│ Phone Number: [0712345678]                 │
│ Total Amount: KSH 5,000.00                 │
└────────────────────────────────────────────┘

┌────────────────────────────────────────────┐
│ 🔍 Already Paid?                           │
│                                            │
│ If you completed the M-Pesa payment but    │
│ your order is still pending, click below.  │
│                                            │
│  [🔄 Check Payment Status]                 │
│                                            │
│  ✅ Payment confirmed! Redirecting...      │
└────────────────────────────────────────────┘

         [💳 Place Order Button]
```

---

## Testing Guide

### Test Case 1: Successful Payment (Webhook Failed)

**Steps:**
1. Place order with M-Pesa
2. Complete payment on phone
3. Manually prevent webhook (e.g., firewall block)
4. Order shows as "On Hold"
5. Click "Check Payment Status"
6. **Expected:** Order updates to "Processing/Completed"

### Test Case 2: Cancelled Payment

**Steps:**
1. Place order with M-Pesa
2. Receive STK push
3. Click "Cancel" on phone
4. Click "Check Payment Status"
5. **Expected:** Shows "Payment was cancelled"

### Test Case 3: Timeout

**Steps:**
1. Place order with M-Pesa
2. Ignore STK push (don't enter PIN)
3. Wait for timeout (usually 60 seconds)
4. Click "Check Payment Status"
5. **Expected:** Shows "Payment request timed out"

### Test Case 4: Already Completed

**Steps:**
1. Complete successful payment
2. Order already marked as complete
3. Click "Check Payment Status"
4. **Expected:** Shows "Already paid and processed"

### Test Case 5: Network Error

**Steps:**
1. Disconnect internet
2. Click "Check Payment Status"
3. **Expected:** Shows "Network error"

---

## Error Handling

### User-Facing Errors

| Scenario | Message | Action |
|----------|---------|--------|
| Payment confirmed | "Payment confirmed! Your order has been updated." | Redirect to order page |
| Already paid | "This order has already been paid." | Show info |
| Cancelled | "Payment was cancelled. Please try again." | Allow new attempt |
| Timeout | "Payment request timed out. Please try again." | Allow new attempt |
| Network error | "Network error. Please try again." | Retry button active |
| Invalid order | "Order not found." | Contact support |
| No CheckoutRequestID | "Payment request ID not found." | Make new payment |

### Developer Logging

All operations should be logged:

```php
error_log("M-Pesa: Checking status for order #{$order_id}");
error_log("M-Pesa Status Query Response: " . $response);
error_log("M-Pesa: Order #{$order_id} marked as paid via status query");
```

---

## Security Considerations

### 1. Nonce Verification

Always verify WordPress nonce:

```php
if (!wp_verify_nonce($_POST['nonce'], 'mpesa_check_status')) {
    throw new Exception('Security check failed');
}
```

### 2. User Authorization

Verify user can access the order:

```php
if (get_current_user_id() !== $order->get_customer_id() && 
    !current_user_can('manage_woocommerce')) {
    throw new Exception('Unauthorized access');
}
```

### 3. Rate Limiting

Implement rate limiting to prevent abuse:

```php
$transient_key = 'mpesa_check_' . $order_id . '_' . get_current_user_id();
if (get_transient($transient_key)) {
    throw new Exception('Please wait 30 seconds before checking again');
}
set_transient($transient_key, true, 30); // 30 seconds
```

### 4. Validate CheckoutRequestID

Ensure it exists and belongs to the order:

```php
if (empty($checkoutRequestID)) {
    throw new Exception('Payment request ID not found');
}
```

---

## Benefits of This Implementation

### For Customers

✅ **Peace of Mind**: Can verify payment status themselves  
✅ **No Support Needed**: Self-service solution  
✅ **Immediate Resolution**: Instant status update  
✅ **Transparency**: Clear feedback on payment state  

### For Business

✅ **Reduced Support Tickets**: Fewer "I paid but..." inquiries  
✅ **Better Conversion**: Customers complete uncertain payments  
✅ **Audit Trail**: All status checks logged  
✅ **Reliability**: Works even if webhooks fail  

### For Developers

✅ **Defensive Programming**: Handles webhook failures gracefully  
✅ **Better Debugging**: Clear logs for troubleshooting  
✅ **Professional Solution**: Industry best practice  
✅ **Maintainable Code**: Well-documented and structured  

---

## Deployment Checklist

- [ ] Add `query_transaction_status()` method to `MpesaService.php`
- [ ] Add `check_payment_status()` method to `wc-mpesa-gateway.php`
- [ ] Register AJAX actions in gateway constructor
- [ ] Update template with check status button
- [ ] Add JavaScript handler to `mpesa-checkout.js`
- [ ] Add CSS styling to `mpesa-payment-page.css`
- [ ] Test in sandbox environment
- [ ] Test all error scenarios
- [ ] Deploy to production
- [ ] Monitor logs for issues
- [ ] Document for users

---

## Future Enhancements

### Automatic Status Checking

Instead of manual button, automatically check status every 5 seconds after STK push:

```javascript
// Auto-check status every 5 seconds for 2 minutes
let checkCount = 0;
const maxChecks = 24; // 2 minutes (24 * 5 seconds)

const autoChecker = setInterval(function() {
    if (checkCount >= maxChecks) {
        clearInterval(autoChecker);
        return;
    }
    
    // Trigger status check
    $('#mpesa-check-status-btn').trigger('click');
    checkCount++;
}, 5000);
```

### Admin Dashboard Widget

Show pending payments that might need status verification:

```php
// Admin dashboard widget showing orders needing verification
function mpesa_pending_payments_widget() {
    $orders = wc_get_orders(array(
        'payment_method' => 'mpesa',
        'status' => 'on-hold',
        'date_created' => '>' . (time() - 3600), // Last hour
        'limit' => 10
    ));
    
    // Display list with quick "Check Status" links
}
```

### SMS Notifications

Send SMS to customer if payment verification succeeds:

```php
// After successful status check
if ($payment_verified) {
    // Send SMS notification
    send_sms_notification($phone, $order_id);
}
```

---

## Support and Troubleshooting

### Common Issues

**Issue:** "CheckoutRequestID not found"  
**Solution:** Order wasn't initiated properly. Customer should make a new payment attempt.

**Issue:** Status check always returns "pending"  
**Solution:** Payment might still be processing. Wait 30 seconds and try again.

**Issue:** Network timeout  
**Solution:** Check M-Pesa API status and credentials.

### Debug Mode

Enable detailed logging in `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Then check `wp-content/debug.log` for detailed traces.

---

## Conclusion

This implementation provides a robust, user-friendly solution for handling webhook failures in M-Pesa payments. By allowing customers to manually verify their payment status, you eliminate a major pain point and reduce support burden.

The code is production-ready, well-documented, and follows WordPress and WooCommerce best practices.

---

**Document Version:** 1.0  
**Last Updated:** December 17, 2025  
**Author:** Andrew Muchiri  
**Plugin:** M-Pesa Payment Gateway v2.0

