<?php
namespace Send_App\Modules\Woocommerce\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Cart_Helper {
	public static function prepare_cart_data( \WC_Cart $cart ): array {
		$items = $cart->get_cart_contents();
		$cart_link = \wc_get_cart_url();
		$currency = \get_woocommerce_currency();
		$precision = \wc_get_price_decimals();

		return [
			'event_timestamp' => time(),
			'cart_link' => $cart_link,
			'currency' => $currency,
			'cart_id' => Cart_Id_Helper::create_or_get_cart_id(),
			'cart_value' => \wc_format_decimal( $cart->get_total( 'edit' ), $precision ),
			'cart_discount' => \wc_format_decimal( $cart->get_discount_total(), $precision ),
			'items_count' => $cart->get_cart_contents_count(),
			'items' => self::prepare_cart_items( $items, $precision ),
			'customer' => Customer_Helper::get_customer_from_cookie(),
		];
	}

	private static function prepare_cart_items( array $cart_items, int $precision ): array {
		$prepared_items = [];
		foreach ( $cart_items as $key => $cart_item ) {
			$product_id = $cart_item['product_id'];
			$variation_id = 0 === $cart_item['variation_id'] ? null : $cart_item['variation_id'];
			$product = \wc_get_product( $variation_id ?? $product_id );

			$prepared_items[] = [
				'product_id' => $product_id,
				'variation_id' => $variation_id,
				'price' => \wc_format_decimal( $product->get_price(), $precision ),
				'name' => $product->get_name(),
				'sku' => $product->get_sku(),
				'quantity' => $cart_item['quantity'],
				'subtotal' => self::prepare_item_total_by_key( $cart_item, 'line_subtotal', $precision ),
				'subtotal_tax' => self::prepare_item_total_by_key( $cart_item, 'line_subtotal_tax', $precision ),
				'total_tax' => self::prepare_item_total_by_key( $cart_item, 'line_tax', $precision ),
				'total' => self::prepare_item_total_by_key( $cart_item, 'line_total', $precision ),
			];
		}

		return $prepared_items;
	}

	private static function prepare_item_total_by_key( $cart_item, $key, $precision ): ?string {
		return isset( $cart_item[ $key ] ) ? \wc_format_decimal( $cart_item[ $key ], $precision ) : null;
	}
}
