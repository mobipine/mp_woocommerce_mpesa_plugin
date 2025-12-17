<?php
defined( 'ABSPATH' ) || exit;

$order_id = absint( get_query_var( 'order-pay' ) );
$order    = wc_get_order( $order_id );

if ( ! $order ) {
    return;
}

$available_gateways = WC()->payment_gateways->get_available_payment_gateways();
WC()->payment_gateways()->set_current_gateway( $available_gateways );

wc_get_template( 'checkout/form-pay.php', array( 'order' => $order ) );
?>

<!-- Add billing and shipping details -->
<h2><?php esc_html_e( 'Billing Details', 'woocommerce' ); ?></h2>
<ul>
    <li><?php echo esc_html( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ); ?></li>
    <li><?php echo esc_html( $order->get_billing_address_1() ); ?></li>
    <li><?php echo esc_html( $order->get_billing_city() ); ?></li>
    <li><?php echo esc_html( $order->get_billing_postcode() ); ?></li>
    <li><?php echo esc_html( $order->get_billing_country() ); ?></li>
    <li><?php echo esc_html( $order->get_billing_phone() ); ?></li>
    <li><?php echo esc_html( $order->get_billing_email() ); ?></li>
</ul>

<h2><?php esc_html_e( 'Shipping Details', 'woocommerce' ); ?></h2>
<ul>
    <li><?php echo esc_html( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() ); ?></li>
    <li><?php echo esc_html( $order->get_shipping_address_1() ); ?></li>
    <li><?php echo esc_html( $order->get_shipping_city() ); ?></li>
    <li><?php echo esc_html( $order->get_shipping_postcode() ); ?></li>
    <li><?php echo esc_html( $order->get_shipping_country() ); ?></li>
</ul>