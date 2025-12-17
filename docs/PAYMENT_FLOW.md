# M-Pesa Payment Flow Documentation

## Overview
This document describes the complete payment flow for the M-Pesa gateway, including the retry mechanism for failed payments.

## Payment Flow Diagram

```
┌─────────────────┐
│  Customer at    │
│  Checkout       │
└────────┬────────┘
         │
         ▼
┌─────────────────────────┐
│ Select M-Pesa Payment   │
│ Place Order             │
└────────┬────────────────┘
         │
         ▼
┌─────────────────────────┐
│ process_payment()       │
│ - Validate phone        │
│ - Validate settings     │
│ - Call M-Pesa API       │
└────────┬────────────────┘
         │
    ┌────┴────┐
    │         │
    ▼         ▼
SUCCESS    FAILURE
    │         │
    │         ▼
    │    ┌──────────────────────┐
    │    │ Order → "pending"    │
    │    │ Redirect to Pay Page │
    │    └──────────┬───────────┘
    │               │
    │               ▼
    │    ┌──────────────────────┐
    │    │ Order Pay Page       │
    │    │ - Show instructions  │
    │    │ - Show retry button  │
    │    └──────────┬───────────┘
    │               │
    │               ▼
    │    ┌──────────────────────┐
    │    │ User Clicks Pay      │
    │    │ process_payment()    │
    │    └──────────────────────┘
    │               │
    └───────┬───────┘
            │
            ▼
┌──────────────────────────┐
│ Order → "on-hold"        │
│ STK Push sent to phone   │
│ Redirect to Thank You    │
└────────┬─────────────────┘
         │
         ▼
┌──────────────────────────┐
│ Thank You Page           │
│ - Show spinner           │
│ - Poll order status      │
│ - Wait for webhook       │
└────────┬─────────────────┘
         │
    ┌────┴────┐
    │         │
    ▼         ▼
USER       USER
PAYS      CANCELS
    │         │
    ▼         ▼
┌────────┐  ┌────────┐
│Webhook │  │Webhook │
│Success │  │Failed  │
└───┬────┘  └───┬────┘
    │           │
    ▼           ▼
┌────────┐  ┌──────────────────────┐
│Order   │  │ Order → "pending"    │
│Complete│  │ Show retry button    │
└────────┘  └──────────────────────┘
```

## Detailed Flow Steps

### 1. Initial Payment Processing

**File:** `wc-mpesa-gateway.php::process_payment()`

When a customer places an order:

1. **Validation Phase**
   - Check if order exists
   - Validate phone number format
   - Validate gateway configuration (API keys, shortcode, etc.)

2. **API Call Phase**
   - Format phone number to M-Pesa format (254...)
   - Prepare STK push request
   - Call M-Pesa API via `MpesaService::stk_push()`

3. **Response Handling**
   - **Success:** 
     - Order status → `on-hold`
     - Save MerchantRequestID to order meta
     - Redirect to thank you page
   
   - **Failure:**
     - Order status → `pending`
     - Add error notice
     - Redirect to order pay page ← **NEW!**

### 2. Order Pay Page (Retry Page)

**File:** `wc-mpesa-gateway.php::before_pay_page()`

When user lands on order pay page (after failure or from email link):

**Displayed Elements:**
- Blue info box with instructions
- Helpful tips:
  - "Make sure your phone number is correct"
  - "Ensure your phone is ON and has network coverage"
  - "Check that you have sufficient M-Pesa balance"
- Standard WooCommerce "Pay for Order" form

**User Actions:**
- User can review order details
- User clicks "Place order" button
- Triggers `process_payment()` again

### 3. Thank You Page

**File:** `wc-mpesa-gateway.php::thankyou_page()`

Displays different content based on order status:

#### If Order Status = "pending" or "failed"
```html
[Yellow Warning Box]
Payment Not Completed
Your M-Pesa payment was not completed. You can try again below.

[Green Retry Button]
```

#### If Order Status = "on-hold" or "processing"
```html
[Blue Info Box]
Awaiting Payment Confirmation
Please check your phone for the M-Pesa payment prompt...

[Spinner Animation]
[JavaScript Polling Status]
```

#### If Order Status = "completed"
```html
[Green Success Box]
Payment Received!
M-Pesa Receipt Number: [RECEIPT]
```

### 4. Webhook Callback Processing

**File:** `wc-mpesa-gateway.php::webhook()`

When M-Pesa sends payment result:

#### Success Response (ResultCode = 0)
```php
- Extract payment details (receipt, date, phone)
- Save to order meta
- Add order note
- Call $order->payment_complete()
- Order status → "processing" or "completed"
```

#### Failed Response (ResultCode != 0)
```php
- Order status → "pending"  ← **NEW!**
- Add order note with failure reason
- Customer can see "Retry" button on thank you page
```

