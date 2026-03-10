<?php
/**
 * Helpers
 *
 * @package   VivaComSmartForWooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Core\Source\SourceItem;
use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Core\Source\SourceList;
use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Api\SourceClient;
use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Api\OrderClient;
use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Api\WebhookClient;
use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Application;
use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Http\Authentication\Authentication;
use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Http\Authentication\BearerAuthentication;
use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Api\MerchantClient;
use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Api\TransactionClient;
use VivaComSmartCheckout\Vivawallet\VivawalletPhp\Http\Response;

/**
 * Class WC_Vivacom_Smart_Helpers
 */
class WC_Vivacom_Smart_Helpers {

	const TECHNICAL_NAME = 'vivacom_smart';

	const TOKEN    = '_vivacom_smart_card_token';
	const TOKEN_ID = '_vivacom_smart_card_token_id';

	const WEBHOOK_NAMESPACE = 'wc_vivacom_smart/v1';
	const WEBHOOK_URI       = '/payments_methods_endpoint';

	const GRANT_TYPE = 'client_credentials';

	const SCOPE = 'urn:viva:payments:core:api:acquiring urn:viva:payments:core:api:acquiring:transactions urn:viva:payments:core:api:redirectcheckout urn:viva:payments:core:api:plugins urn:viva:payments:core:api:nativecheckoutv2 urn:viva:payments:core:api:plugins:woocommerce';

	/**
	 * Create webhook
	 *
	 * @param Authentication $authentication authentication.
	 *
	 * @return bool
	 */
	public static function create_webhook( $authentication ) {
		$webhook_client   = new WebhookClient( $authentication );
		$webhook_url      = get_rest_url( null, '/' . self::WEBHOOK_NAMESPACE . self::WEBHOOK_URI );
		$webhook_response = $webhook_client->createWebhook( $webhook_url );
		if ( ! $webhook_response->isSuccessful() ) {
			WC_Vivacom_Smart_Logger::log( "Api webhook \nURL webhook: \n" . $webhook_url . "\nResult: \n" . wp_json_encode( $webhook_response->getBody() ) );
		}

		return $webhook_response->isSuccessful();
	}

	/**
	 *
	 * Get verification token
	 *
	 * @return string
	 */
	public static function get_verification_token() {
		$viva_settings         = get_option( 'woocommerce_vivacom_smart_settings' );
		$environment           = 'yes' === $viva_settings['test_mode'] ? 'demo' : 'live';
		$bearer_authentication = self::get_bearer_authentication( $environment );
		if ( $bearer_authentication->hasValidToken() ) {
			$webhook_client = new WebhookClient( $bearer_authentication );
			if ( ! empty( $webhook_client ) ) {
				$webhook_response = $webhook_client->getVerificationToken();

				if ( $webhook_response->isSuccessful() && ! is_null( $webhook_response->getBody() ) ) {
					$response = $webhook_response->getBody();
				}

				if ( ! empty( $response ) && ! empty( $response->key ) ) {
					$response_key = $response->key;
				}
			}
		}

		return $response_key ?? '';
	}

	/**
	 * Complete order
	 *
	 * @param int    $order_id order_id.
	 * @param array  $transaction transaction.
	 * @param string $note note.
	 * @param bool   $has_cart has_cart.
	 *
	 * @return void
	 * @throws WC_Data_Exception Throws data exception.
	 */
	public static function complete_order( $order_id, $transaction, $note = '', $has_cart = true ) {
		if ( $has_cart ) {
			global $woocommerce;
			$woocommerce->cart->empty_cart();
		}

		global $wpdb;

		$order = wc_get_order( $order_id );

		$order->payment_complete( $transaction['id'] );

		$vivacom_order = self::get_smart_checkout_order( $order_id );

		$transaction_type = in_array( $transaction['typeId'], array( 1, 80 ) ) ? 'preauthorization' : 'payment';

		$transaction_data = array(
			'order_id'            => $vivacom_order['id'],
			'transaction_id'      => $transaction['id'],
			'transaction_type_id' => self::get_transaction_type( $transaction_type ),
			'amount'              => $order->get_total(),
		);

		$status  = __( 'Order has been paid with Viva.com Smart Checkout, Transaction Id:', 'viva-com-smart-for-woocommerce' );
		$status .= $transaction['id'];
		$status .= '<br>' . $note;

		$wpdb->insert(
			$wpdb->prefix . 'viva_com_smart_wc_checkout_transactions',
			$transaction_data
		);

		$order->set_transaction_id( $transaction['id'] );

		$order->add_order_note( __( 'Viva.com Transaction status: ', 'viva-com-smart-for-woocommerce' ) . $status, false );

		$viva_settings = get_option( 'woocommerce_vivacom_smart_settings' );

		$default_order_status = ! empty( $viva_settings['order_status'] ) ? $viva_settings['order_status'] : 'completed';

		$order_status = 'preauthorization' === $transaction_type ? 'on-hold' : $default_order_status;

		$order->update_status( $order_status );

		$order->save();
	}

