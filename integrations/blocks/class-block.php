<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

class WC_Clickuz_Gateway_Blocks extends AbstractPaymentMethodType {

	/** @var WC_Gateway_Clickuz|null */
	private $gateway;

	protected $name = 'clickuz';

	public function initialize() {
		$this->settings = get_option( 'woocommerce_clickuz_settings', array() );

		if ( class_exists( 'WC_Gateway_Clickuz' ) ) {
			$this->gateway = new WC_Gateway_Clickuz();
		}
	}

	public function is_active() {
		return $this->gateway ? $this->gateway->is_available() : false;
	}

	public function get_payment_method_script_handles() {
		$asset_path = plugin_dir_path( __FILE__ ) . 'checkout.js';
		$version    = file_exists( $asset_path ) ? (string) filemtime( $asset_path ) : CLICK_VERSION;

		wp_register_script(
			'clickuz-blocks-integration',
			plugin_dir_url( __FILE__ ) . 'checkout.js',
			array(
				'wc-blocks-registry',
				'wc-settings',
				'wp-element',
				'wp-html-entities',
				'wp-i18n',
			),
			$version,
			true
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'clickuz-blocks-integration', 'clickuz' );
		}

		return array( 'clickuz-blocks-integration' );
	}

	public function get_payment_method_data() {
		if ( ! $this->gateway ) {
			return array();
		}

		return array(
			'title'       => $this->gateway->title,
			'description' => $this->gateway->description,
			'supports'    => array_filter( $this->gateway->supports, array( $this->gateway, 'supports' ) ),
		);
	}
}
