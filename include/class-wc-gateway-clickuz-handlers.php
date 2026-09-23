<?php
/**
 * CLICK SHOP-API callbacks (prepare / complete).
 *
 * @version 1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_ClickAPI {

	/** @var string */
	private $secret = '';

	/** @var string */
	private $service_id = '';

	/** @var string */
	private $after_payment_status = 'processing';

	/** @var string */
	private $table;

	public function __construct() {
		global $wpdb;

		$this->table = $wpdb->prefix . 'wc_click_transactions';

		add_filter( 'query_vars', array( $this, 'add_query_vars' ), 0 );
		add_action( 'init', array( $this, 'add_endpoint' ), 0 );
		add_action( 'parse_request', array( $this, 'handle_api_requests' ), 0 );
	}

	public function add_query_vars( $vars ) {
		$vars[] = 'click-api';

		return $vars;
	}

	public function add_endpoint() {
		add_rewrite_endpoint( 'click-api', EP_ALL );
	}

	public function handle_api_requests() {
		global $wp;

		if ( ! empty( $_GET['click-api'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$wp->query_vars['click-api'] = sanitize_key( wp_unslash( $_GET['click-api'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		}

		if ( empty( $wp->query_vars['click-api'] ) ) {
			return;
		}

		$api_request = strtolower( wc_clean( $wp->query_vars['click-api'] ) );

		if ( ! in_array( $api_request, array( 'prepare', 'complete' ), true ) ) {
			return;
		}

		$gateways = WC_Payment_Gateways::instance()->payment_gateways();

		if ( ! isset( $gateways['clickuz'] ) ) {
			return;
		}

		/** @var WC_Gateway_Clickuz $gateway */
		$gateway = $gateways['clickuz'];

		$this->secret               = (string) $gateway->get_secret_key();
		$this->service_id           = (string) $gateway->get_service_id();
		$this->after_payment_status = (string) $gateway->get_option( 'after_payment_status' );

		WC_Gateway_Clickuz::log( $api_request . ' request: ' . wp_json_encode( $this->raw_post() ) );

		$response = 'prepare' === $api_request ? $this->prepare() : $this->complete();

		WC_Gateway_Clickuz::log( $api_request . ' response: ' . wp_json_encode( $response ) );

		nocache_headers();
		wp_send_json( $response );
	}

	/* ------------------------------------------------------------------ */
	/* Helpers                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * Unslashed copy of $_POST — WordPress adds slashes to superglobals and the
	 * MD5 signature must be built from the original values.
	 */
	private function raw_post() {
		return wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
	}

	private function error( $code, $note ) {
		return array(
			'error'      => (string) $code,
			'error_note' => $note,
		);
	}

	/**
	 * @param array $post
	 * @param array $required
	 *
	 * @return bool
	 */
	private function has_required( $post, $required ) {
		foreach ( $required as $key ) {
			if ( ! isset( $post[ $key ] ) || '' === $post[ $key ] ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param string $merchant_trans_id
	 *
	 * @return WC_Order|false
	 */
	private function get_order( $merchant_trans_id ) {
		$order_id = $merchant_trans_id;

		if ( false !== strpos( (string) $merchant_trans_id, CLICK_DELIMITER ) ) {
			$parts    = explode( CLICK_DELIMITER, (string) $merchant_trans_id );
			$order_id = end( $parts );
		}

		$order_id = absint( $order_id );

		if ( ! $order_id ) {
			return false;
		}

		$order = wc_get_order( $order_id );

		return ( $order instanceof WC_Order ) ? $order : false;
	}

	/**
	 * Row payload shared by prepare/complete.
	 */
	private function row_data( $post, $order, $status ) {
		return array(
			'click_trans_id'    => absint( $post['click_trans_id'] ),
			'service_id'        => absint( $post['service_id'] ),
			'click_paydoc_id'   => isset( $post['click_paydoc_id'] ) ? absint( $post['click_paydoc_id'] ) : 0,
			'merchant_trans_id' => $order->get_id(),
			'amount'            => (float) $post['amount'],
			'error'             => isset( $post['error'] ) ? (int) $post['error'] : 0,
			'error_note'        => isset( $post['error_note'] ) ? substr( sanitize_text_field( $post['error_note'] ), 0, 255 ) : '',
			'status'            => $status,
		);
	}

	private function row_formats() {
		return array( '%d', '%d', '%d', '%d', '%f', '%d', '%s', '%s' );
	}

	/**
	 * Amount check against the order total, using the same precision we send out.
	 */
	private function amount_matches( $order, $amount ) {
		$expected = (float) WC_Gateway_Clickuz::get_amount( $order );

		return abs( $expected - (float) $amount ) < 0.01;
	}

	/* ------------------------------------------------------------------ */
	/* prepare                                                             */
	/* ------------------------------------------------------------------ */

	public function prepare() {
		global $wpdb;

		$post = $this->raw_post();

		$required = array(
			'click_trans_id',
			'service_id',
			'merchant_trans_id',
			'amount',
			'action',
			'sign_time',
			'sign_string',
		);

		if ( ! $this->has_required( $post, $required ) ) {
			return $this->error( -8, __( 'Error in request from click', 'clickuz' ) );
		}

		$sign_string = md5(
			$post['click_trans_id'] .
			$post['service_id'] .
			$this->secret .
			$post['merchant_trans_id'] .
			$post['amount'] .
			$post['action'] .
			$post['sign_time']
		);

		if ( ! hash_equals( $sign_string, (string) $post['sign_string'] ) ) {
			return $this->error( -1, __( 'Sign check error', 'clickuz' ) );
		}

		$order = $this->get_order( $post['merchant_trans_id'] );

		if ( ! $order ) {
			return $this->error( -5, __( 'User does not exist', 'clickuz' ) );
		}

		if ( $order->is_paid() ) {
			return $this->error( -4, __( 'Already paid', 'clickuz' ) );
		}

		if ( ! $this->amount_matches( $order, $post['amount'] ) ) {
			return $this->error( -2, __( 'Incorrect parameter amount', 'clickuz' ) );
		}

		$prepare_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$this->table} WHERE merchant_trans_id = %d ORDER BY ID DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$order->get_id()
			)
		);

		$data    = $this->row_data( $post, $order, 'prepare' );
		$formats = $this->row_formats();

		if ( ! $prepare_id ) {
			$inserted = $wpdb->insert( $this->table, $data, $formats ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

			if ( false === $inserted ) {
				WC_Gateway_Clickuz::log( 'DB insert failed: ' . $wpdb->last_error, 'error' );

				return $this->error( -7, __( 'Failed to update user', 'clickuz' ) );
			}

			$prepare_id = (int) $wpdb->insert_id;
		} else {
			$prepare_id = (int) $prepare_id;

			$updated = $wpdb->update( $this->table, $data, array( 'ID' => $prepare_id ), $formats, array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

			if ( false === $updated ) {
				WC_Gateway_Clickuz::log( 'DB update failed: ' . $wpdb->last_error, 'error' );

				return $this->error( -7, __( 'Failed to update user', 'clickuz' ) );
			}
		}

		// Persist: set_* alone does not write to the database.
		$order->set_transaction_id( absint( $post['click_trans_id'] ) );
		$order->update_status( 'on-hold', __( 'Click Prepare requested, reserving products.', 'clickuz' ) );

		return array(
			'click_trans_id'      => $post['click_trans_id'],
			'merchant_trans_id'   => $post['merchant_trans_id'],
			'merchant_prepare_id' => $prepare_id,
			'error'               => '0',
			'error_note'          => __( 'Success', 'clickuz' ),
		);
	}

	/* ------------------------------------------------------------------ */
	/* complete                                                            */
	/* ------------------------------------------------------------------ */

	public function complete() {
		global $wpdb;

		$post = $this->raw_post();

		$required = array(
			'click_trans_id',
			'service_id',
			'merchant_trans_id',
			'merchant_prepare_id',
			'amount',
			'action',
			'sign_time',
			'sign_string',
		);

		if ( ! $this->has_required( $post, $required ) ) {
			return $this->error( -8, __( 'Error in request from click', 'clickuz' ) );
		}

		$sign_string = md5(
			$post['click_trans_id'] .
			$post['service_id'] .
			$this->secret .
			$post['merchant_trans_id'] .
			$post['merchant_prepare_id'] .
			$post['amount'] .
			$post['action'] .
			$post['sign_time']
		);

		if ( ! hash_equals( $sign_string, (string) $post['sign_string'] ) ) {
			return $this->error( -1, __( 'Sign check error', 'clickuz' ) );
		}

		$order = $this->get_order( $post['merchant_trans_id'] );

		if ( ! $order ) {
			return $this->error( -5, __( 'User does not exist', 'clickuz' ) );
		}

		$prepare_id = absint( $post['merchant_prepare_id'] );

		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(ID) FROM {$this->table} WHERE ID = %d AND merchant_trans_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$prepare_id,
				$order->get_id()
			)
		);

		if ( ! $exists ) {
			return $this->error( -6, __( 'Transaction does not exist', 'clickuz' ) );
		}

		if ( ! $this->amount_matches( $order, $post['amount'] ) ) {
			return $this->error( -2, __( 'Incorrect parameter amount', 'clickuz' ) );
		}

		if ( $order->has_status( 'failed' ) ) {
			return $this->error( -9, __( 'Transaction cancelled', 'clickuz' ) );
		}

		if ( $order->is_paid() ) {
			return $this->error( -4, __( 'Already paid', 'clickuz' ) );
		}

		$click_error = isset( $post['error'] ) ? (int) $post['error'] : 0;

		if ( $click_error < 0 ) {
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$this->table,
				$this->row_data( $post, $order, 'cancelled' ),
				array( 'ID' => $prepare_id ),
				$this->row_formats(),
				array( '%d' )
			);

			$note = isset( $post['error_note'] ) ? sanitize_text_field( $post['error_note'] ) : __( 'Transaction cancelled', 'clickuz' );

			$order->set_transaction_id( '' );
			$order->update_status( 'failed', $note );

			return array(
				'click_trans_id'      => $post['click_trans_id'],
				'merchant_trans_id'   => $post['merchant_trans_id'],
				'merchant_confirm_id' => $prepare_id,
				'error'               => '-9',
				'error_note'          => __( 'Transaction cancelled', 'clickuz' ),
			);
		}

		$updated = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table,
			$this->row_data( $post, $order, 'complete' ),
			array( 'ID' => $prepare_id ),
			$this->row_formats(),
			array( '%d' )
		);

		if ( false === $updated ) {
			WC_Gateway_Clickuz::log( 'DB update failed: ' . $wpdb->last_error, 'error' );

			return $this->error( -7, __( 'Failed to update user', 'clickuz' ) );
		}

		// payment_complete() stores the transaction id, reduces stock and saves the order.
		$order->payment_complete( absint( $post['click_trans_id'] ) );

		$target_status = $this->after_payment_status ? str_replace( 'wc-', '', $this->after_payment_status ) : '';

		if ( $target_status && ! $order->has_status( $target_status ) ) {
			$order->update_status( $target_status, __( 'Status set after successful CLICK payment.', 'clickuz' ) );
		}

		return array(
			'click_trans_id'      => $post['click_trans_id'],
			'merchant_trans_id'   => $post['merchant_trans_id'],
			'merchant_confirm_id' => $prepare_id,
			'error'               => '0',
			'error_note'          => __( 'Success', 'clickuz' ),
		);
	}
}
