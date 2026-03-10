<?php
/**
 * Plugin Name: Viva Wallet Standard Checkout
 * Plugin URI: http://www.vivawallet.com/
 * Description: Connects WooCommerce to Viva Wallet payment gateway (standard checkout) to process and sync your payments and help you sell more.
 * ShortDescription: Viva Wallet Standard Checkout
 * Version: 4.0.1
 * Author: Viva Wallet
 * Author URI: https://www.vivawallet.com/
 * Text Domain: viva-wallet-for-woocommerce
 * Domain Path: /languages
 * WC requires at least: 3.0
 * WC tested up to: 5.4.0
 * Woo: 6137160:02eafc4556bd66f7c9fc73fd3a51749c
 *
 * @package VivaWalletForWooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}



/**
 * Required minimums and constants
 */
define( 'WC_VIVAWALLET_VERSION', '4.0.1' );


add_action( 'plugins_loaded', 'woocommerce_gateway_vivawallet_init' );

/**
 * Woocommerce_vivawallet_missing_wc_notice
 */
function woocommerce_vivawallet_missing_wc_notice() {
	/* translators: error message */
	echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'The Viva Wallet payment gateway requires WooCommerce to work. You can download %s here.', 'viva-wallet-for-woocommerce' ), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>' ) . '</strong></p></div>';
}

/**
 * Vivawallet_fix_locale
 *
 * @param string $locale locale.
 * @param string $domain domain.
 * @return string
 */
function woocommerce_vivawallet_fix_locale( $locale, $domain ) {
	if ( 'viva-wallet-for-woocommerce' === $domain ) {
		$locale = substr( $locale, 0, 2 );
	}
	return $locale;
}

/**
 * Woocommerce_gateway_vivawallet INIT
 */
