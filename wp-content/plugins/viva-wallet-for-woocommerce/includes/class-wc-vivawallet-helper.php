<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

if ( ! class_exists( 'WC_Vivawallet_Helper' ) ) {
	/**
	 * Class WC_Vivawallet_Helper
	 *
	 * @class   WC_Vivawallet_Helper
	 * @package WooCommerce/WC_Vivawallet_Helper
	 */
	class WC_Vivawallet_Helper {

		/**
		 * Instance
		 *
		 * @var object|mixed
		 */
		private static $instance = null;


		/**
		 * Gets unique instance
		 *
		 * @return object|mixed
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * $viva_settings.
		 *
		 * @var array
		 */
		public $viva_settings;

		const BASE_URL_LIVE = 'https://www.vivapayments.com';
		const BASE_URL_TEST = 'https://demo.vivapayments.com';

		const BASE_URL_API_LIVE = 'https://api.vivapayments.com';
		const BASE_URL_API_TEST = 'https://demo-api.vivapayments.com';

		const URL_GET_TOKEN_LIVE = 'https://accounts.vivapayments.com/connect/token';
		const URL_GET_TOKEN_TEST = 'https://demo-accounts.vivapayments.com/connect/token';

		const FRONT_SCOPE = 'urn:viva:payments:core:api:nativecheckoutv2';

		const BACK_END_SCOPE = 'urn:viva:payments:core:api:acquiring urn:viva:payments:core:api:acquiring:transactions urn:viva:payments:core:api:plugins urn:viva:payments:core:api:nativecheckoutv2 urn:viva:payments:core:api:plugins:woocommerce';

		const ENDPOINT_NATIVE_JS = '/web/checkout/v2/js';

		const NATIVE_JS_VERSION = '2.0';

		const SOURCE_IDENTIFIER = 'WC-';

		const ENDPOINT_INSTALLMENTS = '/nativecheckout/v2/installments';

		const ENDPOINT_GET_SOURCES = '/plugins/v1/sources';

		const ENDPOINT_TRANSACTIONS = '/nativecheckout/v2/transactions';

		const ENDPOINT_RECURRING_TRANSACTIONS = '/acquiring/v1/transactions/';

		const ENDPOINT_APPLE_PAY_REGISTRATION = '/plugins/v1/sources/{%sourceCode%}/paymentMethods/20';

		const ENDPOINT_APPLE_PAY_DOMAIN_ASSOCIATION_FILE = '/plugins/v1/paymentMethods/20/domainAssociationFile';

		const ENDPOINT_APPLE_PAY_TOKEN = '/nativecheckout/v2/chargetokens:digitize';

		const ENDPOINT_GET_PAYMENT_METHODS = '/nativecheckout/v2/paymentmethods';

		const ENDPOINT_POST_PAYMENT_METHODS_WEBHOOK = '/plugins/v1/webhooks';

		const ENDPOINT_GET_PAYMENT_METHODS_WEBHOOK_TOKEN = '/plugins/v1/webhooks/token';

		const WEBHOOK_NAMESPACE = 'wc_vivawallet_native/v1';

		const WEBHOOK_URI = '/payments_methods_endpoint';


		const POST_META_VW_ORDER_REF = '_vivawallet_order_reference';

		const POST_META_VW_TXN = '_vivawallet_order_transaction_id';

		const POST_META_VW_APM_TXN = '_vivawallet_order_apm_transaction_id';

		const POST_META_VW_CARD_TOKEN = '_vivawallet_card_token';

		const POST_META_VW_CARD_TOKEN_ID = '_vivawallet_card_token_id';

		const POST_META_VW_ORDER_STATUS = '_vivawallet_order_payment_status';

		const POST_META_VW_REFUND_DATA = '_vivawallet_order_refund_data';

		const POST_META_WC_ORDER_PAID = '_paid_date';

		const ORDER_STATUS_PENDING = 'pending';

		const ORDER_STATUS_REFUNDED = 'refunded';

		const ORDER_STATUS_ON_HOLD = 'on-hold';


		const ORDER_STATUS_PROCESSING = 'processing';

		const ORDER_STATUS_CANCELLED = 'cancelled';

		const ORDER_STATUS_COMPLETE = 'completed';


		const VW_CHECKOUT_PAYMENT_LOGOS_URL   = 'https://images.prismic.io/vivawallet/331cc7de-f5a3-4120-861b-bb65ec068195_vw.svg';
		const VW_CHECKOUT_IDEAL_LOGO_URL      = 'https://images.prismic.io/vivawallet/78278205-e7c9-4a2d-a23a-8da03d0be72b_ideal.png';
		const VW_CHECKOUT_P24_LOGO_URL        = 'https://images.prismic.io/vivawallet/41265bcf-5763-49d5-98fd-a02e689d7806_p24.png';
		const VW_CHECKOUT_BLIK_LOGO_URL       = 'https://images.prismic.io/vivawallet/e66dd521-b503-48a7-8fdf-539132743490_blik.png';
		const VW_CHECKOUT_PAYU_LOGO_URL       = 'https://images.prismic.io/vivawallet/50ee2d65-953e-4eb8-8244-15292a9070c1_payu.png';
		const VW_CHECKOUT_MULTIBANCO_LOGO_URL = 'https://images.prismic.io/vivawallet/11d8dba2-495a-4b14-9ce3-d05782d4d2d1_multibanco.png';
		const VW_CHECKOUT_GIROPAY_LOGO_URL    = 'https://images.prismic.io/vivawallet/c4a0763c-e36f-4f9f-9010-5e0f59f615a7_giropay.png';
		const VW_CHECKOUT_DIRECTPAY_LOGO_URL  = 'https://images.prismic.io/vivawallet/57b770c3-bceb-4c9c-afca-0f02159ac818_directpay.png';
		const VW_CHECKOUT_EPS_LOGO_URL        = 'https://images.prismic.io/vivawallet/9e2eee3c-868e-4aab-8ffd-f4e0d5d908fe_eps.png';
		const VW_CHECKOUT_WECHATPAY_LOGO_URL  = 'https://images.prismic.io/vivawallet/a0df106a-e035-4323-a88c-6f996f4b32c7_wechatpay.png';
		const VW_CHECKOUT_BITPAY_LOGO_URL     = 'https://images.prismic.io/vivawallet/02214258-7f25-4591-9061-d33a3039e55f_bitpay.png';


		const VW_CC_LOGOS_URL = 'https://images.prismic.io/vivawallet/464ed2b5-00ed-4eac-957d-2ff943150c8f_logos.png';
		const VW_LOGO_URL     = 'https://images.prismic.io/vivawallet/1a64b4db-f5ad-4a4a-848a-a06954ebbe99_vivawallet-logo.svg';

		const ALLOWED_CURRENCIES = array(
			'GBP',
			'BGN',
			'RON',
			'EUR',
			'PLN',
			'DKK',
			'CZK',
			'HRK',
			'CZK',
			'HUF',
			'SEK',
		);

		const REQUEST_TIMEOUT         = 30;
		const REQUEST_CONNECT_TIMEOUT = 30;



		/**
		 * Makes a POST call to VW API to update hooks urls
		 *
		 * @return boolean
		 */
		public static function update_viva_wallet_webhook_url() {

			$settings  = WC_Vivawallet_Credentials::get_client_data();
			$test_mode = $settings['test_mode'];

			$access_token = WC_Vivawallet_Credentials::get_authentication_token( $test_mode );
			$url          = self::get_api_url_endpoint( $test_mode, self::ENDPOINT_POST_PAYMENT_METHODS_WEBHOOK );

			$header_args = array(
				'Authorization' => 'Bearer ' . $access_token,
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
			);

			$site_url = get_rest_url( null, '/' . self::WEBHOOK_NAMESPACE . self::WEBHOOK_URI );

			$post_args = array(
				'url' => $site_url,
			);
			$args      = array(
				'method'  => 'POST',
				'headers' => $header_args,
				'body'    => wp_json_encode( $post_args ),
				'timeout' => self::REQUEST_TIMEOUT,
			);

			$result = self::remote_request( $url, $args );

			WC_Vivawallet_Logger::log( "API UPDATE PPRO WEBHOOK URL \nURL: " . $url . "\nARGS: " . wp_json_encode( $args ) . "\nRESULT: " . wp_json_encode( $result ) );

			if ( ! is_wp_error( $result ) && isset( $result['response']['code'] ) && 204 === $result['response']['code'] ) {
				return true;
			}

			return false;

		}

		/**
		 *  Checks if APM payment method is available for this merchant.
		 *
		 * @param string $test_mode Test mode.
		 * @param string $vivawallet_id The Viva Wallet APM id to check.
		 *
		 * @return bool result
		 */
		public static function check_apm_availability( $test_mode, $vivawallet_id ) {
			$res = self::get_payment_methods( $test_mode );

			if ( false !== $res && 0 < count( $res ) ) {

				foreach ( $res as $item ) {
					if ( $item === $vivawallet_id ) {
						return true;
					}
				}
			}
			return false;
		}

		/**
		 * Makes call to VW api to retrieve the APM key.
		 */
		public static function get_alternative_payments_methods_key() {

			if ( null === WC()->session ) {
				WC()->session = new WC_Session_Handler();
				WC()->session->init();
			}

			if ( ! empty( WC()->session->get( 'VW_APM_KEY' ) ) ) {
				return WC()->session->get( 'VW_APM_KEY' );
			}

			$settings  = WC_Vivawallet_Credentials::get_client_data();
			$test_mode = $settings['test_mode'];

			$access_token = WC_Vivawallet_Credentials::get_authentication_token( $test_mode );

			if ( empty( $access_token ) ) {
				return '';
			}
			$url = self::get_api_url_endpoint( $test_mode, self::ENDPOINT_GET_PAYMENT_METHODS_WEBHOOK_TOKEN );

			$header_args = array(
				'Authorization' => 'Bearer ' . $access_token,
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
			);

			$args = array(
				'method'  => 'GET',
				'headers' => $header_args,
				'timeout' => self::REQUEST_TIMEOUT,
			);

			$result = self::remote_request( $url, $args );

			if ( ! is_wp_error( $result ) && isset( $result['response']['code'] ) && 200 === $result['response']['code'] ) {

				$result_body = json_decode( $result['body'] );
				$key         = $result_body->key;
				WC()->session->set( 'VW_APM_KEY', $key );
				WC_Vivawallet_Logger::log( "API GET PAYMENT METHODS WEBHOOK TOKEN \nURL: " . $url . "\nARGS: " . wp_json_encode( $args ) . "\nRESULT: " . wp_json_encode( $result ) );
				return $key;
			}
			WC_Vivawallet_Logger::log( "API GET PAYMENT METHODS WEBHOOK TOKEN FAILED \nURL: " . $url . "\nARGS: " . wp_json_encode( $args ) . "\nRESULT: " . wp_json_encode( $result ) );
			return '';

		}


		/**
		 *  Checks if subscriptions class exists and if the order id send has subscriptions.
		 *
		 * @param int $order_id The order id.
		 *
		 * @return bool result
		 */
		public static function check_if_subscriptions( $order_id ) {
			if ( function_exists( 'wcs_order_contains_subscription' ) ) {
				// check if we have subscription in the order.
				if ( wcs_order_contains_subscription( $order_id ) || wcs_is_subscription( $order_id ) || wcs_order_contains_renewal( $order_id ) ) {
					return true;
				}
			}
			return false;
		}

		/**
		 *  Complete_order.
		 *
		 * @param int|string $order_id order id.
		 * @param string     $transaction_id transaction id.
		 * @param string     $status status.
		 * @param bool       $has_cart has cart.
		 */
		public static function complete_order( $order_id, $transaction_id, $status, $has_cart = true ) {

			if ( $has_cart ) {
				global $woocommerce;
				$woocommerce->cart->empty_cart();
			}

			$order = wc_get_order( $order_id );

			$order->payment_complete( $transaction_id );

			add_post_meta( $order_id, self::POST_META_VW_TXN, $transaction_id );

			$order->set_transaction_id( $transaction_id );

			$order->add_order_note( $status, false );

			$update_status = false;

			$viva_settings = get_option( 'woocommerce_vivawallet_native_settings', array() );

			if ( 'no' === $viva_settings['advanced_settings_enabled'] ) {
				$update_status = true;
			} else {
				// check the prefered order status value.
				if ( 'completed' === $viva_settings['order_status'] ) {
					$update_status = true;
				}
			}

			if ( $update_status ) {
				$order->update_status( self::ORDER_STATUS_COMPLETE );
			}

			$order->save();
		}


		/**
		 * Get_url_token
		 *
		 * @param string        $transaction_id charge_token.
		 * @param string        $card_type card_type.
		 * @param string        $card_last_four card_last_four.
		 * @param string        $expiry_month expiry_month.
		 * @param string        $expiry_year expiry_year.
		 * @param null|WC_Order $order the order object.
		 *
		 * @return boolean|int false if fail, the id of card token if success.
		 */
		public static function save_card_token( $transaction_id, $card_type, $card_last_four, $expiry_month, $expiry_year, $order ) {

			// check if token exists in users tokens first.
			$new_token = $transaction_id;

			$users_tokens = WC_Payment_Tokens::get_customer_tokens( get_current_user_id(), 'vivawallet_native' );
			foreach ( $users_tokens as $key => $value ) {
				$token_object = WC_Payment_Tokens::get( $key );
				if ( $token_object->get_token() === $new_token ) {
					// if a match is found pass this object to save.
					$token = $token_object;
					break;
				}
			}

			// if no token match found create a new.
			if ( empty( $token ) ) {
				$token = new WC_Payment_Token_CC();
			}

			$token->set_token( $new_token );
			$token->set_gateway_id( 'vivawallet_native' );
			$token->set_card_type( $card_type );
			$token->set_last4( $card_last_four );
			$token->set_expiry_month( $expiry_month );
			$token->set_expiry_year( $expiry_year );
			$token->set_user_id( get_current_user_id() );
			$token->save();

			if ( null !== $order ) {
				if ( self::check_if_subscriptions( $order->get_id() ) ) {
					$subscriptions = wcs_get_subscriptions_for_order( $order );
					foreach ( $subscriptions as $subscription ) {

						// check if subscription already has an saved card related.
						$subscription_has_saved_method = get_post_meta( $subscription->get_id(), self::POST_META_VW_CARD_TOKEN_ID, true );

						update_post_meta( $subscription->get_id(), self::POST_META_VW_CARD_TOKEN, $new_token );
						update_post_meta( $subscription->get_id(), self::POST_META_VW_CARD_TOKEN_ID, $token->get_id() );

						if ( ! empty( $subscription_has_saved_method ) ) {
							WC_Subscriptions_Change_Payment_Gateway::update_payment_method( $subscription, 'vivawallet_native', $token->get_meta_data() );
						}
					}
				}
			}
			return $token->get_id();

		}


		/**
		 * Get_url_token
		 *
		 * @param string $is_test_mode test mode.
		 * @return string
		 */
		public static function get_token_url( $is_test_mode ) {
			if ( 'yes' === $is_test_mode ) {
				return self::URL_GET_TOKEN_TEST;
			} else {
				return self::URL_GET_TOKEN_LIVE;
			}
		}

		/**
		 * Get_base_url
		 *
		 * @param string $is_test_mode Is test mode.
		 *
		 * @return string
		 */
		public static function get_base_url( $is_test_mode ) {
			if ( 'yes' === $is_test_mode ) {
				return self::BASE_URL_TEST;
			} else {
				return self::BASE_URL_LIVE;
			}
		}

		/**
		 * Get_api_url
		 *
		 * @param string $is_test_mode Is test mode.
		 *
		 * @return string
		 */
		public static function get_api_url( $is_test_mode ) {
			if ( 'yes' === $is_test_mode ) {
				return self::BASE_URL_API_TEST;
			} else {
				return self::BASE_URL_API_LIVE;
			}
		}




		/**
		 * Get_api_url_endpoint
		 * returns url for api calls
		 *
		 * @param string $is_test_mode test mode.
		 *
		 * @param string $endpoint url.
		 *
		 * @return string
		 */
		public static function get_api_url_endpoint( $is_test_mode, $endpoint ) {
			switch ( $endpoint ) {
				case self::ENDPOINT_TRANSACTIONS:
					return self::get_api_url( $is_test_mode ) . self::ENDPOINT_TRANSACTIONS;
				case self::ENDPOINT_INSTALLMENTS:
					return self::get_api_url( $is_test_mode ) . self::ENDPOINT_INSTALLMENTS;
				case self::ENDPOINT_NATIVE_JS:
					return self::get_base_url( $is_test_mode ) . self::ENDPOINT_NATIVE_JS;
				case self::ENDPOINT_RECURRING_TRANSACTIONS:
					return self::get_api_url( $is_test_mode ) . self::ENDPOINT_RECURRING_TRANSACTIONS;
				case self::ENDPOINT_GET_SOURCES:
					return self::get_api_url( $is_test_mode ) . self::ENDPOINT_GET_SOURCES;
				case self::ENDPOINT_APPLE_PAY_TOKEN:
					return self::get_api_url( $is_test_mode ) . self::ENDPOINT_APPLE_PAY_TOKEN;
				case self::ENDPOINT_APPLE_PAY_REGISTRATION:
					return self::get_api_url( $is_test_mode ) . self::ENDPOINT_APPLE_PAY_REGISTRATION;
				case self::ENDPOINT_APPLE_PAY_DOMAIN_ASSOCIATION_FILE:
					return self::get_api_url( $is_test_mode ) . self::ENDPOINT_APPLE_PAY_DOMAIN_ASSOCIATION_FILE;
				case self::ENDPOINT_GET_PAYMENT_METHODS:
					return self::get_api_url( $is_test_mode ) . self::ENDPOINT_GET_PAYMENT_METHODS;
				case self::ENDPOINT_POST_PAYMENT_METHODS_WEBHOOK:
					return self::get_api_url( $is_test_mode ) . self::ENDPOINT_POST_PAYMENT_METHODS_WEBHOOK;
				case self::ENDPOINT_GET_PAYMENT_METHODS_WEBHOOK_TOKEN:
					return self::get_api_url( $is_test_mode ) . self::ENDPOINT_GET_PAYMENT_METHODS_WEBHOOK_TOKEN;
			}
		}

		/**
		 * Get_payment_methods
		 *
		 * @param string $test_mode test mode.
		 *
		 * @return boolean|array
		 */
		public static function get_payment_methods( $test_mode ) {

			if ( null === WC()->session ) {
				WC()->session = new WC_Session_Handler();
				WC()->session->init();
			}

			if ( ! empty( WC()->session->get( 'VW_AVAILABLE_APM' ) ) ) {
				return WC()->session->get( 'VW_AVAILABLE_APM' );
			}
			$token = WC_Vivawallet_Credentials::get_authentication_token( $test_mode );

			if ( empty( $token ) ) {
				return false;
			}

			$url = self::get_api_url_endpoint( $test_mode, self::ENDPOINT_GET_PAYMENT_METHODS );

			$header_args = array(
				'Authorization' => 'Bearer ' . $token,
				'Accept'        => 'application/json',
			);

			$args = array(
				'method'  => 'GET',
				'headers' => $header_args,
				'timeout' => self::REQUEST_TIMEOUT,
			);

			$result = self::remote_request( $url, $args );

			if ( ! is_wp_error( $result ) && isset( $result['response']['code'] ) && 200 === $result['response']['code'] ) {
				$result_body = json_decode( $result['body'] );
				$result_body = $result_body->paymentMethods; // phpcs:ignore
				WC()->session->set( 'VW_AVAILABLE_APM', $result_body );
				WC_Vivawallet_Logger::log( "GET ALTERNATIVE PAYMENT METHODS - \nURL: " . $url . "\nARGS: " . wp_json_encode( $args ) . "\nRESULT: " . wp_json_encode( $result ) );
				return $result_body;
			}

			WC_Vivawallet_Logger::log( "GET ALTERNATIVE PAYMENT METHODS CALL FAILED - \nURL: " . $url . "\nARGS: " . wp_json_encode( $args ) . "\nRESULT: " . wp_json_encode( $result ) );

			return false;

		}

		/**
		 * Get_currency_symbol
		 * get woocommerce currency and convert to vw type
		 *
		 * @param string $currency_code woocommerce currency type.
		 *
		 * @return int
		 */
		public static function get_currency_symbol( $currency_code ) {
			switch ( $currency_code ) {
				case 'HRK':
					$currency_number = 191; // CROATIAN KUNA.
					break;
				case 'CZK':
					$currency_number = 203; // CZECH KORUNA.
					break;
				case 'DKK':
					$currency_number = 208; // DANISH KRONE.
					break;
				case 'HUF':
					$currency_number = 348; // HUNGARIAN FORINT.
					break;
				case 'SEK':
					$currency_number = 752; // SWEDISH KRONA.
					break;
				case 'GBP':
					$currency_number = 826; // POUND STERLING.
					break;
				case 'RON':
					$currency_number = 946; // ROMANIAN LEU.
					break;
				case 'BGN':
					$currency_number = 975; // BULGARIAN LEV.
					break;
				case 'EUR':
					$currency_number = 978; // EURO.
					break;
				case 'PLN':
					$currency_number = 985; // POLISH ZLOTY.
					break;
				default:
					$currency_number = 978; // EURO.
			}
			return $currency_number;
		}

		/**
		 * Get_minimum_charge_amount
		 *
		 * @param string|number $currency_symbol VW currency type (ISO).
		 *
		 * @return int the amount to charge in the lowest minimal denominator (pence or cents)
		 */
		public static function get_minimum_charge_amount( $currency_symbol ) {
			switch ( $currency_symbol ) {
				case 191: // HRK.
					$amount = 230;
					break;
				case 203: // CZK.
					$amount = 800;
					break;
				case 208: // DKK.
					$amount = 250;
					break;
				case 348: // HUF.
					$amount = 11000;
					break;
				case 752: // SEK.
					$amount = 350;
					break;
				case 826: // GBP.
					$amount = 25;
					break;
				case 946: // RON.
					$amount = 150;
					break;
				case 975: // BGN.
					$amount = 60;
					break;
				case 978: // EUR.
					$amount = 30;
					break;
				case 985: // PLN.
					$amount = 150;
					break;
				default: // Default EUR.
					$amount = 30;
			}
			return $amount;
		}

		/**
		 * Get token
		 *
		 * @param string $client_id client_id.
		 *
		 * @param string $client_secret client_secret.
		 *
		 * @param string $test_mode test_mode.
		 *
		 * @param string $scope scope.
		 *
		 * @return bool|string
		 */
		public static function get_token( $client_id, $client_secret, $test_mode, $scope ) {

			if ( empty( $client_id ) || empty( $client_secret ) ) {
				return false;
			}

			$url = self::get_token_url( $test_mode );

			if ( 'back' === $scope ) {
				$scope_value = self::BACK_END_SCOPE;
			} else {
				$scope_value = self::FRONT_SCOPE;
			}

			$header_args = array(
				'Accept'        => 'application/json',
				'Authorization' => 'Basic ' . base64_encode( $client_id . ':' . $client_secret ), // phpcs:ignore
			);

			$post_args = array(
				'grant_type' => 'client_credentials',
				'scope'      => $scope_value,
			);

			$result = self::remote_request(
				$url,
				array(
					'method'      => 'POST',
					'headers'     => $header_args,
					'body'        => $post_args,
					'httpversion' => '1.0',
					'timeout'     => self::REQUEST_TIMEOUT,
				)
			);

			if ( ! is_wp_error( $result ) && ! isset( $result->error ) ) {
				$result_body = json_decode( $result['body'] );
				if ( isset( $result_body->access_token ) ) {
					return $result_body->access_token;
				}
			}

			$client_data = WC_Vivawallet_Credentials::get_client_data();
			if ( $client_data['test_mode'] === $test_mode ) { // only log a failed api call when in the selected mode.
				WC_Vivawallet_Logger::log( "API GET CREDENTIALS FAILED \nURL: " . $url . "\nScope: " . wc_strtoupper( $scope ) . "\nRESULT: " . wp_json_encode( $result ) );
			}
			return false;
		}


		/**
		 * Do create transaction api call
		 *
		 * @param array  $post_args post_args.
		 * @param string $access_token access_token.
		 * @param string $test_mode test_mode.
		 * @param string $recurring_trans_id if it is a recurring transaction pass the id here.
		 *
		 * @return array|WP_Error
		 */
		public static function transaction_api_call( $post_args, $access_token, $test_mode, $recurring_trans_id = '' ) {

			if ( '' !== $recurring_trans_id ) {
				$url = self::get_api_url_endpoint( $test_mode, self::ENDPOINT_RECURRING_TRANSACTIONS ) . $recurring_trans_id . ':charge';
			} else {
				$url = self::get_api_url_endpoint( $test_mode, self::ENDPOINT_TRANSACTIONS );
			}

			global $wp_version;
			global $woocommerce;

			$user_agent = '';
			if ( ! empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
				$user_agent = wc_clean( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
			}
			$user_agent .= ' WP/' . $wp_version . ' WC/' . $woocommerce->version . ' VW/' . WC_VIVAWALLET_VERSION . ' IP/' . self::get_ip_address();

			$header_args = array(
				'Authorization' => 'Bearer ' . $access_token,
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
				'User-Agent'    => $user_agent,
			);

			$args = array(
				'method'      => 'POST',
				'headers'     => $header_args,
				'body'        => wp_json_encode( $post_args ),
				'httpversion' => '1.0',
				'timeout'     => self::REQUEST_TIMEOUT,
			);

			$result = self::remote_request( $url, $args );

			WC_Vivawallet_Logger::log( "API CREATE TRANSACTION \nURL: " . $url . "\nARGS: " . wp_json_encode( $args ) . "\nRESULT: " . wp_json_encode( $result ) );

			return $result;

		}

		/**
		 * Prepare_transaction_data_from_order
		 *
		 * @param WC_Order $order Order.
		 *
		 * @return array Result.
		 */
		public static function prepare_transaction_data_from_order( $order ) {

			$amount = $order->get_total();
			$amount = floatval( $amount ) * 100; // convert to cents.

			$currency = $order->get_currency();
			$currency = self::get_currency_symbol( $currency ); // convert currency to iso code.

			$name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();

			$phone = $order->get_billing_phone();
			$phone = preg_replace( '/[^0-9]/', '', $phone ); // clean up phone number..
			if ( strlen( $phone ) <= 1 ) {
				$phone = '0111111111';  // inject default value if empty or has a value of 1.
			}

			$email = $order->get_billing_email();

			$ln = get_locale();
			if ( isset( $ln ) && strlen( $ln ) > 2 ) {
				$ln = substr( $ln, 0, 2 );
			} else {
				$ln = 'en'; // fallback to en if the lang is not properly defined in wp.
			}

			$messages = self::get_trns_messages( $order );

			$shipping_country_code = $order->get_shipping_country();
			$billing_country_code  = $order->get_billing_country();
			$country_code          = '';
			if ( ! empty( $shipping_country_code ) ) {
				$country_code = $shipping_country_code;
			} elseif ( ! empty( $billing_country_code ) ) {
				$country_code = $billing_country_code;
			}

			return array(
				'amount'      => $amount,
				'currency'    => $currency,
				'name'        => $name,
				'phone'       => $phone,
				'email'       => $email,
				'ln'          => $ln,
				'messages'    => $messages,
				'countryCode' => $country_code,
			);

		}

		/**
		 * Get_trns_messages.
		 *
		 * @param WC_Order $order Order.
		 *
		 * @return array
		 */
		private static function get_trns_messages( $order ) {

			$site_url = get_site_url();

			$domain = wp_parse_url( $site_url, PHP_URL_HOST );

			$merchant_message = $domain . ' - ' . $order->get_payment_method_title() . ' - ' . $order->get_order_number();
			$customer_message = $domain . ' - ' . $order->get_payment_method_title() . ' - ' . $order->get_order_number();

			return array(
				'merchant_message' => $merchant_message,
				'customer_message' => $customer_message,
			);
		}


		/**
		 * Get sources
		 *
		 * @param string $bearer bearer token.
		 *
		 * @param string $test_mode test mode.
		 *
		 * @return  array|string
		 */
		public static function get_sources( $bearer, $test_mode ) {

			$url = self::get_api_url_endpoint( $test_mode, self::ENDPOINT_GET_SOURCES );

			$header_args = array(
				'Accept'        => 'application/json',
				'Authorization' => 'Bearer ' . $bearer,
			);

			$args = array(
				'method'      => 'GET',
				'headers'     => $header_args,
				'httpversion' => '1.0',
				'timeout'     => self::REQUEST_TIMEOUT,
			);

			$result = self::remote_request( $url, $args );

			if ( ! is_wp_error( $result ) && isset( $result['response']['code'] ) && 200 === $result['response']['code'] ) {
				$result = json_decode( $result['body'] );
				return $result;
			} else {

				WC_Vivawallet_Logger::log( "GET SOURCES API CALL FAILED \nURL: " . $url . "\nARGS: " . wp_json_encode( $args ) . "\nRESULT: " . wp_json_encode( $result ) );
				return 'error';
			}

		}

		/**
		 * Get free source code
		 *
		 * Get first available source code
		 *
		 * @param array $existing_sources List of all existing sources.
		 *
		 * @return string Free source code
		 * @since 1.4.5
		 */
		public static function get_free_source_code( array $existing_sources ) {

			$prefixed_existing_sources = array_map(
				function ( $obj ) {
					return (int) ltrim( substr( $obj->sourceCode, strlen( self::SOURCE_IDENTIFIER ) ), '0' ); // get only the number.
				},
				array_filter(
					$existing_sources,
					function ( $obj ) {
						return substr( $obj->sourceCode, 0, strlen( self::SOURCE_IDENTIFIER ) ) === self::SOURCE_IDENTIFIER; // return only new WP codes.
					}
				)
			);
			$max_existing_source_id    = empty( $prefixed_existing_sources ) ? 0 : max( $prefixed_existing_sources );
			$free_source_id            = $max_existing_source_id + 1;

			return self::SOURCE_IDENTIFIER . str_pad( $free_source_id, 4, '0', STR_PAD_LEFT );
		}

		/**
		 * Check source
		 *
		 * @param string $bearer bearer token.
		 *
		 * @param string $source source.
		 *
		 * @param string $test_mode test mode.
		 *
		 * @return  string
		 */
		public static function check_source( $bearer, $source, $test_mode ) {
			$url         = self::get_api_url_endpoint( $test_mode, self::ENDPOINT_GET_SOURCES ) . '?sourceCode=' . $source;
			$header_args = array(
				'Accept'        => 'application/json',
				'Authorization' => 'Bearer ' . $bearer,
			);
			$args        = array(
				'method'      => 'GET',
				'headers'     => $header_args,
				'httpversion' => '1.0',
				'timeout'     => self::REQUEST_TIMEOUT,
			);
			$result      = self::remote_request( $url, $args );

			if ( ! is_wp_error( $result ) && isset( $result['response']['code'] ) && 200 === $result['response']['code'] ) { // source found.
				$result = json_decode( $result['body'] );
				if ( isset( $result[0]->state ) ) {
					if ( 1 === $result[0]->state ) {
						return 'Active';
					} elseif ( 2 === $result[0]->state ) {
						return 'Pending';
					} elseif ( 0 === $result[0]->state ) {
						return 'InActive';
					}
				}
			} else {
				if ( ! empty( $bearer ) ) {
					WC_Vivawallet_Logger::log( "Check source API call failed \nURL: " . $url . "\nARGS: " . wp_json_encode( $args ) . "\nRESULT: " . wp_json_encode( $result ) );

				}
				return 'error';
			}

			/*
				Active = 1,
				Pending = 2,
				InProgress = 3
			*/
		}

		/**
		 * Is_valid_domain_name
		 *
		 * @param string $url url.
		 *
		 * @return  boolean
		 */
		public static function is_valid_domain_name( $url ) {
			return ( preg_match( '/^(?!\-)(?:(?:[a-zA-Z\d][a-zA-Z\d\-]{0,61})?[a-zA-Z\d]\.){1,126}(?!\d+)[a-zA-Z\d]{1,63}$/', $url ) );
		}

		/**
		 * Is valid currency
		 *
		 * @return bool
		 */
		public static function is_valid_currency() {
			return in_array( get_woocommerce_currency(), self::ALLOWED_CURRENCIES, true ) ? true : false;
		}


		/**
		 * Check_if_instalments checks if instalments are allowed (only for greek stores).
		 *
		 * @return boolean Result.
		 */
		public static function check_if_instalments() {
			$wc_country = WC_Admin_Settings::get_option( 'woocommerce_default_country' );
			if ( isset( $wc_country ) && ! empty( $wc_country ) ) {
				$wc_country = explode( ':', $wc_country );
				$wc_country = $wc_country[0];
				if ( 'GR' === $wc_country ) {
					return true;
				}
			}
			return false;
		}


		/**
		 * Check_if_instalments checks if instalments are allowed (only for greek stores).
		 *
		 * @param string $id the id of the payment method.
		 *
		 * @return array
		 */
		public static function get_payment_method_by_id( $id ) {

			$settings   = null;
			$method_key = '';

			switch ( $id ) {
				case ( '10' ): // IDeal.
					$method_key = 'vivawallet-ideal';
					$settings   = get_option( 'woocommerce_vivawallet-ideal_settings', array() );
					break;
				case ( '11' ): // P24.
					$method_key = 'vivawallet-p24';
					$settings   = get_option( 'woocommerce_vivawallet-p24_settings', array() );
					break;
				case ( '13' ): // PayU.
					$method_key = 'vivawallet-payu';
					$settings   = get_option( 'woocommerce_vivawallet-payu_settings', array() );
					break;
				case ( '14' ): // Multibanco.
					$method_key = 'vivawallet-multibanco';
					$settings   = get_option( 'woocommerce_vivawallet-multibanco_settings', array() );
					break;
				case ( '15' ): // Giropay.
					$method_key = 'vivawallet-giropay';
					$settings   = get_option( 'woocommerce_vivawallet-giropay_settings', array() );
					break;
				case ( '16' ): // DirectPay.
					$method_key = 'vivawallet-directpay';
					$settings   = get_option( 'woocommerce_vivawallet-directpay_settings', array() );
					break;
				case ( '17' ): // Eps.
					$method_key = 'vivawallet-eps';
					$settings   = get_option( 'woocommerce_vivawallet-eps_settings', array() );
					break;
				case ( '18' ): // WeChatPay.
					$method_key = 'vivawallet-wechatpay';
					$settings   = get_option( 'woocommerce_vivawallet-wechatpay_settings', array() );
					break;
				case ( '19' ): // BitPay.
					$method_key = 'vivawallet-bitpay';
					$settings   = get_option( 'woocommerce_vivawallet-bitpay_settings', array() );
					break;
			}

			$title = '';
			if ( ! empty( $settings['title'] ) ) {
				$title = $settings['title'];
			}

			$result = array(
				'method_key'   => $method_key,
				'method_title' => $title,
			);

			return $result;

		}

		/**
		 * Wrapper for wp_remote_request. Converts header keys to lower case.
		 *
		 * @param string $url  url of the request.
		 * @param array  $args arguments of the request.
		 *
		 * @return array|WP_Error response of the request.
		 * @since 1.4.5
		 */
		public static function remote_request( $url, array $args = array() ) {
			$result = wp_remote_request( $url, $args );
			if ( ! is_wp_error( $result ) && isset( $result['headers'] ) ) {
				if ( $result['headers'] instanceof Requests_Utility_CaseInsensitiveDictionary ) {
					$result['headers'] = $result['headers']->getAll();
				} elseif ( is_array( $result['headers'] ) ) {
					$result['headers'] = array_change_key_case( $result['headers'] );
				}
			}

			return $result;
		}

		/**
		 * Get ip address
		 *
		 * @return string ip
		 * @since 1.4.5
		 */
		public static function get_ip_address() {
			foreach ( array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR' ) as $key ) {
				if ( array_key_exists( $key, $_SERVER ) === true ) {
					$ips = explode( ',', wc_clean( wp_unslash( $_SERVER[ $key ] ) ) );
					foreach ( $ips as $ip ) {
						$ip = trim( $ip );
						if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) !== false ) {
							return $ip;
						}
					}
				}
			}

			return '';
		}
	}

	new WC_Vivawallet_Helper();

}


