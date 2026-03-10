<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

// phpcs:disable WordPress.Files.FileName

/**
 * Class WC_Vivawallet_Payment_Gateway
 */
class WC_Vivawallet_Payment_Gateway extends WC_Payment_Gateway {

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
	 * Test mode
	 *
	 * @var string
	 */
	protected $test_mode;

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
	 * Credentials
	 *
	 * @var array
	 */
	protected $credentials;


	/**
	 * Demo source list
	 *
	 * @var object
	 */
	private $demo_source_list;

	/**
	 * Live source list
	 *
	 * @var object
	 */
	private $live_source_list;

	/**
	 * Capture immediately
	 *
	 * @var string
	 */
	private $capture_immediately;

	/**
	 * Order status
	 *
	 * @var string
	 */
	private $order_status;


	/**
	 * Instalments
	 *
	 * @var string
	 */
	private $instalments;



	/**
	 * WC_Vivawallet_Payment_Gateway constructor.
	 */
	public function __construct() {

		$this->id                 = 'vivawallet_native';
		$this->method_title       = __( 'Viva Wallet Standard Checkout', 'viva-wallet-for-woocommerce' );
		$this->method_description = __( 'Sign up for a demo account to test the API. Accept payments from all major credit cards, and offer other payment methods, such as Apple Pay and Google Pay, local card schemes, local wallets, and alternative payment methods.', 'viva-wallet-for-woocommerce' );

		$this->icon       = apply_filters( 'woocommerce_vivawallet_icon', WC_Vivawallet_Helper::VW_CHECKOUT_PAYMENT_LOGOS_URL );
		$this->has_fields = true;

		$this->supports = array(
			'products',
			'refunds',
			// 'default_credit_card_form',
			'tokenization',
			// 'credit_card_form_cvc_on_saved_method'
			'subscriptions',

			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_date_changes',
			'subscription_payment_method_change',
			'subscription_payment_method_change_customer',
			'subscription_payment_method_change_admin',
			'multiple_subscriptions',

		);

		$this->init_settings();

		$this->client_id          = $this->get_option( 'client_id' );
		$this->client_secret      = $this->get_option( 'client_secret' );
		$this->test_client_id     = $this->get_option( 'test_client_id' );
		$this->test_client_secret = $this->get_option( 'test_client_secret' );
		$this->source_code        = $this->get_option( 'source_code' );
		$this->test_source_code   = $this->get_option( 'test_source_code' );

		$this->capture_immediately = $this->get_option( 'capture_immediately' );
		$this->order_status        = $this->get_option( 'order_status' );
		$this->instalments         = $this->get_option( 'instalments' );

		$this->init_form_fields();

		$this->test_mode   = $this->get_option( 'test_mode' );
		$this->title       = $this->get_option( 'title' );
		$this->enabled     = $this->get_option( 'enabled' );
		$this->description = $this->get_option( 'description' );

		set_transient( 'admin_notice_vivawallet_native', true, 0 );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );

