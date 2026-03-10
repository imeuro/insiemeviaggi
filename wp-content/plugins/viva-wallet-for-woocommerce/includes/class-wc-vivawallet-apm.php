<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}
/**
 * Class WC_Vivawallet_Apm
 *
 * @extends WC_Vivawallet_Payment_Gateway
 *
 * @class   WC_Vivawallet_Apm
 * @package VivaWalletForWooCommerce
 */
class WC_Vivawallet_Apm extends WC_Vivawallet_Payment_Gateway {
	/**
	 * Payment method id
	 *
	 * @var string
	 */
	public $id;

	/**
	 * Icon
	 *
	 * @var string
	 */
	public $icon;

	/**
	 * Client id
	 *
	 * @var string
	 */
	private $client_id;

	/**
	 * Client secret
	 *
	 * @var string
	 */
	private $client_secret;

	/**
	 * Source code
	 *
	 * @var string
	 */
	protected $source_code;

	/**
	 * Test client id
	 *
	 * @var string
	 */
	private $test_client_id;

	/**
	 * Test client secret
	 *
	 * @var string
	 */
	private $test_client_secret;

	/**
	 * Test source code
	 *
	 * @var string
	 */
	protected $test_source_code;

	/**
	 * Viva wallet id
	 *
	 * @var int
	 */
	public $vivawallet_id;
	/**
	 * Main_plugin_enabled
	 *
	 * @var string
	 */
	private $main_plugin_enabled;

	/**
	 * Client id
	 *
	 * @var string
	 */
	public $method_title;

	/**
	 * Constructor.
	 */
	public function __construct() {

		/* translators: link to main page settings 'here' */
		$this->method_description = sprintf( __( 'Please fill in your Viva Wallet credentials and enable the main plugin from <a href="%s">here</a> before you enable this payment method.', 'viva-wallet-for-woocommerce' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=vivawallet_native' ) );

		$this->supports = array(
			'products',
			'refunds',
		);

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		$main_settings     = get_option( 'woocommerce_vivawallet_native_settings' );
		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->enabled     = $this->get_option( 'enabled' );

		$this->test_mode = ( ! empty( $main_settings['test_mode'] ) && 'yes' === $main_settings['test_mode'] ) ? 'yes' : 'no';

		$this->client_id     = ! empty( $main_settings['client_id'] ) ? $main_settings['client_id'] : '';
		$this->client_secret = ! empty( $main_settings['client_secret'] ) ? $main_settings['client_secret'] : '';
		$this->source_code   = ! empty( $main_settings['source_code'] ) ? $main_settings['source_code'] : '';

		$this->test_client_id     = ! empty( $main_settings['test_client_id'] ) ? $main_settings['test_client_id'] : '';
		$this->test_client_secret = ! empty( $main_settings['test_client_secret'] ) ? $main_settings['test_client_secret'] : '';
		$this->test_source_code   = ! empty( $main_settings['test_source_code'] ) ? $main_settings['test_source_code'] : '';

		$this->main_plugin_enabled = ! empty( $main_settings['enabled'] ) ? $main_settings['enabled'] : 'no';

		if ( 'yes' === $this->enabled ) {
			WC_Vivawallet_Helper::get_payment_methods( $this->test_mode );
		}

		set_transient( 'admin_notice_' . $this->id, true, 0 );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'filter_gateways' ), 99, 1 );

		add_action( 'admin_notices', array( $this, 'admin_notices' ), 99, 0 );

		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou' ), 0, 0 );

	}



	/**
	 * Checks APM availability
	 *
	 *  @param array $gateways Gateways.
	 *
	 * @return array|mixed
	 */
	public function filter_gateways( $gateways ) {

		if ( 'no' === $this->enabled ) {
			return $gateways;
		}

		if ( ! is_checkout() ) {
			return $gateways;
		}
		// check if this payment method is available for merchant .

		if ( true === WC_Vivawallet_Helper::check_apm_availability( $this->test_mode, $this->vivawallet_id ) ) {
			return $gateways;
		} else {
			unset( $gateways[ $this->id ] );
			return $gateways;
		}

	}

	/**
	 * Initialize Gateway Settings Form Fields.
	 */
	public function init_form_fields() {
		$this->form_fields = require untrailingslashit( plugin_dir_path( __FILE__ ) ) . '/admin/' . $this->id . '-settings.php';
	}

	/**
	 * Payment form on checkout page
	 */
	public function payment_fields() {

		if ( isset( $this->description ) ) {
			$res  = '<p>';
			$res .= $this->description;
			$res .= '</p>';
			echo wp_kses_post( $res );
		}

	}




