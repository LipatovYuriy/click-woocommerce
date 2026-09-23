<?php
/**
 * Plugin Name: Woocommerce CLICK Payment Method
 * Plugin URI: https://click.uz
 * Description: CLICK Payment Method Plugin for WooCommerce
 * Version: 1.2.1
 * Author: OOO "Click"
 * Author URI: https://click.uz
 * Text Domain: clickuz
 * Domain Path: /i18n/languages/
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 7.0
 * WC tested up to: 10.2
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CLICK_VERSION', '1.2.1' );
define( 'CLICK_DB_VERSION', '1.2' );
define( 'CLICK_PLUGIN_FILE', __FILE__ );
define( 'CLICK_LOGO', plugin_dir_url( __FILE__ ) . 'click-logo.png' );
define( 'CLICK_DELIMITER', '|' );

final class WC_ClickUz {

	/** @var bool Guard so classes are included only once. */
	private $included = false;

	public function __construct() {
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		// Textdomain must be loaded on `init`, not earlier (WP 6.7+ notice otherwise).
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// WooCommerce boots on plugins_loaded:10, so we hook after it.
		add_action( 'plugins_loaded', array( $this, 'init' ), 11 );

		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'settings_link' ) );
		add_action( 'woocommerce_init', array( $this, 'wc_init' ) );
		add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateway' ) );

		add_action( 'before_woocommerce_init', array( $this, 'declare_compatibility' ) );
		add_action( 'woocommerce_blocks_loaded', array( $this, 'register_blocks_support' ) );
	}

	public function load_textdomain() {
		load_plugin_textdomain( 'clickuz', false, dirname( plugin_basename( __FILE__ ) ) . '/i18n/languages/' );
	}

	/**
	 * Include gateway classes. Idempotent — may be called from several hooks.
	 */
	public function includes() {
		if ( $this->included || ! class_exists( 'WC_Payment_Gateway' ) ) {
			return $this->included;
		}

		require_once __DIR__ . '/include/class-wc-gateway-clickuz.php';
		require_once __DIR__ . '/include/class-wc-gateway-clickuz-handlers.php';

		$this->included = true;

		return true;
	}

	public function init() {
		if ( ! $this->includes() ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );

			return;
		}

		new WC_ClickAPI();

		$this->maybe_update_db();
	}

	public function woocommerce_missing_notice() {
		echo '<div class="error"><p>' .
			esc_html__( 'CLICK Payment Method requires WooCommerce to be installed and active.', 'clickuz' ) .
			'</p></div>';
	}

	/**
	 * Empty the cart when the customer comes back from the CLICK payment page.
	 * WC()->customer / WC()->cart exist for frontend requests only.
	 */
	public function wc_init() {
		if ( ! isset( $_GET['click-return'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		if ( ! function_exists( 'WC' ) || ! WC()->customer || ! WC()->cart ) {
			return;
		}

		$returned = sanitize_text_field( wp_unslash( $_GET['click-return'] ) ); // phpcs:ignore WordPress.Security.NonceVerification

		if ( (string) $returned === (string) WC()->customer->get_id() ) {
			WC()->cart->empty_cart( true );
		}
	}

	public function activate() {
		if ( ! function_exists( 'curl_exec' ) ) {
			wp_die( '<pre>This plugin requires the PHP cURL extension in order to be activated.</pre>' );
		}

		$this->install();

		flush_rewrite_rules();
	}

	public function deactivate() {
		flush_rewrite_rules();
	}

	/**
	 * Run the schema update after a plugin update too, not only on activation.
	 */
	public function maybe_update_db() {
		if ( get_option( 'wc_click_db_version' ) !== CLICK_DB_VERSION ) {
			$this->install();
		}
	}

	public function install() {
		global $wpdb;

		$wpdb->hide_errors();

		$collate = '';

		if ( $wpdb->has_cap( 'collation' ) ) {
			$collate = $wpdb->get_charset_collate();
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table = $wpdb->prefix . 'wc_click_transactions';

		// NB: `error` must be signed — CLICK sends negative error codes.
		dbDelta(
			"CREATE TABLE {$table} (
  ID bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  click_trans_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  service_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  click_paydoc_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  merchant_trans_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  amount decimal(20,2) NOT NULL DEFAULT 0,
  error bigint(20) NOT NULL DEFAULT 0,
  error_note varchar(255) DEFAULT NULL,
  status varchar(32) DEFAULT NULL,
  PRIMARY KEY  (ID),
  KEY merchant_trans_id (merchant_trans_id),
  KEY click_trans_id (click_trans_id)
) {$collate};"
		);

		update_option( 'wc_click_db_version', CLICK_DB_VERSION );
	}

	public function settings_link( $links ) {
		$settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=clickuz' ) ) . '">' .
			esc_html__( 'Settings', 'clickuz' ) . '</a>';

		array_unshift( $links, $settings_link );

		return $links;
	}

	public function add_gateway( $methods ) {
		$methods[] = 'WC_Gateway_Clickuz';

		return $methods;
	}

	/**
	 * HPOS (custom order tables) + Cart/Checkout blocks compatibility.
	 */
	public function declare_compatibility() {
		if ( ! class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			return;
		}

		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
	}

	public function register_blocks_support() {
		if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
			return;
		}

		// Blocks may load before our plugins_loaded:11 callback.
		$this->includes();

		require_once plugin_dir_path( __FILE__ ) . 'integrations/blocks/class-block.php';

		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			static function ( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
				$payment_method_registry->register( new WC_Clickuz_Gateway_Blocks() );
			}
		);
	}
}

new WC_ClickUz();