		add_filter( 'woocommerce_credit_card_form_fields', array( $this, 'viva_payments_credit_card_fields' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts_and_styles' ) );

		// Setting a custom timeout value for cURL. Using a high value for priority to ensure the function runs after any other added to the same action hook.
		add_action( 'http_api_curl', array( $this, 'sar_custom_curl_timeout' ), 9999, 1 );

		add_action( 'admin_notices', array( $this, 'admin_notices' ), 99, 0 );

		add_action( 'woocommerce_settings_start', array( $this, 'admin_settings_start' ) );

	}


	/**
	 * Sar_custom_curl_timeout
	 *
	 * @param string $handle handle.
	 */
	public function sar_custom_curl_timeout( $handle ) {
		curl_setopt( $handle, CURLOPT_CONNECTTIMEOUT, WC_Vivawallet_Helper::REQUEST_CONNECT_TIMEOUT ); // phpcs:ignore
		// curl_setopt( $handle, CURLOPT_TIMEOUT, WC_Vivawallet_Helper::REQUEST_TIMEOUT  ); // phpcs:ignore .
	}


	/**
	 * Init form fields
	 */
	public function init_form_fields() {
		if ( ! WC_Vivawallet_Helper::is_valid_currency() ) {
			$this->form_fields = include dirname( __FILE__ ) . '/admin/vivawallet-error-page.php';
		} else {
			$this->form_fields = include dirname( __FILE__ ) . '/admin/vivawallet-settings.php';
		}

	}


	/**
	 * Admin_settings_start.
	 */
	public function admin_settings_start() {

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( is_cart() || is_checkout() ) {
			return;
		}

		if ( 'no' === $this->enabled ) {
			return;
		}

		if ( 'woocommerce_page_wc-settings' !== get_current_screen()->id ) {
			return;
		}

		if ( isset( $_GET['section'], $_GET['section_nonce'] ) && ! wp_verify_nonce( sanitize_key( $_GET['section_nonce'] ), 'section_action' ) ) {
			return;
		}

		if ( isset( $_GET['section'] ) && 'vivawallet_native' !== $_GET['section'] ) {
			return;
		}

		$this->admin_check_and_display_sources_in_admin();
	}


	/**
	 * Display any admin notices to the user.
	 */
	public function admin_notices() {

		// fire only once.

		if ( ! get_transient( 'admin_notice_vivawallet_native' ) ) {
			return;
		}
		set_transient( 'admin_notice_vivawallet_native', false, 0 );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( is_cart() || is_checkout() ) {
			return;
		}

		if ( 'no' === $this->enabled ) {
			return;
		}

		if ( 'woocommerce_page_wc-settings' !== get_current_screen()->id ) {
			return;
		}

		if ( empty( $_GET['section'] ) ) {
			return;
		}

		if ( isset( $_GET['section'], $_GET['section_nonce'] ) && ! wp_verify_nonce( sanitize_key( $_GET['section_nonce'] ), 'section_action' ) ) {
			return;
		}

		if ( ! function_exists( 'curl_version' ) ) {
			$error = __( 'Viva Wallet: The required PHP module, CURL, is not installed, or has been disabled. Please enable it, as this module is required for Viva Wallet services to work correctly.', 'viva-wallet-for-woocommerce' );
			echo '<div class="error"><p><b>' . esc_html( $error ) . '</b></p></div>';
			return;
		}

		if ( isset( $_GET['section'] ) && 'vivawallet_native' !== $_GET['section'] ) {
			return;
		}

		$site_url = get_site_url();
		$domain   = wp_parse_url( $site_url, PHP_URL_HOST );

		if ( ! WC_Vivawallet_Helper::is_valid_domain_name( $domain ) ) { // if not in a valid domain.

			$error  = __( 'Viva Wallet Warning: A valid domain name is needed for Viva Wallet services to work correctly. Your domain,', 'viva-wallet-for-woocommerce' );
			$error .= ' "';
			$error .= $domain;
			$error .= '", ';
			$error .= __( 'does not seem valid.', 'viva-wallet-for-woocommerce' );
			$error .= ' ';
			$error .= __( 'To test locally, edit your hosts file and add a domain, for example, "vivawalletdemo.test".', 'viva-wallet-for-woocommerce' );

			echo '<div class="error"><p><b>' . esc_html( $error ) . '</b></p></div>';

		}

		$token = $this->get_authentication_token();
		if ( 'yes' === $this->test_mode ) {
			if ( empty( $token ) ) {
				$error = __( 'Viva Wallet: Your DEMO credentials are NOT valid. Please check your credentials!', 'viva-wallet-for-woocommerce' );
				echo '<div class="error"><p><b>' . esc_html( $error ) . '</b></p></div>';
				return;
			} else {
				$mes = __( 'Viva Wallet: Your DEMO credentials are valid.', 'viva-wallet-for-woocommerce' );
				echo '<div class="updated"><p><b>' . esc_html( $mes ) . '</b></p></div>';
			}
		} else {
			if ( empty( $token ) ) {

				$error = __( 'Viva Wallet: Your LIVE credentials are NOT valid. Please check your credentials!', 'viva-wallet-for-woocommerce' );
				echo '<div class="error"><p><b>' . esc_html( $error ) . '</b></p></div>';
				return;
			} else {
				$mes = __( 'Viva Wallet: Your LIVE credentials are valid.', 'viva-wallet-for-woocommerce' );
				echo '<div class="updated"><p><b>' . esc_html( $mes ) . '</b></p></div>';
			}
		}

		$creds = $this->get_option( 'source_error' );
		if ( ! empty( $creds ) ) {
			if ( 'code_created' === $creds ) {

				if ( 'yes' === $this->test_mode ) {
					$mes  = __( 'Viva Wallet: A new DEMO source code has been created in the Viva Wallet banking app with code: ', 'viva-wallet-for-woocommerce' );
					$mes .= $this->get_option( 'test_source_code' );

				} else {
					$mes  = __( 'Viva Wallet: A new LIVE source code has been created in the Viva Wallet banking app with code: ', 'viva-wallet-for-woocommerce' );
					$mes .= $this->get_option( 'source_code' );
				}

				$mes .= __( ', and name: ', 'viva-wallet-for-woocommerce' ) . 'Viva Wallet For WC - ' . $domain . '.';
				$mes .= __( ', and set as default source code.', 'viva-wallet-for-woocommerce' );

				echo '<div class="updated"><p><b>' . esc_html( $mes ) . '</b></p></div>';

				$this->update_option( 'source_error', '' );
			} elseif ( 'code_exists' === $creds ) {
				if ( 'yes' === $this->test_mode ) {
					$mes  = __( 'Viva Wallet: You changed or updated your DEMO credentials, a DEMO source code for your domain was found with name: ', 'viva-wallet-for-woocommerce' );
					$mes .= $this->get_option( 'test_source_code' );
				} else {
					$mes  = __( 'Viva Wallet: You changed or updated your LIVE credentials, a LIVE source code for your domain was found with name: ', 'viva-wallet-for-woocommerce' );
					$mes .= $this->get_option( 'source_code' );
				}
				$mes .= __( ', and set as default source code.', 'viva-wallet-for-woocommerce' );
				echo '<div class="updated"><p><b>' . esc_html( $mes ) . '</b></p></div>';
				$this->update_option( 'source_error', '' );

			} elseif ( 'code_error' === $creds ) {
				if ( 'yes' === $this->test_mode ) {
					$error = __( 'Viva Wallet: Your DEMO credentials are valid. ', 'viva-wallet-for-woocommerce' );
				} else {
					$error = __( 'Viva Wallet: Your LIVE credentials are valid. ', 'viva-wallet-for-woocommerce' );
				}
				$error .= __( 'But there was an error trying to create a new source. Error: ', 'viva-wallet-for-woocommerce' ) . $creds;
				$error .= ' ';
				$error .= __( 'Please try to save your settings again. Also check the sources selection box in advanced settings to see your available source codes and set one from there if available.', 'viva-wallet-for-woocommerce' );
				echo '<div class="error"><p><b>' . esc_html( $error ) . '</b></p></div>';
				$this->update_option( 'source_error', '' );
				return;
			}
		}

		if ( 'yes' === $this->test_mode ) {
			$source = $this->get_option( 'test_source_code' );
		} else {
			$source = $this->get_option( 'source_code' );
		}

		if ( ! empty( $source ) ) {

			$res = WC_Vivawallet_Helper::check_source( $token, $source, $this->test_mode );

			if ( 'Active' === $res ) {
				if ( 'yes' === $this->test_mode ) {
					$mes = __( 'Viva Wallet: Your DEMO source code:', 'viva-wallet-for-woocommerce' );
				} else {
					$mes = __( 'Viva Wallet: Your LIVE source code:', 'viva-wallet-for-woocommerce' );
				}

				$mes .= ' ';
				$mes .= $source;
				$mes .= ' ';
				$mes .= __( 'is verified and you are ready to accept payments.', 'viva-wallet-for-woocommerce' );
				echo '<div class="updated"><p><b>' . esc_html( $mes ) . '</b></p></div>';
			} elseif ( 'Pending' === $res ) {
				if ( 'yes' === $this->test_mode ) {
					$error  = __( 'Viva Wallet: Your DEMO credentials are valid and connection with Viva Wallet was successful. ', 'viva-wallet-for-woocommerce' );
					$error .= ' ';
					$error .= __( 'We\'re in the process of reviewing your DEMO website "', 'viva-wallet-for-woocommerce' );
				} else {
					$error  = __( 'Viva Wallet: Your LIVE credentials are valid and connection with Viva Wallet was successful. ', 'viva-wallet-for-woocommerce' );
					$error .= ' ';
					$error .= __( 'We\'re in the process of reviewing your LIVE website "', 'viva-wallet-for-woocommerce' );
				}
				$error .= $source;
				$error .= '". ';
				$error .= __( 'For a perfect one-shot-approval (1-2 business days), make sure that you have included the elements described in the following link. ', 'viva-wallet-for-woocommerce' );
				$error .= 'https://help.vivawallet.com/hc/en-us/articles/360002562577-What-happens-during-payment-source-activation';
				echo '<div class="error"><p><b>' . esc_html( $error ) . '</b></p></div>';
			} elseif ( 'InActive' === $res ) {
				if ( 'yes' === $this->test_mode ) {
					$error  = __( 'Viva Wallet: Your DEMO credentials are valid and connection with Viva Wallet was successful. ', 'viva-wallet-for-woocommerce' );
					$error .= ' ';
					$error .= __( 'But your DEMO source code: ', 'viva-wallet-for-woocommerce' );
				} else {
					$error  = __( 'Viva Wallet: Your LIVE credentials are valid and connection with Viva Wallet was successful. ', 'viva-wallet-for-woocommerce' );
					$error .= ' ';
					$error .= __( 'But your LIVE source code: ', 'viva-wallet-for-woocommerce' );
				}
				$error .= ' ';
				$error .= $source;
				$error .= ' ';
				$error .= __( 'has been BLOCKED. Please check your latest email from Viva Wallet Support for more info.', 'viva-wallet-for-woocommerce' );
				echo '<div class="error"><p><b>' . esc_html( $error ) . '</b></p></div>';
			}
		} else {
			// source is empty...
			$error = __( 'Viva Wallet: Your source code is empty. Please save your settings. Viva Wallet plugin will try to create a new source for your website.', 'viva-wallet-for-woocommerce' );
			echo '<div class="error"><p><b>' . esc_html( $error ) . '</b></p></div>';
		}

		$res = WC_Vivawallet_Helper::check_if_instalments();
		if ( ! $res ) { // if we dont allow instalments.
			if ( $this->check_if_instalments_set() ) { // check if instalments is set.
				$this->update_option( 'instalments', '' ); // empty and notify admin.
				$error = 'Viva Wallet: WARNING Instalments option is only available for greek Viva Wallet accounts. Your instalments setting was reset to default.';
				echo '<div class="error"><p><b>' . esc_html( $error ) . '</b></p></div>';
			}
		}

	}

	/**
	 * Get authentication token
	 *
	 * @param string|null $test_mode yes/no.
	 * @param string      $scope     scope of token.
	 * @param bool        $force     force reload credentials.
	 *
	 * @return string
	 */
	protected function get_authentication_token( $test_mode = null, $scope = 'back', $force = false ) {
		$test_mode   = is_null( $test_mode ) ? $this->test_mode : $test_mode;
		$environment = 'yes' === $test_mode ? 'demo' : 'live';

		if ( $force ) {
			$this->credentials = array();
		}

		if ( ! isset( $this->credentials[ $environment ][ $scope ] ) ) {
			$token = WC_Vivawallet_Credentials::get_authentication_token( $test_mode, $scope, $force );
			if ( ! empty( $token ) ) {
				$this->credentials[ $environment ][ $scope ] = $token;
			}
		}

		return isset( $this->credentials[ $environment ][ $scope ] ) ? $this->credentials[ $environment ][ $scope ] : '';
	}


	/**
	 * Loads and displays the sources in admin settings page
	 */
	private function admin_check_and_display_sources_in_admin() {
		$demo_token = $this->get_authentication_token( 'yes' );
		$live_token = $this->get_authentication_token( 'no' );

		$this->demo_source_list = ! empty( $demo_token ) ? WC_Vivawallet_Helper::get_sources( $demo_token, 'yes' ) : array();
		$this->live_source_list = ! empty( $live_token ) ? WC_Vivawallet_Helper::get_sources( $live_token, 'no' ) : array();

		$site_url = get_site_url();

		$domain = wp_parse_url( $site_url, PHP_URL_HOST );

		if ( ! empty( $this->demo_source_list ) && 'error' !== $this->demo_source_list ) {
			foreach ( $this->demo_source_list as $key => $value ) { // in demo mode we show all sources.
				$this->form_fields['test_source_code']['options'][ $value->sourceCode ] = $value->sourceCode . ' - ' . $value->name . ' - ' . $value->domain; // phpcs:ignore
			}
		}

		if ( ! empty( $this->live_source_list ) && 'error' !== $this->live_source_list ) {
			foreach ( $this->live_source_list as $key => $value ) {
				if ( 'Default' !== $value->sourceCode && $value->domain === $domain ) { // phpcs:ignore
					// // in live sources we hide default and all sources not related to domain.
					$this->form_fields['source_code']['options'][ $value->sourceCode ] = $value->sourceCode . ' - ' . $value->name . ' - ' . $value->domain; // phpcs:ignore
				}
			}
		}
	}


	/**
	 * Admin scripts and styles
	 */
	public function admin_scripts_and_styles() {

		if ( 'woocommerce_page_wc-settings' !== get_current_screen()->id ) {
			return;
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && true === SCRIPT_DEBUG ? '' : '.min';

		wp_register_script( 'vivawallet_admin', plugins_url( '/assets/js/admin-vivawallet' . $suffix . '.js', __FILE__ ), array( 'jquery' ), WC_VIVAWALLET_VERSION, true );
		wp_localize_script(
			'vivawallet_admin',
			'vivawallet_admin_params',
			array(
				'allowInstalments' => WC_Vivawallet_Helper::check_if_instalments(),
			)
		);

		wp_enqueue_script( 'vivawallet_admin' );
	}





	/**
	 * Payment form on checkout page
	 */
	public function payment_fields() {
		ob_start();

		if ( is_add_payment_method_page() || isset( $_GET['change_payment_method'] ) ) { // phpcs:ignore

			$currency = esc_attr( get_woocommerce_currency() );

			$amount = WC_Vivawallet_Helper::get_minimum_charge_amount( WC_Vivawallet_Helper::get_currency_symbol( $currency ) ) / 100;
			$amount = wc_price( $amount, array( 'currency' => $currency ) );

			$res = '<p>';
			if ( is_add_payment_method_page() ) {
				$res .= esc_html__( 'In order to add a card to your payment method, your card must be validated through 3D secure.', 'viva-wallet-for-woocommerce' );
			}

			if ( isset( $_GET['change_payment_method'] ) ) { // phpcs:ignore
				$res .= esc_html__( 'In order to change your subscription payment method, your card must be validated through 3D secure.', 'viva-wallet-for-woocommerce' );
			}

			$res .= esc_html__( 'You will be asked to validate a charge of ', 'viva-wallet-for-woocommerce' );
			$res .= $amount . '. ';
			$res .= '<strong>';
			$res .= esc_html__( 'Note that the charge will be automatically immediately refunded.', 'viva-wallet-for-woocommerce' );
			$res .= '<strong>';
			$res .= '</p>';
			echo wp_kses_post( $res );

		}

		$res = '<p>';
		if ( isset( $this->description ) ) {
			$res .= $this->description;
			$res .= '</p>';
			echo wp_kses_post( $res );
			$res = '<p>';
		}
		$res .= '</p>';
		echo wp_kses_post( $res );

		if ( 'yes' === $this->test_mode ) {
			/* translators: warning */
			$test_mode_warning  = '<div><p>' . esc_html__( 'TEST MODE ENABLED. ', 'viva-wallet-for-woocommerce' );
			$test_mode_warning .= '<br />' . esc_html__( 'For testing you can use 5188 3400 0000 0060 for card number, 111 for card code and any future date for expiry date in order to test the payment process.', 'viva-wallet-for-woocommerce' );
			/* translators: For more info check the %1$s documentation %2$s */
			$test_mode_warning .= '<br />' . sprintf( esc_html__( 'For more info check the %1$s documentation %2$s', 'viva-wallet-for-woocommerce' ), '<a target="_blank" href="https://developer.vivawallet.com/getting-started/test-cards/">', '</a>' ) . '</p></div>';

			$test_mode_warning = trim( $test_mode_warning );
			echo wp_kses_post( $test_mode_warning );
		}

		$cc_form           = new WC_Payment_Gateway_CC();
		$cc_form->id       = $this->id;
		$cc_form->supports = $this->supports;
		$this->tokenization_script();
		$cc_form->form();

		ob_end_flush();
	}

	/**
	 * Payment scripts
	 */
	public function payment_scripts() {
		global $wp;

		$suffix = defined( 'SCRIPT_DEBUG' ) && true === SCRIPT_DEBUG ? '' : '.min';

		// we need JavaScript to process a token only on checkout pages.
		if (
			! is_checkout()
			&& ! is_add_payment_method_page()
			&& ! isset( $_GET['pay_for_order'] ) // phpcs:ignore

		) {
			return;
		}

		if ( is_order_received_page() ) {
			// check for thank you page (return if true.. so we dont inject js in there.
			return;
		}

		// if our payment gateway is disabled, we do not have to enqueue scripts.
		if ( 'no' === $this->enabled ) {
			return;
		}

		wp_register_style( 'vivawallet_styles_core', plugins_url( 'assets/css/vivawallet-styles-core' . $suffix . '.css', __FILE__ ), array(), WC_VIVAWALLET_VERSION );
		wp_enqueue_style( 'vivawallet_styles_core' );

		$site_url = get_site_url();
		$domain   = wp_parse_url( $site_url, PHP_URL_HOST );

		// if not valid domain.
		if ( ! WC_Vivawallet_Helper::is_valid_domain_name( $domain ) ) { // if not in a valid domain.

			$error  = __( 'Viva Wallet: A valid domain name is needed for Viva Wallet services to work correctly. Your domain,', 'viva-wallet-for-woocommerce' );
			$error .= ' "';
			$error .= $domain;
			$error .= '", ';
			$error .= __( 'does not seem valid.', 'viva-wallet-for-woocommerce' );
			$error .= ' ';
			$error .= __( 'To test locally, edit your hosts file and add a domain, for example, "vivawalletdemo.test".', 'viva-wallet-for-woocommerce' );

			wc_add_notice( $error, 'error' );
			return;
		}

		$token           = $this->get_authentication_token();
		$has_valid_creds = ! empty( $token );

		// no reason to enqueue scripts if API keys are not set.
		if ( 'no' === $this->test_mode ) {
			if ( empty( $this->client_secret ) || empty( $this->client_id ) ) {
				$this->update_option( 'source_code', '' );
				$this->source_code = '';
				$error             = __( 'Viva Wallet: Client Secret or Client ID not set. Please check documentation and fill in your Viva Wallet gateway settings.', 'viva-wallet-for-woocommerce' );
				wc_add_notice( $error, 'error' );
				return;
			}
			if ( empty( $this->source_code ) ) {
				$error = __( 'Viva Wallet: Source Code is not set. Please check documentation and fill in your Viva Wallet gateway settings.', 'viva-wallet-for-woocommerce' );
				wc_add_notice( $error, 'error' );
				return;
			}

			if ( false === $has_valid_creds ) {
				$error = __( 'Viva Wallet: Your credentials are NOT valid. Please check your credentials!', 'viva-wallet-for-woocommerce' );
				wc_add_notice( $error, 'error' );
				return;
			}

			$res = WC_Vivawallet_Helper::check_source( $token, $this->source_code, $this->test_mode );

			if ( 'Pending' === $res ) {
				$error = __( 'Viva Wallet: Your LIVE credentials are valid and connection with Viva Wallet was successful.', 'viva-wallet-for-woocommerce' );
				/* translators: error */
				$error .= sprintf( __( 'We\'re in the process of reviewing your LIVE website. For a perfect one-shot-approval (1-2 business days), make sure that you have included the elements described in <a href="%s" target="_blank" style="text-decoration: underline; font-weight: bold;">this link</a>.', 'viva-wallet-for-woocommerce' ), 'https://help.vivawallet.com/hc/en-us/articles/360002562577-What-happens-during-payment-source-activation' );
				wc_add_notice( $error, 'error' );
				return;
			} elseif ( 'InActive' === $res ) {
				if ( current_user_can( 'manage_woocommerce' ) ) {
					$error  = __( 'Viva Wallet: Your LIVE credentials are valid and connection with Viva Wallet was successful. But your LIVE source code, has been <span style="font-weight: bold;">BLOCKED</span>.', 'viva-wallet-for-woocommerce' );
					$error .= __( 'Please check your latest email from Viva Wallet Support for more info.', 'viva-wallet-for-woocommerce' );
					wc_add_notice( $error, 'error' );
				} else {
					$error = __( 'Viva Wallet: Something went wrong! Please try again or come back later. If you are the admin of the website, please check Viva Wallet Standard Checkout plugin.', 'viva-wallet-for-woocommerce' );
					wc_add_notice( $error, 'error' );
				}
				return;
			}
		} else {
			if ( empty( $this->test_client_id ) || empty( $this->test_client_secret ) ) {
				$this->update_option( 'test_source_code', '' );
				$this->test_source_code = '';
				$error                  = __( 'Viva Wallet: YOU ARE OPERATING IN TEST MODE. Test Client Secret or Client ID not set. Please check documentation and fill in your Viva Wallet gateway settings.', 'viva-wallet-for-woocommerce' );
				wc_add_notice( $error, 'error' );
				return;
			}
			if ( empty( $this->test_source_code ) ) {
				$error = __( 'Viva Wallet: YOU ARE OPERATING IN TEST MODE. Test Source Code is not set. Please check documentation and fill in your Viva Wallet gateway settings.', 'viva-wallet-for-woocommerce' );
				wc_add_notice( $error, 'error' );
				return;
			}

			if ( false === $has_valid_creds ) {
				$error = __( 'Viva Wallet: Your credentials are NOT valid. Please check your credentials!', 'viva-wallet-for-woocommerce' );
				wc_add_notice( $error, 'error' );
				return;
			}
			$res = WC_Vivawallet_Helper::check_source( $token, $this->test_source_code, $this->test_mode );

			if ( 'Pending' === $res ) {
				$error = __( 'Viva Wallet: Your DEMO credentials are valid and connection with Viva Wallet was successful.', 'viva-wallet-for-woocommerce' );
				/* translators: error */
				$error .= sprintf( __( 'We\'re in the process of reviewing your LIVE website. For a perfect one-shot-approval (1-2 business days), make sure that you have included the elements described in <a href="%s" target="_blank" style="text-decoration: underline; font-weight: bold;">this link</a>.', 'viva-wallet-for-woocommerce' ), 'https://help.vivawallet.com/hc/en-us/articles/360002562577-What-happens-during-payment-source-activation' );
				wc_add_notice( $error, 'error' );
				return;
			} elseif ( 'InActive' === $res ) {
				if ( current_user_can( 'manage_woocommerce' ) ) {
					$error  = __( 'Viva Wallet: Your DEMO credentials are valid and connection with Viva Wallet was successful. But your DEMO Source Code, has been', 'viva-wallet-for-woocommerce' );
					$error .= ' ';
					$error .= '<span style="font-weight: bold;">';
					$error .= __( 'BLOCKED', 'viva-wallet-for-woocommerce' );
					$error .= '</span>';
					$error .= '. ';
					$error .= __( 'Please check your latest email from Viva Wallet Support for more info.', 'viva-wallet-for-woocommerce' );
					wc_add_notice( $error, 'error' );
				} else {
					$error = __( 'Viva Wallet: Something went wrong! Please try again or come back later. If you are the admin of the website, please check Viva Wallet Standard Checkout plugin.', 'viva-wallet-for-woocommerce' );
					wc_add_notice( $error, 'error' );
				}

				return;
			}
		}
		// do not work with card details without SSL unless your website is in a test mode.
		if ( 'no' === $this->test_mode && ! is_ssl() ) {
			$error = __( 'Viva Wallet: This site is not SSL protected. Please protect your domain to use Viva Wallet payments.', 'viva-wallet-for-woocommerce' );
			wc_add_notice( $error, 'error' );
			return;
		}

		$total = WC()->cart->total;

		$inject_cc_logo = false;

		if ( 'no' === $this->get_option( 'advanced_settings_enabled' ) ) {  // check if advanced settings is enabled.
			// if not enabled show inject logos (the default value is yes).
			$inject_cc_logo = true;
		} else {
			// check the prefered value for cc logo.
			if ( 'yes' === $this->get_option( 'cc_logo_enabled' ) ) {
				$inject_cc_logo = true;
			}
		}

		if ( $inject_cc_logo ) {

			wp_register_style( 'vivawallet_styles_cc_logos', plugins_url( 'assets/css/vivawallet-styles-cc-logos' . $suffix . '.css', __FILE__ ), array(), WC_VIVAWALLET_VERSION );
			wp_enqueue_style( 'vivawallet_styles_cc_logos' );
		}

		$show_vw_logo = false;
		if ( 'no' === $this->get_option( 'advanced_settings_enabled' ) ) {
			$show_vw_logo = true;
		} else {
			if ( 'yes' === $this->get_option( 'logo_enabled' ) ) {
				$show_vw_logo = true;
			}
		}

		$customer_name  = false;
		$customer_email = false;
		$order_id       = false;
		$return_url     = false;

		if ( isset( $_GET['key'], $_GET['pay_for_order'] ) && 'true' === $_GET['pay_for_order'] && ! empty( $_GET['key'] ) ) { // phpcs:ignore
			$key      = wc_clean( wp_unslash( $_GET['key'] ) ); // phpcs:ignore
			$order_id = wc_get_order_id_by_order_key( $key );
			$order    = wc_get_order( $order_id );

			$customer_name  = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
			$customer_email = $order->get_billing_email();
			$total          = $order->get_total();
			$return_url     = $this->get_return_url( $order );
		}

		if ( is_add_payment_method_page() || isset( $_GET['change_payment_method'] ) ) { // phpcs:ignore

			$user           = wp_get_current_user();
			$customer_name  = $user->user_firstname . ' ' . $user->user_lastname;
			$customer_email = $user->user_email;
			$total          = WC_Vivawallet_Helper::get_minimum_charge_amount( WC_Vivawallet_Helper::get_currency_symbol( get_woocommerce_currency() ) ) / 100;
		}

		if ( isset( WC()->session->VW_Error ) ) {
			wc_add_notice( WC()->session->VW_Error, 'error' );
			WC()->session->set( 'VW_Error', '' );

			unset( WC()->session->VW_Error );

		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && true === SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_script( 'vivawallet-web-checkout-v02', WC_Vivawallet_Helper::get_api_url_endpoint( $this->test_mode, WC_Vivawallet_Helper::ENDPOINT_NATIVE_JS ), array( 'jquery' ), WC_Vivawallet_Helper::NATIVE_JS_VERSION, true );

		wp_register_script( 'woocommerce_vivawallet', plugins_url( '/assets/js/payment-vivawallet' . $suffix . '.js', __FILE__ ), array( 'jquery-payment', 'jquery' ), WC_VIVAWALLET_VERSION, true );
		wp_localize_script(
			'woocommerce_vivawallet',
			'vivawallet_params',
			array(
				'token'                    => $this->get_authentication_token( null, 'front' ),
				'ajax_url'                 => WC_AJAX::get_endpoint( '%%endpoint%%' ),
				'scriptUrl'                => WC_Vivawallet_Helper::get_api_url( $this->test_mode ),
				'installmentsUrl'          => WC_Vivawallet_Helper::get_api_url_endpoint( $this->test_mode, WC_Vivawallet_Helper::ENDPOINT_INSTALLMENTS ),
				'amount'                   => $total,
				'allowInstallments'        => WC_Vivawallet_Helper::check_if_instalments(),
				'installmentsLogic'        => $this->instalments,
				'showVWLogo'               => $show_vw_logo,
				'logoPath'                 => WC_Vivawallet_Helper::VW_LOGO_URL,
				'labelLogoTxt'             => esc_html__( 'Powered by', 'viva-wallet-for-woocommerce' ),
				'labelForCCerror'          => '<strong>' . __( 'Please check your card details!', 'viva-wallet-for-woocommerce' ) . '</strong>',
				'labelForAPIerror'         => '<strong>' . __( 'Connection to Viva Wallet API failed. Please check your connection or try again later.', 'viva-wallet-for-woocommerce' ) . '</strong>',
				'labelForNameNULLerror'    => '<strong>' . __( 'A billing name is required to process a charge. No billing name field found in checkout form.', 'viva-wallet-for-woocommerce' ) . '</strong>',
				'labelForTermsError'       => '<strong>' . __( 'Please read and accept the terms and conditions to proceed with your order.', 'viva-wallet-for-woocommerce' ) . '</strong>',
				'labelForAJAXerror'        => '<strong>' . __( 'Connection to WooCommerce checkout failed. Please check your connection or try again later.', 'viva-wallet-for-woocommerce' ) . '</strong>',
				'labelForInstallments'     => __( 'Installments', 'viva-wallet-for-woocommerce' ),
				'orderCustomerName'        => $customer_name,
				'orderCustomerEmail'       => $customer_email,
				'orderId'                  => $order_id,
				'returnUrl'                => $return_url,
				'add_payment_method_nonce' => wp_create_nonce( 'wc-vivawallet-add-payment-method' ),
				'checkoutSecurity'         => wp_create_nonce( 'wc-vivawallet-process-payment' ),
				'cartTotalSecurity'        => wp_create_nonce( 'wc-vivawallet-checkout-amount' ),
				'isUserLoggedIn'           => is_user_logged_in(),
			)
		);
		wp_enqueue_script( 'woocommerce_vivawallet', '', array(), WC_VIVAWALLET_VERSION, true );
	}

	/**
	 * Process payment
	 *
	 * @param int $order_id order id.
	 *
	 * @return array
	 */
	public function process_payment( $order_id ) {

		$order = wc_get_order( $order_id );

		$test_mode   = $this->test_mode;
		$source_code = ( 'yes' === $test_mode ) ? $this->test_source_code : $this->source_code;

		if ( isset( $_POST['viva_nonce'] ) && ! wp_verify_nonce( sanitize_key( $_POST['viva_nonce'] ), 'viva_action' ) ) {

			$status = __( 'Order created via Viva Wallet but something went wrong with authentication.', 'viva-wallet-for-woocommerce' );

			$order->add_order_note( $status, false );
			$order->save();

			wc_add_notice( __( 'Something went wrong. Please refresh your page and try again.', 'viva-wallet-for-woocommerce' ), 'error' );
			return array(
				'result'   => 'error',
				'redirect' => false,
			);
		}

		if ( isset( $_POST['nativeCheckoutForm'] ) && 'true' === $_POST['nativeCheckoutForm'] ) {

			return array(
				'result'            => 'success',
				'resultApi'         => 'success',
				'orderId'           => $order_id,
				'returnUrl'         => $this->get_return_url( $order ),
				'isUserLoggedIn'    => is_user_logged_in(),
				'saveCard'          => ( isset( $_REQUEST['wc-vivawallet_native-new-payment-method'] ) ) ? wc_clean( wp_unslash( $_REQUEST['wc-vivawallet_native-new-payment-method'] ) ) : '',
				'checkoutSecurity'  => wp_create_nonce( 'wc-vivawallet-process-payment' ),
				'cartTotalSecurity' => wp_create_nonce( 'wc-vivawallet-checkout-amount' ),
			);
		}

		if ( isset( $_POST['payment_request_type'] ) && 'apple_pay' === $_POST['payment_request_type'] ) {

			// handle apple pay here...

			if ( empty( $_POST['applePayAccessToken'] ) || empty( $_POST['applePayChargeToken'] ) ) {

				$status = __( 'Order created via Viva Wallet Apple Pay checkout but failed getting Apple Pay token.', 'viva-wallet-for-woocommerce' );

				$order->set_payment_method( __( 'Viva Wallet (via Apple Pay)', 'viva-wallet-for-woocommerce' ) );
				$order->add_order_note( $status, false );
				$order->save();

				return array(
					'result'    => 'error',
					'resultApi' => 'error',
					'redirect'  => false,
				);
			}

			$access_token = sanitize_text_field( wp_unslash( $_POST['applePayAccessToken'] ) );
			$charge_token = sanitize_text_field( wp_unslash( $_POST['applePayChargeToken'] ) );

			$data = WC_Vivawallet_Helper::prepare_transaction_data_from_order( $order );

			$post_args = array(
				'amount'          => $data['amount'],
				'preauth'         => false,
				'sourceCode'      => $source_code,
				'chargeToken'     => $charge_token,
				'installments'    => 1,
				'merchantTrns'    => $data['messages']['merchant_message'],
				'customerTrns'    => $data['messages']['customer_message'],
				'currencyCode'    => $data['currency'],
				'allowsRecurring' => false,
				'customer'        => array(
					'email'       => $data['email'],
					'phone'       => $data['phone'],
					'fullname'    => $data['name'],
					'requestLang' => $data['ln'],
				),
			);
			if ( ! empty( $data['countryCode'] ) ) {
				$post_args['customer']['countryCode'] = $data['countryCode'];
			}

			$result = WC_Vivawallet_Helper::transaction_api_call( $post_args, $access_token, $test_mode );

			if ( ! is_wp_error( $result ) && isset( $result['response']['code'] ) && 200 === $result['response']['code'] ) {

				$transaction_id = json_decode( $result['body'] );
				$transaction_id = $transaction_id->transactionId; // phpcs:ignore

				$status  = __( 'Order has been paid with Viva Wallet (via Apple Pay), TxID: ', 'viva-wallet-for-woocommerce' );
				$status .= $transaction_id;

				WC_Vivawallet_Helper::complete_order( $order_id, $transaction_id, $status );

				$status = __( 'Order created via Viva Wallet Apple Pay checkout.', 'viva-wallet-for-woocommerce' );

				$order->add_order_note( $status, false );
				$order->save();

				return array(
					'result'    => 'success',
					'resultApi' => 'success',
					'message'   => $transaction_id,
					'redirect'  => $this->get_return_url( $order ),
				);
			}
			/* translators: bolded word */
			wc_add_notice( sprintf( __( 'Something went %s. Please try again.', 'viva-wallet-for-woocommerce' ), '<strong>' . __( ' wrong', 'viva-wallet-for-woocommerce' ) . '</strong>' ), 'error' );

			$status = __( 'Order created with Viva Wallet (via Apple Pay) but the processing of transaction failed.', 'viva-wallet-for-woocommerce' );

			$order->add_order_note( $status, false );
			$order->save();

			return array(
				'result'   => 'error',
				'messages' => 'Something went wrong. Please try again.',
			);
		}
	}

	/**
	 * Process refund
	 *
	 * @param int    $order_id order_id.
	 * @param null   $amount amount.
	 * @param string $reason reason.
	 *
	 * @return bool
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		if ( ! is_numeric( $amount ) && 'refunded' === $amount ) {
			$order  = wc_get_order( $order_id );
			$amount = $order->get_total();
		}

		$token       = $this->get_authentication_token();
		$source_code = ( 'yes' === $this->test_mode ) ? $this->test_source_code : $this->source_code;

		return WC_Vivawallet_Refund::process_refund( $this->test_mode, $token, $source_code, $order_id, $amount, $reason );

	}

	/**
	 * Method to create sources in Viva Wallet
	 */
	public function process_admin_options() {

		$old_client_id_test = $this->get_option( 'test_client_id' );
		$old_client_id_live = $this->get_option( 'client_id' );

		$old_client_secret_test = $this->get_option( 'test_client_secret' );
		$old_client_secret_live = $this->get_option( 'client_secret' );

		$old_demo_live_setting = $this->get_option( 'test_mode' );

		parent::process_admin_options();

		$this->update_option( 'viva_settings_changed', 'yes' );

		$this->enabled   = $this->get_option( 'enabled' );
		$this->test_mode = $this->get_option( 'test_mode' );

		$this->client_id     = $this->get_option( 'client_id' );
		$this->client_secret = $this->get_option( 'client_secret' );

		$this->test_client_id     = $this->get_option( 'test_client_id' );
		$this->test_client_secret = $this->get_option( 'test_client_secret' );

		/**
		 * Deletes db entries for options of apms when credentials are changed.
		 */
		function deleteApmOptions() {
			delete_option( 'woocommerce_vivawallet-ideal_settings' );
			delete_option( 'woocommerce_vivawallet-p24_settings' );
			delete_option( 'woocommerce_vivawallet-payu_settings' );
			delete_option( 'woocommerce_vivawallet-multibanco_settings' );
			delete_option( 'woocommerce_vivawallet-giropay_settings' );
			delete_option( 'woocommerce_vivawallet-directpay_settings' );
			delete_option( 'woocommerce_vivawallet-directpay_settings' );
			delete_option( 'woocommerce_vivawallet-eps_settings' );
			delete_option( 'woocommerce_vivawallet-wechatpay_settings' );
			delete_option( 'woocommerce_vivawallet-bitpay_settings' );
		}

		if ( 'yes' === $this->test_mode ) {
			if ( $this->test_client_id !== $old_client_id_test || $this->test_client_secret !== $old_client_secret_test ) {
				$this->update_option( 'test_source_code', '' );
				deleteApmOptions();
			}
		} else {
			if ( $this->client_id !== $old_client_id_live || $this->client_secret !== $old_client_secret_live ) {
				$this->update_option( 'source_code', '' );
				deleteApmOptions();
			}
		}

		if ( $this->test_mode !== $old_demo_live_setting ) {
			deleteApmOptions();
		}

		$this->test_source_code = $this->get_option( 'test_source_code' );
		$this->source_code      = $this->get_option( 'source_code' );

		$token = $this->get_authentication_token( null, 'back', true );

		if ( null !== WC()->session ) {
			WC()->session->set( 'VW_AVAILABLE_APM', null );
			WC()->session->set( 'VW_APM_KEY', null );
		}

		if ( ! empty( $token ) ) {

			$source = ( 'yes' === $this->test_mode ) ? $this->test_source_code : $this->source_code;

			if ( empty( $source ) ) { // no source found.. or credentials changed.. create one.

				// first check if source for this domain exists.

				$existing_sources = WC_Vivawallet_Helper::get_sources( $token, $this->test_mode );

				$source_name = '';

				// scan the object for sources and if we have a match to the domain.
				if ( 'error' !== $existing_sources ) {
					$site_url = get_site_url();
					$domain   = wp_parse_url( $site_url, PHP_URL_HOST );
					foreach ( $existing_sources as $id => $item ) {
						if ( $item->domain === $domain ) { // source exists for this domain.
							$source_name = $item->sourceCode; // phpcs:ignore
							$this->update_option( 'source_error', 'code_exists' );
							break;
						}
					}
				}

				// if no sources found for the domain .. create one..
				if ( '' === $source_name ) {
					$source_name = WC_Vivawallet_Helper::get_free_source_code( $existing_sources );

					$res = WC_Vivawallet_Source::create_source( $token, $source_name, $this->test_mode );

					if ( 'yes' === $res ) {
						$this->update_option( 'source_error', 'code_created' );
					} else {
						$source_name = '';
						$this->update_option( 'source_error', 'code_error' );
					}
				}

				// set the correct source to the saved source code value.
				if ( 'yes' === $this->test_mode ) {
					$this->update_option( 'test_source_code', $source_name );
				} else {
					$this->update_option( 'source_code', $source_name );
				}
			}
		}
	}


	/**
	 * Viva payments credit card fields
	 *
	 * @param array $cc_fields cc_fields.
	 *
	 * @return array
	 */
	public function viva_payments_credit_card_fields( $cc_fields ) {

		foreach ( $cc_fields as $key => $value ) {
			// change the name and add data-vp to cc inputs.
			if ( 'card-number-field' === $key ) {
				$value             = str_replace( 'id="vivawallet_native-card-number"', 'id="vivawallet_native-card-number" data-vp="cardnumber" ', $value );
				$cc_fields[ $key ] = $value;
			}
			if ( 'card-expiry-field' === $key ) {
				$value             = str_replace( 'id="vivawallet_native-card-expiry"', 'id="vivawallet_native-card-expiry" data-vp="expdate"', $value );
				$cc_fields[ $key ] = $value;
			}
			if ( 'card-cvc-field' === $key ) {
				$value             = str_replace( 'id="vivawallet_native-card-cvc"', 'id="vivawallet_native-card-cvc" data-vp="cvv"', $value );
				$cc_fields[ $key ] = $value;
			}
		}

		return $cc_fields;
	}


	/**
	 * Check_if_instalments_set
	 *
	 * @return boolean
	 */
	public function check_if_instalments_set() {
		$vw_instalments = $this->get_option( 'instalments' );
		if ( isset( $vw_instalments ) && '' !== $vw_instalments ) {
			return true;
		}
		return false;
	}

	/**
	 * Get advanced settings
	 *
	 * @return string
	 */
	public function get_advanced_settings() {
		return $this->get_option( 'advanced_settings_enabled' );
	}

	/**
	 * Get_order_update_status
	 *
	 * @return string
	 */
	public function get_order_update_status() {
			return $this->get_option( 'order_status' );
	}

}
