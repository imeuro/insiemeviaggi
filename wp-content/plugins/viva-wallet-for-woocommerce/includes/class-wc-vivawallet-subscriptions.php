<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Vivawallet_Payment_Gateway_Subscriptions
 *
 * @extends WC_Vivawallet_Payment_Gateway
 *
 * @class   WC_Vivawallet_Payment_Gateway_Subscriptions
 * @package VivaWalletForWooCommerce
 */
class WC_Vivawallet_Payment_Gateway_Subscriptions extends WC_Vivawallet_Payment_Gateway {

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();

		if ( class_exists( 'WC_Subscriptions_Order' ) ) {

			add_filter( 'woocommerce_subscription_payment_method_to_display', array( $this, 'viva_payments_subscription_payment_method_to_display' ) );

			add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array( $this, 'scheduled_subscription_payment' ), 10, 2 );

			add_filter( 'woocommerce_subscription_payment_meta', array( $this, 'add_subscription_payment_meta' ), 10, 2 );

			add_filter( 'woocommerce_subscription_validate_payment_meta', array( $this, 'validate_subscription_payment_meta' ), 10, 3 );

		}
	}

	/**
	 * Scheduled_subscription_payment
	 *
	 * @param int      $amount_to_charge Amount to charge.
	 * @param WC_Order $order Order.
	 *
	 * @throws WC_Data_Exception Exception.
	 */
	public function scheduled_subscription_payment( $amount_to_charge, $order ) {

		$order_id = $order->get_id();

		$result = $this->process_subscription_payment( $order_id, $amount_to_charge );

		WC_Vivawallet_Logger::log( 'PROCESS_SUBSCRIPTION_PAYMENT ' . wp_json_encode( $result ) );

		if ( true === $result ) {
			WC_Subscriptions_Manager::prepare_renewal( $order_id );
		} else {
			if ( ! wcs_is_subscription( $order_id ) ) {
				$order_id = $order->get_parent_id();
			}
			WC_Vivawallet_Logger::log( 'FAILED SUBSCRIPTION - Subs_id: ' . wp_json_encode( $order_id ) . ' Parent order id: ' . $order->get_id() );
			WC_Subscriptions_Manager::expire_subscription( $order_id );
		}
	}

	/**
	 * The meta of the items to show to admin as payment method.
	 *
	 * @param array           $payment_meta Payment meta.
	 * @param WC_Subscription $subscription Subscription object.
	 *
	 * @return array
	 */
	public function add_subscription_payment_meta( $payment_meta, $subscription ) {
		$token_id = get_post_meta( $subscription->get_id(), WC_Vivawallet_Helper::POST_META_VW_CARD_TOKEN_ID, true );
		$token    = get_post_meta( $subscription->get_id(), WC_Vivawallet_Helper::POST_META_VW_CARD_TOKEN, true );

		// disabled option to not allow admin to change the tokens from subscription edit admin page.

		$payment_meta[ $this->id ] = array(
			'post_meta' => array(
				WC_Vivawallet_Helper::POST_META_VW_CARD_TOKEN_ID => array(
					'value'    => $token_id,
					'label'    => __( 'Viva Wallet Card Token Id ', 'viva-wallet-for-woocommerce' ),
					'disabled' => true,
				),
				WC_Vivawallet_Helper::POST_META_VW_CARD_TOKEN => array(
					'value'    => $token,
					'label'    => __( 'Viva Wallet Card Token', 'viva-wallet-for-woocommerce' ),
					'disabled' => true,
				),
			),
		);
		return $payment_meta;

	}

	/**
	 * Validate the payment meta data required to process automatic recurring payments so that store managers can
	 * manually set up automatic recurring payments for a customer via the Edit Subscriptions screen in 2.0+.
	 *
	 * @param string          $payment_method_id The ID of the payment method to validate.
	 * @param array           $payment_meta Associative array of meta data required for automatic payments.
	 * @param WC_Subscription $subscription Subscription Object.
	 *
	 * @throws Exception Exception.
	 */
	public function validate_subscription_payment_meta( $payment_method_id, $payment_meta, $subscription ) {
		if ( $this->id === $payment_method_id ) {

			// only for subscription edit admin page. Merchant can change token only for same user.

			if ( ! empty( $payment_meta['post_meta'] )
				&& ! empty( $payment_meta['post_meta'][ WC_Vivawallet_Helper::POST_META_VW_CARD_TOKEN_ID ]['value'] )
				&& ! empty( $payment_meta['post_meta'][ WC_Vivawallet_Helper::POST_META_VW_CARD_TOKEN ]['value'] )
			) {
				$token_object = WC_Payment_Tokens::get( $payment_meta['post_meta'][ WC_Vivawallet_Helper::POST_META_VW_CARD_TOKEN_ID ]['value'] );
				if ( empty( $token_object )
					|| empty( $subscription )
					|| $subscription->get_user_id() !== $token_object->get_user_id()
					|| $token_object->get_gateway_id() !== $payment_method_id
					|| $token_object->get_token() !== $payment_meta['post_meta'][ WC_Vivawallet_Helper::POST_META_VW_CARD_TOKEN ]['value']
				) {
					throw new Exception( __( 'You are not allowed to change the Card Token.', 'viva-wallet-for-woocommerce' ) );
				}
			}
		}
	}

	/**
	 * Hook to change the default title of payment method in my subscriptions page
	 * show also the title of the card token.
	 *
	 * @param string $title Title that gets passed.
	 *
	 * @return string Title to return.
	 */
	public function viva_payments_subscription_payment_method_to_display( $title ) {

		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return $title;
		}
		$url = wc_clean( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		// check if url contains vars.
		if ( strpos( $url, '/?' ) !== false ) {
			$url = substr( $url, 0, strpos( $url, '/?' ) );
		}
		$needle  = '/my-account/view-subscription/';
		$needle2 = '/checkout/order-pay/';

		if ( strpos( $url, $needle ) === false && strpos( $url, $needle2 ) === false ) {
			return $title;
		}

		$key          = str_replace( $needle, '', $url );
		$key          = str_replace( $needle2, '', $key );
		$key          = str_replace( '/', '', $key );
		$subscription = wcs_get_subscription( $key );

		if ( $subscription->get_payment_method() !== 'vivawallet_native' ) { // if not viva wallet payment method then return normal title.
			return $title;
		}

		if ( $subscription->get_requires_manual_renewal() ) { // if is manual renewal then return normal title.
			return $title;
		}

		$card_token_id = get_post_meta( $subscription->get_id(), WC_Vivawallet_Helper::POST_META_VW_CARD_TOKEN_ID, true );

		$token = WC_Payment_Tokens::get( $card_token_id );
		if ( null === $token ) {
			return $title;
		}

		return $title . ' - ' . $token->get_display_name();

	}



	/**
	 * Process subscriptions payment
	 *
	 * @param int $order_id order id.
	 * @param int $amount_to_charge ammount to charge for subscription renewal.
	 *
	 * @return boolean Result.
	 */
	public function process_subscription_payment( $order_id, $amount_to_charge ) {
		// handle auto payments for subscriptions.

		$order = wc_get_order( $order_id );

		$test_mode = $this->test_mode;

		$source_code = ( 'yes' === $test_mode ) ? $this->test_source_code : $this->source_code;

		$access_token = $this->get_authentication_token();

		if ( wcs_order_contains_renewal( $order ) ) { // make sure that the order contains a renewal subscription.
			$related_subscriptions = wcs_get_subscriptions_for_renewal_order( $order );
			foreach ( $related_subscriptions as $subscription ) {
				$subscription  = wcs_get_subscription( $subscription );
				$card_token_id = get_post_meta( $subscription->get_id(), WC_Vivawallet_Helper::POST_META_VW_CARD_TOKEN_ID, true );
				$token         = WC_Payment_Tokens::get( $card_token_id );
			}
		}

		if ( ! isset( $token ) ) {
			return false;
		}

		// check that the token selected belongs to the user/customer related to this subscription order.
		if ( $token->get_user_id() !== $order->get_user_id() || $token->get_gateway_id() !== 'vivawallet_native' ) {
			return false;
		}

		$data = WC_Vivawallet_Helper::prepare_transaction_data_from_order( $order );

		$recurring_trans_id = $token->get_token();

		if ( false !== $recurring_trans_id ) {

			$post_args = array(
				'amount'       => floatval( $amount_to_charge ) * 100,
				'sourceCode'   => $source_code,
				'merchantTrns' => $data['messages']['merchant_message'],
				'customerTrns' => $data['messages']['customer_message'],
			);

			$result = WC_Vivawallet_Helper::transaction_api_call( $post_args, $access_token, $test_mode, $recurring_trans_id );

			if ( ! is_wp_error( $result ) && isset( $result['response']['code'] ) && 200 === $result['response']['code'] ) {
				$transaction_id = json_decode( $result['body'] );
				$transaction_id = $transaction_id->transactionId; // phpcs:ignore

				$status  = __( 'Order has been paid with Viva Wallet Standard Checkout, TxID:', 'viva-wallet-for-woocommerce' );
				$status .= $transaction_id;
				$status .= '<br>';

				$status .= __( 'Card used for automatic renewal: ', 'viva-wallet-for-woocommerce' );
				$status .= $token->get_display_name();

				WC_Vivawallet_Helper::complete_order( $order_id, $transaction_id, $status, false );

				return true;

			}
		}
		return false;

	}



}
