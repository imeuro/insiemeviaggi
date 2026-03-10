<?php
namespace Send_App\Modules\Woocommerce\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Send_App\Core\Logger;
use Send_App\Modules\Woocommerce\Classes\Product_Helper;
use Send_App\Modules\Woocommerce\Classes\Woo_Integration_Base;
use Send_App\Modules\Woocommerce\Classes\API_Helper;

class Products extends Woo_Integration_Base {

	const ENTITY = 'product';
	const ACTION_CREATED = 'created';
	const ACTION_DELETED = 'deleted';
	const ACTION_UPDATED = 'updated';

	protected function get_next_page( bool $is_first = false ) {
		$args = [
			'limit' => $this->get_limit(),
			'page' => $this->get_current_page(),
			'paginate' => true,
		];

		$products = \wc_get_products( $args );

		if ( $is_first ) {
			$this->set_max_pages( $products->max_num_pages );
			$this->set_total_items( $products->total );
		}

		if ( empty( $products ) || empty( $products->products ) ) {
			return new \WP_Error( static::GET_NEXT_PAGE_ERROR_CODE, 'No products found' );
		}

		return $products->products;
	}

	/**
	 * @param $entity_object
	 *
	 * @return array
	 */
	protected function get_sync_data( $entity_object ): array {
		if ( is_numeric( $entity_object ) ) {
			$entity_object = \wc_get_product( $entity_object );
		}

		if ( empty( $entity_object ) ) {
			return [];
		}

		return Product_Helper::get_product_data( $entity_object );
	}

	protected function process( array $items ) {
		$products_data = [];
		foreach ( $items as $item ) {
			$products_data[] = $this->get_sync_data( $item );
		}
		parent::process( $products_data );
	}

	public static function get_all_categories() {
		return get_terms( [
			'taxonomy'   => 'product_cat', // Taxonomy for product categories
			'hide_empty' => false, // Set to `false` to include empty categories
		] );
	}

	public static function get_all_coupons() {
		$coupons = get_posts( [
			'post_type'   => 'shop_coupon', // Type of post representing coupons
			'numberposts' => -1, // Retrieve all coupons (-1 for all)
		] );
		$coupons_payload = [];
		foreach ( $coupons as $coupon ) {
			$coupon_data = get_post_meta( $coupon->ID );
			$coupons_payload[] = [
				'meta' => $coupon_data,
				'data' => $coupon,
			];
		}
		return $coupons_payload;
	}

	public function register_wc_hooks(): void {
		parent::register_wc_hooks();

		add_action( 'transition_post_status', [ $this, 'on_product_status_change' ], 10, 3 );
		add_action( 'woocommerce_update_product', [ $this, 'on_product_updated' ], 10, 1 );

		// add_action( 'woocommerce_product_set_stock_status', [ $this, 'on_product_stock_status_change' ], 10, 3 );
		// add_action( 'created_term', [ $this, 'on_create_category' ], 10, 4 );
		// add_action( 'delete_term', [ $this, 'on_delete_category' ], 10, 5 );
		// add_action( 'edited_term', [ $this, 'on_edit_category' ], 10, 4 );
		// add_action( 'woocommerce_coupon_options_save', [ $this, 'on_coupon_save' ], 10, ?? );
		// add_action( 'comment_post', [ $this, 'on_product_review' ], 10, ?? );
		// add_action( 'update_option_woocommerce_currency', [ $this, 'on_update_currency' ], 10, ?? );
	}

	public function on_product_updated( $product_id ) {
		$this->add_scheduled_action( $this->get_async_update_hook_name(), [
			'id' => $product_id,
			'trigger' => 'update product',
			'action' => self::ACTION_UPDATED,
		], static::ASYNC_UPDATE_GROUP );
	}

	/* //TODO: rename and update data structure
	public function on_product_stock_status_change( $product_id, $stock_status, $product ) {
		switch ( $stock_status ) {
			case 'outofstock':
				$trigger = 'product out of stock';
				break;
			case 'instock':
				$trigger = 'product in stock';
				break;
			default:
				$trigger = 'product stock status changed [' . $stock_status . ']';
				break;
		}

		$this->add_scheduled_action( $this->get_async_update_hook_name(), [
			'id' => $product_id,
			'trigger' => $trigger,
			'action' => self::ACTION_UPDATED,
		], static::ASYNC_UPDATE_GROUP );
	}
	*/
	/**
	 * TODO: the trigger is wrong.
	 *
	 * @param $new_status
	 * @param $old_status
	 * @param $post
	 *
	 * @return void
	 */
	public function on_product_status_change( $new_status, $old_status, $post ) {
		// workaround - check issue in github
		// https://github.com/woocommerce/woocommerce/issues/23610
		if ( empty( wc_get_product( $post->ID ) ) ) {
			return;
		}

		$trigger = 'product status changed [`' . $old_status . '` -> `' . $new_status . '`]';

		$this->add_scheduled_action( $this->get_async_update_hook_name(), [
			'id' => $post->ID,
			'trigger' => $trigger,
			'action' => self::ACTION_UPDATED,
		], static::ASYNC_UPDATE_GROUP );
	}

	// TODO: move to async
	public function on_update_currency( $currency ) {
		return API_Helper::update_site_currency();
	}

	/* // TODO: move to async
	public function on_product_review( $comment_id, $comment_approved ) {
		$comment = get_comment( $comment_id );
		return API_Helper::send_meta_data( $comment, 'product review', 'NEW_COMMENT' );
	}
	// TODO: move to async
	public function on_coupon_save( $id ) {
		Logger::debug( 'Woo Commerce Logger: Coupon ID: ' . $id );
		$coupon = new \WC_Coupon( $id );
		$coupon_data = $coupon->get_data();
		return API_Helper::send_meta_data( $coupon_data, 'coupon save', 'NEW_COUPON' );
	}

	// TODO: move to async
	public function on_coupon_delete( $id ) {
		// Not working yet.
		Logger::debug( 'Woo Commerce Logger: Deleted Coupon ID: ' . $id );
		return API_Helper::send_meta_data( [ 'id' => $id ], 'coupon delete', 'couponDelete' );
	}

	public function on_create_category( $term_id, $tt_id, $taxonomy, $args ) {
		$this->sync_term( $term_id, $taxonomy, 'new category', 'CATEGORY_CREATED', 'Woo Commerce Logger: Category Created!' );
	}

	public function on_delete_category( $term, $tt_id, $taxonomy, $deleted_term, $object_ids ) {
		$this->sync_term( $term, $taxonomy, 'delete category', 'CATEGORY_DELETED', 'Woo Commerce Logger: Category Deleted' );
	}

	public function on_edit_category( $term_id, $tt_id, $taxonomy, $args ) {
		$this->sync_term( $term_id, $taxonomy, 'edit category', 'CATEGORY_UPDATED', 'Woo Commerce Logger: Category Edited' );
	}

	// TODO: maybe move to async
	protected function sync_term( $term_id, $taxonomy, $data_type, $action, $log_msg ) {
		if ( 'product_cat' !== $taxonomy ) {
			return;
		}

		$category = get_term( $term_id, 'product_cat' ); // Replace 'product_cat' with the appropriate taxonomy if needed
		if ( is_wp_error( $category ) ) {
			return;
		}

		Logger::debug( $log_msg );
		API_Helper::send_meta_data( $category, $data_type, $action, $log_msg );
	}
	*/
}
