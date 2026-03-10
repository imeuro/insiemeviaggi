<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}
/**
 * Class WC_Vivawallet_Apple_Pay_Registration
 *
 * @class   WC_Vivawallet_Apple_Pay_Register
 */
class WC_Vivawallet_Apple_Pay_Register {

	/**
	 * $viva_settings.
	 *
	 * @var array
	 */
	public $viva_settings;

	/**
	 * Main VivaWallet Enabled.
	 *
	 * @var bool
	 */
	public $viva_enabled;

	/**
	 * Do we accept Apple Pay?
	 *
	 * @var bool
	 */
	public $apple_pay;

	/**
	 * Apple Pay Domain Set.
	 *
	 * @var bool
	 */
	public $apple_pay_domain_registered;


	/**
	 * Testmode.
	 *
	 * @var bool
	 */
	public $test_mode;

	/**
	 * Error messages.
	 *
	 * @var array
	 */
	public $error_messages;

	/**
	 * Authentication token.
	 *
	 * @var string
	 */
	private $token;

	/**
	 * Notices.
	 *
	 * @var array
	 */
	private $notices;

	/**
	 * Construct.
	 */
	public function __construct() {

		$this->init_apple_pay();

		add_action( 'admin_notices', array( $this, 'admin_notices' ), 100, 0 );
	}

	/**
	 * Initializes Apple Pay process on settings page.
	 */
	public function init_apple_pay() {
		if (
			is_admin() &&
			isset( $_GET['page'] ) && 'wc-settings' === $_GET['page'] && // phpcs:ignore
			isset( $_GET['tab'] ) && 'checkout' === $_GET['tab'] && // phpcs:ignore
			isset( $_GET['section'] ) && 'vivawallet_native' === $_GET['section'] // phpcs:ignore
		) {

			$this->viva_settings = get_option( 'woocommerce_vivawallet_native_settings', array() );
			$this->viva_enabled  = $this->get_option( 'enabled' );

			if ( empty( $this->viva_settings ) ) {
				return;
			}

			if ( 'yes' !== $this->viva_enabled ) {
				return;
			}

			$this->test_mode = $this->get_option( 'test_mode', 'no' );

			if ( 'yes' === $this->test_mode ) {
				$this->apple_pay                   = 'yes' === $this->get_option( 'test_apple_pay', 'yes' );
				$this->apple_pay_domain_registered = 'yes' === $this->get_option( 'test_apple_pay_domain_registered', 'no' );
			} else {
				$this->apple_pay                   = 'yes' === $this->get_option( 'apple_pay', 'yes' );
				$this->apple_pay_domain_registered = 'yes' === $this->get_option( 'apple_pay_domain_registered', 'no' );
			}

			set_transient( 'admin_notice_vivawallet_applepay', true, 0 );

			$this->error_messages = array();
			$this->notices        = array();

			if ( ! is_ssl() ) {
				$this->error_messages[] = __( 'Viva Wallet - Apple Pay: This site is not SSL protected. Please protect your domain to register with Apple Pay.', 'viva-wallet-for-woocommerce' );
				return;
			}

			if ( ! function_exists( 'curl_version' ) ) {
				// no need to return an error here.. as the main plugin will throw a notice to admin.
				return;
			}

			$this->token = WC_Vivawallet_Credentials::get_authentication_token( $this->test_mode, 'back', true );

			if ( $this->apple_pay ) { // check that apple pay is enabled.
				if ( $this->apple_pay_domain_registered ) { // check if registration is due.
					$this->check_apple_pay(); // check if domain is ok.
				} else {
					$this->apple_pay_verify(); // registers the domain for the first time.
				}
			}
		}
	}

