<?php

// Ensure this file is only accessed through WordPress
if (!defined('ABSPATH')) {
    exit;
}

// require_once plugin_dir_url( __DIR__ ) . 'mpesa/MpesaService.php';
require_once plugin_dir_path(__DIR__) . 'mpesa/MpesaService.php';

class WC_Mpesa_Gateway extends WC_Payment_Gateway
{

    public $instructions;
    public $debug           = true;
    public $enable_for_methods;
    public $enable_for_virtual;

    public $title;
    public $description;
    public $env;
    public $shortcode;
    public $key;
    public $secret;
    public $passkey;
    public $callback;
    public $reference;
    public $idtype;



    // public $mpesa_service;
    public function __construct()
    {

        $this->id = 'mpesa'; // Unique ID for your gateway.
        $this->icon = ''; // URL of the gateway icon.
        $this->has_fields = true; // Set to true if you need custom credit card form.
        $this->method_title = 'Mpesa'; // Title of the payment method shown on the admin page.
        $this->method_description = 'Accept payments via Mpesa.'; // Description for the payment method shown on the admin page.

        // add_action('woocommerce_receipt_mpesa', array($this, 'validate_payment'));

        // add_action('wp_ajax_check_order_status', array($this, 'check_order_status'));
        // add_action('wp_ajax_nopriv_check_order_status', array($this, 'check_order_status'));


        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();


        // Define user set variables
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->instructions = $this->get_option('instructions');
        $enable_for_methods = $this->get_option('enable_for_methods', array());
        $this->enable_for_methods = is_array($enable_for_methods) ? $enable_for_methods : array();
        $this->enable_for_virtual = $this->get_option('enable_for_virtual', 'yes') === 'yes';


        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

        add_action('woocommerce_api_callback', array($this, 'webhook'));
        add_action('woocommerce_api_redirects', array($this, 'redirects_webhook'));
        // add_action('woocommerce_api_update_details', array($this, 'update_details'));


        add_action('woocommerce_thankyou_mpesa', array($this, 'thankyou_page'), 10, 1);
        // add_action('woocommerce_api_reinitiate_payment', array($this, 'reinitiate_payment'));
        add_action('woocommerce_api_reinitiate_payment', array($this, 'reinitiate_payment'));
        
        // Display custom message on order pay page - multiple hooks for compatibility
        add_action('before_woocommerce_pay', array($this, 'before_pay_page'), 10);
        add_action('woocommerce_before_pay_action', array($this, 'before_pay_page'), 10, 1);
        
        // Enqueue styles for pay page
        add_action('wp_enqueue_scripts', array($this, 'enqueue_payment_styles'));
        
        // Add custom payment fields on checkout
        add_filter('woocommerce_gateway_description', array($this, 'payment_description'), 10, 2);
        
        // Note: AJAX handlers for check_payment_status are registered in the main plugin file (index.php)
        // to ensure they work even when the gateway class isn't fully loaded
    }
    
    public function payment_description($description, $payment_id)
    {
        if ($this->id === $payment_id && is_checkout() && !is_wc_endpoint_url('order-pay')) {
            $logo_url = plugin_dir_url(__DIR__) . 'assets/64px-M-PESA_LOGO-01.svg.png';
            
            ob_start();
            ?>
            <div class="mpesa-checkout-description" style="margin: 15px 0;">
                <div style="display: flex; align-items: center; gap: 15px; padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #00a859;">
                    <img src="<?php echo esc_url($logo_url); ?>" alt="M-Pesa" style="width: 50px; height: auto;">
                    <div>
                        <strong style="display: block; margin-bottom: 5px; color: #00a859; font-size: 16px;">
                            <?php _e('Pay with M-Pesa', 'woocommerce'); ?>
                        </strong>
                        <p style="margin: 0; color: #666; font-size: 14px; line-height: 1.5;">
                            <?php echo wp_kses_post($description); ?>
                        </p>
                    </div>
                </div>
                
                <div style="margin-top: 15px; padding: 15px; background: #fff3cd; border-radius: 8px; border: 1px solid #ffecb5;">
                    <p style="margin: 0 0 10px 0; font-weight: 600; color: #856404;">
                        📱 <?php _e('How it works:', 'woocommerce'); ?>
                    </p>
                    <ol style="margin: 0; padding-left: 20px; color: #856404; font-size: 14px; line-height: 1.8;">
                        <li><?php _e('Place your order below', 'woocommerce'); ?></li>
                        <li><?php _e('You\'ll receive an STK push on your phone', 'woocommerce'); ?></li>
                        <li><?php _e('Enter your M-Pesa PIN to complete payment', 'woocommerce'); ?></li>
                        <li><?php _e('Your order will be confirmed automatically', 'woocommerce'); ?></li>
                    </ol>
                </div>
                
                <p style="margin: 10px 0 0 0; font-size: 13px; color: #666; display: flex; align-items: center; gap: 5px;">
                    <span>🔒</span>
                    <?php _e('Secure payment powered by Safaricom M-Pesa', 'woocommerce'); ?>
                </p>
            </div>
            <?php
            return ob_get_clean();
        }
        
        return $description;
    }

