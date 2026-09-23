<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(

	'enabled' => array(
		'title'   => __( 'Enable/Disable', 'clickuz' ),
		'type'    => 'checkbox',
		'label'   => __( 'Enable pay via CLICK', 'clickuz' ),
		'default' => 'yes',
	),

	'title' => array(
		'title'       => __( 'Title', 'clickuz' ),
		'type'        => 'text',
		'default'     => 'CLICK',
		'description' => __( 'Payment method title shown at checkout.', 'clickuz' ),
		'desc_tip'    => true,
	),

	'description' => array(
		'title'    => __( 'Description', 'clickuz' ),
		'type'     => 'textarea',
		'default'  => __( 'Pay with CLICK', 'clickuz' ),
		'desc_tip' => true,
	),

	'api_details' => array(
		'title'       => __( 'API credentials', 'clickuz' ),
		'type'        => 'title',
		'description' => __( 'Enter your Click.Uz API credentials.', 'clickuz' ),
	),

	'merchant_id' => array(
		'title'    => __( 'Merchant ID', 'clickuz' ),
		'type'     => 'text',
		'default'  => '',
		'desc_tip' => true,
	),

	'merchant_user_id' => array(
		'title'    => __( 'Merchant User ID', 'clickuz' ),
		'type'     => 'text',
		'default'  => '',
		'desc_tip' => true,
	),

	'merchant_service_id' => array(
		'title'    => __( 'Merchant Service ID', 'clickuz' ),
		'type'     => 'text',
		'default'  => '',
		'desc_tip' => true,
	),

	'secret_key' => array(
		'title'    => __( 'Secret Key', 'clickuz' ),
		'type'     => 'password',
		'default'  => '',
		'desc_tip' => true,
	),

	'click_button' => array(
		'title' => __( 'Pay button details', 'clickuz' ),
		'type'  => 'title',
	),

	'click_button_type' => array(
		'title'   => __( 'Button type', 'clickuz' ),
		'type'    => 'select',
		'default' => 'redirect',
		'options' => array(
			'redirect' => __( 'With redirect', 'clickuz' ),
			'popup'    => __( 'Without redirect', 'clickuz' ),
		),
	),

	'click_button_title' => array(
		'title'   => __( 'Button title', 'clickuz' ),
		'type'    => 'text',
		'default' => __( 'Pay with CLICK', 'clickuz' ),
	),

	'after_payment' => array(
		'title' => __( 'After payment details', 'clickuz' ),
		'type'  => 'title',
	),

	'after_payment_status' => array(
		'title'   => __( 'Status of order after payment', 'clickuz' ),
		'type'    => 'select',
		'default' => 'wc-processing',
		'options' => function_exists( 'wc_get_order_statuses' ) ? wc_get_order_statuses() : array(),
	),

	'url_details' => array(
		'title'       => __( 'Service parameters', 'clickuz' ),
		'type'        => 'title',
		'description' => __( 'Url addesses for changing to set on Merchant cabinet', 'clickuz' ) . '<br/><br/>' .
			__( 'Prepare url', 'clickuz' ) . ': ' . esc_url( site_url( 'click-api/prepare' ) ) . '<br/><br/>' .
			__( 'Complete url', 'clickuz' ) . ': ' . esc_url( site_url( 'click-api/complete' ) ),
	),

	'debug' => array(
		'title'       => __( 'Debug log', 'clickuz' ),
		'type'        => 'checkbox',
		'label'       => __( 'Enable logging', 'clickuz' ),
		'default'     => 'no',
		'description' => __( 'Log CLICK requests and responses to WooCommerce > Status > Logs (source: clickuz).', 'clickuz' ),
	),
);
