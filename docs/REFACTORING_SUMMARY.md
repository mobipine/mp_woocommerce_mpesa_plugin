# M-Pesa Payment Gateway - Refactoring Summary

## Issues Fixed

### 1. **CRITICAL: Fatal Error in WooCommerce Store API (Main Issue)**
**Error:** `TypeError: array_merge(): Argument #2 must be of type array, null given`

**Root Cause:** The `process_payment()` method was returning `null` in error cases instead of a proper array. WooCommerce's Store API expects all payment gateways to return an array response.

**Fix:** Modified `process_payment()` method in `wc-mpesa-gateway.php` to ALWAYS return an array, even in error cases:
```php
// Before (caused fatal error):
return;

// After (proper error handling):
return array(
    'result'   => 'failure',
    'messages' => $e->getMessage(),
);
```

### 2. **Environment Parameter Not Respected**
**Issue:** The M-Pesa service was hardcoded to use the live API URL, ignoring the sandbox/live environment setting.

**Fix:** 
- Updated `getAccessToken()` method to accept and use the `$env` parameter
- Updated STK Push request to properly select sandbox or live URLs based on environment
- Ensured environment parameter is passed through all API calls

### 3. **Poor Error Handling**
**Issues:**
- No try-catch blocks to handle exceptions
- Limited validation of API responses
- Insufficient logging for debugging
- No validation of gateway configuration

**Fixes:**
- Wrapped entire `process_payment()` in try-catch block
- Added comprehensive validation for phone numbers, settings, and API responses
- Enhanced error logging with contextual information
- Added proper error messages for users
- Added cURL error handling in `MpesaService.php`

### 4. **Webhook Callback Issues**
**Issues:**
- Limited error handling for malformed callbacks
- No validation of callback structure
- Incomplete payment details storage

**Fixes:**
- Added JSON validation for incoming callbacks
- Added structure validation for required fields
- Extract and save payment details (receipt number, transaction date, phone)
- Better order status management
- Proper HTTP response codes for invalid requests

### 5. **WooCommerce Blocks Compatibility**
**Issue:** Plugin declared as incompatible with WooCommerce blocks

**Fix:** Changed compatibility declaration to `true` since the payment gateway now properly supports the blocks checkout

## Key Improvements

### Enhanced Validation
- ✅ Phone number format validation (accepts 07XX, 01XX, 2547XX, 2541XX)
- ✅ Gateway configuration validation
- ✅ API response validation
- ✅ JSON parsing validation

### Better Logging
- ✅ All critical operations now logged with context
- ✅ Order ID included in all log messages
- ✅ API responses logged for debugging
- ✅ Error messages logged with full details

### Robust Error Handling
- ✅ Try-catch blocks around critical code
- ✅ cURL error handling
- ✅ Invalid JSON response handling
- ✅ M-Pesa API error response handling
- ✅ Graceful degradation with user-friendly error messages

### Payment Tracking
- ✅ MerchantRequestID saved to order meta
- ✅ CheckoutRequestID saved to order meta
- ✅ Receipt number saved on successful payment
- ✅ Transaction date saved
- ✅ Phone number used for payment saved
- ✅ Order notes added for audit trail

### **NEW: Payment Retry Flow**
- ✅ Failed payments redirect to order pay page
- ✅ Clear instructions shown on retry page
- ✅ One-click retry button on thank you page
- ✅ Order status set to "pending" on failure (allowing retry)
- ✅ Visual feedback for different payment states
- ✅ Helpful tips displayed before payment retry

## Testing Checklist

### Basic Functionality
- [ ] Place order with M-Pesa as payment method
- [ ] Verify STK push is sent to phone
- [ ] Complete payment on phone
- [ ] Verify order status updates to "Processing" or "Completed"
- [ ] Check order notes for payment details

### Error Scenarios
- [ ] Try with invalid phone number format
- [ ] Try without phone number
- [ ] Cancel payment on phone (verify order status updates to "Failed")
- [ ] Try with incorrect gateway configuration
- [ ] Verify error messages are user-friendly

### Environment Testing
- [ ] Test in sandbox mode
- [ ] Test in live mode
- [ ] Verify correct API endpoints are used

### Blocks Checkout
- [ ] Test checkout using WooCommerce blocks
- [ ] Verify payment gateway appears correctly
- [ ] Complete a payment using blocks checkout

### Webhook Testing
- [ ] Verify successful payment callback updates order
- [ ] Verify failed payment callback updates order
- [ ] Check that payment details are saved to order meta
- [ ] Verify order notes are added

### **NEW: Payment Retry Flow Testing**
- [ ] Simulate a failed payment (cancel STK push on phone)
- [ ] Verify redirect to order pay page
- [ ] Check that helpful instructions are displayed
- [ ] Click "Retry M-Pesa Payment" button
- [ ] Verify new STK push is sent
- [ ] Complete payment and verify order status updates
- [ ] Check thank you page shows appropriate messages

## Files Modified

1. **`includes/wc-mpesa-gateway.php`** - Main gateway class
   - Fixed `process_payment()` return values
   - Enhanced error handling and validation
   - Improved webhook callback processing
   - Better payment reinitiation handling

2. **`mpesa/MpesaService.php`** - M-Pesa API integration
   - Added environment parameter support
   - Enhanced error handling
   - Better response validation
   - Improved logging

3. **`index.php`** - Plugin initialization
   - Enabled WooCommerce blocks compatibility

## Configuration Required

Ensure the following settings are properly configured in WooCommerce > Settings > Payments > M-Pesa:

1. **Environment**: Select "Sandbox" for testing or "Live" for production
2. **Business Shortcode**: Your M-Pesa paybill/till number
3. **App Consumer Key**: From Safaricom Daraja portal
4. **App Consumer Secret**: From Safaricom Daraja portal
5. **Online Pass Key**: From Safaricom Daraja portal
6. **Callback URL**: Should be `https://yourdomain.com/index.php/wc-api/callback`

## Debug Mode

To enable detailed logging:
1. Ensure WordPress debug mode is enabled in `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

2. Check logs at: `wp-content/debug.log`

## Notes

- All API responses are now properly logged for debugging
- Phone numbers are automatically formatted to M-Pesa requirements
- Order meta now stores complete transaction details
- Webhook callbacks are validated before processing
- The plugin is now fully compatible with WooCommerce blocks checkout

## Support

If you encounter any issues:
1. Check `wp-content/debug.log` for detailed error messages
2. Verify your M-Pesa credentials are correct
3. Ensure your callback URL is accessible from the internet
4. Test in sandbox mode first before going live

---

**Version:** 2.0
**Last Updated:** December 17, 2025
**Refactored By:** Andrew Muchiri

