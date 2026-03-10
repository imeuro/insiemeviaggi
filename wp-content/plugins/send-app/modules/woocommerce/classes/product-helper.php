<?php
namespace Send_App\Modules\Woocommerce\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Product_Helper {
	public static function get_product_data( \WC_Abstract_Legacy_Product $product ): array {
		$upsell_ids = $product->get_upsell_ids();
		$cross_sell_ids = $product->get_cross_sell_ids();

		return [
			'productId' => strval( $product->get_id() ),
			'parentId' => strval( $product->get_parent_id() ),
			'dateCreated' => self::str_date_to_timestamp( $product->get_date_created() ),
			'dateModified' => self::str_date_to_timestamp( $product->get_date_modified() ),
			'name' => $product->get_name(),
			'description' => $product->get_description(),
			'shortDescription' => $product->get_short_description(),
			'sku' => $product->get_sku(),
			'status' => $product->get_status(),
			'pricing' => [
				'current' => $product->get_price(),
				'list' => $product->get_regular_price(),
				'sale' => $product->get_sale_price(),
				'saleStart' => self::str_date_to_timestamp( $product->get_date_on_sale_from() ),
				'saleEnd' => self::str_date_to_timestamp( $product->get_date_on_sale_to() ),
			],
			'categories' => self::prepare_product_categories( $product ),
			'tags' => self::prepare_product_tags( $product ),
			'images' => [
				'mainImage' => self::get_main_image( $product ),
				'galleryImages' => self::get_gallery_images( $product ),
			],
			'inventory' => [
				'managed' => $product->managing_stock(),
				'quantity' => $product->get_stock_quantity(),
				'status' => $product->get_stock_status(),
				'lowStockThreshold' => $product->get_low_stock_amount(),
			],
			'attributes' => $product->get_attributes(),
			'supportedAttributes' => [], //TODO: find the way to extract this data. string[] '_product_attributes'
			'addToCartUrl' => $product->add_to_cart_url(),
			'permalink' => $product->get_permalink(),
			'featured' => $product->is_featured(),
			'totalSales' => $product->get_total_sales(),
			'upsellProductIds' => self::convert_to_string( $upsell_ids ),
			'crossSellProductIds' => self::convert_to_string( $cross_sell_ids ),
			'downloadable' => $product->is_downloadable(),
			'downloadLimit' => $product->get_download_limit(),
			'downloadExpiry' => $product->get_download_expiry(),
			'virtual' => $product->is_virtual(),
			'catalogVisibility' => $product->get_catalog_visibility(),
			'backOrders' => $product->get_backorders(),
			'soldIndividually' => $product->is_sold_individually(),
			'shipping' => [
				'classId' => strval( $product->get_shipping_class_id() ),
				'weight' => $product->get_weight(),
				'height' => $product->get_height(),
				'length' => $product->get_length(),
			],
			'reviews' => [
				'allowed' => $product->get_reviews_allowed(),
				'count' => $product->get_review_count(),
			],
			'rating' => [
				'average' => strval( $product->get_average_rating() ),
				'count' => $product->get_rating_count(),
				'votes' => $product->get_rating_counts(),
			],
		];
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