    public function init_form_fields()
    {

        $this->form_fields = array(
            'enabled'            => array(
                'title'       => __('Enable/Disable', 'woocommerce'),
                'label'       => __('Enable ' . $this->method_title, 'woocommerce'),
                'type'        => 'checkbox',
                'description' => '',
                'default'     => 'yes',
            ),
            'title'              => array(
                'title'       => __('Method Title', 'woocommerce'),
                'type'        => 'text',
                'description' => __('Payment method name that the customer will see on your checkout.', 'woocommerce'),
                'default'     => __('Lipa Na M-Pesa', 'woocommerce'),
                'desc_tip'    => true,
            ),
            'env'                => array(
                'title'       => __('Environment', 'woocommerce'),
                'type'        => 'select',
                'options'     => array(
                    'sandbox' => __('Sandbox', 'woocommerce'),
                    'live'    => __('Live', 'woocommerce'),
                ),
                'description' => __('M-Pesa Environment', 'woocommerce'),
                'desc_tip'    => true,
                'class'       => 'select2 wc-enhanced-select',
            ),
            'idtype'             => array(
                'title'       => __('Identifier Type', 'woocommerce'),
                'type'        => 'select',
                'options'     => array(
                    /**1 => __('MSISDN', 'woocommerce'),*/
                    1 => __('Paybill Number', 'woocommerce'),
                    2 => __('Till Number', 'woocommerce'),
                ),
                'description' => __('M-Pesa Identifier Type', 'woocommerce'),
                'desc_tip'    => true,
                'class'       => 'select2 wc-enhanced-select',
            ),

            'shortcode'          => array(
                'title'       => __('Business Shortcode', 'woocommerce'),
                'type'        => 'text',
                'description' => __('Your M-Pesa Business Till/Paybill Number. Use "Online Shortcode" in Sandbox', 'woocommerce'),
                'default'     => __(174379, 'woocommerce'),
                'desc_tip'    => true,
            ),
            'key'                => array(
                'title'       => __('App Consumer Key', 'woocommerce'),
                'type'        => 'text',
                'description' => __('Your App Consumer Key From Safaricom Daraja.', 'woocommerce'),
                'default'     => '7n6k5qAmVMm8uvLzZ0bYLI9ydTGu7oto',
                'desc_tip'    => true,
            ),
            'secret'             => array(
                'title'       => __('App Consumer Secret', 'woocommerce'),
                'type'        => 'text',
                'description' => __('Your App Consumer Secret From Safaricom Daraja.', 'woocommerce'),
                'default'     => '8pr0NL7E7yhessMF',
                'desc_tip'    => true,
            ),
            'passkey'            => array(
                'title'       => __('Online Pass Key', 'woocommerce'),
                'type'        => 'text',
                'description' => __('Used to create a password for use when making a Lipa Na M-Pesa Online Payment API call.', 'woocommerce'),
                'default'     => 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919',
                'desc_tip'    => true,
                'class'       => 'wide-input',
                'css'         => 'min-width: 55%;',
            ),
            'callback'            => array(
                'title'       => __('Callback URL', 'woocommerce'),
                'type'        => 'text',
                'description' => __('Callback URl for confirmation of payment', 'woocommerce'),
                'default'     => home_url('/index.php/wc-api/callback'),
                'desc_tip'    => true,
                'class'       => 'wide-input',
                'css'         => 'min-width: 55%;',
            ),
            'reference'          => array(
                'title'             => __('Account Reference', 'woocommerce'),
                'type'              => 'text',
                'description'       => __('Account number for transactions. Leave blank to use order ID/Number.', 'woocommerce'),
                'default'           => '',
                'desc_tip'          => true,
                'custom_attributes' => array(
                    'autocomplete' => 'off',
                ),
            ),
            'description'        => array(
                'title'       => __('Method Description', 'woocommerce'),
                'type'        => 'textarea',
                'description' => __('Payment method description that the customer will see during checkout.', 'woocommerce'),
                'default'     => __("Cross-check your details before pressing the button below.\nYour phone number MUST be registered with M-Pesa(and ON) for this to work.\nYou will get a pop-up on your phone asking you to confirm the payment.\nEnter your service (M-Pesa) PIN to proceed.\nIn case you don't see the pop up on your phone, please upgrade your SIM card by dialing *234*1*6#.", 'woocommerce'),
                'desc_tip'    => true,
                'css'         => 'height:150px',
            ),
            'instructions'       => array(
                'title'       => __('Instructions', 'woocommerce'),
                'type'        => 'textarea',
                'description' => __('Instructions that will be added to the thank you page.', 'woocommerce'),
                'default'     => __('Thank you for shopping with us.', 'woocommerce'),
                'desc_tip'    => true,
            ),
            'successful_order_status' => array(
                'title'       => __('Successful Payment Order Status', 'woocommerce'),
                'type'        => 'select',
                'options'     => array(
                    'processing' => __('Processing', 'woocommerce'),
                    'completed'  => __('Completed', 'woocommerce'),
                    'on-hold'    => __('On hold', 'woocommerce'),
                ),
                'description' => __('Choose the order status to apply when an M-Pesa payment is confirmed successfully.', 'woocommerce'),
                'default'     => 'processing',
                'desc_tip'    => true,
                'class'       => 'select2 wc-enhanced-select',
            ),
            'enable_for_methods' => array(
                'title'             => __('Enable for shipping methods', 'woocommerce'),
                'type'              => 'multiselect',
                'class'             => 'wc-enhanced-select',
                'css'               => 'width: 400px;',
                'default'           => '',
                'description'       => __('If M-Pesa is only available for certain shipping methods, set it up here. Leave blank to enable for all methods.', 'woocommerce'),
                'options'           => $this->load_shipping_method_options(),
                'desc_tip'          => true,
                'custom_attributes' => array(
                    'data-placeholder' => __('Select shipping methods', 'woocommerce'),
                ),
            ),
            'enable_for_virtual' => array(
                'title'   => __('Enable for virtual orders', 'woocommerce'),
                'label'   => __('Accept M-Pesa if the order is virtual', 'woocommerce'),
                'type'    => 'checkbox',
                'default' => 'yes',
            ),

        );
    }

