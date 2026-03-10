<?php
namespace Send_App\Modules\Woocommerce\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Send_App\Modules\Woocommerce\Classes\{
	Cart_Id_Helper,
	Customer_Helper,
	Order_Helper,
	Woo_Integration_Base,
	API_Helper
};

class Orders extends Woo_Integration_Base {

	const ENTITY = 'order';
	const ACTION_UPDATED = 'update';
	const ACTION_CREATED = 'create';
	const ACTION_COMPLETED = 'complete';
	const ACTION_REFUND = 'refunded';
	const ACTION_PAYMENT_FAILED = 'payment_failed';

	const META_KEY_CART_ID = '_send_app_cart_id';

	// Custom WC-Session keys:
	protected function get_async_update_refund_hook_name(): string {
		return static::ASYNC_UPDATE_HOOK_PREFIX . 'refund/' . static::ENTITY;
	}

	protected function register_as_hooks() {
		parent::register_as_hooks();
		add_action( $this->get_async_update_refund_hook_name(), [ $this, 'async_update_refund' ], 10, 4 );
	}

	public function register_wc_hooks(): void {
		parent::register_wc_hooks();

		// Order related hooks:
		add_action( 'woocommerce_order_status_completed', [ $this, 'on_order_complete' ], 10, 3 );
		add_action( 'woocommerce_order_status_failed', [ $this, 'on_payment_failed' ], 10, 3 );
		add_action( 'woocommerce_checkout_order_processed', [ $this, 'trigger_checkout_order_processed' ], 10, 3 );
		add_action( 'woocommerce_store_api_checkout_order_processed', [ $this, 'trigger_api_checkout_order_processed' ], 10, 1 );
		add_action( 'woocommerce_new_order', [ $this, 'trigger_new_order' ], 10, 2 );
		add_action( 'woocommerce_order_refunded', [ $this, 'on_refund_order' ], 10, 2 );
		add_action( 'woocommerce_order_status_changed', [ $this, 'on_order_status_changed' ], 10, 4 );
	}

	/**
	 * @param $entity_object
	 *
	 * @return array
	 */
	protected function get_sync_data( $entity_object ): array {
		if ( is_numeric( $entity_object ) ) {
			$entity_object = wc_get_order( $entity_object );
			if ( empty( $entity_object ) ) {
				return [];
			}
		}

		$cart_id = $entity_object->get_meta( self::META_KEY_CART_ID );

		return Order_Helper::get_order_data( $entity_object, $cart_id );
	}

	/**
	 * Triggered by the action scheduler and sends the refund related data:
	 *
	 * @param int $order_id
	 * @param int $refund_id
	 * @param string $trigger
	 * @param string $action
	 *
	 * @return void
	 */
	public function async_update_refund( int $order_id, int $refund_id, string $trigger, string $action ) {
		$refund = wc_get_order( $refund_id );
		$data = [
			'original_order' => $this->get_sync_data( $order_id ),
			'refund_id' => $refund_id,
			'refund_data' => $refund->get_data(),
		];

		return API_Helper::send_event_data( $data, $trigger, static::ENTITY, $action );
	}

	public function trigger_checkout_order_processed( $order_id, $posted_data, $order ) {
		$this->trigger_new_order( $order_id, $order );
	}

	public function trigger_api_checkout_order_processed( $order ) {
		$this->trigger_new_order( $order->get_id(), $order );
	}

	/**
	 * trigger a single new order sync.
	 * IMPORTANT: cannot use this method for bulk updates.
	 *
	 * @param $order_id
	 * @param $order
	 *
	 * @return void
	 */
	public function trigger_new_order( $order_id, $order ) {
		// once per request is enough.
		static $already_triggered = false;

		if ( $already_triggered ) {
			return;
		}

		$non_user_details = Customer_Helper::get_customer_data_by_order( $order );

		// Encode the array as a JSON string and maybe save the data as a cookie:
		$encoded_user_details = Customer_Helper::set_non_user_cookie( $non_user_details );

		$cart_id = Cart_Id_Helper::create_or_get_cart_id();
		$order->update_meta_data( self::META_KEY_CART_ID, $cart_id );
		$order->save();
		$this->on_new_order( $order, $encoded_user_details );
		$already_triggered = true;
	}