	/**
	 * Update the Apple Pay domain verification file.
	 *
	 * @return bool
	 */
	public function fix_verification_file() {
		$file_name = 'apple-developer-merchantid-domain-association';
		$url       = WC_Vivawallet_Helper::get_api_url_endpoint( $this->test_mode, WC_Vivawallet_Helper::ENDPOINT_APPLE_PAY_DOMAIN_ASSOCIATION_FILE );

		if ( empty( $this->token ) ) {
			return false;
		}

		$header_args = array(
			'Accept'        => 'application/json',
			'Authorization' => 'Bearer ' . $this->token,
		);

		$params = array(
			'method'  => 'GET',
			'headers' => $header_args,
			'timeout' => WC_Vivawallet_Helper::REQUEST_TIMEOUT,
		);

		$result = WC_Vivawallet_Helper::remote_request(
			$url,
			$params
		);

		if ( ! is_wp_error( $result ) && isset( $result['response']['code'] ) && 200 === $result['response']['code'] ) {
			$new_contents = json_decode( $result['body'] );
			if ( isset( $new_contents->data ) ) {
				$new_contents = $new_contents->data;
			} else {
				WC_Vivawallet_Logger::log( "Viva Wallet - Apple Pay: Unable to load domain association file from Viva Wallet API. \n API result: \n" . wp_json_encode( $result ) );
				$this->error_messages[] = __( 'Viva Wallet - Apple Pay: Unable to load domain association file from Viva Wallet API. ', 'viva-wallet-for-woocommerce' );
				return false;
			}
		} else {
			WC_Vivawallet_Logger::log( "Viva Wallet - Apple Pay: Unable to load domain association file from Viva Wallet API. \n API result: \n" . wp_json_encode( $result ) );
			$this->error_messages[] = __( 'Viva Wallet - Apple Pay: Unable to load domain association file from Viva Wallet API. ', 'viva-wallet-for-woocommerce' );
			return false;
		}

		if ( ! isset( $_SERVER['DOCUMENT_ROOT'] ) ) {
			$this->error_messages[] = __( 'Viva Wallet - Apple Pay: Unable to load variable $_SERVER[\'DOCUMENT_ROOT\']. Please check the documentation for further info.', 'viva-wallet-for-woocommerce' );
			return false;
		}

		$path = esc_url_raw( wp_unslash( $_SERVER['DOCUMENT_ROOT'] ) );

		$dir       = '.well-known';
		$full_path = $path . '/' . $dir . '/' . $file_name;

		$existing_contents = @file_get_contents( $full_path ); // phpcs:ignore

		if ( $existing_contents === $new_contents ) {
			$this->notices[] = __( 'Viva Wallet - Apple Pay: Domain association already exists. ', 'viva-wallet-for-woocommerce' );
			return true;
		}

		if ( ! file_exists( $path . '/' . $dir ) ) {
			if ( ! @mkdir( $path . '/' . $dir, 0755 ) ) { // phpcs:ignore
				$this->error_messages[] = __( 'Viva Wallet - Apple Pay: Unable to create .well-known folder for the domain association file in domain root. Please check the documentation for further info.', 'viva-wallet-for-woocommerce' );
				return false;
			}
		}

		if ( ! @file_put_contents( $full_path, $new_contents ) ) { // phpcs:ignore
			$this->error_messages[] = __( 'Viva Wallet - Apple Pay: Unable to copy domain association file to domain root (in .well-known folder). ', 'viva-wallet-for-woocommerce' );
			return false;
		}

		$this->notices[] = __( 'Viva Wallet - Apple Pay: Domain association file updated. ', 'viva-wallet-for-woocommerce' );
		return true;
	}

