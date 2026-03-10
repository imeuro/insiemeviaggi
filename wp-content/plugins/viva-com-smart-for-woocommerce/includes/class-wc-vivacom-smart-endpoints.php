<?php
/**
 * Endpoints
 *
 * @package   VivaComSmartForWooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

if ( ! class_exists( 'WC_Vivacom_Smart_Endpoints' ) ) {
	/**
	 * Class WC_Vivacom_Smart_Endpoints
	 */
	class WC_Vivacom_Smart_Endpoints {

		/**
		 * WC_Vivacom_Smart_Endpoints constructor
		 */
		public function __construct() {
			add_action( 'rest_api_init', array( $this, 'create_payments_methods_endpoint' ) );
			add_action( 'woocommerce_api_wc_vivacom_smart_success', array( $this, 'check_hook_response_success' ) );
			add_action( 'woocommerce_api_wc_vivacom_smart_fail', array( $this, 'check_hook_response_fail' ) );
			add_action( 'woocommerce_thankyou_vivacom_smart', array( $this, 'notify_pending_payment' ) );
		}

		/**
		 * Notify pending payment
		 *
		 * @return void
		 */
		public function notify_pending_payment() {
			echo '<p class="vivacom-smart-notice-pending-payment">' . esc_html__( 'Order is currently awaiting payment. After successful payment, we will send you an email confirmation.', 'viva-com-smart-for-woocommerce' ) . '</p>';
		}
		/**
		 * Create payment webhook
		 */
		public function create_payments_methods_endpoint() {
			register_rest_route(
				WC_Vivacom_Smart_Helpers::WEBHOOK_NAMESPACE,
				WC_Vivacom_Smart_Helpers::WEBHOOK_URI,
				array(
					array(
						'methods'             => 'GET',
						'callback'            => 'WC_Vivacom_Smart_Endpoints::payments_methods_endpoint_get_callback',
						'permission_callback' => '__return_true',
					),
					array(
						'methods'             => 'POST',
						'callback'            => 'WC_Vivacom_Smart_Endpoints::payments_methods_endpoint_post_callback',
						'permission_callback' => '__return_true',
					),
				)
			);
		}

		/**
		 * Get callback
		 *
		 * @param object $request request.
		 *
		 * @return mixed|WP_Error|WP_HTTP_Response|WP_REST_Response
		 */
		public static function payments_methods_endpoint_get_callback( $request ) {

			$response_key = WC_Vivacom_Smart_Helpers::get_verification_token();
			$data         = array( 'key' => $response_key );
			WC_Vivacom_Smart_Logger::log( 'Payments methods endpoint get callback\n Key: ' . wp_json_encode( $response_key ) );

			return rest_ensure_response( new WP_REST_Response( $data ) );
		}

		/**
		 * Post callback
		 *
		 * @param object $request request.
		 *
		 * @return WP_REST_Response
		 */
		public static function payments_methods_endpoint_post_callback( $request ) {

			$parameters = json_decode( $request->get_body(), true, 512, JSON_BIGINT_AS_STRING );
			$res        = array( 'status_message' => 'Success' );

			if (
				empty( $parameters['EventData']['TransactionId'] )
				|| empty( $parameters['EventData']['OrderCode'] )
				|| is_null( $parameters['EventData']['TransactionTypeId'] )
			) {
				return new WP_REST_Response( $res, 200 );
			}

			$order_code  = (string) $parameters['EventData']['OrderCode'];
			$transaction = array(
				'id'     => (string) $parameters['EventData']['TransactionId'],
				'typeId' => (int) $parameters['EventData']['TransactionTypeId'],
			);

			global $wpdb;

			$wc_order_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT woocommerce_order_id FROM {$wpdb->prefix}viva_com_smart_wc_checkout_orders WHERE vivacom_order_code = %s ORDER BY date_add LIMIT 1",
					$order_code
				)
			);

			if ( ! empty( $wc_order_id ) ) {
				$order = wc_get_order( $wc_order_id );
				if ( empty( $order ) ) {
					return new WP_REST_Response( $res, 200 );
				} elseif ( $order->get_status() !== 'pending' ) {
					return new WP_REST_Response( $res, 200 );
				}
			} else {
				return new WP_REST_Response( $res, 200 );
			}

			WC_Vivacom_Smart_Logger::log( "Payments methods endpoint post callback\n Smart checkout\n Request: " . wp_json_encode( $parameters ) );

			if ( isset( $parameters['EventTypeId'] ) && 1796 === $parameters['EventTypeId'] ) {
				$viva_settings         = get_option( 'woocommerce_vivacom_smart_settings' );
				$environment           = 'yes' === $viva_settings['test_mode'] ? 'demo' : 'live';
				$bearer_authentication = WC_Vivacom_Smart_Helpers::get_bearer_authentication( $environment );
				$transaction_response  = WC_Vivacom_Smart_Helpers::get_transaction( $bearer_authentication, $transaction['id'] );

				if ( ! empty( $transaction_response ) && ! empty( $transaction_response->orderCode ) ) {
					// CHECK TRANSACTION VALID AND UPDATE.
					$transaction_order = (string) $transaction_response->orderCode;

					if ( $transaction_order === $order_code ) {
						if ( 'F' === $transaction_response->statusId ) {
							$date      = new DateTime( $parameters['EventData']['CardExpirationDate'] );
							$token_data = array(
								'lastFourDigits' => ( isset( $parameters['EventData']['CardNumber'] ) && strlen( $parameters['EventData']['CardNumber'] ) > 4 ) ? substr( (string) $parameters['EventData']['CardNumber'], -4 ) : 'XXXX',
								'cardType'       => (string) $parameters['EventData']['BankId'],
								'expiryMonth'    => $date->format( 'm' ) ?? null,
								'expiryYear'     => $date->format( 'Y' ) ?? null,
							);
							WC_Vivacom_Smart_Helpers::complete_order( $order->get_id(), $transaction, '', false );

							// Check and save card token if subscription.
							if ( WC_Vivacom_Smart_Helpers::check_subscription( $order->get_id() ) && $order->get_user() ) {
								WC_Vivacom_Smart_Helpers::save_payment_token( $transaction['id'], $order, $token_data );
							}
						}
					}
				} else {
					// WEBHOOK WILL BE RESEND.
					return new WP_REST_Response( $res, 400 );
				}
			} else {
				$note = __( 'Transaction failed using Viva.com Smart Checkout', 'viva-com-smart-for-woocommerce' );

				if ( isset( $parameters['CorrelationId'] ) && ! empty( $parameters['CorrelationId'] ) ) {
					$note .= __( 'with Viva-CorrelationId: ', 'viva-com-smart-for-woocommerce' ) . $parameters['CorrelationId'];

				} else {
					$note .= '.';
				}

				$order->add_order_note( $note, false );
				$order->save();
			}

			return new WP_REST_Response( $res, 200 );
		}

		/**
		 * Success handler
		 *
		 * @return void
		 */
		public function check_hook_response_success() {

			if ( empty( $_GET['t'] ) || empty( $_GET['s'] ) ) {
				wp_safe_redirect( esc_url_raw( ( wc_get_checkout_url() ) ) );
			}

			$viva_ref = sanitize_text_field( wp_unslash( $_GET['s'] ) );

			global $wpdb;

			$wc_order_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT woocommerce_order_id FROM {$wpdb->prefix}viva_com_smart_wc_checkout_orders WHERE vivacom_order_code = %s ORDER BY date_add DESC LIMIT 1",
					$viva_ref
				)
			);

			if ( ! empty( $wc_order_id ) ) {
				$order = wc_get_order( $wc_order_id );

				if ( in_array( $order->get_status(), array( 'pending', 'processing', 'completed', 'on-hold' ), true ) ) {
					wp_safe_redirect( esc_url_raw( ( $order->get_checkout_order_received_url() ) ) );
				} else {
					wp_safe_redirect( esc_url_raw( ( wc_get_checkout_url() ) ) );
				}
			} else {
				wp_safe_redirect( esc_url_raw( ( wc_get_checkout_url() ) ) );
			}
		}

		/**
		 * Fail handler
		 *
		 * @return void
		 */
		public function check_hook_response_fail() {

			if ( empty( $_GET['s'] ) ) {
				wc_add_notice( __( 'There was a problem processing your payment. Please try again', 'viva-com-smart-for-woocommerce' ), 'error' );
				wp_safe_redirect( esc_url_raw( ( wc_get_checkout_url() ) ) );
				exit();
			}

			$viva_ref  = sanitize_text_field( wp_unslash( $_GET['s'] ) );
			$cancelled = isset( $_GET['cancel'] ) && 1 == $_GET['cancel'];
			global $wpdb;

			$wc_order_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT woocommerce_order_id FROM {$wpdb->prefix}viva_com_smart_wc_checkout_orders WHERE vivacom_order_code = %s ORDER BY date_add DESC LIMIT 1",
					$viva_ref
				)
			);

			if ( empty( $wc_order_id ) ) {

				wc_add_notice( __( 'There was a problem processing your payment. Please try again', 'viva-com-smart-for-woocommerce' ), 'error' );
				wp_safe_redirect( esc_url_raw( ( wc_get_checkout_url() ) ) );
				exit();

			}

			$order = wc_get_order( $wc_order_id );

			if ( $cancelled ) {
				$viva_settings         = get_option( 'woocommerce_vivacom_smart_settings' );
				$environment           = 'yes' === $viva_settings['test_mode'] ? 'demo' : 'live';
				$bearer_authentication = WC_Vivacom_Smart_Helpers::get_bearer_authentication( $environment );
				$order_response        = WC_Vivacom_Smart_Helpers::get_order( $bearer_authentication, array( 'orderCode' => $viva_ref ) );
				$state_id               = $order_response->stateId;
			}

			if ( isset( $state_id ) && 2 == $state_id ) {
				$order->update_status( 'cancelled', __( 'Unpaid order cancelled - customer cancelled in smart checkout.', 'viva-com-smart-for-woocommerce' ) );
				wc_add_notice( __( 'The Order was cancelled', 'viva-com-smart-for-woocommerce' ), 'error' );
				wp_safe_redirect( esc_url_raw( ( wc_get_checkout_url() ) ) );
				exit();
			}

			wc_add_notice( __( 'There was a problem processing your payment. Please try again', 'viva-com-smart-for-woocommerce' ), 'error' );
			wp_safe_redirect( esc_url_raw( ( wc_get_checkout_url() ) ) );
			exit();
		}
	}

	new WC_Vivacom_Smart_Endpoints();
}
