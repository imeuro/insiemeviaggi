<?php



add_filter('searchwp_extra_metadata', 'my_searchwp_index_woocommerce_variation_product_code', 10, 2);



// index WooCommerce product_variation product code with the parent post

function my_searchwp_index_woocommerce_variation_product_code($extra_meta, $post_being_indexed) {



	// we only care about WooCommerce Products

	if ('product' !== get_post_type($post_being_indexed)) {

		return $extra_meta;
	}



	// retrieve all the product variations

	$args = array(

		'post_type'       => 'product_variation',

		'posts_per_page'  => -1,

		'fields'          => 'ids',

		'post_parent'     => $post_being_indexed->ID,

	);

	$product_variations = get_posts($args);



	if (!empty($product_variations)) {



		$extra_meta['_product_code'] = array();

		$extra_meta['_product_code_second'] = array();





		// loop through all product variations, grab and store the SKU

		foreach ($product_variations as $product_variation) {

			$extra_meta['_product_code'][] = get_post_meta(absint($product_variation), '_product_code', true);

			$extra_meta['_product_code_second'][] = get_post_meta(absint($product_variation), '_product_code_second', true);
		}
	}



	return $extra_meta;
}



add_filter('searchwp_custom_field_keys', 'my_searchwp_custom_field_keys_product_code_variant', 10, 1);



function my_searchwp_custom_field_keys_product_code_variant($keys) {

	$keys[] = '_product_code';

	$keys[] = '_product_code_second';

	return $keys;
}
