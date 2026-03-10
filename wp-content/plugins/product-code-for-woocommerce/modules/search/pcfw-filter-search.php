<?php

/**
 * Remove method for an hook when, it's a class method used and class don't have global for instanciation !
 */
function pcfw_remove_filters_with_method_name($hook_name = '', $method_name = '', $priority = 0) {
	global $wp_filter;

	// Take only filters on right hook name and priority
	if (!isset($wp_filter[$hook_name][$priority]) || !is_array($wp_filter[$hook_name][$priority])) {
		return false;
	}

	// Loop on filters registered
	foreach ( (array) $wp_filter[$hook_name][$priority] as $unique_id => $filter_array) {
		if (isset($filter_array['function']) && is_array($filter_array['function'])) {
			if (is_object($filter_array['function'][0]) && get_class($filter_array['function'][0]) && $filter_array['function'][1] == $method_name) {
				unset($wp_filter[$hook_name][$priority][$unique_id]);
			}
		}
	}
	return false;
}

/**
 * Allow to remove method for an hook when, it's a class method used and class don't have variable, but you know the class name :)
 */
function pcfw_remove_filters_for_anonymous_class($hook_name = '', $class_name = '', $method_name = '', $priority = 0) {
	global $wp_filter;
	// Take only filters on right hook name and priority
	if (!isset($wp_filter[$hook_name][$priority]) || !is_array($wp_filter[$hook_name][$priority])) {
		return false;
	}

	// Loop on filters registered
	foreach ((array) $wp_filter[$hook_name][$priority] as $unique_id => $filter_array) {
		if (isset($filter_array['function']) && is_array($filter_array['function'])) {
			if (is_object($filter_array['function'][0]) && get_class($filter_array['function'][0]) && get_class($filter_array['function'][0]) == $class_name && $filter_array['function'][1] == $method_name) {
				unset($wp_filter[$hook_name][$priority][$unique_id]);
			}
		}
	}
	return false;
}