### 5. Payment Reinitiation

**File:** `wc-mpesa-gateway.php::reinitiate_payment()`

Endpoint: `/wp-json/wc/v1/reinitiate_payment`

Called via AJAX from polling script:

1. Validate order ID
2. Check order status (must be pending/failed/on-hold)
3. Call `process_payment()` again
4. Return success/failure to JavaScript

## User Experience Scenarios

### Scenario 1: Successful Payment (Happy Path)
```
1. Customer places order
2. Receives STK push on phone
3. Enters M-Pesa PIN
4. Payment completes
5. Thank you page updates → "Payment Received!"
6. Order email sent
```

### Scenario 2: User Cancels Payment
```
1. Customer places order
2. Receives STK push on phone
3. Clicks "Cancel" on phone
4. Webhook receives failure callback
5. Order status → "pending"
6. Thank you page shows "Retry Payment" button
7. User clicks retry
8. Returns to order pay page
9. User can try again
```

### Scenario 3: Network/API Error
```
1. Customer places order
2. M-Pesa API error (network issue, invalid credentials, etc.)
3. Exception caught in process_payment()
4. Order status → "pending"
5. User redirected to order pay page
6. Error message displayed
7. User can review details and retry
```

### Scenario 4: Phone Not Available
```
1. Customer places order
2. STK push sent (M-Pesa accepts request)
3. Phone is OFF or out of coverage
4. User doesn't receive prompt
5. After timeout, webhook sends failure
6. Order status → "pending"
7. User sees retry option
```

## Key Design Decisions

### Why Redirect to Order Pay Page on Failure?

**Before:**
- Failure returned to checkout page
- Cart might be empty
- User confused about what to do

**After:**
- Order is created and saved
- User can review their order
- Clear path to retry payment
- Order details preserved
- Better user experience

### Why Set Status to "pending" Instead of "failed"?

**Reasoning:**
- "Failed" status often means order is abandoned
- "Pending" status indicates payment awaiting
- WooCommerce allows payment for "pending" orders
- Sends reminder emails for pending payments
- More encouraging for users to complete payment

### Why Use "success" Result on Failure?

```php
// In catch block
return array(
    'result'   => 'success',  // ← Seems counterintuitive?
    'redirect' => $order->get_checkout_payment_url(),
);
```

**Reasoning:**
- `result` refers to checkout process, not payment
- `success` = checkout completed, order created
- Allows redirect to custom URL
- `failure` = checkout failed, stays on page
- We want to redirect, so we use `success`

## Testing the Flow

### Test Case 1: Normal Payment Failure
```bash
1. Place order
2. Cancel STK push on phone
3. Wait for webhook callback
4. Should see "Retry Payment" button
5. Click button
6. Should go to order pay page
7. Click "Place order"
8. Complete payment
9. Verify order status updates
```

### Test Case 2: API Error
```bash
1. Temporarily disable internet
2. Place order
3. Should redirect to order pay page with error
4. Re-enable internet
5. Click "Place order"
6. Complete payment
```

### Test Case 3: Invalid Phone Number
```bash
1. Enter invalid phone (e.g., 123456)
2. Place order
3. Should redirect to order pay page
4. Error shown
5. User can update billing phone
6. Retry payment
```

## Configuration Requirements

For this flow to work properly:

1. **Callback URL must be accessible**
   - Format: `https://yoursite.com/index.php/wc-api/callback`
   - Must be publicly accessible
   - Must accept POST requests

2. **WordPress Permalinks**
   - Must not be "plain"
   - Recommended: "Post name"

3. **Order Pay Page**
   - WooCommerce creates automatically
   - Usually: `/checkout/order-pay/{order-id}/`

4. **Email Notifications**
   - "Order on-hold" email includes pay link
   - "Payment pending" reminder includes pay link

## Troubleshooting

### User Not Seeing Retry Button

**Check:**
- Order status (should be "pending" or "failed")
- Payment method (should be "mpesa")
- Theme compatibility (check if thankyou hook runs)

### Webhook Not Updating Order

**Check:**
- Callback URL accessible from internet
- No firewall blocking Safaricom IPs
- WordPress debug log for errors
- Merchant Request ID saved to order

### Redirect Not Working

**Check:**
- No output before headers sent
- WooCommerce version compatibility
- Theme/plugin conflicts

## Security Considerations

1. **Nonce Validation**
   - Reinitiate payment uses nonce
   - Prevents unauthorized payment attempts

2. **Order Ownership**
   - Only logged-in user can pay their orders
   - Or valid payment URL key required

3. **Status Checks**
   - Only pending/failed/on-hold orders can be paid
   - Prevents duplicate payments

4. **Webhook Validation**
   - Validates JSON structure
   - Checks for required fields
   - Merchant Request ID must match

---

**Last Updated:** December 17, 2025
**Version:** 2.0

