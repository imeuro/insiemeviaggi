<?php
// Indexing variation product code
add_filter('relevanssi_content_to_index', 'rlv_index_variation_product_code', 10, 2);

function rlv_index_variation_product_code($content, $post) {
	if ('product' === $post->post_type) {
		$args = array(
			'post_parent' => $post->ID,
			'post_type' => 'product_variation',
			'posts_per_page' => -1
		);
		$variations = get_posts($args);
		if (!empty($variations)) {
			foreach ($variations as $variation) {
				$product_code = get_post_meta($variation->ID, '_product_code', true);
				$content .= " $product_code";
			}
		}
	}
	return $content;
}
