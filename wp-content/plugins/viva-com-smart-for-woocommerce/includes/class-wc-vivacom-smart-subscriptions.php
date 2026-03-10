<?php
/**
 * Subscriptions
 *
 * @package   VivaComSmartForWooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use \VivaComSmartCheckout\Vivawallet\VivawalletPhp\Api\TransactionClient;

/**
 * Class WC_Vivacom_Smart_Payment_Gateway_Subscriptions
 *
 * @class   WC_Vivacom_Smart_Payment_Gateway_Subscriptions
 */
class WC_Vivacom_Smart_Payment_Gateway_Subscriptions extends WC_Vivacom_Smart_Payment_Gateway {

	/**
	 * WC_Vivacom_Smart_Subscriptions constructor.
	 */
	public function __construct() {

		parent::__construct();

		if ( class_exists( 'WC_Subscriptions_Order' ) ) {
			add_filter( 'woocommerce_subscription_payment_method_to_display', array( $this, 'viva_payments_subscription_payment_method_to_display' ) );
			add_filter( 'woocommerce_subscription_payment_meta', array( $this, 'add_subscription_payment_meta' ), 10, 2 );
			add_filter( 'woocommerce_subscription_validate_payment_meta', array( $this, 'validate_subscription_payment_meta' ), 10, 3 );
			add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array( $this, 'scheduled_subscription_payment' ), 10, 2 );

		}
	}

	/**
	 * Hook to change the default title of payment method in my subscriptions' page
	 * show also the title of the card token.
	 *
	 * @param string $title Input title.
	 *
	 * @return string Title to return.
	 */
	public function viva_payments_subscription_payment_method_to_display( $title ) {

		global $wpdb;
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

		if ( $subscription->get_payment_method() !== WC_Vivacom_Smart_Helpers::TECHNICAL_NAME ) { // if not viva payment method then return normal title.
			return $title;
		}

		if ( $subscription->get_requires_manual_renewal() ) { // if is manual renewal then return normal title.
			return $title;
		}

		$token_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT token_id FROM {$wpdb->prefix}viva_com_smart_wc_checkout_recurring WHERE recurring_id = %s ORDER BY date_add LIMIT 1",
				$subscription->get_id()
			)
		);

		$token = WC_Payment_Tokens::get( $token_id );
		if ( null === $token ) {
			return $title;
		}

		return $title . ' - ' . $token->get_display_name();
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
		global $wpdb;

		$token_data = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}viva_com_smart_wc_checkout_recurring WHERE recurring_id = %s ORDER BY date_add LIMIT 1",
				$subscription->get_id()
			),
			ARRAY_A
		);

		$token = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT token FROM {$wpdb->prefix}woocommerce_payment_tokens WHERE token_id = %s LIMIT 1",
				$token_data['token_id']
			),
			ARRAY_A
		);

		$token_id = $token_data['token_id'];

		// disabled option to not allow admin to change the tokens from subscription edit admin page.

		$payment_meta[ $this->id ] = array(
			'post_meta' => array(
				WC_Vivacom_Smart_Helpers::TOKEN_ID => array(
					'value'    => $token_id,
					'label'    => __( 'Viva.com Smart Card Token Id ', 'viva-com-smart-for-woocommerce' ),
					'disabled' => true,
				),
				WC_Vivacom_Smart_Helpers::TOKEN    => array(
					'value'    => $token['token'],
					'label'    => __( 'Viva.com Smart Card Token', 'viva-com-smart-for-woocommerce' ),
					'disabled' => true,
				),
			),
		);
		return $payment_meta;
	}

	/**
	 * Validate the payment metadata required to process automatic recurring payments so that store managers can
	 * manually set up automatic recurring payments for a customer via the Edit Subscriptions screen in 2.0+.
	 *
	 * @param string          $payment_method_id The ID of the payment method to validate.
	 * @param array           $payment_meta Associative array of metadata required for automatic payments.
	 * @param WC_Subscription $subscription Subscription Object.
	 *
	 * @throws Exception Exception.
	 */
	public function validate_subscription_payment_meta( $payment_method_id, $payment_meta, $subscription ) {
		if ( $this->id === $payment_method_id ) {

			// only for subscription edit admin page. Merchant can change token only for same user.

			if ( ! empty( $payment_meta['post_meta'] )
			&& ! empty( $payment_meta['post_meta'][ WC_Vivacom_Smart_Helpers::TOKEN_ID ]['value'] )
			&& ! empty( $payment_meta['post_meta'][ WC_Vivacom_Smart_Helpers::TOKEN ]['value'] )
			) {
				$token_object = WC_Payment_Tokens::get( $payment_meta['post_meta'][ WC_Vivacom_Smart_Helpers::TOKEN_ID ]['value'] );
				if ( empty( $token_object )
				|| empty( $subscription )
				|| $subscription->get_user_id() !== $token_object->get_user_id()
				|| $token_object->get_gateway_id() !== $payment_method_id
				|| $token_object->get_token() !== $payment_meta['post_meta'][ WC_Vivacom_Smart_Helpers::TOKEN ]['value']
				) {
					throw new Exception( esc_html__( 'You are not allowed to change the Card Token.', 'viva-com-smart-for-woocommerce' ) );
				}
			}
		}
	}

	/**
	 * Scheduled_subscription_payment
	 *
	 * @param int      $amount Amount to charge.
	 * @param WC_Order $order Order.
	 */
	public function scheduled_subscription_payment( $amount, $order ) {

		$order_id = $order->get_id();

		$process_result = $this->process_subscription_payment( $order_id, $amount );

		WC_Vivacom_Smart_Logger::log( 'Process subscription: ' . wp_json_encode( $process_result ) );

		if ( true === $process_result ) {
			WC_Subscriptions_Manager::prepare_renewal( $order_id );
		} else {
			if ( ! wcs_is_subscription( $order_id ) ) {
				$order_id = $order->get_parent_id();
			}
			WC_Vivacom_Smart_Logger::log( 'Failed subscription - Subscription id: ' . wp_json_encode( $order_id ) . ' Parent order id: ' . $order->get_id() );
			WC_Subscriptions_Manager::expire_subscription( $order_id );
		}
	}

	/**
	 * Process subscriptions payment
	 *
	 * @param int $order_id order id.
	 * @param int $amount amount to charge for subscription renewal.
	 *
	 * @return boolean Result.
	 */
	public function process_subscription_payment( $order_id, $amount ) {
		global $wpdb;

		$order = wc_get_order( $order_id );

		$environment = 'yes' === $this->test_mode ? 'demo' : 'live';

		$source_code = 'demo' === $environment ? $this->demo_source_code : $this->source_code;

		if ( wcs_order_contains_renewal( $order ) ) {
			$related_subscriptions = wcs_get_subscriptions_for_renewal_order( $order );
			foreach ( $related_subscriptions as $subscription ) {
				$subscription  = wcs_get_subscription( $subscription );
				$token_id      = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT token_id FROM {$wpdb->prefix}viva_com_smart_wc_checkout_recurring WHERE recurring_id = %s ORDER BY date_add LIMIT 1",
						$subscription->get_id()
					)
				);
				$payment_token = WC_Payment_Tokens::get( $token_id );
			}
		}

		if ( empty( $payment_token ) ) {
			return false;
		}

		// check that the token selected belongs to the user/customer related to this subscription order.
		if (
			$payment_token->get_user_id() !== $order->get_user_id()
			|| $payment_token->get_gateway_id() !== WC_Vivacom_Smart_Helpers::TECHNICAL_NAME
		) {
			return false;
		}

		$recurring_transaction_id = $payment_token->get_token();

		if ( ! empty( $recurring_transaction_id ) ) {

			$bearer_authentication = WC_Vivacom_Smart_Helpers::get_bearer_authentication( $environment );
			if ( $bearer_authentication->hasValidToken() ) {
				$order_data = WC_Vivacom_Smart_Helpers::get_order_data( $order );

				$args = array(
					'sourceCode' => $source_code,
                    'currencyCode' => $order_data['currency'],
					'messages'   => array(
						'customer' => $order_data['messages']['customer_message'],
						'merchant' => $order_data['messages']['merchant_message'],
					),
				);

				$format_amount = (int) number_format( $amount, 2, '', '' );

				$transaction_client = new TransactionClient( $bearer_authentication );

				$transaction_response = $transaction_client->createRecurringTransaction( $recurring_transaction_id, $format_amount, $args );

				if ( $transaction_response->isSuccessful() && ! empty( $transaction_response->getBody() ) ) {
					$transaction = array(
						'id'     => $transaction_response->getBody()->transactionId,
						'typeId' => 5, // id for chrgetoken.
					);
					$note        = __( 'Payment method used for automatic renewal: ', 'viva-com-smart-for-woocommerce' );
					$note       .= $payment_token->get_display_name();

					WC_Vivacom_Smart_Helpers::complete_order( $order_id, $transaction, $note, false );

					return true;
				}
			}
		}

		return false;
	}
}