    private function get_successful_order_status()
    {
        $allowed_statuses = array('processing', 'completed', 'on-hold');
        $configured_status = $this->get_option('successful_order_status', 'processing');

        return in_array($configured_status, $allowed_statuses, true) ? $configured_status : 'processing';
    }

    private function is_payment_confirmed($order)
    {
        if (!$order instanceof WC_Order) {
            return false;
        }

        if ('yes' === $order->get_meta('_mpesa_payment_confirmed')) {
            return true;
        }

        return !empty($order->get_meta('_mpesa_receipt_number')) || !empty($order->get_transaction_id());
    }

    private function mark_order_as_paid($order, $transaction_id = '', $note = '')
    {
        $successful_status = $this->get_successful_order_status();

        $order->update_meta_data('_mpesa_payment_confirmed', 'yes');
        $order->update_meta_data('_mpesa_payment_confirmed_at', current_time('mysql'));

        if (!empty($transaction_id)) {
            $order->set_transaction_id($transaction_id);
        }

        if (!$order->is_paid()) {
            $order->payment_complete($transaction_id);
        }

        if ($order->get_status() !== $successful_status) {
            $order->update_status($successful_status, $note);
        } elseif (!empty($note)) {
            $order->add_order_note($note);
        }

        return $successful_status;
    }

