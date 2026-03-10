<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}


if ( ! class_exists( 'WC_Vivawallet_Endpoints' ) ) {
	/**
	 * Class WC_Vivawallet_Endpoints
	 *
	 * @class   WC_Vivawallet_Endpoints
	 * @package WooCommerce/WC_Vivawallet_Endpoints
	 */
	class WC_Vivawallet_Endpoints {

		/**
		 * Construct.
		 */
		public function __construct() {
			add_action( 'wc_ajax_wc_vivawallet_get_cart_total_amount', array( $this, 'ajax_get_cart_total_amount' ) );

			add_action( 'set_logged_in_cookie', array( $this, 'update_logged_in_cookie' ) );

			add_action( 'wc_ajax_wc_vivawallet_process_payment', array( $this, 'ajax_process_payment' ) );

			add_action( 'wc_ajax_wc_vivawallet_add_payment_method', array( $this, 'ajax_add_payment_method' ) );

			add_action( 'rest_api_init', array( $this, 'create_payments_methods_endpoint' ) );

			add_action( 'woocommerce_api_wc_vivawallet_native_success', array( $this, 'check_hook_response_success' ) );
			add_action( 'woocommerce_api_wc_vivawallet_native_fail', array( $this, 'check_hook_response_fail' ) );

		}


		/**
		 * Ajax get cart total amount.
		 */
		public function ajax_get_cart_total_amount() {
			check_ajax_referer( 'wc-vivawallet-checkout-amount', 'security' );

			if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
				define( 'WOOCOMMERCE_CART', true );
			}
			$total = WC()->cart->total;
			wp_send_json( $total );
		}


		/**
		 * Update_logged_in_cookie is a hook when a user is getting logged in when in checkout
		 * by creating a new user account. We need to update the $_COOKIE session var in order
		 * for the new nonces to authenticate successfully.
		 *
		 * @param string $logged_in_cookie The logged-in cookie value.
		 */
		public static function update_logged_in_cookie( $logged_in_cookie ) {
			if ( ! is_checkout() ) {
				return;
			}
			$_COOKIE[ LOGGED_IN_COOKIE ] = $logged_in_cookie;
		}



		/**
		 * AJAX process payment.
		 *
		 * @throws WC_Data_Exception If error.
		 */
		public function ajax_process_payment() {

			WC()->session->__unset( 'VW_Error' );

			check_ajax_referer( 'wc-vivawallet-process-payment', 'security' );

			// check posted values.
			if ( ! isset( $_POST['orderId'] ) || ! isset( $_POST['returnUrl'] ) ) {
				$data = array(
					'result'   => 'failure',
					'messages' => __( 'There was a connection problem. Please try again later.', 'viva-wallet-for-woocommerce' ),
				);
				wp_send_json( $data );
				return;
			}

			if ( ! isset( $_POST['accessToken'] ) || ! isset( $_POST['orderId'] ) ) {
				$data = array(
					'result'   => 'failure',
					/* translators: Please check your card details or try again later! (card details bolded) */
					'messages' => __( 'Please check your <strong>card details</strong> or try again later!', 'viva-wallet-for-woocommerce' ),
				);
				wp_send_json( $data );
				return;
			}

			$access_token = sanitize_text_field( wp_unslash( $_POST['accessToken'] ) );

			$charge_token      = '';
			$payment_method_id = null;
			if ( empty( $_POST['paymentMethodId'] ) ) { // if no APM then check card details.
				if ( ! isset( $_POST['chargeToken'] ) || ! isset( $_POST['installments'] ) ) {
					$data = array(
						'result'   => 'failure',
						'messages' => __( 'Please check your <strong>card details</strong> or try again later!', 'viva-wallet-for-woocommerce' ),
					);
					wp_send_json( $data );
					return;
				}

				$charge_token = sanitize_text_field( wp_unslash( $_POST['chargeToken'] ) );
			} else {
				$payment_method_id = sanitize_text_field( wp_unslash( $_POST['paymentMethodId'] ) );
			}

			// prepare values.

			$order_id = sanitize_text_field( wp_unslash( $_POST['orderId'] ) );
			$order    = wc_get_order( $order_id );

			// check if order has already a trans ID and fail if trans id exists.
			// check both the trans id set in order but also the vivawallet meta trans_id.

			$post_meta_trans_id = get_post_meta( $order_id, WC_Vivawallet_Helper::POST_META_VW_TXN );

			if ( ! empty( $order->get_transaction_id() ) || ! empty( $post_meta_trans_id ) ) {
				$data = array(
					'result'   => 'failure',
					'messages' => __( 'Sorry we could not process your payment! Please try again later.', 'viva-wallet-for-woocommerce' ),
				);
				wp_send_json( $data );
				return;
			}

			$this->viva_settings = get_option( 'woocommerce_vivawallet_native_settings', array() );

			$test_mode = ( ! empty( $this->viva_settings['test_mode'] ) && 'yes' === $this->viva_settings['test_mode'] ) ? 'yes' : 'no';

			$source_code = ( 'yes' === $test_mode ) ? $this->viva_settings['test_source_code'] : $this->viva_settings['source_code'];

			$save_payment_token = false;

			if ( WC_Vivawallet_Helper::check_if_subscriptions( $order_id ) ) {
				// check if we have subscription in the order.
				$save_payment_token = true;
			}

			if ( isset( $_POST['savePaymentMethod'] ) && 'true' === $_POST['savePaymentMethod'] ) {
				$save_payment_token = true;
			}

			$installments_post_val = sanitize_text_field( wp_unslash( $_POST['installments'] ) );

			if ( ! WC_Vivawallet_Helper::check_if_instalments() ) { // reset installments (if not allowed).
				$installments_post_val = '1';
			}

			if ( 1 < $installments_post_val ) {
				$note  = __( 'WARNING: This order was paid with installments!', 'viva-wallet-for-woocommerce' );
				$note .= ' ';
				$note .= __( 'Number of installments: ', 'viva-wallet-for-woocommerce' ) . $installments_post_val;
				$order->add_order_note( $note, false );
			}

			if ( null !== $payment_method_id ) {  // for APMS.
				// Check if email and name is set and send an error message if not.

				$method_info = WC_Vivawallet_Helper::get_payment_method_by_id( $payment_method_id );

				$order->set_payment_method( $method_info['method_key'] );
				$order->set_payment_method_title( $method_info['method_title'] . ' via Viva Wallet.' );
				$order->save();

			} else { // for standard checkout.

				$order->set_payment_method( 'vivawallet_native' );
				$order->set_payment_method_title( $this->viva_settings['title'] );
				$order->save();
			}

			$data = WC_Vivawallet_Helper::prepare_transaction_data_from_order( $order );

			$post_args = array(
				'amount'          => $data['amount'],
				'preauth'         => false,
				'sourceCode'      => $source_code,
				'chargeToken'     => $charge_token,
				'installments'    => $installments_post_val,
				'merchantTrns'    => $data['messages']['merchant_message'],
				'customerTrns'    => $data['messages']['customer_message'],
				'currencyCode'    => $data['currency'],
				'allowsRecurring' => $save_payment_token,
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

			$status = __( 'Order created via Viva Wallet Standard Checkout.', 'viva-wallet-for-woocommerce' );

			$order->add_order_note( $status, false );

			if ( null !== $payment_method_id ) {  // for APMS.
				// test name that is not empty . remove spaces and empty chars first.

				$temp_name = preg_replace( '/\s+/', '', $post_args['customer']['fullname'] );

				if ( empty( $post_args['customer']['email'] ) && empty( $temp_name ) ) {
					$data = array(
						'result'   => 'failure',
						'messages' => __( 'Sorry we could not process your payment because customer name and email was not set! Please try again later.', 'viva-wallet-for-woocommerce' ),
					);
					wp_send_json( $data );
					return;
				}

				if ( empty( $temp_name ) ) {
					$data = array(
						'result'   => 'failure',
						'messages' => __( 'Sorry we could not process your payment because no customer name was set! Please try again later.', 'viva-wallet-for-woocommerce' ),
					);
					wp_send_json( $data );
					return;
				}

				if ( empty( $post_args['customer']['email'] ) ) {
					$data = array(
						'result'   => 'failure',
						'messages' => __( 'Sorry we could not process your payment because no customer email was set! Please try again later.', 'viva-wallet-for-woocommerce' ),
					);
					wp_send_json( $data );
					return;
				}

				$payment_method_info = WC_Vivawallet_Helper::get_payment_method_by_id( $payment_method_id );

				$status  = __( 'Gateway selected:', 'viva-wallet-for-woocommerce' );
				$status .= $payment_method_info['method_title'];
				$status .= __( ' via Viva Wallet Standard Checkout', 'viva-wallet-for-woocommerce' );

				$order->add_order_note( $status, false );
				$order->save();

				$post_args['paymentMethodId'] = $payment_method_id;

			} else { // for standard checkout.

				$status  = __( 'Gateway selected:', 'viva-wallet-for-woocommerce' );
				$status .= __( 'Viva Wallet Standard Checkout.', 'viva-wallet-for-woocommerce' );

				$order->add_order_note( $status, false );
				$order->save();
			}

			$result = WC_Vivawallet_Helper::transaction_api_call( $post_args, $access_token, $test_mode );

			if ( ! is_wp_error( $result ) && isset( $result['response']['code'] ) && 200 === $result['response']['code'] ) {

				$result_body    = json_decode( $result['body'] );
				$transaction_id = $result_body->transactionId; // phpcs:ignore

				if ( $save_payment_token && ! empty( $_POST['cardType'] ) && ! empty( $_POST['cardNumberLast4'] ) && ! empty( $_POST['expiryMonth'] ) && ! empty( $_POST['expiryYear'] ) && is_user_logged_in() ) {

					// process save token for future transactions.
					$card_type      = wc_clean( wp_unslash( $_POST['cardType'] ) );
					$card_last_four = wc_clean( wp_unslash( $_POST['cardNumberLast4'] ) );
					$expiry_month   = wc_clean( wp_unslash( $_POST['expiryMonth'] ) );
					$expiry_year    = wc_clean( wp_unslash( $_POST['expiryYear'] ) );

					WC_Vivawallet_Helper::save_card_token( $transaction_id, $card_type, $card_last_four, $expiry_month, $expiry_year, $order );

				}

				if ( null === $payment_method_id ) { // for normal checkout (not APM).

					$status  = __( 'Order has been paid with Viva Wallet Standard Checkout, TxID: ', 'viva-wallet-for-woocommerce' );
					$status .= $transaction_id;

					WC_Vivawallet_Helper::complete_order( $order_id, $transaction_id, $status );

					$data = array(
						'result'   => 'success',
						'redirect' => sanitize_text_field( wp_unslash( $_POST['returnUrl'] ) ),
					);

				} else {

					add_post_meta( $order_id, WC_Vivawallet_Helper::POST_META_VW_APM_TXN, $transaction_id );

					$redirect_url = $result_body->redirectUrl; // phpcs:ignore

					$data = array(
						'result'   => 'success',
						'redirect' => $redirect_url,
					);

				}

				wp_send_json( $data );
				return;
			}

			// transaction call to API failed.

			$data = array(
				'result' => 'failure',
			);

			if ( isset( $result['headers']['x-viva-correlationid'] ) ) {
				if ( ! isset( $result_body ) && isset( $result['body'] ) ) {
					$result_body = json_decode( $result['body'] );
				}
				$notes = array_filter(
					array(
						'Transaction failed with Viva-CorrelationId: ' . $result['headers']['x-viva-correlationid'],
						isset( $result['response']['message'] ) ? 'General message: ' . $result['response']['message'] . ' (' . $result['response']['code'] . ')' : '',
						isset( $result_body->message ) ? 'Specific message: ' . $result_body->message : '',
					)
				);
				$order->add_order_note( implode( '<br/>', $notes ), false );
				$order->save();
			}
			if ( isset( $result['headers']['x-viva-eventid'] ) ) {
				$int = (int) $result['headers']['x-viva-eventid'];
				if ( 10000 < $int ) {
					$data['messages'] = __( 'The card issuer rejected this transaction. Please use a different card.', 'viva-wallet-for-woocommerce' );
				} else {
					$data['messages'] = __( 'There was a problem with your card. Please check the card details and try again.', 'viva-wallet-for-woocommerce' );
				}
			} else {
				$data['messages'] = __( 'There was a connection problem. Please try again later.', 'viva-wallet-for-woocommerce' );
			}

			// check if user has logged in during checkout and reload to get correct session again.
			if ( is_user_logged_in() && isset( $_POST['isUserLoggedIn'] ) && '' === $_POST['isUserLoggedIn'] ) {
				// user was not logged when checkout process started.. but is logged now.
				$data['reload'] = true;
				WC()->session->set( 'VW_Error', $data['messages'] );
			}

			wp_send_json( $data );

		}




		/**
		 * Ajax add payment method.
		 */
		public function ajax_add_payment_method() {

			check_ajax_referer( 'wc-vivawallet-add-payment-method', 'security' );

			if ( ! isset( $_POST['accessToken'] ) || ! isset( $_POST['chargeToken'] ) ) {
				$data = array(
					'result'   => 'failure',
					/* translators: Please check your card details or try again later! */
					'messages' => sprintf( __( 'Please check your %s or try again later!', 'viva-wallet-for-woocommerce' ), '<strong>' . __( 'card details', 'viva-wallet-for-woocommerce' ) . '</strong>' ),
				);
				wp_send_json( $data );
				return;
			}

			if ( ! isset( $_POST['cardType'] ) || ! isset( $_POST['cardNumberLast4'] ) || ! isset( $_POST['expiryMonth'] ) || ! isset( $_POST['expiryYear'] ) ) {

				$data = array(
					'result'   => 'failure',
					'messages' => __( 'Something went wrong trying to get token for your card! Please try again.', 'viva-wallet-for-woocommerce' ),
				);

				wp_send_json( $data );
				return;
			}

			if ( ! is_user_logged_in() ) {
				$data = array(
					'result'   => 'failure',
					'messages' => __( 'You must be logged in to add payment methods.', 'viva-wallet-for-woocommerce' ),
				);
				wp_send_json( $data );
				return;
			}

			$access_token = sanitize_text_field( wp_unslash( $_POST['accessToken'] ) );
			$charge_token = sanitize_text_field( wp_unslash( $_POST['chargeToken'] ) );

			$viva_settings  = get_option( 'woocommerce_vivawallet_native_settings', array() );
			$test_mode      = ( ! empty( $viva_settings['test_mode'] ) && 'yes' === $viva_settings['test_mode'] ) ? 'yes' : 'no';
			$source_code    = ( 'yes' === $test_mode ) ? $viva_settings['test_source_code'] : $viva_settings['source_code'];
			$card_type      = wc_clean( wp_unslash( $_POST['cardType'] ) );
			$card_last_four = wc_clean( wp_unslash( $_POST['cardNumberLast4'] ) );
			$expiry_month   = wc_clean( wp_unslash( $_POST['expiryMonth'] ) );
			$expiry_year    = wc_clean( wp_unslash( $_POST['expiryYear'] ) );

			$currency = get_woocommerce_currency();
			$currency = WC_Vivawallet_Helper::get_currency_symbol( $currency );

			$amount = WC_Vivawallet_Helper::get_minimum_charge_amount( $currency );

			$user  = wp_get_current_user();
			$name  = $user->first_name . ' ' . $user->last_name;
			$email = $user->user_email;

			$ln = get_locale();
			if ( isset( $ln ) && strlen( $ln ) > 2 ) {
				$ln = substr( $ln, 0, 2 );
			} else {
				$ln = 'en'; // fallback to en if the lang is not properly defined in wp.
			}

			$payment_title = $viva_settings['title'];

			$site_url = get_site_url();

			$domain           = wp_parse_url( $site_url, PHP_URL_HOST );
			$merchant_message = $domain . ' - ' . $payment_title . ' - ' . __( 'Adding Payment Source', 'viva-wallet-for-woocommerce' );
			$customer_message = $domain . ' - ' . $payment_title . ' - ' . __( 'Adding Payment Source', 'viva-wallet-for-woocommerce' );

			$messages = array(
				'merchant_message' => $merchant_message,
				'customer_message' => $customer_message,
			);

			$country_code = WC()->countries->get_base_country();

			WC_Vivawallet_Logger::log( "CREATING TRANSACTION TO ADD PAYMENT METHOD FOR USER \n User Name: " . $name . "\n User Email: " . $email . "\n User Login: " . $user->user_login . "\n User Id: " . $user->ID );

			$post_args = array(
				'amount'          => $amount,
				'preauth'         => false,
				'sourceCode'      => $source_code,
				'chargeToken'     => $charge_token,
				'installments'    => 1,
				'merchantTrns'    => $messages['merchant_message'],
				'customerTrns'    => $messages['customer_message'],
				'currencyCode'    => $currency,
				'allowsRecurring' => true,
				'customer'        => array(
					'email'       => $email,
					'phone'       => '0111111111',
					'fullname'    => $name,
					'requestLang' => $ln,
				),
			);
			if ( ! empty( $country_code ) ) {
				$post_args['customer']['countryCode'] = $country_code;
			}

			$result_transaction = WC_Vivawallet_Helper::transaction_api_call( $post_args, $access_token, $test_mode );

			if ( ! is_wp_error( $result_transaction ) && isset( $result_transaction['response']['code'] ) && 200 === $result_transaction['response']['code'] ) {

				$transaction_id = json_decode( $result_transaction['body'] );
				$transaction_id = $transaction_id->transactionId; // phpcs:ignore

				$result_transaction = true;

				$back_end_access_token = WC_Vivawallet_Credentials::get_authentication_token( $test_mode );

				WC_Vivawallet_Logger::log( 'CREATING REFUND TRANSACTION FOR USER: ' . $name );

				$result_refund = WC_Vivawallet_Refund::refund_api_call( $transaction_id, $amount, $source_code, $back_end_access_token, $test_mode );

				if ( ! is_wp_error( $result_refund ) && isset( $result_refund['response']['code'] ) && 200 === $result_refund['response']['code'] ) {
					$result_refund = true;
				}
			}

			if ( ! empty( $_POST['relatedSubscription'] ) && 'false' !== sanitize_text_field( wp_unslash( $_POST['relatedSubscription'] ) ) ) {
				$subscription_id = sanitize_text_field( wp_unslash( $_POST['relatedSubscription'] ) );
				$subscription    = wcs_get_subscription( $subscription_id );
				$related_order   = $subscription->get_parent();
				$returning_url   = wp_unslash( wc_get_account_endpoint_url( 'view-subscription/' . $subscription_id . '/' ) );
			} else {
				$related_order = null;
				$returning_url = wp_unslash( wc_get_account_endpoint_url( 'payment-methods' ) );
			}

			if ( true === $result_transaction ) {
				// if initial transaction passed save the charge token.
				WC_Vivawallet_Helper::save_card_token( $transaction_id, $card_type, $card_last_four, $expiry_month, $expiry_year, $related_order );

				// if customer has chosen to update all subscriptions.. update them.
				if ( isset( $_POST['updateAllSubscriptionsPayment'] ) && 'true' === $_POST['updateAllSubscriptionsPayment'] ) {

					$user_id       = $related_order->get_user_id();
					$subscriptions = wcs_get_users_subscriptions( $user_id );

					foreach ( $subscriptions as $subscription ) {
						$related_order = $subscription->get_parent();
						WC_Vivawallet_Helper::save_card_token( $transaction_id, $card_type, $card_last_four, $expiry_month, $expiry_year, $related_order );
					}
				}
			}

			if ( true === $result_transaction && ! empty( $result_refund ) && true === $result_refund ) {
				// all process succeded.
				$data = array(
					'result'   => 'success',
					'redirect' => $returning_url,
				);
				wp_send_json( $data );
				return;
			}

			if ( true === $result_transaction && ( empty( $result_refund ) || true !== $result_refund ) ) {
				// payment succesful but refund failed.
				$data = array(
					'result'   => 'failure',
					'messages' => __( 'Something went wrong trying to get token for your card! The charge was successful, but the automatic refund failed. Please contact the store owner and ask to refund you manually. ', 'viva-wallet-for-woocommerce' ),
				);
			}

			if ( true !== $result_transaction ) {
				// no successful payment.
				$data = array(
					'result'   => 'failure',
					'messages' => __( 'Something went wrong trying to get token for your card! Please try again.', 'viva-wallet-for-woocommerce' ),
				);
			}

			wp_send_json( $data );

		}


		/**
		 * Create Payments methods webhoook.
		 */
		public function create_payments_methods_endpoint() {

			register_rest_route(
				WC_Vivawallet_Helper::WEBHOOK_NAMESPACE,
				WC_Vivawallet_Helper::WEBHOOK_URI,
				array(
					array(
						'methods'             => 'GET',
						'callback'            => 'WC_Vivawallet_Endpoints::payments_methods_endpoint_get_callback',
						'permission_callback' => '__return_true',
					),
					array(
						'methods'             => 'POST',
						'callback'            => 'WC_Vivawallet_Endpoints::payments_methods_endpoint_post_callback',
						'permission_callback' => '__return_true',
					),
				)
			);
		}

		/**
		 * GET alternative payments method endpoint
		 *
		 * @param array $request request data.
		 *
		 * @return WP_Error|WP_REST_Response|WP_HTTP_Response
		 */
		public static function payments_methods_endpoint_get_callback( $request ) {

			$key  = WC_Vivawallet_Helper::get_alternative_payments_methods_key();
			$data = array( 'key' => $key );

			WC_Vivawallet_Logger::log( "PAYMENTS_METHODS_ENDPOINT_GET_CALLBACK\n KEY: " . wp_json_encode( $key ) );

			return rest_ensure_response( new WP_REST_Response( $data ) );
		}

		/**
		 * POST alternative payments method endpoint
		 *
		 * @param array $request request data.
		 *
		 * @return WP_REST_Response Response.
		 */
		public static function payments_methods_endpoint_post_callback( $request ) {

			$parameters = $request->get_json_params();

			$res = array( 'status_message' => 'Success' );

			if ( ! isset( $parameters['EventData']['TransactionTypeId'] ) || empty( $parameters['EventData']['TransactionTypeId'] ) ) {
				return new WP_REST_Response( $res, 200 );
			}

			if ( ! isset( $parameters['EventData']['TransactionId'] ) || empty( $parameters['EventData']['TransactionId'] ) ) {
				return new WP_REST_Response( $res, 200 );
			}

			$transaction_type_id = $parameters['EventData']['TransactionTypeId'];

			$transaction_id = $parameters['EventData']['TransactionId'];

			// IDeal = 23.
			// P24 = 25.
			// Blik = 27.
			// PayU = 29.
			// Multibanco = 32.
			// GiroPay = 34.
			// DirectPay = 36.
			// Eps = 38.
			// WeChatPay = 40.
			// BitPay = 42.

			$uses_ppro = false;

			if ( 23 === $transaction_type_id || 25 === $transaction_type_id || 27 === $transaction_type_id
				|| 29 === $transaction_type_id || 32 === $transaction_type_id || 34 === $transaction_type_id
				|| 36 === $transaction_type_id || 38 === $transaction_type_id || 40 === $transaction_type_id
				|| 42 === $transaction_type_id ) {

				$uses_ppro = true;

			}

			if ( ! $uses_ppro ) {
				return new WP_REST_Response( $res, 200 );
			}

			WC_Vivawallet_Logger::log( "PAYMENTS_METHODS_ENDPOINT_POST_CALLBACK\n REQUEST: " . wp_json_encode( $parameters ) );

			global $wpdb;
			$res = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT `post_id` from {$wpdb->prefix}postmeta WHERE `meta_key` = %s AND `meta_value` = %s",
					array(
						WC_Vivawallet_Helper::POST_META_VW_APM_TXN,
						$transaction_id,
					)
				)
			);

			if ( isset( $res[0]->post_id ) && ! empty( $res[0]->post_id ) ) {
				$id    = intval( $res[0]->post_id );
				$order = wc_get_order( $id );

				$method_info = WC_Vivawallet_Helper::get_payment_method_by_id( $transaction_type_id );

				if ( isset( $parameters['EventTypeId'] ) && 1796 === $parameters['EventTypeId'] ) {

					$status  = __( 'Order has been paid with Viva Wallet Standard Checkout, TxID: ', 'viva-wallet-for-woocommerce' );
					$status .= $transaction_id;

					WC_Vivawallet_Helper::complete_order( $order->get_id(), $transaction_id, $status, false );

				} else {
					$note = 'Transaction failed using ' . $method_info['method_title'] . ' (via Viva Wallet Standard Checkout)';

					if ( isset( $parameters['CorrelationId'] ) && ! empty( $parameters['CorrelationId'] ) ) {
						$note .= 'with Viva-CorrelationId: ' . $parameters['CorrelationId'];

					} else {
						$note .= '.';
					}

					$order->add_order_note( $note, false );
					$order->save();
				}
			}

			return new WP_REST_Response( $res, 200 );

		}


		/**
		 * This function is being called in APMs after redirection to each service to process a transaction
		 * In the case of success we set values and redirect to thank you page
		 */
		public function check_hook_response_success() {

			if ( ! isset( $_GET['t'] ) || empty( $_GET['t'] ) ) { // phpcs:ignore
				exit();
			}

			$transaction_id = sanitize_text_field( wp_unslash( $_GET['t'] ) ); // phpcs:ignore

			global $wpdb;
			$res = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT `post_id` from {$wpdb->prefix}postmeta WHERE `meta_key` = %s AND `meta_value` = %s",
					array(
						WC_Vivawallet_Helper::POST_META_VW_APM_TXN,
						$transaction_id,
					)
				)
			);

			if ( isset( $res[0]->post_id ) && ! empty( $res[0]->post_id ) ) {
				$id    = intval( $res[0]->post_id );
				$order = wc_get_order( $id );
				wp_safe_redirect( esc_url_raw( ( $order->get_checkout_order_received_url() ) ) );
			}

		}


		/**
		 * This function is being called in APMs after redirection to each service to process a transaction
		 * In the case of fail we set values and error message in session and redirect to checkout page
		 */
		public function check_hook_response_fail() {

			if ( ! isset( $_GET['t'] ) || empty( $_GET['t'] ) ) { // phpcs:ignore
				exit();
			}

			$transaction_id = sanitize_text_field( wp_unslash( $_GET['t'] ) ); // phpcs:ignore

			global $wpdb;
			$res = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT `post_id` from {$wpdb->prefix}postmeta WHERE `meta_key` = %s AND `meta_value` = %s",
					array(
						WC_Vivawallet_Helper::POST_META_VW_APM_TXN,
						$transaction_id,
					)
				)
			);

			if ( isset( $res[0]->post_id ) && ! empty( $res[0]->post_id ) ) {
				$id = $res[0]->post_id;
				delete_post_meta( intval( $id ), WC_Vivawallet_Helper::POST_META_VW_APM_TXN );
			}

			$message = __( 'There was a problem processing your payment. Please try again or use an other payment method.', 'viva-wallet-for-woocommerce' );

			WC()->session->set( 'VW_Error', $message );

			wp_safe_redirect( esc_url_raw( ( wc_get_checkout_url() ) ) );

		}


	}

	new WC_Vivawallet_Endpoints();
}