	/**
	 * Call Viva Wallet API to register (POST) or check the status (GET) of the source in relation to Apple Pay domain registration
	 *
	 * @param string $method method.
	 *
	 * @return bool
	 */
	private function apple_pay_api_call( $method ) {
		$url = WC_Vivawallet_Helper::get_api_url_endpoint( $this->test_mode, WC_Vivawallet_Helper::ENDPOINT_APPLE_PAY_REGISTRATION );

		$source_code = ( 'yes' === $this->test_mode ) ? $this->get_option( 'test_source_code' ) : $this->get_option( 'source_code' );

		$url = str_replace( '{%sourceCode%}', $source_code, $url );

		if ( empty( $this->token ) ) {
			return false;
		}

		$params = array(
			'headers' => array(
				'Accept'        => 'application/json',
				'Authorization' => 'Bearer ' . $this->token,
			),
			'method'  => $method,
			'timeout' => WC_Vivawallet_Helper::REQUEST_TIMEOUT,
		);

		$result = WC_Vivawallet_Helper::remote_request(
			$url,
			$params
		);

		if ( is_wp_error( $result ) ) {
			$this->error_messages[] = __( 'Viva Wallet: There was a problem connecting to Viva Wallet API endpoints. Please also check logs for the errors', 'viva-wallet-for-woocommerce' );
			return false;
		}

		if ( ! isset( $result['response']['code'] ) ) {
			if ( 'POST' === $method ) {
				WC_Vivawallet_Logger::log( "Apple Pay: Register this domain to accept Apple Pay payments. \n API result: \n" . wp_json_encode( $result ) );
			} else {
				WC_Vivawallet_Logger::log( "Apple Pay: Check if this domain has been registered with Apple Pay. \n API result: \n" . wp_json_encode( $result ) );
			}
			$this->error_messages[] = __( 'Viva Wallet: There was a problem connecting to Viva Wallet API endpoints. Please also check logs for the errors', 'viva-wallet-for-woocommerce' );
			return false;
		}

		$res = substr( $result['response']['code'], 0, 1 );

		if ( '2' !== $res ) {
			if ( 'POST' === $method ) {
				WC_Vivawallet_Logger::log( "Apple Pay: Register this domain to accept Apple Pay payments. \n API result: \n" . wp_json_encode( $result ) );
			} else {
				WC_Vivawallet_Logger::log( "Apple Pay: Check if this domain has been registered with Apple Pay. \n API result: \n" . wp_json_encode( $result ) );
			}
			$this->error_messages[] = __( 'Viva Wallet: Something seems wrong in the setup of Apple Pay, please reload this page to try again!', 'viva-wallet-for-woocommerce' );
			return false;
		}

		return true;
	}
	/**
	 * Check the status of Apple Pay
	 */
	public function check_apple_pay() {
		$res = $this->apple_pay_api_call( 'GET' );
		if ( true !== $res ) {
			$this->update_apple_pay_domain_settings( 'no' );
		} else {
			$this->update_apple_pay_domain_settings( 'yes' );
			if ( 'yes' === $this->test_mode ) {
				$this->notices[] = __( 'Viva Wallet: Your domain is verified with Apple Pay and you are ready to accept DEMO payments! Please note that you need an Apple Sandbox account to process DEMO transactions.', 'viva-wallet-for-woocommerce' );
			} else {
				$this->notices[] = __( 'Viva Wallet: Your domain is verified with Apple Pay and you are ready to accept LIVE payments!', 'viva-wallet-for-woocommerce' );
			}
		}
	}

	/**
	 * Processes the Apple Pay domain verification.
	 */
	public function apple_pay_verify() {
		if ( ! $this->fix_verification_file() ) {
			$this->update_apple_pay_domain_settings( 'no' );
			return;
		}
		try {
			$this->register_domain();
		} catch ( Exception $e ) {
			$this->update_apple_pay_domain_settings( 'no' );
			if ( 'yes' === $this->test_mode ) {
				$this->error_messages[] = __( 'Viva Wallet: Something seems wrong in the DEMO setup of Apple Pay!', 'viva-wallet-for-woocommerce' );
			} else {
				$this->error_messages[] = __( 'Viva Wallet: Something seems wrong in the setup of Apple Pay!', 'viva-wallet-for-woocommerce' );
			}
		}

	}

