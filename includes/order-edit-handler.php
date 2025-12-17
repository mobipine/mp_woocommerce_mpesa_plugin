<?php

/**
 * Order Edit Handler - DEPRECATED
 * 
 * This file has been replaced by the enhanced M-Pesa payment UI
 * in wc-mpesa-gateway.php which includes phone number editing
 * functionality along with a much better user experience.
 * 
 * All functions in this file are disabled but kept for reference.
 * 
 * New location: wc-mpesa-gateway.php::before_pay_page()
 * Phone update: wc-mpesa-gateway.php::handle_phone_update()
 */

// DISABLED - Replaced by enhanced M-Pesa UI in wc-mpesa-gateway.php
// add_action('woocommerce_pay_order_before_submit', 'custom_add_checkout_form_for_pending_payment');
// add_action('woocommerce_before_checkout_form', 'custom_add_checkout_form_for_pending_payment');
// add_action('woocommerce_after_checkout_form', 'custom_add_checkout_form_for_pending_payment');
// add_filter('woocommerce_checkout_fields', 'custom_add_checkout_form_for_pending_payment');
// add_filter('woocommerce_review_order_before_payment', 'custom_add_checkout_form_for_pending_payment');
// add_filter('woocommerce_payment_page_before_payment', 'custom_add_checkout_form_for_pending_payment');
// add_filter('woocommerce_payment_page_after_payment', 'custom_add_checkout_form_for_pending_payment');
// add_action('woocommerce_order_status_pending', 'custom_add_checkout_form_for_pending_payment');
// add_filter('woocommerce_checkout_after_customer_details', 'custom_add_checkout_form_for_pending_payment');


function custom_add_checkout_form_for_pending_payment() {
    if (!is_wc_endpoint_url('order-pay')) {
        return;
    }

    $order_id = get_query_var('order-pay');
    $order = wc_get_order($order_id);

    if (!$order || $order->get_status() !== 'pending') {
        return;
    }

    // echo '<h6>Edit Your Billing Details (Shipping will be the same)</h6>';
    echo '<h6>Order Details</h6>';
    //update your phone number if needed and proceed to payment
    echo '<p>Update your phone number if need be and proceed to payment</p>';
    echo '<form method="post" action="">';

    $checkout = WC()->checkout();
    $billing_fields = $checkout->get_checkout_fields('billing');

    // error_log(print_r($billing_fields, true));

    // dd($billing_fields);

    // Billing Fields (used for both billing and shipping)
    foreach ($billing_fields ?? [] as $key => $field) {

        // error_log()
        if($key == 'billing_name') {
            //get the first and last name and combine them
            $first_name = $order->get_billing_first_name();
            $last_name = $order->get_billing_last_name();
            $field['default'] = $first_name . ' ' . $last_name;

        } 
        elseif ($key == 'billing_phone_number' || $key == 'billing_phone') {
            $field['default'] = $order->get_billing_phone();
            woocommerce_form_field($key, $field, $field['default'] ?? '');

        } 
        elseif($key == 'billing_delivery_date') {
            $field['default'] = get_post_meta($order_id, '_billing_delivery_date', true);

            //set into a date format
            $field['default'] = date('Y-m-d', strtotime($field['default']));
        } 
        elseif($key == 'billing_customer_delivery_location') {
            $field['default'] = $order->get_billing_address_1();
        } 
        elseif($key == 'order_comments') {
            $field['default'] = get_post_meta($order_id, '_order_comments', true);
        } 
        elseif($key == 'billing_state') {
            // $field['default'] = $order->get_billing_email();
            continue;
        
        } else if($key == 'billing_country') {
            continue;
        }
        
        // else {
        //     $field['default'] = $order->{'get_' . $key}() ?? '';

        // }


        // woocommerce_form_field($key, $field, $field['default'] ?? '');
    }

    // //add the order comments
    // woocommerce_form_field('order_comments', $order_comments['order_comments'], $order->get_customer_order_notes()[0] ?? '');

    // //log the order_comment
    // error_log(print_r($order->get_customer_order_notes(), true));

    echo '<input type="hidden" name="order_id" value="' . $order_id . '">';
    wp_nonce_field('custom_edit_order_details', 'custom_order_details_nonce');
    // echo '<button style="margin-bottom:20px; width:100%;" type="submit" name="proceed_to_payment" class="button">Update Order Details</button>';
    echo '</form>';
}

