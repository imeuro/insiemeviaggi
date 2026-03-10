<?php
/**
 * Plugin Name: Viva.com | Smart Checkout for WooCommerce
 *  Plugin URI: https://wordpress.org/plugins/viva-com-smart-for-woocommerce
 *  Description: Take secure online payments on your WooCommerce store with Viva.com Smart Checkout.
 *  Author: Viva.com
 *  Author URI: https://www.viva.com/
 *  Version: 1.0.2
 *  Requires Plugins: woocommerce
 *  Requires at least: 6.5
 *  Tested up to: 6.7
 *  WC requires at least: 9.2
 *  WC tested up to: 9.5
 *  Text Domain: viva-com-smart-for-woocommerce
 *  License: GPLv2
 *  Domain Path: /languages
 *
 * @package VivaComSmartForWooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}
/**
 * Instantiate Singleton class
 *
 */
if ( ! class_exists( 'WC_Vivacom_Smart' ) ) {
	/**
	 * WC_Vivacom_Smart Class
	 */
	class WC_Vivacom_Smart {

		/**
		 * Instance
		 *
		 * @var WC_Vivacom_Smart The reference the *Singleton* instance of this class
		 */
		private static $instance;

		/**
		 * Get instance
		 *
		 * @return WC_Vivacom_Smart The *Singleton* instance.
		 */
		public static function get_instance(): self {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Construct
		 */
		private function __construct() {
			add_action( 'plugins_loaded', array( $this, 'init' ) );
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
		 * Init
		 */
		public function init() {
			try {
				$this->define_constants();
				$this->validate_setup();
				$this->load_plugin_files();
				$this->setup_options();
				$this->add_hooks();
				$this->add_filters();
				$this->setup_locale();
			} catch ( Exception $e ) {
				$errors = unserialize( $e->getMessage() );
				add_action(
					'admin_notices',
					function () use ( $errors ) {
						$this->print_notices( $errors );
					}
				);
				return;
			}
		}

		/**
		 * Validates the WooCommerce and plugin setup requirements.
		 *
		 * @throws \Exception If WooCommerce is not installed, active, or does not meet the minimum version requirement.
		 */
		private function validate_setup() {
			$errors = array();

			// Woocommerce needs to be active.
			if ( ! class_exists( 'WooCommerce' ) ) {
				$errors[] = sprintf(
					esc_html__(
						'The Viva.com payment gateway requires WooCommerce. Download link %s here.',
						'viva-com-smart-for-woocommerce'
					),
					'<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>'
				);
			}

			// WooCommerce needs to adhere to our minimum supported WC versions.
			if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, WC_VIVA_COM_SMART_MIN_WOO_VERSION, '<' ) ) {
				$errors[] = sprintf(
					esc_html__(
						'The Viva.com payment gateway requires WooCommerce. Download link %s here.',
						'viva-com-smart-for-woocommerce'
					),
					'<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>'
				);
			}
			if ( ! empty( $errors ) ) {
				throw new \Exception( serialize( $errors ) );
			}
		}

		/**
		 * Defines essential constants for the Viva.com Smart payment gateway.
		 *
		 * @return void
		 */
		private function define_constants(): void {
			define( 'WC_VIVA_COM_SMART_VERSION', '1.0.2' );
			define( 'WC_VIVA_COM_SMART_MIN_PHP_VERSION', '7.3.0' );
			define( 'WC_VIVA_COM_SMART_MIN_WOO_VERSION', '3.5.0' );
			define( 'WC_VIVA_COM_SMART_MAIN_FILE', __FILE__ );
			define( 'WC_VIVA_COM_SMART_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
			define( 'WC_VIVA_COM_SMART_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
			define( 'WC_VIVA_PREFIXED_DIRECTORY', '/vendor_prefixed' );
			define( 'WC_VIVA_COM_SMART_DB_SCHEMA', '1.0' );
		}

		/**
		 * Loads the necessary plugin files for Viva.com Smart payment gateway.
		 *
		 * @return void
		 */
		private function load_plugin_files(): void {
			require_once WC_VIVA_COM_SMART_PLUGIN_PATH . '/vendor/autoload.php';

			if ( is_dir( WC_VIVA_COM_SMART_PLUGIN_PATH . WC_VIVA_PREFIXED_DIRECTORY ) ) {
				require_once WC_VIVA_COM_SMART_PLUGIN_PATH . WC_VIVA_PREFIXED_DIRECTORY . '/guzzlehttp/guzzle/src/functions.php';
				require_once WC_VIVA_COM_SMART_PLUGIN_PATH . WC_VIVA_PREFIXED_DIRECTORY . '/symfony/deprecation-contracts/function.php';
				require_once WC_VIVA_COM_SMART_PLUGIN_PATH . WC_VIVA_PREFIXED_DIRECTORY . '/ralouphie/src/getallheaders.php';
			}

			require_once WC_VIVA_COM_SMART_PLUGIN_PATH . '/includes/class-wc-vivacom-smart-logger.php';
			require_once WC_VIVA_COM_SMART_PLUGIN_PATH . '/includes/class-wc-vivacom-smart-helpers.php';
			require_once WC_VIVA_COM_SMART_PLUGIN_PATH . '/includes/class-wc-vivacom-smart.php';
			require_once WC_VIVA_COM_SMART_PLUGIN_PATH . '/includes/class-wc-vivacom-smart-endpoints.php';
			require_once WC_VIVA_COM_SMART_PLUGIN_PATH . '/includes/class-wc-vivacom-smart-subscriptions.php';
		}

		/**
		 * Sets up the plugin options and handles version updates.
		 *
		 * @return void
		 */
		private function setup_options(): void {
			$viva_settings = get_option( 'woocommerce_vivacom_smart_settings', array() );
			if ( empty( $viva_settings['version'] ) ) {
				$this->update_version_in_db( $viva_settings, false );
			} elseif ( WC_VIVA_COM_SMART_VERSION !== $viva_settings['version'] ) {
				$this->update_version_in_db( $viva_settings, true );
			}
		}

		/**
		 * Update version in database
		 *
		 * @param array $viva_settings viva_settings.
		 *
		 * @param bool  $current current.
		 *
		 * @return void
		 */
		public function update_version_in_db( $viva_settings, $current ) {
			global $wp_version;
			$active_plugins = get_option( 'active_plugins' );

			if ( $current ) {
				$message = "Viva.com smart plugin version updated \nNew version: " . WC_VIVA_COM_SMART_VERSION . "\nOld version : " . $viva_settings['version'];
			} else {
				$message = "Viva.com smart plugin version updated \nNew version: " . WC_VIVA_COM_SMART_VERSION;
			}

			$message .= "\nWordpress Version: " . $wp_version;

			// Check if get_plugins() function exists. This is required on the front end of the site.
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			$all_plugins = get_plugins();

			$message .= "\n Other active plugins: ";

			foreach ( $active_plugins as $active_plugin ) {
				$version  = ! empty( $all_plugins[ $active_plugin ]['Version'] ) ? $all_plugins[ $active_plugin ]['Version'] : 'Unknown Version';
				$message .= "\n " . $all_plugins[ $active_plugin ]['Name'] . ' - Version: ' . $version;
			}

			WC_Vivacom_Smart_Logger::log( $message, true );

			$viva_settings['version'] = WC_VIVA_COM_SMART_VERSION;
			update_option( 'woocommerce_vivacom_smart_settings', $viva_settings );
		}

		/**
		 * Adds custom action hooks for the plugin.
		 *
		 * @return void
		 */
		private function add_hooks(): void {
			add_action( 'woocommerce_blocks_loaded', array( $this, 'woocommerce_vivacom_smart_woocommerce_blocks_support' ) );
			add_action( 'before_woocommerce_init', array( $this, 'woocommerce_vivacom_smart_before_woocommerce_init' ) );
		}

		/**
		 * Woocommerce blocks support
		 */
		public function woocommerce_vivacom_smart_woocommerce_blocks_support(): void {
			if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
				require_once __DIR__ . '/includes/class-wc-vivacom-smart-blocks-support.php';
				add_action(
					'woocommerce_blocks_payment_method_type_registration',
					function ( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
						$payment_method_registry->register( new WC_Vivacom_Smart_Blocks_support() );
					}
				);
			}
		}

		/**
		 * Declares compatibility with specific WooCommerce features before WooCommerce initialization.
		 *
		 * @return void
		 */
		public function woocommerce_vivacom_smart_before_woocommerce_init(): void {
			if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			}
		}

		/**
		 * Adds custom filters for the Viva.com Smart payment gateway plugin.
		 *
		 * @return void
		 */
		private function add_filters(): void {
			add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateways' ) );
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
			add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );
		}

		/**
		 * Add gateways
		 *
		 * @param array $gateways add vivacom gateways.
		 *
		 * @return array
		 */
		public function add_gateways( $gateways ) {
			if ( class_exists( 'WC_Subscriptions_Order' ) && function_exists( 'wcs_create_renewal_order' ) ) {
				$gateways[] = 'WC_Vivacom_Smart_Payment_Gateway_Subscriptions';
			} else {
				$gateways[] = 'WC_Vivacom_Smart_Payment_Gateway';
			}

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
				'<a href="admin.php?page=wc-settings&tab=checkout&section=vivacom_smart">' . esc_html__( 'Settings', 'viva-com-smart-for-woocommerce' ) . '</a>',
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
					'docs'    => '<a href="' . esc_url( 'https://docs.woocommerce.com/document/viva-com-smart-for-woocommerce' ) . '" aria-label="' . esc_attr__( 'View Viva.com Smart Checkout plugin documentation', 'viva-com-smart-for-woocommerce' ) . '">' . esc_html__( 'Documentation', 'viva-com-smart-for-woocommerce' ) . '</a>',
					'support' => '<a href="' . esc_url( 'mailto: support@vivawallet.com' ) . '" aria-label="' . esc_attr__( 'Get support from Viva.com Team', 'viva-com-smart-for-woocommerce' ) . '">' . esc_html__( 'Get support', 'viva-com-smart-for-woocommerce' ) . '</a>',
				);
				return array_merge( $links, $row_meta );
			}
			return (array) $links;
		}

		/**
		 * Sets up the plugin's locale for translation.
		 *
		 * @return void
		 */
		public function setup_locale(): void {
			add_filter( 'plugin_locale', array( $this, 'woocommerce_vivacom_smart_fix_locale' ), 99, 2 );
			load_plugin_textdomain( 'viva-com-smart-for-woocommerce', false, plugin_basename( __DIR__ ) . '/languages' );
		}

		/**
		 * Vivacom_fix_locale
		 *
		 * @param string $locale locale.
		 * @param string $domain domain.
		 * @return string
		 */
		public function woocommerce_vivacom_smart_fix_locale( $locale, $domain ) {
			if ( 'viva-com-smart-for-woocommerce' === $domain ) {
				$locale = substr( $locale, 0, 2 );
			}
			return $locale;
		}

		/**
		 * Prints error notices for the plugin.
		 *
		 * @param array $notices An array of notice messages to be displayed.
		 *
		 * @return void
		 */
		public function print_notices( array $notices ) {
			foreach ( $notices as $notice ) {
				echo '<div class="notice notice-error"><p><strong>' . esc_html( $notice ) . '</strong></p></div>';
			}
		}

		/**
		 * Creates the Viva orders table in the WordPress database.
		 *
		 * @return void
		 */
		private function create_orders_table(): void {
			$charset_collate = $this->wpdb()->get_charset_collate();
			$db_prefix       = $this->wpdb()->prefix;

			$sql = "CREATE TABLE IF NOT EXISTS {$db_prefix}viva_com_smart_wc_checkout_orders
                    ( 
                        id int(11) NOT NULL AUTO_INCREMENT,
                        woocommerce_order_id varchar(255) NOT NULL,
                        vivacom_order_code varchar(100) NOT NULL,
                        client_id varchar(100) NOT NULL,
                        currency varchar(3) NOT NULL,
                        amount DECIMAL(10,2) NOT NULL,
                        is_demo boolean NOT NULL DEFAULT false,
                        date_add datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        gmt_date_add datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY  (id),
                        INDEX(woocommerce_order_id),
                        INDEX(vivacom_order_code)
                    ) $charset_collate;";
			dbDelta( $sql );
		}

		/**
		 * Creates the Viva transaction types table in the WordPress database.
		 *
		 * @return void
		 */
		private function create_transaction_types_table(): void {
			$charset_collate = $this->wpdb()->get_charset_collate();
			$db_prefix       = $this->wpdb()->prefix;

			$sql = "CREATE TABLE IF NOT EXISTS {$db_prefix}viva_com_smart_wc_checkout_transaction_types
                    (
                        id int(11) NOT NULL AUTO_INCREMENT,
                        name varchar(100) NOT NULL,
                        PRIMARY KEY  (id),
                        UNIQUE(name)
                    ) $charset_collate;";
			dbDelta( $sql );
		}

		/**
		 * Creates the Viva transactions table in the WordPress database.
		 *
		 * @return void
		 */
		private function create_transactions_table(): void {
			$charset_collate = $this->wpdb()->get_charset_collate();
			$db_prefix       = $this->wpdb()->prefix;

			$sql = "CREATE TABLE IF NOT EXISTS {$db_prefix}viva_com_smart_wc_checkout_transactions
                    (
                        id int(11) NOT NULL AUTO_INCREMENT,
                        order_id int(11) NOT NULL,
                        transaction_id varchar(100) NOT NULL,
                        transaction_type_id int(11) NOT NULL,
                        amount DECIMAL(10,2) NOT NULL,
                        date_add datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        gmt_date_add datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY  (id),
                        INDEX(order_id),
                        UNIQUE order_id_transaction_id (order_id, transaction_id),
                        FOREIGN KEY(order_id) REFERENCES {$db_prefix}viva_com_smart_wc_checkout_orders (id),
                        FOREIGN KEY(transaction_type_id) REFERENCES {$db_prefix}viva_com_smart_wc_checkout_transaction_types (id)
                    ) $charset_collate;";
			dbDelta( $sql );
		}

		/**
		 * Creates the Viva recurring table in the WordPress database.
		 *
		 * @return void
		 */
		private function create_recurring_table(): void {
			$charset_collate = $this->wpdb()->get_charset_collate();
			$db_prefix       = $this->wpdb()->prefix;

			$sql = "CREATE TABLE IF NOT EXISTS {$db_prefix}viva_com_smart_wc_checkout_recurring
                    (
                        id int(11) NOT NULL AUTO_INCREMENT,
                        transaction_id int(11) NOT NULL,
                        recurring_id varchar(100) NOT NULL,
                        token_id varchar(100) NOT NULL,
                        date_add datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        gmt_date_add datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY(id),
                        INDEX(transaction_id),
                        INDEX(recurring_id),
                        INDEX(token_id),
                        FOREIGN KEY(transaction_id) REFERENCES {$db_prefix}viva_com_smart_wc_checkout_transactions (id)
                    ) $charset_collate;";
			dbDelta( $sql );
		}

		/**
		 * Inserts missing transaction types into the database.
		 *
		 * @return void
		 */
		private function insert_transaction_types(): void {
			$db_prefix = $this->wpdb()->prefix;

			$transaction_types = $this->wpdb()->get_results(
				"SELECT name FROM {$db_prefix}viva_com_smart_wc_checkout_transaction_types",
				ARRAY_A
			);
			$missing_types     = array_diff( array( 'payment', 'refund', 'preauthorization', 'capture', 'void' ), array_column( $transaction_types, 'name' ) );
			$insert_types      = array_map(
				function ( $value ) {
					return array( 'name' => $value );
				},
				$missing_types
			);
			foreach ( $insert_types as $insert_type ) {
				$result = $this->wpdb()->insert(
					"{$db_prefix}viva_com_smart_wc_checkout_transaction_types",
					$insert_type
				);
				if ( false === $result ) {
					WC_Vivacom_Smart_Logger::log( 'Failed to insert transaction types to db.' );
				}
			}
		}

		/**
		 * Retrieves the global WordPress database object.
		 *
		 * @return wpdb The global WordPress database object.
		 */
		private function wpdb() {
			global $wpdb;
			return $wpdb;
		}

		/**
		 * Activates the plugin and sets up the necessary database tables.
		 *
		 * @return void
		 */
		public function activate(): void {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			$this->create_orders_table();
			$this->create_transaction_types_table();
			$this->create_transactions_table();
			$this->create_recurring_table();
			$this->insert_transaction_types();
		}

		/**
		 * Deactivates the plugin.
		 *
		 * @return void
		 */
		public function deactivate(): void {
		}

		/**
		 * Unistall the plugin.
		 *
		 * @return void
		 */
		public static function uninstall(): void {
			// global $wpdb;
			// Delete all options
			// delete_option('woocommerce_vivacom_smart_settings');
			// delete_transient('viva_com_smart_wc_admin_notice');
			//
			// Delete all tables
			// $db_prefix = $wpdb->prefix;
			// $wpdb->query("DROP TABLE IF EXISTS {$db_prefix}viva_com_smart_wc_checkout_transaction_types");
			// $wpdb->query("DROP TABLE IF EXISTS {$db_prefix}viva_com_smart_wc_checkout_recurring");
			// $wpdb->query("DROP TABLE IF EXISTS {$db_prefix}viva_com_smart_wc_checkout_transactions");
			// $wpdb->query("DROP TABLE IF EXISTS {$db_prefix}viva_com_smart_wc_checkout_orders");
		}
	}
}

$vivacom = WC_Vivacom_Smart::get_instance();

register_activation_hook( __FILE__, array( $vivacom, 'activate' ) );

register_deactivation_hook( __FILE__, array( $vivacom, 'deactivate' ) );

// register_uninstall_hook(__FILE__, [ WC_Vivacom_Smart::class, 'uninstall' ]);
