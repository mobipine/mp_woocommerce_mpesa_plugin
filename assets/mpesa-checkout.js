/**
 * M-Pesa Checkout Enhancement Script
 */
jQuery(document).ready(function($) {
    'use strict';
    
    // Function to enhance M-Pesa payment option
    function enhanceMpesaCheckout() {
        const mpesaRadio = $('input[name="payment_method"][value="mpesa"]');
        
        if (mpesaRadio.length === 0) {
            return;
        }
        
        // Add M-Pesa logo to the payment method label
        const mpesaLabel = $('label[for="payment_method_mpesa"]');
        if (mpesaLabel.length && !mpesaLabel.find('.mpesa-label-logo').length) {
            const logoUrl = mpesaCheckoutParams.logoUrl;
            mpesaLabel.prepend(
                '<img src="' + logoUrl + '" alt="M-Pesa" class="mpesa-label-logo" style="width: 30px; height: auto; margin-right: 10px; vertical-align: middle;">'
            );
        }
        
        // Style the place order button when M-Pesa is selected
        function updatePlaceOrderButton() {
            const placeOrderButton = $('#place_order');
            
            if (mpesaRadio.is(':checked')) {
                placeOrderButton.addClass('mpesa-active');
                placeOrderButton.html('💳 Pay with M-Pesa');
            } else {
                placeOrderButton.removeClass('mpesa-active');
                placeOrderButton.html(placeOrderButton.data('original-text') || 'Place order');
            }
        }
        
        // Save original button text
        const placeOrderButton = $('#place_order');
        if (!placeOrderButton.data('original-text')) {
            placeOrderButton.data('original-text', placeOrderButton.html());
        }
        
        // Update button on payment method change
        $('input[name="payment_method"]').on('change', function() {
            updatePlaceOrderButton();
        });
        
        // Initial update
        updatePlaceOrderButton();
        
        // Add smooth scroll to M-Pesa description when selected
        mpesaRadio.on('change', function() {
            if ($(this).is(':checked')) {
                const description = $(this).closest('li').find('.payment_box');
                if (description.length) {
                    $('html, body').animate({
                        scrollTop: description.offset().top - 100
                    }, 500);
                }
            }
        });
    }
    
    // Run on page load
    enhanceMpesaCheckout();
    
    // Re-run on checkout update (WooCommerce AJAX)
    $(document.body).on('updated_checkout', function() {
        enhanceMpesaCheckout();
    });
    
    // Style enhancement for order pay page
    if ($('body').hasClass('woocommerce-order-pay')) {
        // Wrap the payment button if not already wrapped
        const placeOrderBtn = $('#place_order');
        if (placeOrderBtn.length && !placeOrderBtn.parent().hasClass('mpesa-payment-button-wrapper')) {
            placeOrderBtn.wrap('<div class="mpesa-payment-button-wrapper"></div>');
            placeOrderBtn.after('<div class="mpesa-secure-badge">🔒 Secure payment powered by Safaricom M-Pesa</div>');
        }
        
        // Add focus effect to phone input
        $('#billing_phone').on('focus', function() {
            $(this).css({
                'border-color': '#008a47',
                'box-shadow': '0 0 0 3px rgba(0, 168, 89, 0.1)'
            });
        }).on('blur', function() {
            $(this).css({
                'border-color': '#00a859',
                'box-shadow': 'none'
            });
        });
        
        // Validate phone number on input
        $('#billing_phone').on('input', function() {
            const phone = $(this).val().replace(/[\s\-\+]/g, '');
            const isValid = /^(07|01)[0-9]{8}$/.test(phone) || /^(2547|2541)[0-9]{8}$/.test(phone);
            
            if (phone.length > 0 && !isValid) {
                $(this).css('border-color', '#dc3545');
            } else {
                $(this).css('border-color', '#00a859');
            }
        });
        
        // Check payment status functionality
        $('#mpesa-check-status-btn').on('click', function() {
            const btn = $(this);
            const orderId = btn.data('order-id');
            const nonce = btn.data('nonce');
            const resultDiv = $('#mpesa-status-result');
            
            console.log('M-Pesa Check Status clicked');
            console.log('Order ID:', orderId);
            console.log('Nonce:', nonce);
            
            // Validate we have the required data
            if (!orderId || !nonce) {
                console.error('Missing order ID or nonce');
                resultDiv.html(
                    '<div class="mpesa-error-msg" style="padding: 12px; border-radius: 4px; margin: 0; background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24;">' +
                    '❌ Configuration error. Please refresh the page.' +
                    '</div>'
                ).fadeIn();
                return;
            }
            
            // Disable button and show loading
            btn.prop('disabled', true).html('⏳ Checking...');
            resultDiv.hide();
            
            // Get AJAX URL - try multiple sources for compatibility
            const ajaxUrl = mpesaCheckoutParams.ajaxUrl || 
                           (typeof wc_checkout_params !== 'undefined' ? wc_checkout_params.ajax_url : '') ||
                           (typeof ajaxurl !== 'undefined' ? ajaxurl : '');
            
            console.log('AJAX URL:', ajaxUrl);
            console.log('mpesaCheckoutParams:', typeof mpesaCheckoutParams !== 'undefined' ? mpesaCheckoutParams : 'undefined');
            
            if (!ajaxUrl) {
                console.error('AJAX URL not found');
                resultDiv.html(
                    '<div class="mpesa-error-msg" style="padding: 12px; border-radius: 4px; margin: 0; background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24;">' +
                    '❌ Configuration error. Please refresh the page.' +
                    '</div>'
                ).fadeIn();
                btn.prop('disabled', false).html('🔄 Check Payment Status');
                return;
            }
            
            console.log('Sending AJAX request to:', ajaxUrl);
            
            const ajaxData = {
                action: 'mpesa_check_payment_status',
                order_id: orderId,
                nonce: nonce
            };
            console.log('AJAX Data:', ajaxData);
            
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: ajaxData,
                success: function(response) {
                    console.log('AJAX Success Response:', response);
                    if (response.success) {
                        const data = response.data;
                        let messageClass = '';
                        let icon = '';
                        
                        switch(data.status) {
                            case 'paid':
                                messageClass = 'mpesa-success-msg';
                                icon = '✅';
                                setTimeout(function() {
                                    if (data.redirect) {
                                        window.location.href = data.redirect;
                                    }
                                }, 2000);
                                break;
                            case 'already_completed':
                                messageClass = 'mpesa-info-msg';
                                icon = 'ℹ️';
                                break;
                            case 'cancelled':
                            case 'timeout':
                            case 'failed':
                                messageClass = 'mpesa-error-msg';
                                icon = '❌';
                                break;
                            default:
                                messageClass = 'mpesa-notice-msg';
                                icon = 'ℹ️';
                        }
                        
                        // Custom styled messages without WooCommerce icons
                        let bgColor, borderColor, textColor;
                        if (messageClass === 'mpesa-success-msg') {
                            bgColor = '#d4edda';
                            borderColor = '#c3e6cb';
                            textColor = '#155724';
                        } else if (messageClass === 'mpesa-error-msg') {
                            bgColor = '#f8d7da';
                            borderColor = '#f5c6cb';
                            textColor = '#721c24';
                        } else {
                            bgColor = '#d1ecf1';
                            borderColor = '#bee5eb';
                            textColor = '#0c5460';
                        }
                        
                        resultDiv.html(
                            '<div class="' + messageClass + '" style="padding: 12px; border-radius: 4px; margin: 0; background: ' + bgColor + '; border: 1px solid ' + borderColor + '; color: ' + textColor + ';">' +
                            icon + ' ' + data.message +
                            '</div>'
                        ).fadeIn();
                        
                    } else {
                        resultDiv.html(
                            '<div class="mpesa-error-msg" style="padding: 12px; border-radius: 4px; margin: 0; background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24;">' +
                            '❌ ' + (response.data.message || 'Failed to check status') +
                            '</div>'
                        ).fadeIn();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('M-Pesa AJAX Error:', status, error);
                    console.error('XHR Response:', xhr.responseText);
                    console.error('XHR Status Code:', xhr.status);
                    resultDiv.html(
                        '<div class="mpesa-error-msg" style="padding: 12px; border-radius: 4px; margin: 0; background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24;">' +
                        '❌ Network error. Please try again.' +
                        '</div>'
                    ).fadeIn();
                },
                complete: function() {
                    btn.prop('disabled', false).html('🔄 Check Payment Status');
                }
            });
        });
    }
});