// DISABLED - Functionality moved to enhanced M-Pesa UI
// add_action('init', 'handle_custom_order_details_update');

function handle_custom_order_details_update() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['custom_order_details_nonce'])) {
        if (!wp_verify_nonce($_POST['custom_order_details_nonce'], 'custom_edit_order_details')) {
            wp_die('Security check failed.');
        }

        //validate the request
        if ((!isset($_POST['billing_phone_number']) || empty($_POST['billing_phone_number'])) && (!isset($_POST['billing_phone']) || empty($_POST['billing_phone']))) {
            wc_add_notice('Phone number is required.', 'error');
            return;
            
        }

        $order_id = absint($_POST['order_id']);
        $order = wc_get_order($order_id);

        if (!$order || $order->get_status() !== 'pending') {
            wc_add_notice('Order not available for update.', 'error');
            return;
        }

        // Update Billing and Shipping Details with the same data
        $billing_fields = WC()->checkout()->get_checkout_fields('billing');
        foreach ($billing_fields as $key => $field) {
            if (isset($_POST[$key])) {
                $value = sanitize_text_field($_POST[$key]);


                if($key == 'billing_name') {
                    $name = $_POST['billing_name'] ?? '';
                    $first_name = explode(' ', $name)[0] ?? '';
                    $last_name = explode(' ', $name)[1] ?? '';
                    $order->set_billing_first_name($first_name);
                    $order->set_billing_last_name($last_name);

        
                } 
                elseif ($key == 'billing_phone_number') {
                    // $field['default'] = $order->get_billing_phone();
                    $phone = $_POST['billing_phone_number'] ?? '';
                    $order->set_billing_phone($phone);

                } 
                elseif($key == 'billing_delivery_date') {
                    // $field['default'] = get_post_meta($order_id, '_billing_delivery_date', true);
        
                    // //set into a date format
                    // $field['default'] = date('Y-m-d', strtotime($field['default']));

                    $delivery_date = sanitize_text_field($_POST['billing_delivery_date'] ?? '');
                    $delivery_date = date('d-m-Y', strtotime($delivery_date));
                    update_post_meta($order_id, '_billing_delivery_date', $delivery_date);

                } 
                elseif($key == 'billing_customer_delivery_location') {
                    // $field['default'] = $order->get_billing_address_1();
                    $customer_delivery_location = $_POST['billing_customer_delivery_location'] ?? '';
                    $order->set_billing_address_1($customer_delivery_location);

                } 
                elseif($key == 'order_comments') {
                    // $field['default'] = get_post_meta($order_id, '_order_comments', true);
                    $order_comments = $_POST['order_comments'] ?? '';
                    update_post_meta($order_id, '_order_comments', $order_comments);
                }  else {

                    $order->{"set_$key"}($value);
                }


                // Check if the corresponding shipping method exists and update
                // $shipping_method = "set_shipping_" . str_replace('billing_', '', $key);
                // if (method_exists($order, $shipping_method)) {
                //     $order->{$shipping_method}($value);
                // }
            }
        }

        // Save the order with updated details
        $order->save();

        // Redirect to the payment page
        if (isset($_POST['proceed_to_payment'])) {
            wc_add_notice('Order details updated successfully.', 'success');
            wp_redirect($order->get_checkout_payment_url());
            exit;
        }

        wc_add_notice('Order details updated successfully.', 'success');
    }
}


// DISABLED - CSS now handled by mpesa-payment-page.css
// function enqueue_custom_order_pay_styles() {
//     if (is_wc_endpoint_url('order-pay')) {
//         wp_enqueue_style('custom-order-pay-styles', plugin_dir_url(__FILE__) . 'order-edit-handler.css');
//     }
// }
// add_action('wp_enqueue_scripts', 'enqueue_custom_order_pay_styles');