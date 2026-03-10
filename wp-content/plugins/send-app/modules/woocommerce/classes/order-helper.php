<?php
namespace Send_App\Modules\Woocommerce\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Order_Helper {
	public static function get_order_data( \WC_Order $order, $cart_id = null ): array {
		$order_date = $order->get_date_created();
		$order_id = $order->get_id();

		$customer = Customer_Helper::get_customer_data_by_order( $order );

		$order_details = [
			'order_id' => $order_id,
			'type' => 'sync',
			'status' => $order->get_status(),
			'total_price' => $order->get_total(),
			'order_date_created' => $order_date->getTimestamp(),
			'order_date_paid' => $order->get_date_paid() ? $order->get_date_paid()->getTimestamp() : 0,
			'order_date_completed' => $order->get_date_completed() ? $order->get_date_completed()->getTimestamp() : 0,
			'order_date_modified' => $order->get_date_modified() ? $order->get_date_modified()->getTimestamp() : 0,
			'is_woo_customer' => true,
			'customer' => $customer,
			'payment_method' => $order->get_payment_method(),
			'payment_method_title' => $order->get_payment_method_title(),
			'customer_note' => $order->get_customer_note(),
			'order_items' => [], // Array of order items details
			'user_data' => $customer, // same as customer info - just for consistency with other functions
			'quantity' => $order->get_item_count(),
			'currency' => $order->get_currency(),
			'created_via' => $order->get_created_via(),
			'view_link' => $order->get_view_order_url(),
			// Add more details as needed using order object methods
		];

		if ( ! empty( $cart_id ) ) {
			$order_details['cart_id'] = $cart_id;
		}

		// Loop through order items and get details
		foreach ( $order->get_items() as $item_id => $item ) {
			$order_details['order_items'][] = [
				'product_id' => $item->get_product_id(),
				'variation_id' => $item->get_variation_id(),
				'name' => $item->get_name(),
				'quantity' => $item->get_quantity(),
				'subtotal' => $item->get_subtotal(),
				'total' => $item->get_total(),
				'tax_class' => $item->get_tax_class(),
				// Add more item details as needed using item object methods
			];
		}

		return $order_details;
	}

	public static function convert_to_string( array $ids ): array {
		foreach ( $ids as $key => $id ) {
			$ids[ $key ] = strval( $id );
		}

		return $ids;
	}

	public static function prepare_product_categories( $product ): array {
		$cat_ids = $product->get_category_ids();

		$categories = get_categories( [
			'taxonomy' => 'product_cat',
			'include' => $cat_ids,
		] );

		return array_map( function ( $cat ) {
			if ( is_array( $cat ) ) {
				$cat = (object) $cat;
			}

			return [
				'categoryId' => strval( $cat->cat_ID ),
				'name' => $cat->cat_name,
				'nicename' => $cat->category_nicename,
				'description' => $cat->category_description,
			];
		}, $categories );
	}

	public static function prepare_product_tags( $product ): array {
		$tag_ids = $product->get_tag_ids();

		$tags = get_tags( [
			'taxonomy' => 'product_tag',
			'include' => $tag_ids,
		] );

		return array_map( function ( $tag ) {
			if ( is_array( $tag ) ) {
				$tag = (object) $tag;
			}

			return [
				'tagId' => strval( $tag->term_id ),
				'name' => $tag->name,
				'slug' => $tag->slug,
				'taxonomy' => $tag->taxonomy,
			];
		}, $tags );
	}

	public static function prepare_supported_attributes( array $attributes ): array {
		return array_keys( $attributes );
	}

	public static function get_main_image( $product, string $size = 'thumbnail' ): ?array {
		$image_id = $product->get_image_id();
		if ( ! $image_id ) {

			return null;
		}

		$image_src = \wp_get_attachment_image_src( $image_id, $size );
		if ( empty( $image_src[0] ) ) {

			return null;
		}

		return [
			'id' => $image_id,
			'url' => $image_src[0],
		];
	}

	public static function get_gallery_images( $product, string $size = 'thumbnail' ): ?array {
		return []; //TODO: implement
	}

	public static function str_date_to_timestamp( $str_date ): int {
		if ( ! empty( $str_date ) ) {
			return strtotime( $str_date );
		}

		return 0;
	}
}