	/**
	 * Display any admin notices to the user.
	 */
	public function admin_notices() {

		if ( ! get_transient( 'admin_notice_' . $this->id ) ) {
			return;
		}

		set_transient( 'admin_notice_' . $this->id, false, 0 );

		if ( empty( $_GET['section'] ) ) { //phpcs:ignore
			return;
		}

		if ( isset( $_GET['section'] ) && $this->id !== $_GET['section'] ) { //phpcs:ignore
			return;
		}

		if ( empty( $this->get_option( 'enabled' ) ) || 'yes' !== $this->get_option( 'enabled' ) ) {
			// if this plugin is not enabled.
			return;
		}

		if ( 'no' === $this->main_plugin_enabled ) {
			$error = __( 'Viva Wallet: The plugin of Viva Wallet Standard Checkout must be enabled. Please enable it and add your credentials.', 'viva-wallet-for-woocommerce' );
			echo '<div class="error"><p><b>' . esc_html( $error ) . '</b></p></div>';
			return;
		}

		$token = $this->get_authentication_token( null, 'front' );

		if ( empty( $token ) ) {
			$error = __( 'Viva Wallet: Your credentials are NOT valid. Please check your credentials in the main settings page of Viva Wallet Standard Checkout!', 'viva-wallet-for-woocommerce' );
			echo '<div class="error"><p><b>' . esc_html( $error ) . '</b></p></div>';
			return;
		}

		$method_checked = $this->get_option( 'method_checked' );

		if ( ! empty( $method_checked ) ) {
			if ( 'yes' === $method_checked ) {

				$mes  = __( 'Viva Wallet: You are ready to receive payments using ', 'viva-wallet-for-woocommerce' );
				$mes .= $this->method_title;
				echo '<div class="updated"><p><b>' . esc_html( $mes ) . '</b></p></div>';

			} elseif ( 'hook_error' === $method_checked ) {

				$error  = __( 'Viva Wallet: Your account supports: ', 'viva-wallet-for-woocommerce' );
				$error .= $this->method_title;
				$error .= '. ';
				$error .= __( 'But there was a problem updating hooks for your website. ', 'viva-wallet-for-woocommerce' );
				$error .= __( 'Note that this payment method will work only on a server. Endpoints must be accessible from the web.', 'viva-wallet-for-woocommerce' );

				echo '<div class="error"><p><b>' . esc_html( $error ) . '</b></p></div>';

			} elseif ( 'apm_unavailable' === $method_checked ) {

				$error  = __( 'Viva Wallet: Your account does not support: ', 'viva-wallet-for-woocommerce' );
				$error .= $this->method_title;
				$error .= '. ';
				$error .= __( 'Please contact Viva Wallet. ', 'viva-wallet-for-woocommerce' );
				echo '<div class="error"><p><b>' . esc_html( $error ) . '</b></p></div>';

			}
		}

	}

	/**
	 * When admin settings are being saved.
	 */
	public function process_admin_options() {

		$settings          = get_option( 'woocommerce_' . $this->id . '_settings', array() );
		$old_enabled_value = isset( $settings['enabled'] ) ? $settings['enabled'] : 'no';

		WC_Payment_Gateway::process_admin_options();

		$settings          = get_option( 'woocommerce_' . $this->id . '_settings', array() );
		$new_enabled_value = isset( $settings['enabled'] ) ? $settings['enabled'] : 'no';

		if ( $old_enabled_value !== $new_enabled_value ) {
			if ( 'yes' === $new_enabled_value ) {
				if ( true === WC_Vivawallet_Helper::check_apm_availability( $this->test_mode, $this->vivawallet_id ) ) {
					if ( true === WC_Vivawallet_Helper::update_viva_wallet_webhook_url() ) { // all checks ok.
						$this->update_option( 'method_checked', 'yes' );
					} else { // apm is available, but hooks failed to connect.
						if ( 'yes' === $this->main_plugin_enabled ) {
							$this->update_option( 'method_checked', 'hook_error' );
						}
					}
				} else { // this apm is not available.
					if ( 'yes' === $this->main_plugin_enabled ) {
						$this->update_option( 'method_checked', 'apm_unavailable' );
					}
				}
			}
		}
	}

	/**
	 * Override hook for thank you page. To let the customer know that the order is still pending payment
	 */
	public function thankyou() {
		echo '<p>' . esc_html__( 'Order is currently awaiting payment. After successful payment, we will send you an email confirmation.', 'viva-wallet-for-woocommerce' ) . '</p>';
	}


}

