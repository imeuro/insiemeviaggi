<?php
namespace Send_App\Modules\Woocommerce\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Send_App\Modules\Woocommerce\Classes\{
	Cart_Helper,
	Cart_Id_Helper,
	Woo_Integration_Base,
	API_Helper,
};

class Cart extends Woo_Integration_Base {

	const ENTITY = 'cart';
	const ACTION_EMPTY = 'empty';
	const ACTION_ADD_ITEM = 'add_item';
	const ACTION_REMOVE_ITEM = 'remove_item';
	const ACTION_UPDATE_ITEM = 'update_item';
	const ACTION_RESTORE_ITEM = 'restore_item';

	public function register_wc_hooks(): void {
		// No need to call the parent method.

		// Async processing hook:
		add_action( $this->get_async_update_hook_name(), [ $this, 'async_cart_update_action' ], 10, 3 );

		// Listen to Cart events:
		add_action( 'woocommerce_cart_emptied', [ $this, 'on_cart_emptied' ], 10, 1 );
		add_action( 'woocommerce_add_to_cart', [ $this, 'on_add_to_cart' ], 10, 6 );
		add_action( 'woocommerce_cart_item_removed', [ $this, 'on_cart_item_removed' ], 10, 2 );
		add_action( 'woocommerce_cart_item_set_quantity', [ $this, 'on_cart_item_set_quantity' ], 10, 3 );
		add_action( 'woocommerce_cart_item_restored', [ $this, 'on_cart_item_restored' ], 10, 2 );
	}

	/**
	 * @param bool $first
	 *
	 * @return bool|\WP_Error
	 */
	public function fetch_and_sync( bool $first ) {
		return new \WP_Error( static::ASYNC_SYNC_ERROR_CODE, 'not relevant for cart' );
	}

	public function sync(): bool {
		// Do nothing.
		return true;
	}

	/**
	 * @param bool $is_first
	 *
	 * @return \WP_Error|array
	 */
	protected function get_next_page( bool $is_first ) {
		return new \WP_Error( static::GET_NEXT_PAGE_ERROR_CODE, 'not relevant for cart' );
	}

	/**
	 * @param $entity_object
	 *
	 * @return array
	 */
	protected function get_sync_data( $entity_object ): array {
		return [];
	}

	public function on_cart_item_removed( $removed_cart_item_id, \WC_Cart $cart ) {
		$data = Cart_Helper::prepare_cart_data( $cart );
		$empty = $cart->is_empty();

		$this->add_scheduled_action( $this->get_async_update_hook_name(), [
			'trigger' => $empty ? 'cart empty' : 'remove from cart',
			'action' => $empty ? self::ACTION_EMPTY : self::ACTION_REMOVE_ITEM,
			'data' => $data,
		], static::ASYNC_UPDATE_GROUP );

		remove_action( 'woocommerce_after_calculate_totals', [ $this, 'on_after_calculate_totals_item_removed' ], 10 );
	}

	public function on_cart_emptied( $persistent_cart ) {
		$data = Cart_Helper::prepare_cart_data( WC()->cart );

		$this->add_scheduled_action( $this->get_async_update_hook_name(), [
			'trigger' => 'cart empty',
			'action' => self::ACTION_EMPTY,
			'data' => $data,
		], static::ASYNC_UPDATE_GROUP );

		Cart_Id_Helper::revoke_cart_id();
	}

	/**
	 * @param $cart_item_key
	 * @param $product_id
	 * @param $quantity
	 * @param $variation_id
	 * @param $variation
	 * @param $cart_item_data
	 *
	 * @return void
	 */
	public function on_add_to_cart( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
		add_action( 'woocommerce_after_calculate_totals', [ $this, 'on_after_calculate_totals_add_to_cart' ], 10, 1 );
	}

	public function on_after_calculate_totals_add_to_cart( $cart ) {
		$data = Cart_Helper::prepare_cart_data( $cart );

		$this->add_scheduled_action( $this->get_async_update_hook_name(), [
			'trigger' => 'add to cart',
			'action' => self::ACTION_ADD_ITEM,
			'data' => $data,
		], static::ASYNC_UPDATE_GROUP );

		remove_action( 'woocommerce_after_calculate_totals', [ $this, 'on_after_calculate_totals_add_to_cart' ], 10 );
	}

	public function on_cart_item_set_quantity( $cart_item_key, $quantity, \WC_Cart $cart ) {
		$data = Cart_Helper::prepare_cart_data( $cart );

		$this->add_scheduled_action( $this->get_async_update_hook_name(), [
			'trigger' => 'cart item quantity changed',
			'action' => self::ACTION_UPDATE_ITEM,
			'data' => $data,
		], static::ASYNC_UPDATE_GROUP );
	}

	public function on_cart_item_restored( $cart_item_key, $cart ) {
		$data = Cart_Helper::prepare_cart_data( $cart );

		$this->add_scheduled_action( $this->get_async_update_hook_name(), [
			'trigger' => 'cart item restored',
			'action' => self::ACTION_RESTORE_ITEM,
			'data' => $data,
		], static::ASYNC_UPDATE_GROUP );
	}

	public function async_cart_update_action( string $trigger, string $action, array $data ) {
		API_Helper::send_event_data( $data, $trigger, static::ENTITY, $action );
	}
}
