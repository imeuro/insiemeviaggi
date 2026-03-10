<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}
/**
 * Class
 *
 * WC_Vivawallet_Refund
 */
class WC_Vivawallet_Refund {


	/**
	 * Process refund
	 *
	 * @param string $test_mode Test_mode.
	 * @param string $token Token.
	 * @param string $source_code Source_code.
	 * @param int    $order_id Order ID.
	 * @param float  $amount Refund amount.
	 *
	 * @param string $reason Refund reason.
	 *
	 * @throws Exception When in error.
	 * @return bool|WP_Error True if success, false or WP_Error if error
	 */
	public static function process_refund( $test_mode, $token, $source_code, $order_id, $amount = null, $reason = '' ) {
		$order               = wc_get_order( $order_id );
		$refund_amount_cents = round( $amount * 100 );
		$order_total         = $order->get_total();
		$order_total         = round( $order_total * 100 );
		$is_full_refund      = ( $refund_amount_cents === $order_total ) ? true : false;

		try {
			if ( $order->get_status() === WC_Vivawallet_Helper::ORDER_STATUS_REFUNDED || $order->get_status() === WC_Vivawallet_Helper::ORDER_STATUS_CANCELLED ) {
				throw new Exception( __( 'You cannot edit an already refunded or canceled order.', 'viva-wallet-for-woocommerce' ) );
			}

			$post_meta = get_post_meta( $order_id, WC_Vivawallet_Helper::POST_META_VW_TXN );
			if ( empty( $post_meta ) ) {
				throw new Exception( __( 'The transaction ID for this order could not be found. Something is wrong!', 'viva-wallet-for-woocommerce' ) );
			}

			if ( false === $is_full_refund ) {
				$post_meta_order_paid = get_post_meta( $order_id, WC_Vivawallet_Helper::POST_META_WC_ORDER_PAID );
				$paid_date            = $post_meta_order_paid [0];
				if ( 'vivawallet_native' === $order->get_payment_method() ) {
					// only for native calls check the time and dont allow partial refunds before the clearance of transaction.
					if ( ! self::can_refund_on_vivawallet( $paid_date ) ) {
						throw new Exception( __( 'Partial Refund not available yet. Please try later or contact Viva Wallet support for more info.', 'viva-wallet-for-woocommerce' ) );
					}
				}
			}

			$payment_transaction_id = $post_meta [0];

			$result = self::refund_api_call( $payment_transaction_id, $refund_amount_cents, $source_code, $token, $test_mode );

			if ( is_wp_error( $result ) ) {
				throw new Exception( __( 'Error connecting to Viva Wallet services. Please try again!', 'viva-wallet-for-woocommerce' ) );
			}

			$result_body = json_decode( $result['body'] );
			$response    = $result['response'];

			if ( ! isset( $response['code'] ) ) {
				throw new Exception( __( 'Error connecting to Viva Wallet services. Please try again!', 'viva-wallet-for-woocommerce' ) );
			}

			if ( '2066' === $result_body->eventId ) { // phpcs:ignore
				throw new Exception( __( 'Something went wrong and we could not process the refund.', 'viva-wallet-for-woocommerce' ) );
			}
			if ( 200 !== $response['code'] && ! isset( $result_body->transactionId ) ) { // phpcs:ignore
				throw new Exception( $response['message'] );
			}

			$refund_data = array(
				'refunded_amount'        => $refund_amount_cents,
				'refund_transaction_id'  => $result_body->transactionId, // phpcs:ignore
				'payment_transaction_id' => $payment_transaction_id,
			);

			add_post_meta( $order_id, WC_Vivawallet_Helper::POST_META_VW_REFUND_DATA, $refund_data );
			if ( $is_full_refund ) {
				$note = __( 'Full refund was executed on Viva Wallet with ID: ', 'viva-wallet-for-woocommerce' ) . $refund_data['refund_transaction_id'];
			} else {
				$note = __( 'Partial refund was executed on Viva Wallet with ID: ', 'viva-wallet-for-woocommerce' ) . $refund_data['refund_transaction_id'];
			}
			$order->add_order_note( $note, false );
			return true;
		} catch ( Exception $e ) {
			return new WP_Error( 'error', $e->getMessage() );
		}

	}

	/**
	 * Refund_api_call
	 *
	 * @param string     $payment_transaction_id  payment transaction id.
	 * @param int|string $refund_amount_cents refund amount cents.
	 * @param string     $source_code source code.
	 * @param string     $token token.
	 * @param string     $test_mode test mode.
	 *
	 * @return array|WP_Error
	 */
	public static function refund_api_call( $payment_transaction_id, $refund_amount_cents, $source_code, $token, $test_mode ) {

		$url  = WC_Vivawallet_Helper::get_api_url_endpoint( $test_mode, WC_Vivawallet_Helper::ENDPOINT_RECURRING_TRANSACTIONS );
		$url .= $payment_transaction_id;

		$body = array(
			'amount'     => $refund_amount_cents,
			'SourceCode' => $source_code,
		);

		$params = http_build_query( $body );
		$url   .= '?' . $params;

		$args = array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			),
			'method'  => 'DELETE',
			'timeout' => WC_Vivawallet_Helper::REQUEST_TIMEOUT,
		);

		$response = WC_Vivawallet_Helper::remote_request( $url, $args );

		WC_Vivawallet_Logger::log( "API DO REFUND \nURL: " . $url . "\nARGS: " . wp_json_encode( $args ) . "\nRESULT: " . wp_json_encode( $response ) );

		return $response;
	}

	/**
	 * Can Refund On Vivawallet
	 *
	 * @param string $paid_date paid date.
	 *
	 * @return bool
	 */
	private static function can_refund_on_vivawallet( $paid_date ) {
		$today    = gmdate( 'Ymd' );
		$tomorrow = gmdate( 'Ymd', strtotime( 'tomorrow' ) );
		if ( gmdate( 'Ymd', strtotime( $paid_date ) ) === $today ) {
			return false;
		} elseif ( gmdate( 'Ymd', strtotime( $paid_date ) ) === $tomorrow ) {
			$valid_tomorrow = $paid_date + ( 2 * 60 * 60 );
			$valid_tomorrow = gmdate( 'Y-m-d H:i:s', strtotime( $valid_tomorrow ) );
			$now            = gmdate();
			$now            = gmdate( 'Y-m-d H:i:s', strtotime( $now ) );
			if ( $now < $valid_tomorrow ) {
				return false;
			}
		}
		return true;
	}
}
