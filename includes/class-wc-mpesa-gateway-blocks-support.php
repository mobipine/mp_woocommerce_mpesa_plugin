<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WC_Mpesa_Gateway_Blocks_Support extends AbstractPaymentMethodType {
	
	private $gateway;
	
	protected $name = 'mpesa'; // payment gateway id

	public function initialize() {
		// get payment gateway settings
		$this->settings = get_option( "woocommerce_{$this->name}_settings", array() );
		
		// you can also initialize your payment gateway here
		// $gateways = WC()->payment_gateways->payment_gateways();
		// $this->gateway  = $gateways[ $this->name ];
	}

	public function is_active() {
		return ! empty( $this->settings[ 'enabled' ] ) && 'yes' === $this->settings[ 'enabled' ];
	}

	public function get_payment_method_script_handles() {

		wp_register_script(
			'wc-mpesa-blocks-integration',
			plugin_dir_url( __DIR__ ) . 'build/index.js',
			array(
				'wc-blocks-registry',
				'wc-settings',
				'wp-element',
				'wp-html-entities',
			),
			filemtime( plugin_dir_path( __DIR__ ) . 'build/index.js' ),
			true
		);

		return array( 'wc-mpesa-blocks-integration' );

	}

	public function get_payment_method_data() {
		return array(
			'title'        => $this->get_setting( 'title' ),
			'description'  => $this->get_setting( 'description' ),
			'instructions' => $this->get_setting( 'instructions' ),
			'icon'         => plugin_dir_url( __DIR__ ) . 'assets/icon.png',
			

			// almost the same way:
			// 'title'     => isset( $this->settings[ 'title' ] ) ? $this->settings[ 'title' ] : 'Default value';
			
			// if $this->gateway was initialized on line 15
			// 'supports'  => array_filter( $this->gateway->supports, [ $this->gateway, 'supports' ] ),

			// example of getting a public key
			// 'publicKey' => $this->get_publishable_key(),
		);
	}

	//private function get_publishable_key() {
	//	$test_mode   = ( ! empty( $this->settings[ 'testmode' ] ) && 'yes' === $this->settings[ 'testmode' ] );
	//	$setting_key = $test_mode ? 'test_publishable_key' : 'publishable_key';
	//	return ! empty( $this->settings[ $setting_key ] ) ? $this->settings[ $setting_key ] : '';
	//}

}