	/**
	 * Get order from viva orders table
	 *
	 * @param string $order_id order_id.
	 *
	 * @return array|null
	 */
	public static function get_smart_checkout_order( string $order_id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}viva_com_smart_wc_checkout_orders WHERE woocommerce_order_id = %s ORDER BY date_add DESC LIMIT 1",
				$order_id
			),
			ARRAY_A
		);
	}

	/**
	 * Get transaction
	 *
	 * @param string $order_id order_id.
	 * @param string $type_id type_id.
	 *
	 * @return string|null
	 */
	public static function get_smart_checkout_transaction( string $order_id, string $type_id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}viva_com_smart_wc_checkout_transactions WHERE order_id = %s AND transaction_type_id = %s ORDER BY date_add DESC LIMIT 1",
				$order_id,
				$type_id
			),
			ARRAY_A
		);
	}

	/**
	 * Get transaction type from db
	 *
	 * @param string $name name.
	 *
	 * @return string|null $transaction_type_id transaction_type_id
	 */
	public static function get_transaction_type( string $name ) {
		global $wpdb;

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}viva_com_smart_wc_checkout_transaction_types WHERE name = %s",
				$name
			)
		);
	}

	/**
	 * Process refund
	 *
	 * @param Authentication $authentication authentication.
	 * @param string         $source source.
	 * @param int            $order_id order_id.
	 * @param string         $amount amount.
	 *
	 * @return bool|WP_Error
	 * @throws Exception Throws exception.
	 */
	public static function process_refund( $authentication, $source, $order_id, $amount ) {

		global $wpdb;
		$order       = wc_get_order( $order_id );
		$order_total = $order->get_total();

		$order_total   = (int) number_format( $order_total, 2, '', '' );
		$refund_amount = (int) number_format( $amount, 2, '', '' );
		$full_refund   = $refund_amount === $order_total;

		$order_status = $order->get_status();

		try {
			if ( in_array( $order_status, array( 'cancelled', 'refunded' ), true ) ) {
				throw new Exception( __( 'You cannot edit an already refunded or canceled order.', 'viva-com-smart-for-woocommerce' ) );
			}

			$vivacom_order = self::get_smart_checkout_order( $order_id );

			if ( empty( $vivacom_order ) ) {
				throw new Exception( __( 'The viva order code for this order could not be found. Something is wrong!', 'viva-com-smart-for-woocommerce' ) );
			}

			// Get capture transaction if present or payment.
			$vivacom_transaction = self::get_smart_checkout_transaction( $vivacom_order['id'], self::get_transaction_type( 'capture' ) ) ?? self::get_smart_checkout_transaction( $vivacom_order['id'], self::get_transaction_type( 'payment' ) );

			if ( empty( $vivacom_transaction ) ) {
				throw new Exception( __( 'The transaction ID for this order could not be found. Something is wrong!', 'viva-com-smart-for-woocommerce' ) );
			}

			// Transaction call.
			$transaction_client   = new TransactionClient( $authentication );
			$transaction_response = $transaction_client->refundTransaction( $vivacom_transaction['transaction_id'], $refund_amount, $vivacom_order['currency'], $source );

			WC_Vivacom_Smart_Logger::log( 'Api refund response: ' . wp_json_encode( $transaction_response->all() ) );

			if ( $transaction_response->isSuccessful() && ! empty( $transaction_response->getBody() ) && ! empty( $transaction_response->getBody()->transactionId ) ) {
				$transaction_id = $transaction_response->getBody()->transactionId;

				$transaction_data = array(
					'order_id'            => $vivacom_order['id'],
					'transaction_id'      => $transaction_id,
					'transaction_type_id' => self::get_transaction_type( 'refund' ),
					'amount'              => $refund_amount,
				);

				$wpdb->insert(
					$wpdb->prefix . 'viva_com_smart_wc_checkout_transactions',
					$transaction_data
				);

				$note = ( $full_refund ) ? __( 'Full refund was executed with ID: ', 'viva-com-smart-for-woocommerce' ) . $transaction_id : __( 'Partial refund was executed with ID: ', 'viva-com-smart-for-woocommerce' ) . $transaction_id;

				$order->add_order_note( $note, false );
				$order->save();
			} elseif ( $transaction_response->hasError() ) {
					throw new Exception( __( 'Transaction Response Error: ', 'viva-com-smart-for-woocommerce' ) . $transaction_response->getError()->getMessage() );
			}

			return true;
		} catch ( Exception $e ) {
			return new WP_Error( 'error', $e->getMessage() );
		}
	}

	/**
	 * Process Capture
	 *
	 * @param Authentication  $authentication authentication.
	 * @param object|WC_Order $order order.
	 * @param string          $amount amount.
	 *
	 * @return array|WP_Error
	 * @throws Exception Throws exception.
	 */
	public static function process_capture( $authentication, $order, $amount ) {

		global $wpdb;
		$transaction_id = null;
		$order_id       = $order->get_id();
		$order_status   = $order->get_status();

		try {

			if ( 'on-hold' !== $order_status ) {
				throw new Exception( __( 'Wrong order status for capturing.', 'viva-com-smart-for-woocommerce' ) );
			}

			if ( self::TECHNICAL_NAME !== $order->get_payment_method() ) {
				throw new Exception( __( 'Wrong payment method of order.', 'viva-com-smart-for-woocommerce' ) );
			}

			$vivacom_order = self::get_smart_checkout_order( $order_id );

			if ( empty( $vivacom_order ) ) {
				throw new Exception( __( 'The viva order code for this order could not be found. Something is wrong!', 'viva-com-smart-for-woocommerce' ) );
			}

			$vivacom_transaction = self::get_smart_checkout_transaction( $vivacom_order['id'], self::get_transaction_type( 'preauthorization' ) );

			if ( empty( $vivacom_transaction ) ) {
				throw new Exception( __( 'The transaction ID for this order could not be found. Something is wrong!', 'viva-com-smart-for-woocommerce' ) );
			}

			if ( ! ( self::get_transaction_type( 'preauthorization' ) === $vivacom_transaction['transaction_type_id'] ) ) {
				throw new Exception( __( 'Wrong transaction type!', 'viva-com-smart-for-woocommerce' ) );
			}

			$transaction_client   = new TransactionClient( $authentication );
			$transaction_response = $transaction_client->captureAuthorizedTransaction( $vivacom_transaction['transaction_id'], $amount, array('currencyCode' => $vivacom_order['currency'] ) );

			WC_Vivacom_Smart_Logger::log( 'Api Capture response: ' . wp_json_encode( $transaction_response->all() ) );

			if ( $transaction_response->isSuccessful() && ! empty( $transaction_response->getBody() ) && ! empty( $transaction_response->getBody()->transactionId ) ) {
				$transaction_id = $transaction_response->getBody()->transactionId;

				$transaction_data = array(
					'order_id'            => $vivacom_order['id'],
					'transaction_id'      => $transaction_id,
					'transaction_type_id' => self::get_transaction_type( 'capture' ),
					'amount'              => $amount,
				);

				$wpdb->insert(
					$wpdb->prefix . 'viva_com_smart_wc_checkout_transactions',
					$transaction_data
				);

			} elseif ( $transaction_response->hasError() ) {
					throw new Exception( __( 'Transaction Response Error: ', 'viva-com-smart-for-woocommerce' ) . $transaction_response->getError()->getMessage() );
			}
		} catch ( Exception $e ) {
			return new WP_Error( 'error', $e->getMessage() );
		}

		return array(
			'transaction_id' => $transaction_id ?? 'n/a',
		);
	}

	/**
	 * Process Void
	 *
	 * @param Authentication  $authentication authentication.
	 * @param string          $source source.
	 * @param object|WC_Order $order order.
	 * @param string          $amount amount.
	 *
	 * @return array|WP_Error
	 * @throws Exception Throws exception.
	 */
	public static function process_void( $authentication, $source, $order, $amount ) {
		global $wpdb;
		$transaction_id = null;
		$order_id       = $order->get_id();
		$order_status   = $order->get_status();

		try {
			if ( 'on-hold' !== $order_status ) {
				throw new Exception( __( 'Wrong order status for capturing.', 'viva-com-smart-for-woocommerce' ) );
			}

			if ( self::TECHNICAL_NAME !== $order->get_payment_method() ) {
				throw new Exception( __( 'Wrong payment method of order.', 'viva-com-smart-for-woocommerce' ) );
			}

			$vivacom_order = self::get_smart_checkout_order( $order_id );

			if ( empty( $vivacom_order ) ) {
				throw new Exception( __( 'The viva order code for this order could not be found. Something is wrong!', 'viva-com-smart-for-woocommerce' ) );
			}

			$vivacom_transaction = self::get_smart_checkout_transaction( $vivacom_order['id'], self::get_transaction_type( 'preauthorization' ) );

			if ( empty( $vivacom_transaction ) ) {
				throw new Exception( __( 'The transaction ID for this order could not be found. Something is wrong!', 'viva-com-smart-for-woocommerce' ) );
			}

			if ( ! ( self::get_transaction_type( 'preauthorization' ) === $vivacom_transaction['transaction_type_id'] ) ) {
				throw new Exception( __( 'Wrong transaction type!', 'viva-com-smart-for-woocommerce' ) );
			}

			$transaction_client   = new TransactionClient( $authentication );
			$transaction_response = $transaction_client->voidAuthorizedTransaction( $vivacom_transaction['transaction_id'], $amount, $vivacom_order['currency'], $source );

			WC_Vivacom_Smart_Logger::log( 'Api Void response: ' . wp_json_encode( $transaction_response->all() ) );

			if ( $transaction_response->isSuccessful() && ! empty( $transaction_response->getBody() ) && ! empty( $transaction_response->getBody()->transactionId ) ) {
				$transaction_id = $transaction_response->getBody()->transactionId;

				$transaction_data = array(
					'order_id'            => $vivacom_order['id'],
					'transaction_id'      => $transaction_id,
					'transaction_type_id' => self::get_transaction_type( 'void' ),
					'amount'              => $amount,
				);

				$wpdb->insert(
					$wpdb->prefix . 'viva_com_smart_wc_checkout_transactions',
					$transaction_data
				);

			} elseif ( $transaction_response->hasError() ) {
					throw new Exception( __( 'Transaction Response Error: ', 'viva-com-smart-for-woocommerce' ) . $transaction_response->getError()->getMessage() );
			}
		} catch ( Exception $e ) {
			return new WP_Error( 'error', $e->getMessage() );
		}

		return array(
			'transaction_id' => $transaction_id ?? 'n/a',
		);
	}

	/**
	 * Save card token
	 *
	 * @param string          $transaction_id transaction_id.
	 * @param object|WC_Order $order order.
	 * @param array           $token_data token_data.
	 *
	 * @return int
	 */
	public static function save_payment_token( $transaction_id, $order, $token_data ) {
		// Check token provided or create.
		global $wpdb;
		$users_tokens = WC_Payment_Tokens::get_customer_tokens( $order->get_customer_id(), self::TECHNICAL_NAME );

		foreach ( $users_tokens as $key => $value ) {
			$token_object = WC_Payment_Tokens::get( $key );
			if ( $token_object->get_token() === $transaction_id ) {
				$token = $token_object;
				break;
			}
		}

		if ( empty( $token ) ) {
			$token = new WC_Payment_Token_CC();
		}

		$token->set_token( $transaction_id );
		$token->set_gateway_id( self::TECHNICAL_NAME );
		$token->set_user_id( $order->get_customer_id() );
		$token->set_card_type( $token_data['cardType'] );
		$token->set_last4( $token_data['lastFourDigits'] );
		$token->set_expiry_month( $token_data['expiryMonth'] );
		$token->set_expiry_year( $token_data['expiryYear'] );
		$token->save();

		if ( ! empty( $order ) ) {
			if ( self::check_subscription( $order->get_id() ) ) {
				if ( function_exists( 'wcs_get_subscriptions_for_order' ) && class_exists( 'WC_Subscriptions_Change_Payment_Gateway' ) ) {
					$subscriptions = wcs_get_subscriptions_for_order( $order );
					foreach ( $subscriptions as $subscription ) {
						// check if subscription already has a saved token related.
						$token_id = $wpdb->get_var(
							$wpdb->prepare(
								"SELECT token_id FROM {$wpdb->prefix}viva_com_smart_wc_checkout_recurring WHERE recurring_id = %s ORDER BY date_add LIMIT 1",
								$subscription->get_id()
							)
						);

						$transaction_object_id = $wpdb->get_var(
							$wpdb->prepare(
								"SELECT id FROM {$wpdb->prefix}viva_com_smart_wc_checkout_transactions WHERE transaction_id = %s ORDER BY date_add LIMIT 1",
								$transaction_id
							)
						);

						$wpdb->insert(
							$wpdb->prefix . 'viva_com_smart_wc_checkout_recurring',
							array(
								'token_id'       => $token->get_id(),
								'transaction_id' => $transaction_object_id,
								'recurring_id'   => $subscription->get_id(),
							)
						);

						if ( ! empty( $token_id ) ) {
							WC_Subscriptions_Change_Payment_Gateway::update_payment_method( $subscription, self::TECHNICAL_NAME, $token->get_meta_data() );
						}
					}
				}
			}
		}

		return $token->get_id();
	}

	/**
	 * Get valid currencies
	 *
	 * @param Authentication $authentication authentication.
	 *
	 * @return bool|array
	 */
	public static function get_valid_currencies( $authentication ) {

		$merchant_client = new MerchantClient( $authentication );

		if ( ! empty( $merchant_client ) ) {
			$merchant_response = $merchant_client->getInfo();
			if ( $merchant_response->isSuccessful() && ! empty( $merchant_response->getBody()->currencies ) ) {
				$merchant_currencies = $merchant_response->getBody()->currencies;
			}
		}

		return ! empty( $merchant_currencies ) ? $merchant_currencies : array();
	}

	/**
	 * Get bearer
	 *
	 * @param string $environment environment.
	 *
	 * @return BearerAuthentication
	 */
	public static function get_bearer_authentication( $environment ) {

		$credentials = self::get_credentials( $environment );

		$bearer_authentication = new BearerAuthentication(
			$environment,
			$credentials['client_id'] ?? '',
			$credentials['client_secret'] ?? '',
			self::GRANT_TYPE,
			self::SCOPE
		);

		if ( ! $bearer_authentication->hasValidToken() ) {
			$response = $bearer_authentication->getResponse() ?? '';
			$message  = ! empty( $response ) ? $response->all() : '';
			WC_Vivacom_Smart_Logger::log( 'Bearer authentication failed: Environment ' . $environment . "\n" . wp_json_encode( $message ) );
		}

		return $bearer_authentication;
	}

	/**
	 * Get credentials
	 *
	 * @param string $environment environment.
	 *
	 * @return array
	 */
	public static function get_credentials( $environment ) {
		$viva_settings                = get_option( 'woocommerce_vivacom_smart_settings' );
		$credentials['environment']   = $environment;
		$credentials['client_id']     = 'live' === $environment ? $viva_settings['client_id'] : $viva_settings['demo_client_id'];
		$credentials['client_secret'] = 'live' === $environment ? $viva_settings['client_secret'] : $viva_settings['demo_client_secret'];

		return $credentials;
	}

	/**
	 * Get transaction
	 *
	 * @param Authentication $authentication authentication.
	 * @param string         $transaction_id transaction_id.
	 *
	 * @return object|null
	 */
	public static function get_transaction( $authentication, $transaction_id ) {
		$transaction_client   = new TransactionClient( $authentication );
		$transaction_response = $transaction_client->retrieveTransactionById( $transaction_id );
		if ( $transaction_response->isSuccessful() && ! is_null( $transaction_response->getBody() ) ) {
			// CHECK TRANSACTION IN ORDER AND STATUS.
			$response = $transaction_response->getBody();
		} else {
			WC_Vivacom_Smart_Logger::log( 'Transaction Retrieve failed for transaction : ' . $transaction_id );
		}

		return ! empty( $response ) ? $response : null;
	}

	/**
	 * Get sources
	 *
	 * @param Authentication $authentication authentication.
	 *
	 * @return SourceList
	 */
	public static function get_sources( Authentication $authentication ): SourceList {

		$source_client   = new SourceClient( $authentication );
		$source_response = $source_client->getSources();
		if ( $source_response->isSuccessful() && $source_response->getBody() instanceof SourceList ) {
			$urls = array(
				'success' => self::get_redirect_url( 'success' ),
				'failure' => self::get_redirect_url( 'fail' ),
			);

			/**
			 * Source List
			 *
			 * @var SourceList $source_list
			 */
			$source_list = $source_response->getBody();
			$source_list = $source_list->filterByDomain( self::get_domain() )->filterByState( 1 )->filterByUrls( $urls );
		} else {
			WC_Vivacom_Smart_Logger::log( "GET SOURCES API CALL FAILED \nRESULT: " . wp_json_encode( $source_response->all() ) );
		}

		return $source_list ?? new SourceList();
	}

	/**
	 * Create Source Item
	 *
	 * @param Authentication $authentication authentication.
	 * @param string         $prefix prefix.
	 * @param array          $options options.
	 *
	 * @return SourceItem
	 */
	public static function create_source_item( Authentication $authentication, string $prefix, array $options ) {
		$source_client = new SourceClient( $authentication );
		do {
			$already_exists_error = false;
			$random_code          = sprintf( '%04d', rand( 101, 9999 ) );
			$options['code']      = "$prefix-" . $random_code;
			$source_item          = new SourceItem( (object) $options );
			$source_response      = $source_client->createSource( $source_item );
			WC_Vivacom_Smart_Logger::log( 'API CREATE SOURCE' . ( $source_response->isSuccessful() ? ' SUCCESS ' : ' FAILED ' ) . " \nARGS: " . wp_json_encode( $options ) . "\nRESULT: " . wp_json_encode( $source_response->all() ) );
			$already_exists_error = $source_response->isSuccessful() && 409 === $source_response->getHttpCode() && $source_response->hasError() && stripos( $source_response->getBody()->message, 'already exists' );
		} while ( true === $already_exists_error );

		return $source_item;
	}

	/**
	 * Create Source
	 *
	 * @param Authentication $authentication authentication.
	 *
	 * @return SourceItem
	 */
	public static function create_source( Authentication $authentication ) {
		$cms_abbreviation = Application::getInformation()['cms']['abbreviation'] ?? 'Unknown_cms';
		$domain           = self::get_domain();
		$options          = array(
			'name'             => sprintf( Application::SOURCE_NAME_FORMAT, $cms_abbreviation, $domain ),
			'domain'           => $domain,
			'successUrl'       => self::get_redirect_url( 'success' ),
			'failureUrl'       => self::get_redirect_url( 'fail' ),
			'paramCancelOrder' => Application::PARAM_CANCEL_ORDER,
		);

		$source_item = self::create_source_item( $authentication, $cms_abbreviation, $options );
		return ! empty( $source_item ) ? $source_item : null;
	}

	/**
	 * Check source
	 *
	 * @param string         $source_code source_code.
	 * @param Authentication $authentication authentication.
	 *
	 * @return string
	 */
	public static function check_source( $source_code, Authentication $authentication ) {
		$domain          = self::get_domain();
		$source_client   = new SourceClient( $authentication );
		$source_response = $source_client->checkSource( $source_code );

		if ( $source_response->isSuccessful() ) {
			$state = $source_response->getBody()->getState();
			switch ( $state ) {
				case 0:
					return 'inactive';
				case 1: // Active.
					break;
				case 2:
					return 'pending';
				default:
					return 'unknownState';
			}
			if ( $source_response->getBody()->getDomain() !== $domain ) {
				return 'notValidDomain';
			}
			if (
				$source_response->getBody()->getSuccessUrl() !== self::get_redirect_url( 'success' )
				|| $source_response->getBody()->getFailureUrl() !== self::get_redirect_url( 'fail' )
			) {
				return 'notValidUrls';
			}
		} else {
			WC_Vivacom_Smart_Logger::log( "Check source API call failed \nARGS: " . $source_code . "\nRESULT: " . wp_json_encode( $source_response->all() ) );
			if ( 404 === $source_response->getHttpCode() ) {
				return 'noSourcesFound';
			}

			return 'error';
		}

		return 'active';
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
	 * Create Order
	 *
	 * @param string         $amount amount.
	 * @param string         $currency_code currencyCode.
	 * @param array          $options options.
	 * @param Authentication $authentication authentication.
	 *
	 * @return Response|null
	 */
	public static function create_order(
		$amount,
		$currency_code,
		$options = array(),
		$authentication = null
	) {
		if ( ! empty( $options ) ) {
			$order_client = new OrderClient( $authentication );
			if ( ! empty( $order_client ) ) {
				$order_response = $order_client->createOrder( $amount, $currency_code, $options );
			}
		}

		return $order_response ?? null;
	}

	/**
	 * Get Viva order
	 *
	 * @param Authentication $authentication authentication.
	 * @param array          $options options.
	 *
	 * @return Response|null
	 */
	public static function get_order( Authentication $authentication, $options = array() ) {
		$order_client   = new OrderClient( $authentication );
		$order_response = $order_client->retrieveOrder( $options );
		if ( $order_response->isSuccessful() && ! is_null( $order_response->getBody() ) ) {
			$response = $order_response->getBody();
		} else {
			WC_Vivacom_Smart_Logger::log( 'Order Retrieve failed for order code : ' . $options['orderCode'] );
		}

		return $response ?? null;
	}

	/**
	 * Get Domain
	 *
	 * @return array|false|mixed|null
	 */
	public static function get_domain() {

		$site_url = get_site_url();

		return wp_parse_url( $site_url, PHP_URL_HOST );
	}

	/**
	 * Get redirect url
	 *
	 * @param string $case case.
	 *
	 * @return string
	 */
	public static function get_redirect_url( $case ) {

		return 'wc-api/wc_vivacom_smart_' . $case;
	}

	/**
	 * Get order data
	 *
	 * @param object $order order.
	 *
	 * @return array
	 */
	public static function get_order_data( $order ) {

		$amount = $order->get_total();
		$amount = (int) number_format( $amount, 2, '', '' );

		$currency = $order->get_currency();

		$email      = $order->get_billing_email();
		$first_name = method_exists( $order, 'get_billing_first_name' ) ? $order->get_billing_first_name() : '';
		$last_name  = method_exists( $order, 'get_billing_last_name' ) ? $order->get_billing_last_name() : '';
		$name       = $first_name . ' ' . $last_name;
		$phone      = method_exists( $order, 'get_billing_phone' ) ? $order->get_billing_phone() : '';
		$phone      = preg_replace( '/[^0-9]/', '', $phone ); // clean up phone number..
		if ( strlen( $phone ) <= 1 ) {
			$phone = '0111111111';  // inject default value if empty or has a value of 1.
		}

		$locale = get_locale();
		if ( isset( $locale ) && strlen( $locale ) > 2 ) {
			$lang = substr( $locale, 0, 2 );
		} else {
			$lang = 'en'; // fallback to en if the lang is not properly defined in wp.
		}

		$shipping_country_code = $order->get_shipping_country();
		$billing_country_code  = $order->get_billing_country();
		$country_code          = '';
		if ( ! empty( $shipping_country_code ) ) {
			$country_code = $shipping_country_code;
		} elseif ( ! empty( $billing_country_code ) ) {
			$country_code = $billing_country_code;
		}

		$messages = self::get_trns_messages( $order );

		return array(
			'amount'      => $amount,
			'currency'    => $currency,
			'name'        => $name,
			'phone'       => $phone,
			'email'       => $email,
			'lang'        => $lang,
			'messages'    => $messages,
			'countryCode' => $country_code,
		);
	}

	/**
	 * Check subscription
	 *
	 * @param int $order_id order_id.
	 *
	 * @return bool
	 */
	public static function check_subscription( $order_id ) {

		return ( function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order_id ) )
			|| ( function_exists( 'wcs_is_subscription' ) && wcs_is_subscription( $order_id ) )
			|| ( function_exists( 'wcs_order_contains_renewal' ) && wcs_order_contains_renewal( $order_id ) );
	}

	/**
	 * Init form fields
	 *
	 * @return mixed|void
	 */
	public static function init_form_fields() {

		$vivacom_demo_url     = 'https://demo.vivapayments.com/';
		$vivacom_live_url     = 'https://www.vivapayments.com/';
		$vivacom_woo_docs_url = 'https://docs.woocommerce.com/document/viva-com-smart-for-woocommerce/';
		/* translators: credentials */
		$credentials_desc = sprintf( __( 'To find out how to retrieve your credentials for the payment gateway, please visit the Viva.com Smart Checkout %1$s installation guide %2$s', 'viva-com-smart-for-woocommerce' ), '<a target="_blank" href="' . esc_url( $vivacom_woo_docs_url ) . '">', '</a>' );
		/* translators: Demo Mode */
		$test_mode_desc = sprintf( __( 'If Demo Mode is enabled, please use the credentials you got from %1$s demo.vivapayments.com %2$s.', 'viva-com-smart-for-woocommerce' ), '<a target="_blank" href="' . esc_url( $vivacom_demo_url ) . '">', '</a>' );
		$main_desc      = __(
			'Set the title and description of the payment gateway. Title and description are visible to end users in the checkout page.',
			'viva-com-smart-for-woocommerce'
		);
		$settings       = array(
			'main_title'                => array(
				'title' => __( 'Viva.com Smart Checkout settings', 'viva-com-smart-for-woocommerce' ),
				'type'  => 'title',
			),
			'enabled'                   => array(
				'title'   => __( 'Enable Viva.com Smart Checkout', 'viva-com-smart-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Viva.com Smart Checkout', 'viva-com-smart-for-woocommerce' ),
				'default' => 'no',
			),
			'sep'                       => array(
				'title'       => '',
				'type'        => 'title',
				'description' => '<hr>',
			),
			'credentials'               => array(
				'title'       => __( 'Set Viva.com API credentials', 'viva-com-smart-for-woocommerce' ),
				'type'        => 'title',
				'description' => $credentials_desc,
			),
			'test_mode'                 => array(
				'title'       => __( 'Demo mode', 'viva-com-smart-for-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable demo mode', 'viva-com-smart-for-woocommerce' ),
				'description' => $test_mode_desc,
				'default'     => 'yes',
			),
			'title_live'                => array(
				'title' => __( 'Live mode credentials', 'viva-com-smart-for-woocommerce' ),
				'type'  => 'title',
			),
			'title_demo'                => array(
				'title' => __( 'Demo mode credentials', 'viva-com-smart-for-woocommerce' ),
				'type'  => 'title',
			),
			'client_id'                 => array(
				'title'       => __( 'Live Client ID', 'viva-com-smart-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Client ID provided by Viva.com.', 'viva-com-smart-for-woocommerce' ),
				'default'     => '',
			),
			'demo_client_id'            => array(
				'title'       => __( 'Demo Client ID', 'viva-com-smart-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Client ID provided by Viva.com. ', 'viva-com-smart-for-woocommerce' ),
				'default'     => '',
			),
			'client_secret'             => array(
				'title'       => __( 'Live Client Secret', 'viva-com-smart-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Client Secret provided by Viva.com.', 'viva-com-smart-for-woocommerce' ),
				'default'     => '',
			),
			'demo_client_secret'        => array(
				'title'       => __( 'Demo Client Secret', 'viva-com-smart-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Client Secret provided by Viva.com.', 'viva-com-smart-for-woocommerce' ),
				'default'     => '',
			),
			'sep_2'                     => array(
				'title'       => '',
				'type'        => 'title',
				'description' => '<hr>',
			),
			'advanced_settings_title'   => array(
				'title' => __( 'Advanced settings', 'viva-com-smart-for-woocommerce' ),
				'type'  => 'title',
			),
			'advanced_settings_enabled' => array(
				'title'   => __( 'Show advanced settings', 'viva-com-smart-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Show advanced settings. If this checkbox is unchecked, the plugin will use default settings.', 'viva-com-smart-for-woocommerce' ),
				'default' => 'no',
			),
			'sep_3'                     => array(
				'title'       => '',
				'type'        => 'title',
				'description' => '<hr>',
			),
			'main_descr'                => array(
				'title' => $main_desc,
				'type'  => 'title',
			),
			'title'                     => array(
				'title'       => __( 'Title', 'viva-com-smart-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'This controls the title that the user sees during checkout.', 'viva-com-smart-for-woocommerce' ),
				'default'     => __( 'Viva.com Smart Checkout', 'viva-com-smart-for-woocommerce' ),
			),
			'description'               => array(
				'title'       => __( 'Description', 'viva-com-smart-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'This controls the description that the user sees during checkout.', 'viva-com-smart-for-woocommerce' ),
				'default'     => __( 'Pay using 30+ methods (cards, digital wallets, local payment methods, online banking, and more)', 'viva-com-smart-for-woocommerce' ),
			),
			'installments'              => array(
				'title'       => __( 'Instalments', 'viva-com-smart-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'WARNING: Only available to Greek Viva.com accounts. <br>Example: 90:3,180:6<br>Order total 90 euro -> allow 0 and 3 installments <br>Order total 180 euro -> allow 0, 3 and 6 installments<br>Leave empty in case you do not want to offer installments.', 'viva-com-smart-for-woocommerce' ),
				'default'     => '',
			),
            'brand_color'              => array(
                'title'       => __( 'Brand color', 'viva-com-smart-for-woocommerce' ),
                'type'        => 'text',
                'description' => __('Select a color or enter a HEX code.', 'viva-com-smart-for-woocommerce'),
                'default'     => '',
            ),
			'source_code'               => array(
				'title'       => __( 'Live Source Code List', 'viva-com-smart-for-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Provides a list with all source codes that are set in your Viva.com banking app.', 'viva-com-smart-for-woocommerce' ),
				'default'     => '',
				'options'     => array(),
			),
			'demo_source_code'          => array(
				'title'       => __( 'Demo Source Code List', 'viva-com-smart-for-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Provides a list with all source codes that are set in your Viva.com banking app.', 'viva-com-smart-for-woocommerce' ),
				'default'     => '',
				'options'     => array(),
			),
			'logo_enabled'              => array(
				'title'   => __( 'Show logo on checkout page', 'viva-com-smart-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Viva.com logo on checkout page (default = yes).', 'viva-com-smart-for-woocommerce' ),
				'default' => 'yes',
			),
			'order_status'              => array(
				'title'       => __( 'Order status after successful payment.', 'viva-com-smart-for-woocommerce' ),
				'description' => __( 'Your WooCommerce will be updated to this status after successful payment on Viva.com (default = completed).', 'viva-com-smart-for-woocommerce' ),
				'default'     => 'completed',
				'type'        => 'select',
				'options'     => array(
					'completed'  => __( 'Completed', 'viva-com-smart-for-woocommerce' ),
					'processing' => __( 'Processing', 'viva-com-smart-for-woocommerce' ),
				),
			),
		);

		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '6.5.0', '>' ) ) {
			$settings['enable_preauthorizations'] = array(
				'title'       => __( 'Preauthorized Payments', 'viva-com-smart-for-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable Preauthorized Payments', 'viva-com-smart-for-woocommerce' ),
				'description' => 'By enabling this you will create order in preauthorized status',
				'default'     => 'no',
			);
		}

		/**
		 * Settings filter apply
		 *
		 * @since 1.0.0
		 */
		return apply_filters(
			'wc_vivacom_smart_settings',
			$settings
		);
	}

	/**
	 * Get_trns_messages.
	 *
	 * @param WC_Order|object $order Order.
	 *
	 * @return array
	 */
	private static function get_trns_messages( $order ) {
		$domain = self::get_domain();

		$customer_message = get_bloginfo( 'name' );
		$merchant_message = 'WooCommerce - ' . $order->get_order_number();

		return array(
			'merchant_message' => $merchant_message,
			'customer_message' => $customer_message,
		);
	}

	/**
	 * Check_if_instalments checks if instalments are allowed (only for greek stores).
	 *
	 * @return boolean Result.
	 */
	public static function check_if_instalments() {
		$wc_country = WC_Admin_Settings::get_option( 'woocommerce_default_country' );

		if ( isset( $wc_country ) && ! empty( $wc_country ) ) {
			$wc_country = substr( $wc_country, 0, 2 );
			if ( 'GR' === $wc_country ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get max installments
	 *
	 * @param string $installments_pattern installments_pattern.
	 * @param string $amount amount.
	 *
	 * @return int|mixed
	 */
	public static function get_max_installments( $installments_pattern, $amount ) {
		$maxinstallments = 1;

		$split_instal_vivacom = explode( ',', $installments_pattern );
		$c                    = count( $split_instal_vivacom );
		$instal_vivacom_max   = array();
		for ( $i = 0; $i < $c; $i++ ) {
			list( $instal_amount, $instal_term ) = explode( ':', $split_instal_vivacom[ $i ] );
			$instal_amount                       = (int) number_format( $instal_amount, 2, '', '' );
			if ( $amount >= $instal_amount ) {
				$instal_vivacom_max[] = trim( $instal_term );
			}
		}
		if ( count( $instal_vivacom_max ) > 0 ) {
			$maxinstallments = max( $instal_vivacom_max );
		}

		return $maxinstallments;
	}
}
