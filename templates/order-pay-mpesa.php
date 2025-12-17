<?php
/**
 * M-Pesa Payment Page Template
 * 
 * This template displays the enhanced M-Pesa payment interface
 * on the order pay page.
 * 
 * Available variables:
 * @var WC_Order $order
 * @var int $order_id
 * @var string $phone
 * @var float $amount
 * @var string $currency
 * @var string $logo_url
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="mpesa-payment-wrapper">
    
    <!-- Order Summary with Editable Phone -->
    <div class="mpesa-order-summary">
        <h4><?php _e('Order Summary', 'woocommerce'); ?></h4>
        <div class="mpesa-order-details">
            
            <div class="mpesa-order-detail-row">
                <span class="mpesa-order-detail-label"><?php _e('Order Number:', 'woocommerce'); ?></span>
                <span class="mpesa-order-detail-value">#<?php echo esc_html($order->get_order_number()); ?></span>
            </div>
            
            <!-- Phone Number Row - Editable Input -->
            <div class="mpesa-order-detail-row mpesa-phone-row" style="flex-direction: column; align-items: flex-start; gap: 8px;">
                <label for="billing_phone" style="font-weight: 600; color: #333; margin: 0;">
                    <?php _e('Phone Number (edit if needed):', 'woocommerce'); ?>
                </label>
                <input 
                    type="tel" 
                    id="billing_phone" 
                    name="billing_phone" 
                    value="<?php echo esc_attr($phone); ?>" 
                    required 
                    style="width: 100%; padding: 12px; border: 2px solid #00a859; border-radius: 6px; font-size: 15px; font-weight: 500; transition: all 0.3s ease;" 
                    placeholder="07XXXXXXXX or 01XXXXXXXX"
                >
                <small style="color: #666; font-size: 13px; margin-top: -4px;">
                    📱 <?php _e('This phone will receive the M-Pesa payment prompt', 'woocommerce'); ?>
                </small>
            </div>
            
            <div class="mpesa-order-detail-row">
                <span class="mpesa-order-detail-label"><?php _e('Total Amount:', 'woocommerce'); ?></span>
                <span class="mpesa-order-detail-value">
                    <?php echo esc_html($currency); ?> <?php echo esc_html(number_format($amount, 2)); ?>
                </span>
            </div>
        </div>
    </div>
    
    <!-- Payment Instructions -->
    <div class="mpesa-tips">
        <h4><?php _e('Important', 'woocommerce'); ?></h4>
        <ul>
            <li><?php _e('Make sure your phone is ON and has network coverage', 'woocommerce'); ?></li>
            <li><?php _e('You will receive an STK push on your phone', 'woocommerce'); ?></li>
            <li><?php _e('Enter your M-Pesa PIN to complete payment', 'woocommerce'); ?></li>
            <li><?php _e('Do not close this page until payment is confirmed', 'woocommerce'); ?></li>
        </ul>
    </div>
    
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

</div>

<script>
jQuery(document).ready(function($) {
    // Ensure the phone input is included in form submission
    $('form#order_review').on('submit', function() {
        var phoneValue = $('#billing_phone').val();
        console.log('M-Pesa: Submitting with phone:', phoneValue);
        
        // Make sure the phone input is part of the form
        if (!$(this).find('input[name="billing_phone"]').length) {
            $(this).append('<input type="hidden" name="billing_phone" value="' + phoneValue + '">');
        }
    });
    
    // Update button text to be clearer
    $('#place_order').text('<?php _e('Pay with M-Pesa', 'woocommerce'); ?>');
    
    // Log for debugging
    console.log('M-Pesa: Template loaded successfully');
});
</script>