	public function on_order_status_changed( $order_id, $from, $to, $order ) {
		$this->add_scheduled_action( $this->get_async_update_hook_name(), [
			'id' => $order_id,
			'trigger' => "order status changed from: [{$from}] to: [{$to}]",
			'action' => self::ACTION_UPDATED,
		], static::ASYNC_UPDATE_GROUP );
	}

	public function on_new_order( $order, $non_user_details ) {

		$trigger = 'guest user has ordered';

		if ( $order && $order->get_customer_id() ) {
			// Get all orders for the user, excluding the current order
			$customer_orders = wc_get_orders( [
				'customer_id' => $order->get_customer_id(),
				'limit' => 2,
			] );
			$trigger = count( $customer_orders ) > 1 ? 'first order' : 'Subsequent Order Detected';
		}

		$this->add_scheduled_action( $this->get_async_update_hook_name(), [
			'id' => $order->get_id(),
			'trigger' => $trigger,
			'action' => self::ACTION_CREATED,
		], static::ASYNC_UPDATE_GROUP );
	}

	public function on_refund_order( $order_id, $refund_id ) {
		$this->add_scheduled_action( $this->get_async_update_refund_hook_name(), [
			'id' => $order_id,
			'refund_id' => $refund_id,
			'trigger' => 'refund order',
			'action' => self::ACTION_REFUND,
		], static::ASYNC_UPDATE_GROUP );
	}

	// Corresponding functions
	public function on_order_complete( $order_id, $order, $status_transition ) {
		$this->add_scheduled_action( $this->get_async_update_hook_name(), [
			'id' => $order_id,
			'trigger' => 'order completed',
			'action' => self::ACTION_COMPLETED,
		], static::ASYNC_UPDATE_GROUP );
	}

	/* // TODO: Shipping
	public function on_shipping_completed( $order_id ) {
		$this->add_scheduled_action( $this->get_async_update_hook_name(), [
			'id' => $order_id,
			'trigger' => 'order shipped',
			'action' => self::ACTION_SHIPPED, //TODO: Add this constant
		], static::ASYNC_UPDATE_GROUP );
	}

	public function order_shipped( $order_id ) {
		return API_Helper::send_meta_data( [ 'order_id' => $order_id ], 'order shipped', 'orderShipped' );
	}
	*/

	/* TODO: Invoices
	public function on_invoice_sent( $order_id ) {
		return API_Helper::send_meta_data( [ 'order_id' => $order_id ], 'invoice sent', 'invoiceSent' );
	}
	*/

	public function on_payment_failed( $order_id, $order, $status ) {
		$this->add_scheduled_action( $this->get_async_update_hook_name(), [
			'id' => $order_id,
			'trigger' => 'payment failed',
			'action' => self::ACTION_PAYMENT_FAILED,
		], static::ASYNC_UPDATE_GROUP );
	}

	protected function get_next_page( bool $is_first ) {
		$orders = wc_get_orders(
			[
				'limit' => $this->get_limit(),
				'page' => $this->get_current_page(),
				'paginate' => true,
				'type' => 'shop_order', // what about refunds?
			]
		);

		if ( $is_first ) {
			$this->set_max_pages( $orders->max_num_pages );
			$this->set_total_items( $orders->total );
		}

		return ! empty( $orders ) ? $orders->orders : new \WP_Error( static::GET_NEXT_PAGE_ERROR_CODE, 'no orders found' );
	}

	protected function process( array $items ) {
		// Loop through each order and get full details
		$orders_data = [];
		foreach ( $items as $item ) {
			/** @var \WC_Order $item */
			$orders_data[] = $this->get_sync_data( $item );
		}

		parent::process( $orders_data );
	}
}