	/**
	 * Do a POST to API endpoints to register this domain (and merchant).
	 */
	public function register_domain() {

		$res = $this->apple_pay_api_call( 'POST' );

		if ( true !== $res ) {
			$this->update_apple_pay_domain_settings( 'no' );
		} else {
			$this->update_apple_pay_domain_settings( 'yes' );
			if ( 'yes' === $this->test_mode ) {
				$this->notices[] = __( 'Viva Wallet: Your domain is verified with Apple Pay and you are ready to accept DEMO payments! Please note that you need an Apple Sandbox account to process DEMO transactions.', 'viva-wallet-for-woocommerce' );
			} else {
				$this->notices[] = __( 'Viva Wallet: Your domain is verified with Apple Pay and you are ready to accept LIVE payments!', 'viva-wallet-for-woocommerce' );
			}
		}

	}

	/**
	 * Updates the apple_pay_domain_registered option in db
	 *
	 * @param string $result setting to set.
	 */
	private function update_apple_pay_domain_settings( $result = 'no' ) {
		if ( 'yes' === $this->test_mode ) {
			$this->viva_settings['test_apple_pay_domain_registered'] = $result;
			update_option( 'woocommerce_vivawallet_native_settings', $this->viva_settings );
		} else {
			$this->viva_settings['apple_pay_domain_registered'] = $result;
			update_option( 'woocommerce_vivawallet_native_settings', $this->viva_settings );
		}
	}

	/**
	 * Display notices or error messages to the admin.
	 */
	public function admin_notices() {

		if ( ! get_transient( 'admin_notice_vivawallet_applepay' ) ) {
			return;
		}
		set_transient( 'admin_notice_vivawallet_applepay', false, 0 );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$this->viva_settings = get_option( 'woocommerce_vivawallet_native_settings', array() );
		$this->viva_enabled  = $this->get_option( 'enabled', 'no' );

		if ( isset( $this->viva_settings['viva_settings_changed'] ) && 'yes' === $this->viva_settings['viva_settings_changed'] ) {
			$this->viva_settings['viva_settings_changed'] = 'no';
			update_option( 'woocommerce_vivawallet_native_settings', $this->viva_settings );
			$this->init_apple_pay();
		}
		if ( 'yes' !== $this->viva_enabled ) {
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

		if ( isset( $_GET['section'] ) && 'vivawallet_native' !== $_GET['section'] ) {
			return;
		}

		if ( 'yes' === $this->test_mode ) {
			if ( empty( $this->viva_settings['test_apple_pay'] ) ) {
				return;
			}

			if ( isset( $this->viva_settings['test_apple_pay'] ) && 'yes' !== $this->viva_settings['test_apple_pay'] ) {
				return;
			}
		} else {
			if ( empty( $this->viva_settings['apple_pay'] ) ) {
				return;
			}

			if ( isset( $this->viva_settings['apple_pay'] ) && 'yes' !== $this->viva_settings['apple_pay'] ) {
				return;
			}
		}

		$allowed_html = array(
			'a'  => array(
				'href'  => array(),
				'title' => array(),
			),
			'br' => array(),
			'b'  => array(),
		);

		if ( isset( $this->notices ) && 0 < count( $this->notices ) ) {
			foreach ( $this->notices as $value ) {
				echo '<div class="updated"><p><b>' . wp_kses( $value, $allowed_html ) . '</b></p></div>';
			}
		}
		if ( isset( $this->error_messages ) && 0 < count( $this->error_messages ) ) {
			foreach ( $this->error_messages as $value ) {
				echo '<div class="error"><p><b>' . wp_kses( $value, $allowed_html ) . '</b></p></div>';
			}
		}
	}


	/**
	 * Gets the Viva Wallet settings.
	 *
	 * @param string $setting Setting.
	 * @param string $default_value Default value.
	 *
	 * @return mixed|string
	 */
	public function get_option( $setting = '', $default_value = '' ) {
		if ( empty( $this->viva_settings ) ) {
			return $default_value;
		}

		if ( ! empty( $this->viva_settings[ $setting ] ) ) {
			return $this->viva_settings[ $setting ];
		}

		return $default_value;
	}


}

new WC_Vivawallet_Apple_Pay_Register();
