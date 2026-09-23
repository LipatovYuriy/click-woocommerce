<?php
/**
 * Click.uz Payment Gateway.
 *
 * @class   WC_Gateway_Clickuz
 * @extends WC_Payment_Gateway
 * @version 1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Gateway_Clickuz extends WC_Payment_Gateway {

	const PAY_URL = 'https://my.click.uz/services/pay';

	/** @var WC_Logger|null */
	protected static $log = null;

	public function __construct() {
		$this->id                 = 'clickuz';
		$this->has_fields         = false;
		$this->order_button_text  = __( 'Pay', 'clickuz' );
		$this->method_title       = 'CLICK';
		$this->method_description = __( 'Proceed payment with CLICK', 'clickuz' );
		$this->supports           = array( 'products' );

		$this->init_form_fields();
		$this->init_settings();

		$title             = $this->get_option( 'title' );
		$description       = $this->get_option( 'description' );
		$this->title       = '' !== $title ? $title : 'CLICK';
		$this->description = '' !== $description ? $description : __( 'Pay with CLICK', 'clickuz' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'form' ) );
	}

	/**
	 * Credential accessors — a wp-config constant wins over the stored option.
	 */
	public function get_merchant_id() {
		return defined( 'CLICK_MERCHANT_ID' ) && CLICK_MERCHANT_ID ? CLICK_MERCHANT_ID : $this->get_option( 'merchant_id' );
	}

	public function get_merchant_user_id() {
		return defined( 'CLICK_MERCHANT_USER_ID' ) && CLICK_MERCHANT_USER_ID ? CLICK_MERCHANT_USER_ID : $this->get_option( 'merchant_user_id' );
	}

	public function get_service_id() {
		return defined( 'CLICK_SERVICE_ID' ) && CLICK_SERVICE_ID ? CLICK_SERVICE_ID : $this->get_option( 'merchant_service_id' );
	}

	public function get_secret_key() {
		return defined( 'CLICK_SECRET_KEY' ) && CLICK_SECRET_KEY ? CLICK_SECRET_KEY : $this->get_option( 'secret_key' );
	}

	/**
	 * Hide the method until it is actually configured.
	 */
	public function is_available() {
		if ( ! parent::is_available() ) {
			return false;
		}

		if ( ! $this->get_merchant_id() || ! $this->get_service_id() || ! $this->get_secret_key() ) {
			return false;
		}

		/**
		 * CLICK settles in UZS. Return true from this filter if your shop converts elsewhere.
		 */
		return (bool) apply_filters( 'clickuz_is_available', 'UZS' === get_woocommerce_currency(), $this );
	}

	public function get_icon() {
		$icon_html = '<img src="' . esc_url( CLICK_LOGO ) . '" alt="CLICK" style="max-height:24px;width:auto;" />';

		return apply_filters( 'woocommerce_gateway_icon', $icon_html, $this->id );
	}

	/**
	 * Logging (enabled by the "Debug log" setting).
	 */
	public static function log( $message, $level = 'info' ) {
		$settings = get_option( 'woocommerce_clickuz_settings', array() );

		if ( empty( $settings['debug'] ) || 'yes' !== $settings['debug'] ) {
			return;
		}

		if ( null === self::$log ) {
			self::$log = wc_get_logger();
		}

		self::$log->log( $level, is_scalar( $message ) ? $message : wp_json_encode( $message ), array( 'source' => 'clickuz' ) );
	}

	public function init_form_fields() {
		$this->form_fields = include __DIR__ . '/settings.php';
	}

	/**
	 * Amount sent to CLICK. Must match what the prepare/complete callbacks verify.
	 *
	 * @param WC_Order $order
	 *
	 * @return string
	 */
	public static function get_amount( $order ) {
		return number_format( (float) $order->get_total(), 2, '.', '' );
	}

	/**
	 * transaction_param: "<order number>|<order id>" when a plugin changes the visible number.
	 *
	 * @param WC_Order $order
	 *
	 * @return string
	 */
	public static function get_transaction_param( $order ) {
		$order_id     = $order->get_id();
		$order_number = $order->get_order_number();

		return (string) $order_id !== (string) $order_number
			? $order_number . CLICK_DELIMITER . $order_id
			: (string) $order_id;
	}

	/**
	 * Return URL. order-received works for guests too; view-order requires a login.
	 *
	 * @param WC_Order $order
	 *
	 * @return string
	 */
	public static function get_return_url_for( $order ) {
		$customer_id = $order->get_customer_id();

		$url = add_query_arg( array( 'click-return' => $customer_id ), $order->get_checkout_order_received_url() );

		return apply_filters( 'click_return_url', $url, $order );
	}

	/**
	 * @param int $order_id
	 *
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			wc_add_notice( __( 'Order not found.', 'clickuz' ), 'error' );

			return array( 'result' => 'failure' );
		}

		$query_args = array(
			'merchant_id'       => $this->get_merchant_id(),
			'merchant_user_id'  => $this->get_merchant_user_id(),
			'service_id'        => $this->get_service_id(),
			'transaction_param' => self::get_transaction_param( $order ),
			'amount'            => self::get_amount( $order ),
			'return_url'        => self::get_return_url_for( $order ),
		);

		$order->update_status( 'pending', __( 'Awaiting CLICK payment.', 'clickuz' ) );

		self::log( 'Redirecting order #' . $order->get_id() . ' to CLICK: ' . wp_json_encode( $query_args ) );

		return array(
			'result'   => 'success',
			'redirect' => add_query_arg( $query_args, self::PAY_URL ),
		);
	}

	/**
	 * @param WC_Order $order
	 *
	 * @return bool
	 */
	public function can_refund_order( $order ) {
		return false; // CLICK refunds are done from the merchant cabinet.
	}

	/**
	 * Receipt page (order-pay flow).
	 *
	 * @param int $order_id
	 */
	public function form( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$merchant_id      = $this->get_merchant_id();
		$merchant_user_id = $this->get_merchant_user_id();
		$service_id       = $this->get_service_id();
		$trans_id         = self::get_transaction_param( $order );
		$amount           = self::get_amount( $order );
		$return_url       = self::get_return_url_for( $order );
		$button_title     = $this->get_option( 'click_button_title' );

		if ( '' === $button_title ) {
			$button_title = __( 'Pay with CLICK', 'clickuz' );
		}

		if ( ! $merchant_id || ! $service_id ) {
			echo '<p>' . esc_html__( 'CLICK payment method is not configured.', 'clickuz' ) . '</p>';

			return;
		}

		if ( 'redirect' === $this->get_option( 'click_button_type' ) ) : ?>
			<form action="<?php echo esc_url( self::PAY_URL ); ?>" id="click-pay-form" method="get">
				<input type="hidden" name="amount" value="<?php echo esc_attr( $amount ); ?>"/>
				<input type="hidden" name="merchant_id" value="<?php echo esc_attr( $merchant_id ); ?>"/>
				<input type="hidden" name="merchant_user_id" value="<?php echo esc_attr( $merchant_user_id ); ?>"/>
				<input type="hidden" name="service_id" value="<?php echo esc_attr( $service_id ); ?>"/>
				<input type="hidden" name="transaction_param" value="<?php echo esc_attr( $trans_id ); ?>"/>
				<input type="hidden" name="return_url" value="<?php echo esc_url( $return_url ); ?>"/>
				<button type="submit" id="click-pay-button"><i></i><?php echo esc_html( $button_title ); ?></button>
			</form>
		<?php else : ?>
			<button type="button" id="click-pay-button"><i></i><?php echo esc_html( $button_title ); ?></button>

			<script src="https://my.click.uz/pay/checkout.js"></script>
			<script>
				( function () {
					var params = <?php echo wp_json_encode(
						array(
							'merchant_id'       => (int) $merchant_id,
							'merchant_user_id'  => (string) $merchant_user_id,
							'service_id'        => (int) $service_id,
							'transaction_param' => (string) $trans_id,
							'amount'            => (float) $amount,
						)
					); ?>;
					var returnUrl = <?php echo wp_json_encode( $return_url ); ?>;
					var btn = document.getElementById( 'click-pay-button' );

					if ( ! btn || typeof createPaymentRequest !== 'function' ) {
						return;
					}

					btn.addEventListener( 'click', function () {
						createPaymentRequest( params, function ( data ) {
							if ( data && ( 2 === data.status || 0 === data.status ) ) {
								window.location.href = returnUrl;
							}
						} );
					} );
				} )();
			</script>
		<?php endif; ?>

		<style>
			#click-pay-button {
				width: auto;
				border: 0;
				border-radius: 4px;
				background: #00a6ff;
				margin: 10px 0 0;
				padding: 0 15px;
				height: 49px;
				font: 17px/49px "Microsoft Sans Serif", Arial, Helvetica, sans-serif;
				color: #fff;
				cursor: pointer;
			}

			#click-pay-button i {
				background: url(https://m.click.uz/static/img/logo.png) no-repeat center left;
				width: 30px;
				height: 49px;
				float: left;
			}
		</style>
		<?php
	}
}