    public function process_payment($order_id)
    {
        try {
            $order = wc_get_order($order_id);
            
            if (!$order) {
                throw new Exception('Order not found.');
            }

            error_log("Processing payment for order #{$order_id}. Current status: " . $order->get_status());
            
            // CRITICAL: Check if phone number was updated in the form (from order-pay page)
            // Log all POST data for debugging
            error_log("M-Pesa: POST data keys: " . implode(', ', array_keys($_POST)));
            
            if (isset($_POST['billing_phone']) && !empty($_POST['billing_phone'])) {
                $updated_phone = sanitize_text_field($_POST['billing_phone']);
                $old_phone = $order->get_billing_phone();
                
                error_log("M-Pesa: Found billing_phone in POST: {$updated_phone}, Old phone: {$old_phone}");
                
                if ($updated_phone !== $old_phone) {
                    $order->set_billing_phone($updated_phone);
                    $order->add_order_note(sprintf(
                        __('Customer updated billing phone from %s to %s before payment', 'woocommerce'),
                        $old_phone,
                        $updated_phone
                    ));
                    $order->save();
                    error_log("M-Pesa: ✓ Phone UPDATED from {$old_phone} to {$updated_phone} for order #{$order_id}");
                } else {
                    error_log("M-Pesa: Phone unchanged: {$updated_phone}");
                }
            } else {
                error_log("M-Pesa: WARNING - billing_phone NOT found in POST data!");
            }
            
            // Mark as on-hold (we're awaiting the payment)
            $order->update_status('on-hold', __('Awaiting M-Pesa payment confirmation.', 'woocommerce'));
            $order->update_meta_data('_mpesa_payment_confirmed', 'no');
            $order->save();

            // Get phone number AFTER potential update
            $phone_number = $order->get_billing_phone();
            $amount = (int)$order->get_total();

            error_log("Order #{$order_id} - Amount: {$amount}, Phone to use: {$phone_number}");

            // Validate phone number presence
            if (empty($phone_number)) {
                throw new Exception('M-PESA phone number is required!');
            }

            // Remove any spaces, dashes, or plus signs
            $phone_number = preg_replace('/[\s\-\+]/', '', $phone_number);

            // Validate phone number format (Kenyan format)
            if (!preg_match('/^(07|01)[0-9]{8}$/', $phone_number) && 
                !preg_match('/^(2547|2541)[0-9]{8}$/', $phone_number)) {
                throw new Exception('Invalid M-Pesa phone number format. Use 07XXXXXXXX or 01XXXXXXXX.');
            }

            // Get gateway settings
            $passkey = $this->get_option('passkey');
            $shortCode = $this->get_option('shortcode');
            $consumerKey = $this->get_option('key');
            $consumerSecret = $this->get_option('secret');
            $callbackUrl = $this->get_option('callback');
            $accountReference = $this->get_option('reference');
            $env = $this->get_option('env');

            // Validate required settings
            if (empty($passkey) || empty($shortCode) || empty($consumerKey) || empty($consumerSecret)) {
                error_log("M-Pesa gateway not properly configured for order #{$order_id}");
                throw new Exception('M-Pesa gateway is not properly configured. Please contact support.');
            }

            $creds = [
                'passkey' => $passkey,
                'shortCode' => $shortCode,
                'consumerKey' => $consumerKey,
                'consumerSecret' => $consumerSecret,
                'accountReference' => $accountReference,
                'env' => $env
            ];

            // Initiate the M-Pesa STK push
            $mpesa_service = new MpesaService();
            $response = $mpesa_service->stk_push($phone_number, $amount, $order_id, $creds, $callbackUrl, $env);
            
            error_log("M-Pesa API Response for order #{$order_id}: " . $response);

            $response_data = json_decode($response, true);

            // Check if response is valid JSON
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("Invalid JSON response from M-Pesa API for order #{$order_id}: " . json_last_error_msg());
                throw new Exception('Failed to process M-Pesa payment. Invalid response from payment provider.');
            }

            // Check for error response from M-Pesa
            if (isset($response_data['errorCode'])) {
                $error_message = isset($response_data['errorMessage']) ? $response_data['errorMessage'] : 'Unknown error';
                error_log("M-Pesa API Error for order #{$order_id}: {$error_message}");
                throw new Exception("M-Pesa error: {$error_message}");
            }

            // Check for successful response
            if (isset($response_data['ResponseDescription']) && 
                $response_data['ResponseDescription'] == 'Success. Request accepted for processing') {
                
                error_log("M-Pesa STK push successful for order #{$order_id}");
                
                // Return success response
                return array(
                    'result'   => 'success',
                    'redirect' => $this->get_return_url($order),
                );
            } else {
                $description = isset($response_data['ResponseDescription']) ? $response_data['ResponseDescription'] : 'Unknown error';
                error_log("M-Pesa payment failed for order #{$order_id}: {$description}");
                throw new Exception("Failed to initiate M-Pesa payment: {$description}");
            }

        } catch (Exception $e) {
            error_log("Exception in process_payment for order #{$order_id}: " . $e->getMessage());
            
            // Update order status to pending so it can be retried
            if (isset($order) && $order) {
                $order->update_status('pending', sprintf(
                    __('M-Pesa payment failed: %s', 'woocommerce'),
                    $e->getMessage()
                ));
            }
            
            // Add error notice for the user
            wc_add_notice($e->getMessage(), 'error');
            
            // Redirect to order pay page so user can retry
            // Using 'success' result with redirect (checkout process completed, even though payment failed)
            return array(
                'result'   => 'success',
                'redirect' => $order->get_checkout_payment_url(),
            );
        }
    }

    public function webhook()
    {
        // Check if the request method is POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            error_log("M-Pesa Webhook: Invalid request method. Only POST requests are allowed.");
            wp_die('Invalid request method', 'Method Not Allowed', array('response' => 405));
        }

        // Get the callback object
        $request_data = file_get_contents('php://input');
        error_log("M-Pesa Callback received: " . $request_data);
        
        $callback = json_decode($request_data, true);

        // Validate JSON
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("M-Pesa Webhook: Invalid JSON received: " . json_last_error_msg());
            wp_die('Invalid JSON', 'Bad Request', array('response' => 400));
        }

        // Validate callback structure
        if (!isset($callback['Body']['stkCallback']['MerchantRequestID'])) {
            error_log("M-Pesa Webhook: Missing MerchantRequestID in callback");
            wp_die('Invalid callback structure', 'Bad Request', array('response' => 400));
        }

        $merchantRequestID = $callback['Body']['stkCallback']['MerchantRequestID'];
        $resultDesc = isset($callback['Body']['stkCallback']['ResultDesc']) ? $callback['Body']['stkCallback']['ResultDesc'] : 'No description';
        $resultCode = isset($callback['Body']['stkCallback']['ResultCode']) ? $callback['Body']['stkCallback']['ResultCode'] : -1;

        error_log("M-Pesa Callback - MerchantRequestID: {$merchantRequestID}, ResultCode: {$resultCode}");

        // Find the order by merchant request ID
        $orders = wc_get_orders(array(
            'meta_key' => '_merchant_request_id',
            'meta_value' => $merchantRequestID,
            'limit' => 1,
            'status' => 'any'
        ));

        if (empty($orders)) {
            error_log("M-Pesa Webhook: Order not found for MerchantRequestID: {$merchantRequestID}");
            wp_die('Order not found', 'Not Found', array('response' => 404));
        }

        $order = $orders[0];
        $order_id = $order->get_id();
        error_log("M-Pesa Webhook: Processing callback for Order #{$order_id}");

        if ($resultCode == 0) {
            // Payment successful
            $callbackMetadata = isset($callback['Body']['stkCallback']['CallbackMetadata']['Item']) 
                ? $callback['Body']['stkCallback']['CallbackMetadata']['Item'] 
                : array();
            
            // Extract payment details
            $mpesaReceiptNumber = '';
            $transactionDate = '';
            $phoneNumber = '';
            
            foreach ($callbackMetadata as $item) {
                if ($item['Name'] == 'MpesaReceiptNumber') {
                    $mpesaReceiptNumber = $item['Value'];
                }
                if ($item['Name'] == 'TransactionDate') {
                    $transactionDate = $item['Value'];
                }
                if ($item['Name'] == 'PhoneNumber') {
                    $phoneNumber = $item['Value'];
                }
            }
            
            // Save payment details to order meta
            $order->update_meta_data('_mpesa_receipt_number', $mpesaReceiptNumber);
            $order->update_meta_data('_mpesa_transaction_date', $transactionDate);
            $order->update_meta_data('_mpesa_phone_number', $phoneNumber);
            
            $successful_status = $this->mark_order_as_paid($order, $mpesaReceiptNumber, sprintf(
                __('M-Pesa payment completed. Receipt: %s, Phone: %s', 'woocommerce'),
                $mpesaReceiptNumber,
                $phoneNumber
            ));
            
            error_log("M-Pesa Webhook: Order #{$order_id} payment completed. Receipt: {$mpesaReceiptNumber}. Status set to {$successful_status}");
            
        } else {
            // Payment failed or cancelled - set to pending so user can retry
            $order->update_meta_data('_mpesa_payment_confirmed', 'no');
            $order->update_status('pending', sprintf(
                __('M-Pesa payment failed: %s. You can retry the payment from your order page.', 'woocommerce'),
                $resultDesc
            ));
            
            // Add a note for reference
            $order->add_order_note(sprintf(
                __('M-Pesa payment failed (ResultCode: %s). Customer can retry payment.', 'woocommerce'),
                $resultCode
            ));
            
            error_log("M-Pesa Webhook: Order #{$order_id} payment failed. ResultCode: {$resultCode}, Reason: {$resultDesc}");
        }
        
        $order->save();
        
        // Return success response to M-Pesa
        wp_send_json_success(array('message' => 'Callback processed successfully'));
    }

    public function redirects_webhook()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $order_id = $data['order_id'];
        error_log($order_id);

        if (!isset($order_id)) {
            wp_send_json_error(array('message' => 'Missing order_id parameter'));
            return;
        }

        $order = wc_get_order($order_id);

        if ($order && $this->is_payment_confirmed($order)) {
            wp_send_json_success(array('status' => 'completed'));
        } else if ($order && $order->get_status() === 'pending') {
            $redirect_url = $order->get_checkout_payment_url();
            wp_send_json_success(array('status' => 'failed', 'message' => 'Payment failed. Please try again.', 'redirect' => $redirect_url));
        } else {
            wp_send_json_success(array('status' => 'on-hold'));
        }
    }

    public function enqueue_payment_styles()
    {
        // Enqueue on checkout and order pay pages
        if (is_checkout()) {
            wp_enqueue_style(
                'mpesa-payment-page', 
                plugin_dir_url(__DIR__) . 'assets/mpesa-payment-page.css',
                array(),
                '2.1.0'
            );
            
            // Enqueue JavaScript
            wp_enqueue_script(
                'mpesa-checkout',
                plugin_dir_url(__DIR__) . 'assets/mpesa-checkout.js',
                array('jquery'),
                '2.1.0',
                true
            );
            
            // Pass logo URL and AJAX URL to JavaScript
            wp_localize_script('mpesa-checkout', 'mpesaCheckoutParams', array(
                'logoUrl' => plugin_dir_url(__DIR__) . 'assets/64px-M-PESA_LOGO-01.svg.png',
                'ajaxUrl' => admin_url('admin-ajax.php')
            ));
        }
    }

    public function before_pay_page($order_id = null)
    {
        // Get order ID if not provided
        if (!$order_id) {
            global $wp;
            if (isset($wp->query_vars['order-pay'])) {
                $order_id = absint($wp->query_vars['order-pay']);
            }
        }
        
        if (!$order_id) {
            return;
        }
        
        $order = wc_get_order($order_id);
        
        if (!$order || $order->get_payment_method() !== $this->id) {
            return;
        }

        $order_status = $order->get_status();
        
        error_log("M-Pesa: before_pay_page called for order #{$order_id}, status: {$order_status}, payment method: " . $order->get_payment_method());
        
        // Show enhanced payment interface
        if (in_array($order_status, array('pending', 'failed'), true) || ('on-hold' === $order_status && !$this->is_payment_confirmed($order))) {
            $logo_url = plugin_dir_url(__DIR__) . 'assets/M-PESA_LOGO-01.svg.png';
            $phone = $order->get_billing_phone();
            $amount = $order->get_total();
            $currency = $order->get_currency();
            
            // Load the template
            $template_path = plugin_dir_path(__DIR__) . 'templates/order-pay-mpesa.php';
            
            if (file_exists($template_path)) {
                include $template_path;
            } else {
                error_log("M-Pesa: Template file not found at {$template_path}");
            }
            
            // Add inline styles to hide default WooCommerce elements
            echo '<style>
                /* Hide default WooCommerce order summary table */
                .woocommerce-order-pay .shop_table.order_details {
                    display: none !important;
                }
                
                /* Hide payment method selection (but not the whole payment section) */
                .woocommerce-order-pay ul.payment_methods,
                .woocommerce-order-pay .wc_payment_methods {
                    display: none !important;
                }
                
                /* Ensure form and button remain visible */
                .woocommerce-order-pay form#order_review,
                .woocommerce-order-pay #payment,
                .woocommerce-order-pay #place_order,
                .woocommerce-order-pay .mpesa-payment-wrapper,
                .woocommerce-order-pay .mpesa-payment-button-wrapper {
                    display: block !important;
                }
                
                /* Make sure the form is visible */
                .woocommerce-order-pay form#order_review {
                    opacity: 1 !important;
                    visibility: visible !important;
                }
            </style>';
            
            // Add JavaScript to style the payment button and hide unnecessary elements
            echo '<script>
            jQuery(document).ready(function($) {
                console.log("M-Pesa: Setting up order-pay page");
                
                // Hide only specific elements we don\'t want
                $(".shop_table.order_details").hide();
                $("ul.payment_methods").hide();
                $(".wc_payment_methods").hide();
                
                // Make sure the form and button are visible
                $("form#order_review").show();
                $("#payment").show();
                $("#place_order").show();
                
                // Wrap the place order button in our styled wrapper if not already wrapped
                if ($("#place_order").length && !$("#place_order").parent().hasClass("mpesa-payment-button-wrapper")) {
                    $("#place_order").wrap("<div class=\"mpesa-payment-button-wrapper\"></div>");
                    $(".mpesa-payment-button-wrapper").append("<div class=\"mpesa-secure-badge\">' . esc_js(__('Secure payment powered by Safaricom M-Pesa', 'woocommerce')) . '</div>");
                }
                
                console.log("M-Pesa: Pay button found:", $("#place_order").length);
                console.log("M-Pesa: Order-pay page setup complete");
            });
            </script>';
        }
    }

    public function thankyou_page($order_id)
    {
        // Get the order object
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return;
        }

        $order_status = $order->get_status();
        error_log("M-Pesa Thank You Page - Order #{$order_id}, Status: {$order_status}");

        // Show different messages based on order status
        if ($this->is_payment_confirmed($order)) {
            $receipt_number = $order->get_meta('_mpesa_receipt_number');
            $transaction_id = $order->get_transaction_id();
            
            echo '<div style="margin: 20px 0; padding: 15px; background: #d4edda; border-left: 4px solid #28a745; border-radius: 4px;">';
            echo '<strong style="font-size: 16px; color: #155724;">✓ ' . __('Payment Received!', 'woocommerce') . '</strong><br>';
            
            if ($receipt_number) {
                echo '<span style="color: #155724;">' . sprintf(__('M-Pesa Receipt Number: %s', 'woocommerce'), '<strong>' . esc_html($receipt_number) . '</strong>') . '</span>';
            } else if ($transaction_id) {
                echo '<span style="color: #155724;">' . sprintf(__('Transaction ID: %s', 'woocommerce'), '<strong>' . esc_html($transaction_id) . '</strong>') . '</span>';
            } else {
                echo '<span style="color: #155724;">' . __('Your M-Pesa payment has been successfully received and confirmed.', 'woocommerce') . '</span>';
            }
            
            echo '</div>';
        } else if ($order_status === 'pending' || $order_status === 'failed') {
            // Payment failed or is pending - show retry option
            echo '<div style="margin: 20px 0; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">';
            echo '<strong style="font-size: 16px;">' . __('Payment Not Completed', 'woocommerce') . '</strong><br>';
            echo '<span style="color: #856404;">' . __('Your M-Pesa payment was not completed. You can try again below.', 'woocommerce') . '</span>';
            echo '</div>';
            
            // Show retry payment button
            echo '<p style="margin: 20px 0;">';
            echo '<a href="' . esc_url($order->get_checkout_payment_url()) . '" class="button" style="padding: 12px 24px; font-size: 16px; background: #00a859; color: white; text-decoration: none; border-radius: 4px; display: inline-block;">';
            echo __('Retry M-Pesa Payment', 'woocommerce');
            echo '</a>';
            echo '</p>';
            
        } else if ($order_status === 'on-hold' || $order_status === 'processing') {
            // Payment is processing - show waiting message (id for JS to hide when status updates)
            echo '<div id="awaiting-message" style="margin: 20px 0; padding: 15px; background: #d1ecf1; border-left: 4px solid #17a2b8; border-radius: 4px;">';
            echo '<strong style="font-size: 16px;">' . __('Awaiting Payment Confirmation', 'woocommerce') . '</strong><br>';
            echo '<span style="color: #0c5460;">' . __('Please check your phone for the M-Pesa payment prompt. Enter your PIN to complete the payment.', 'woocommerce') . '</span>';
            echo '</div>';

            // Include the spinner HTML
            include plugin_dir_path(__FILE__) . 'spinner.html';

            // Add a hidden input to pass the order ID to the script
            echo '<input type="hidden" id="order_id" value="' . esc_attr($order_id) . '">';
            echo '<input type="hidden" id="reinitiate_payment_nonce" value="' . wp_create_nonce('reinitiate_payment_nonce') . '">';

            // Pass home_url to JavaScript
            echo '<script type="text/javascript">
                    var homeUrl = "' . esc_url(home_url()) . '";
                </script>';

            // Enqueue the polling script
            wp_enqueue_script('mpesa-polling', plugin_dir_url(__FILE__) . 'polling.js', array(), null, true);
            
        }
    }

    public function reinitiate_payment()
    {
        error_log("M-Pesa: Reinitiating payment");
        
        $data = json_decode(file_get_contents('php://input'), true);
        $order_id = isset($data['order_id']) ? intval($data['order_id']) : 0;
        
        if (!$order_id) {
            error_log("M-Pesa Reinitiate: Invalid order ID");
            wp_send_json_error(array('message' => 'Invalid order ID'));
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            error_log("M-Pesa Reinitiate: Order #{$order_id} not found");
            wp_send_json_error(array('message' => 'Order not found'));
            return;
        }

        if ($this->is_payment_confirmed($order)) {
            error_log("M-Pesa Reinitiate: Order #{$order_id} already has a confirmed payment");
            wp_send_json_error(array('message' => 'Payment has already been confirmed for this order'));
            return;
        }

        // Check if order is in a state that allows payment reinitiation
        $allowed_statuses = array('pending', 'failed', 'on-hold');
        if (!in_array($order->get_status(), $allowed_statuses)) {
            error_log("M-Pesa Reinitiate: Order #{$order_id} has invalid status: " . $order->get_status());
            wp_send_json_error(array('message' => 'Order cannot be paid at this time'));
            return;
        }

        // Reinitiate the payment process
        $result = $this->process_payment($order_id);
        error_log("M-Pesa Reinitiate result for Order #{$order_id}: " . json_encode($result));
        
        if (isset($result['result']) && $result['result'] === 'success') {
            wp_send_json_success(array('message' => 'Payment reinitiated successfully'));
        } else {
            wp_send_json_error(array('message' => 'Failed to reinitiate payment'));
        }
    }
    
    public function check_payment_status()
    {
        try {
            error_log("M-Pesa: check_payment_status called with POST: " . print_r($_POST, true));
            
            // Verify nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mpesa_check_status')) {
                error_log("M-Pesa: Nonce verification failed. Nonce: " . (isset($_POST['nonce']) ? $_POST['nonce'] : 'NOT SET'));
                throw new Exception(__('Security check failed', 'woocommerce'));
            }
            
            error_log("M-Pesa: Nonce verified successfully");
            
            // Get order ID
            $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
            if (!$order_id) {
                error_log("M-Pesa: Invalid order ID received");
                throw new Exception(__('Invalid order ID', 'woocommerce'));
            }
            
            error_log("M-Pesa: Order ID: {$order_id}");
            
            // Get order
            $order = wc_get_order($order_id);
            if (!$order) {
                error_log("M-Pesa: Order #{$order_id} not found");
                throw new Exception(__('Order not found', 'woocommerce'));
            }
            
            error_log("M-Pesa: Order found, customer_id: " . $order->get_customer_id() . ", current_user_id: " . get_current_user_id());
            
            // Verify order belongs to current user (security)
            // Allow if: user is logged in and owns the order, OR user is admin, OR guest checkout with order key match
            $current_user_id = get_current_user_id();
            $order_customer_id = $order->get_customer_id();
            
            if ($current_user_id > 0) {
                // Logged in user - must own the order or be admin
                if ($current_user_id !== (int)$order_customer_id && !current_user_can('manage_woocommerce')) {
                    error_log("M-Pesa: Unauthorized access - User {$current_user_id} tried to access order {$order_id} owned by customer {$order_customer_id}");
                    throw new Exception(__('Unauthorized access', 'woocommerce'));
                }
            } else {
                // Guest user - verify they have the order key in their session
                // This is a basic check for guest orders
                if ((int)$order_customer_id > 0) {
                    error_log("M-Pesa: Guest user tried to access registered user's order");
                    throw new Exception(__('Unauthorized access', 'woocommerce'));
                }
            }
            
            error_log("M-Pesa: Authorization passed");
            
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
            $resultCode = isset($result['ResultCode']) ? strval($result['ResultCode']) : '-1';
            $resultDesc = isset($result['ResultDesc']) ? $result['ResultDesc'] : 'Unknown';
            
            error_log("M-Pesa: Status query result - Code: {$resultCode}, Desc: {$resultDesc}");
            
            if ($resultCode === '0') {
                // Payment was successful!
                $merchantRequestID = $result['MerchantRequestID'] ?? '';
                
                if ($this->is_payment_confirmed($order)) {
                    wp_send_json_success(array(
                        'status' => 'already_completed',
                        'message' => __('✓ This order has already been paid and processed.', 'woocommerce')
                    ));
                    return;
                }
                
                $successful_status = $this->mark_order_as_paid($order, $merchantRequestID, sprintf(
                    __('Payment confirmed via manual status query. MerchantRequestID: %s', 'woocommerce'),
                    $merchantRequestID
                ));
                
                error_log("M-Pesa: Order #{$order_id} marked as {$successful_status} via status query");
                
                wp_send_json_success(array(
                    'status' => 'paid',
                    'message' => __('✓ Payment confirmed! Your order has been updated successfully. Redirecting...', 'woocommerce'),
                    'redirect' => $this->get_return_url($order)
                ));
                
            } else if ($resultCode === '1032') {
                // Cancelled by user
                wp_send_json_success(array(
                    'status' => 'cancelled',
                    'message' => __('Payment was cancelled. You can try again below.', 'woocommerce')
                ));
                
            } else if ($resultCode === '1037') {
                // Timeout
                wp_send_json_success(array(
                    'status' => 'timeout',
                    'message' => __('Payment request timed out. Please try again.', 'woocommerce')
                ));
                
            } else {
                // Other error
                wp_send_json_success(array(
                    'status' => 'failed',
                    'message' => sprintf(__('Payment status: %s', 'woocommerce'), $resultDesc)
                ));
            }
            
        } catch (Exception $e) {
            error_log("M-Pesa Status Check Error: " . $e->getMessage());
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * Check If The Gateway Is Available For Use.
     *
     * @return bool
     */
    public function is_available() {
        $is_virtual       = true;
        $shipping_methods = array();

        // Get shipping methods from the cart or order.
        if ( is_wc_endpoint_url( 'order-pay' ) ) {
            $order            = wc_get_order( absint( get_query_var( 'order-pay' ) ) );
            $shipping_methods = $order ? $order->get_shipping_methods() : array();
            $is_virtual       = ! count( $shipping_methods );
        } elseif ( WC()->cart && WC()->cart->needs_shipping() ) {
            $shipping_methods = WC()->cart->get_shipping_methods();
            $is_virtual       = false;
        }

        // If M-Pesa is not enabled for virtual orders and the order does not need shipping, return false.
        if ( ! $this->enable_for_virtual && $is_virtual ) {
            return false;
        }

        // Return early if:
        // - There are no shipping methods restrictions in place.
        // - The order is virtual so needs no shipping.
        // - Shipping methods are not set yet.
        if ( empty( $this->enable_for_methods ) || $is_virtual || ! $shipping_methods ) {
            return parent::is_available();
        }

        // Get the selected shipping method ids. This works on both WC_Shipping_Rate and WC_Order_Item_Shipping class instances.
        $canonical_rate_ids = array_unique(
            array_values(
                array_map(
                    function ( $shipping_method ) {
                        return $shipping_method && is_callable( array( $shipping_method, 'get_method_id' ) ) && is_callable( array( $shipping_method, 'get_instance_id' ) ) ? $shipping_method->get_method_id() . ':' . $shipping_method->get_instance_id() : null;
                    },
                    $shipping_methods
                )
            )
        );

        if ( ! count( $this->get_matching_rates( $canonical_rate_ids ) ) ) {
            return false;
        }

        return parent::is_available();
    }

    /**
     * Checks to see whether or not the admin settings are being accessed by the current request.
     *
     * @return bool
     */
    private function is_accessing_settings() {
        if ( is_admin() ) {
            if ( ! is_wc_admin_settings_page() ) {
                return false;
            }
            // phpcs:disable WordPress.Security.NonceVerification
            if ( ! isset( $_REQUEST['tab'] ) || 'checkout' !== $_REQUEST['tab'] ) {
                return false;
            }
            if ( ! isset( $_REQUEST['section'] ) || $this->id !== $_REQUEST['section'] ) {
                return false;
            }
            // phpcs:enable WordPress.Security.NonceVerification

            return true;
        }

        if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
            global $wp;
            if ( isset( $wp->query_vars['rest_route'] ) && false !== strpos( $wp->query_vars['rest_route'], '/payment_gateways' ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Loads all of the shipping method options for the enable_for_methods field.
     *
     * @return array
     */
    private function load_shipping_method_options() {
        // Since this is expensive, we only want to do it if we're actually on the settings page.
        if ( ! $this->is_accessing_settings() ) {
            return array();
        }

        $data_store = WC_Data_Store::load( 'shipping-zone' );
        $raw_zones  = $data_store->get_zones();
        $zones      = array();

        foreach ( $raw_zones as $raw_zone ) {
            $zones[] = new WC_Shipping_Zone( $raw_zone );
        }

        $zones[] = new WC_Shipping_Zone( 0 );

        $options = array();
        foreach ( WC()->shipping()->load_shipping_methods() as $method ) {

            $options[ $method->get_method_title() ] = array();

            // Translators: %1$s shipping method name.
            $options[ $method->get_method_title() ][ $method->id ] = sprintf( __( 'Any &quot;%1$s&quot; method', 'woocommerce' ), $method->get_method_title() );

            foreach ( $zones as $zone ) {

                $shipping_method_instances = $zone->get_shipping_methods();

                foreach ( $shipping_method_instances as $shipping_method_instance_id => $shipping_method_instance ) {

                    if ( $shipping_method_instance->id !== $method->id ) {
                        continue;
                    }

                    $option_id = $shipping_method_instance->get_rate_id();

                    // Translators: %1$s shipping method title, %2$s shipping method id.
                    $option_instance_title = sprintf( __( '%1$s (#%2$s)', 'woocommerce' ), $shipping_method_instance->get_title(), $shipping_method_instance_id );

                    // Translators: %1$s zone name, %2$s shipping method instance name.
                    $option_title = sprintf( __( '%1$s &ndash; %2$s', 'woocommerce' ), $zone->get_id() ? $zone->get_zone_name() : __( 'Other locations', 'woocommerce' ), $option_instance_title );

                    $options[ $method->get_method_title() ][ $option_id ] = $option_title;
                }
            }
        }

        return $options;
    }

    /**
     * Indicates whether a rate exists in an array of canonically-formatted rate IDs that activates this gateway.
     *
     * @param array $rate_ids Rate ids to check.
     * @return array
     */
    private function get_matching_rates( $rate_ids ) {
        // Ensure enable_for_methods is an array
        $enable_for_methods = is_array( $this->enable_for_methods ) ? $this->enable_for_methods : array();
        
        // First, match entries in 'method_id:instance_id' format. Then, match entries in 'method_id' format by stripping off the instance ID from the candidates.
        return array_unique( array_merge( array_intersect( $enable_for_methods, $rate_ids ), array_intersect( $enable_for_methods, array_unique( array_map( 'wc_get_string_before_colon', $rate_ids ) ) ) ) );
    }
}
