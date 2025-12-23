<?php
/*
 * Plugin Name: MPESA Payment Gateway
 * Plugin URI: https://github.com/mobipine/mp_woocommerce_mpesa_plugin
 * Description: Custom built MPESA payment gateway for WooCommerce with transaction status verification
 * Author: Mobipine Limited
 * Author URI: https://mobipine.com
 * Version: 2.1.2
 * GitHub Plugin URI: mobipine/mp_woocommerce_mpesa_plugin
 * GitHub Branch: main
 * Requires WP: 5.8
 * Requires PHP: 7.4
 */



// Autoloader function to automatically require class files
function my_plugin_autoloader($class) {
    if (strpos($class, 'WC_') === 0) {
        $class = strtolower($class);
        $class = str_replace('_', '-', $class);
        $path = plugin_dir_path(__FILE__) . "includes/{$class}.php";
        if (file_exists($path)) {
            require_once $path;
        }
    }
}
spl_autoload_register('my_plugin_autoloader');
// Include licensing file
// include_once plugin_dir_path(__FILE__) . 'includes/licensing.php';

// // Schedule a cron job to validate the license daily
// if (!wp_next_scheduled('myplugin_daily_license_check')) {
//     wp_schedule_event(time(), 'daily', 'myplugin_daily_license_check');
// }

// add_action('myplugin_daily_license_check', 'myplugin_validate_license');

// Register AJAX handlers early for M-Pesa gateway
function mpesa_check_payment_status_ajax() {
    require_once plugin_dir_path(__FILE__) . 'includes/wc-mpesa-gateway.php';
    $gateway = new WC_Mpesa_Gateway();
    $gateway->check_payment_status();
}
add_action('wp_ajax_mpesa_check_payment_status', 'mpesa_check_payment_status_ajax');
add_action('wp_ajax_nopriv_mpesa_check_payment_status', 'mpesa_check_payment_status_ajax');

// Check if the license key is valid before registering gateways and other functionalities
function myplugin_init() {
    // if (myplugin_validate_license()) {
        // Register the gateways
        add_filter('woocommerce_payment_gateways', 'register_my_custom_gateways');
        function register_my_custom_gateways($gateways) {
            $gateways[] = 'WC_Mpesa_Gateway';
            return $gateways;
        }

        add_action('woocommerce_blocks_loaded', 'rudr_gateway_block_support');
        function rudr_gateway_block_support() {
            require_once __DIR__ . '/includes/class-wc-mpesa-gateway-blocks-support.php';
            add_action(
                'woocommerce_blocks_payment_method_type_registration',
                function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                    $payment_method_registry->register(new WC_Mpesa_Gateway_Blocks_Support);
                }
            );
        }

        add_action('before_woocommerce_init', 'rudr_cart_checkout_blocks_compatibility');
        function rudr_cart_checkout_blocks_compatibility() {
            if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                    'cart_checkout_blocks',
                    __FILE__,
                    true // true (compatible) - M-Pesa gateway now supports WooCommerce Blocks
                );
            }
        }
    // }
}
add_action('plugins_loaded', 'myplugin_init');

include_once plugin_dir_path(__FILE__) . 'includes/order-edit-handler.php';