function woocommerce_gateway_vivawallet_init() {

	add_filter( 'plugin_locale', 'woocommerce_vivawallet_fix_locale', 99, 2 );

	load_plugin_textdomain( 'viva-wallet-for-woocommerce', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );

	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'woocommerce_vivawallet_missing_wc_notice' );
		return;
	}

	if ( ! class_exists( 'PGWC_Vivawallet' ) ) :

		define( 'WC_VIVAWALLET_MIN_PHP_VER', '5.6.0' );
		define( 'WC_VIVAWALLET_MIN_WC_VER', '3.0.0' );
		define( 'WC_VIVAWALLET_FUTURE_MIN_WC_VER', '4.0' );
		define( 'WC_VIVAWALLET_MAIN_FILE', __FILE__ );
		define( 'WC_VIVAWALLET_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
		define( 'WC_VIVAWALLET_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );

		/**
		 * PGWC_Vivawallet
		 */
		class PGWC_Vivawallet {

			/**
			 * Instance
			 *
			 * @var Singleton The reference the *Singleton* instance of this class
			 */
			private static $instance;

			/**
			 * Get instance
			 *
			 * @return Singleton The *Singleton* instance.
			 */
			public static function get_instance() {
				if ( null === self::$instance ) {
					self::$instance = new self();
				}
				return self::$instance;
			}
			/**
			 * Clone
			 */
			private function __clone() {
			}
			/**
			 * Wakeup
			 */
			public function __wakeup() {
			}
			/**
			 * Construct
			 */
			private function __construct() {
				$this->init();
			}

			/**
			 * Init
			 */
			public function init() {
				if ( is_admin() ) {
					include_once WC_VIVAWALLET_PLUGIN_PATH . '/includes/admin/class-wc-vivawallet-source.php';
					include_once WC_VIVAWALLET_PLUGIN_PATH . '/includes/admin/vivawallet-error-page.php';
				}

				include_once WC_VIVAWALLET_PLUGIN_PATH . '/includes/class-wc-vivawallet-logger.php';
				include_once WC_VIVAWALLET_PLUGIN_PATH . '/includes/class-wc-vivawallet-credentials.php';
				include_once WC_VIVAWALLET_PLUGIN_PATH . '/includes/class-wc-vivawallet.php';
				include_once WC_VIVAWALLET_PLUGIN_PATH . '/includes/admin/vivawallet-settings.php';
				include_once WC_VIVAWALLET_PLUGIN_PATH . '/includes/class-wc-vivawallet-helper.php';
				include_once WC_VIVAWALLET_PLUGIN_PATH . '/includes/class-wc-vivawallet-refund.php';
				include_once WC_VIVAWALLET_PLUGIN_PATH . '/includes/class-wc-vivawallet-subscriptions.php';

				include_once WC_VIVAWALLET_PLUGIN_PATH . '/includes/class-wc-vivawallet-apm.php';

				include_once WC_VIVAWALLET_PLUGIN_PATH . '/includes/class-wc-vivawallet-ideal.php';
				include_once WC_VIVAWALLET_PLUGIN_PATH . '/includes/class-wc-vivawallet-p24.php';
				include_once WC_VIVAWALLET_PLUGIN_PATH . '/includes/class-wc-vivawallet-payu.php';
				include_once WC_VIVAWALLET_PLUGIN_PATH . '/includes/class-wc-vivawallet-multibanco.php';
				include_once WC_VIVAWALLET_PLUGIN_PATH . '/includes/class-wc-vivawallet-giropay.php';
				include_once WC_VIVAWALLET_PLUGIN_PATH . '/includes/class-wc-vivawallet-directpay.php';
				include_once WC_VIVAWALLET_PLUGIN_PATH . '/includes/class-wc-vivawallet-eps.php';
				include_once WC_VIVAWALLET_PLUGIN_PATH . '/includes/class-wc-vivawallet-wechatpay.php';
				include_once WC_VIVAWALLET_PLUGIN_PATH . '/includes/class-wc-vivawallet-bitpay.php';

				if ( is_admin() ) {
					include_once WC_VIVAWALLET_PLUGIN_PATH . '/includes/class-wc-vivawallet-apple-pay-register.php';
				}

				include_once WC_VIVAWALLET_PLUGIN_PATH . '/includes/class-wc-vivawallet-applepay.php';

				include_once WC_VIVAWALLET_PLUGIN_PATH . '/includes/class-wc-vivawallet-endpoints.php';

				// check versions of plugin. if updated refresh tokens, change DB version option and log it.
				$viva_settings = get_option( 'woocommerce_vivawallet_native_settings', array() );
				if ( empty( $viva_settings['version'] ) ) {
					$this->update_version_in_db( $viva_settings, false );
				} elseif ( WC_VIVAWALLET_VERSION !== $viva_settings['version'] ) {
					$this->update_version_in_db( $viva_settings, true );
				}

				add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateways' ) );

				add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );

				add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );

			}

			/**
			 * Update_version_in_db
			 *
			 * @param array   $viva_settings Settings.
			 * @param boolean $existing Existing.
			 */
			private function update_version_in_db( $viva_settings, $existing ) {
				global $wp_version;
				$active_plugins = get_option( 'active_plugins' );

				if ( $existing ) {
					$res = "VERSION OF VIVA WALLET PLUGIN WAS UPDATED \nNew version: " . WC_VIVAWALLET_VERSION . "\nOld version: " . $viva_settings['version'];
				} else {
					$res = "VERSION OF VIVA WALLET PLUGIN WAS UPDATED \nNew version: " . WC_VIVAWALLET_VERSION;
				}
				$res .= "\nEnvironment info: \n       WordPress Version: " . $wp_version;

				// Check if get_plugins() function exists. This is required on the front end of the site.
				if ( ! function_exists( 'get_plugins' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}
				$all_plugins = get_plugins();

				$res .= "\nActive Plugins: ";

				foreach ( $active_plugins as $plugin ) {
					$author  = empty( $all_plugins[ $plugin ]['Author'] ) ? 'Unknown' : $all_plugins[ $plugin ]['Author'];
					$version = empty( $all_plugins[ $plugin ]['Version'] ) ? 'Unknown version' : $all_plugins[ $plugin ]['Version'];
					$res    .= "\n       " . $all_plugins[ $plugin ]['Name'] . ' by ' . $author . ' - ' . $version;
				}

				WC_Vivawallet_Logger::log( $res, true );
				// update settings in DB.
				$viva_settings['version'] = WC_VIVAWALLET_VERSION;
				update_option( 'woocommerce_vivawallet_native_settings', $viva_settings );
			}

			/**
			 * Add gateways
			 *
			 * @param array $gateways add vivawallet gateways.
			 *
			 * @return array
			 */
			public function add_gateways( $gateways ) {

				if ( class_exists( 'WC_Subscriptions_Order' ) && function_exists( 'wcs_create_renewal_order' ) ) {
					$gateways[] = 'WC_Vivawallet_Payment_Gateway_Subscriptions';
				} else {
					$gateways[] = 'WC_Vivawallet_Payment_Gateway';
				}
				$gateways[] = 'WC_Vivawallet_IDeal';
				$gateways[] = 'WC_Vivawallet_P24';
				$gateways[] = 'WC_Vivawallet_PayU';
				$gateways[] = 'WC_Vivawallet_Multibanco';
				$gateways[] = 'WC_Vivawallet_Giropay';
				$gateways[] = 'WC_Vivawallet_DirectPay';
				$gateways[] = 'WC_Vivawallet_Eps';
				$gateways[] = 'WC_Vivawallet_WeChatPay';
				$gateways[] = 'WC_Vivawallet_BitPay';

				// 10    IDeal.
				// 11    P24.
				// 12    Blik.
				// 13    PayU.
				// 14    Multibanco.
				// 15    Giropay.
				// 16    DirectPay.
				// 17    Eps.
				// 18    WeChatPay.
				// 19    BitPay.

				return $gateways;
			}

			/**
			 * Add plugin action links.
			 *
			 * @param array $links Links.
			 *
			 * @return array
			 */
			public function plugin_action_links( $links ) {
				$plugin_links = array(
					'<a href="admin.php?page=wc-settings&tab=checkout&section=vivawallet_native">' . esc_html__( 'Settings', 'viva-wallet-for-woocommerce' ) . '</a>',
				);
				return array_merge( $plugin_links, $links );
			}

			/**
			 * Add plugin row meta.
			 *
			 * @param mixed $links Plugin Row Meta.
			 * @param mixed $file  Plugin Base file.
			 *
			 * @return array
			 */
			public function plugin_row_meta( $links, $file ) {
				if ( plugin_basename( __FILE__ ) === $file ) {
					$row_meta = array(
						'docs'    => '<a href="' . esc_url( 'https://docs.woocommerce.com/document/viva-wallet-for-woocommerce' ) . '" aria-label="' . esc_attr__( 'View Viva Wallet Standard Checkout plugin documentation', 'viva-wallet-for-woocommerce' ) . '">' . esc_html__( 'Documentation', 'viva-wallet-for-woocommerce' ) . '</a>',
						'support' => '<a href="' . esc_url( 'mailto: woosupport@vivawallet.com' ) . '" aria-label="' . esc_attr__( 'Get support from Viva Wallet Team', 'viva-wallet-for-woocommerce' ) . '">' . esc_html__( 'Get support', 'viva-wallet-for-woocommerce' ) . '</a>',
					);
					return array_merge( $links, $row_meta );
				}
				return (array) $links;
			}

		}
		PGWC_Vivawallet::get_instance();
	endif;
}
