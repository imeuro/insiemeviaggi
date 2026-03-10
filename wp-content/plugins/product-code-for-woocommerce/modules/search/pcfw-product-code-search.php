<?php

/**
 * Replacement of WC_Admin_Post_Types::product_search()
 */
add_filter('posts_search', 'pcfw_product_code_search', 9);
function pcfw_product_code_search($where) {
	global $pagenow, $wpdb, $wp;
	$type = array('product', 'product_variation');
	if (( is_admin() && 'edit.php' != $pagenow )
		|| !is_search()
		|| !isset($wp->query_vars['s'])
		//post_types can also be arrays..
		|| ( isset($wp->query_vars['post_type']) && 'product' != $wp->query_vars['post_type'] )
		|| ( isset($wp->query_vars['post_type']) && is_array($wp->query_vars['post_type']) && !in_array('product', $wp->query_vars['post_type']) )
	) {
		return $where;
	}
	$search_ids = array();
	$terms = explode(',', $wp->query_vars['s']);

	foreach ($terms as $term) {
		//Include search by id if admin area.
		if (is_admin() && is_numeric($term)) {
			$search_ids[] = $term;
		}
		// search variations with a matching product code and return the parent.
		$term_like = '%' . $wpdb->esc_like($term) . '%';
		$product_code_to_parent_id = $wpdb->get_col($wpdb->prepare("SELECT p.post_parent as post_id FROM {$wpdb->posts} as p join {$wpdb->postmeta} pm on p.ID = pm.post_id and pm.meta_key IN('_product_code', '_product_code_second' ) and pm.meta_value LIKE %s where p.post_parent <> 0 group by p.post_parent", $term_like));

		//Search a regular product that matches the product code.
		$product_code_to_id = $wpdb->get_col($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ('_product_code', '_product_code_second' ) AND meta_value LIKE %s;", $term_like));

		$search_ids = array_merge($search_ids, $product_code_to_id, $product_code_to_parent_id);
	}

	$search_ids = array_filter(array_map('absint', $search_ids));

	if (count($search_ids) > 0) {
		$where = str_replace(')))', ") OR ({$wpdb->posts}.ID IN (" . implode(',', $search_ids) . '))))', $where);
	}

	pcfw_remove_filters_for_anonymous_class('posts_search', 'WC_Admin_Post_Types', 'product_search', 10);
	return $where;
}

/**
 * Search products by product code via administartor product list page
 */
add_filter('request', 'pcfw_product_code_admin_search', 20);
function pcfw_product_code_admin_search($query_vars) {
	global $typenow;
	global $wpdb;
	global $pagenow;

	if (is_admin() && 'product' === $typenow && isset($_GET['s']) && 'edit.php' === $pagenow) {
		$search_term            = esc_sql(sanitize_text_field($_GET['s']));
		$search_results         = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT posts.ID as product_id, posts.post_parent as parent_id FROM {$wpdb->posts} posts LEFT JOIN {$wpdb->postmeta} AS postmeta ON posts.ID = postmeta.post_id WHERE postmeta.meta_key IN('_product_code_second', '_product_code' )  AND postmeta.meta_value LIKE %s AND posts.post_type IN ('product', 'product_variation') ORDER BY posts.post_parent ASC, posts.post_title ASC",
				'%' . $wpdb->esc_like($search_term) . '%'
			)
		);
		$product_ids            = wp_parse_id_list(array_merge(wp_list_pluck($search_results, 'product_id'), wp_list_pluck($search_results, 'parent_id')));
		if (isset($query_vars['post__in'])) {
			$query_vars['post__in'] = array_merge($product_ids, $query_vars['post__in']);
		}
	}

	return $query_vars;
}
