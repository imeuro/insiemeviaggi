<?php

namespace Artiosmedia\WC_Product_Code;

class PCFW_Services {

	private $admin_settings;
	public function __construct() {
		$this->admin_settings = new PCFW_Admin_Settings();
		$this->actions();
	}
	public function actions() {
		$this->admin_settings->actions();
		add_action('admin_init', array($this, 'validate_parent_plugin_exists'));
		add_action('wp_enqueue_scripts', [$this, 'enqueue']);
		add_action('admin_head', [$this, 'add_css']);
		add_filter('woocommerce_add_cart_item_data', [$this, 'add_code_to_cart_product'], 10, 3);
		add_filter('woocommerce_get_item_data', [$this, 'retrieve_product_code_in_cart'], 10, 2);
		add_action('woocommerce_checkout_create_order_line_item', [$this, 'process_order_item'], 10, 4);
		add_action('woocommerce_order_item_get_formatted_meta_data', [$this, 'get_formatted_order_item_meta_data'], 10, 2);
		add_action('woocommerce_order_item_display_meta_key', [$this, 'get_order_item_meta_display_key'], 10, 3);
		add_action('woocommerce_product_meta_start', [$this, 'display_product_code']);

		//add_action( 'woocommerce_product_meta_start', [ $this, 'display_product_code_second' ] );
		add_filter('body_class', [$this, 'PCFW_add_body_class']);

		add_filter('plugin_row_meta', [$this, 'plugin_row_filter'], 10, 3);
		add_action('wp_ajax_product_code', [$this, 'ajax_get_product_code']);
		add_action('wp_ajax_nopriv_product_code', [$this, 'ajax_get_product_code']);

		//Structured data
		add_filter('woocommerce_structured_data_product', array($this, 'structured_data_product_code'), 10, 2);

		// Shortcode
		add_shortcode('pcfw_display_product_code', array($this, 'product_code_shortcode'));
	}

	public function PCFW_add_body_class($classes) {
		if ('yes' == get_option('product_code_hide_empty_field')) {
			$classes[] = 'hide_pcode';
		}

		return $classes;
	}

	public function validate_parent_plugin_exists() {
		if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
			add_action('admin_notices', array($this, 'show_woocommerce_missing_notice'));
			add_action('network_admin_notices', array($this, 'show_woocommerce_missing_notice'));
			deactivate_plugins('product-code-for-woocommerce/product-code-for-woocommerce.php');
			if (isset($_GET['activate'])) {

				// Do not sanitize it because we are destroying the variables from URL
				unset($_GET['activate']);
			}
		}
	}

	public function enqueue() {
		global $post;
		if (( !empty($post) && 'product' == $post->post_type ) || is_wc_endpoint_url('order-pay') || is_wc_endpoint_url('order-received') || is_wc_endpoint_url('view-order')) :

			wp_enqueue_style('product-code-frontend', PRODUCT_CODE_URL . '/assets/css/single-product.css');
			wp_enqueue_script('product-code-for-woocommerce', PRODUCT_CODE_URL . '/assets/js/editor.js', ['jquery', 'wc-add-to-cart-variation'], PRODUCT_CODE_VERSION);
			wp_localize_script('product-code-for-woocommerce', 'PRODUCT_CODE', ['ajax' => admin_url('admin-ajax.php'), 'HIDE_EMPTY' => get_option('product_code_hide_empty_field')]);
		endif;
	}

	public function add_css() {
		wp_register_style('product-code-backend', PRODUCT_CODE_URL . '/assets/css/single-product.css', [], PRODUCT_CODE_VERSION, 'all');
		wp_enqueue_style('product-code-backend');
		
		// Hide WooCommerce GTIN field if option is enabled
		if ('yes' === get_option('pcfw_hide_wc_gtin_field', 'yes')) {
			echo '<style>._global_unique_id_field { display: none !important; }</style>';
		}
	}

	public function plugin_row_filter($links, $plugin_file, $plugin_data) {

		// Not our plugin.
		if (strpos($plugin_file, 'product-code-for-woocommerce.php') === false) {
			return $links;
		}

		$slug = basename($plugin_data['PluginURI']);

		// $link_template = $this->get_template( 'link' );
		$links[2] = sprintf('<a href="%s" title="More information about %s">%s</a>', add_query_arg([
			'tab'		 => 'plugin-information',
			'plugin'	 => $slug,
			'TB_iframe'	 => 'true',
			'width'		 => 772,
			'height'	 => 563,

		], self_admin_url('plugin-install.php')), $plugin_data['Name'], __('View Details', 'product-code-for-woocommerce'));

		$links['donation'] = sprintf(
			'<a href="%s" target="_blank">%s</a>',
			esc_url('https://www.zeffy.com/en-US/donation-form/your-donation-makes-a-difference-6'),
			__('Donation for Homeless', 'product-code-for-woocommerce')
		);

		return $links;
	}

	public function add_code_to_cart_product($cart_item_data, $product_id, $variation_id) {
		// Ensure $cart_item_data is always an array
		if (!is_array($cart_item_data)) {
			$cart_item_data = array();
		}
		
		$id = $variation_id ? $variation_id : $product_id;
		$simple_field_name = PRODUCT_CODE_FIELD_NAME;
		if (get_option('product_code') == 'yes') :
			$simple_value = get_post_meta($id, $simple_field_name, true);
			if ($simple_value) {
				$cart_item_data[$simple_field_name] = $simple_value;
			}
		endif;
		if (get_option('product_code_second') == 'yes' && get_option('product_code_second_show') == 'yes') :

			// Second Product Code adding to Cart
			$simple_field_name = PRODUCT_CODE_FIELD_NAME_SECOND;
			$simple_value = get_post_meta($id, $simple_field_name, true);
			if ($simple_value) {
				$cart_item_data[$simple_field_name] = $simple_value;
			}
		endif;

		return $cart_item_data;
	}
	public function retrieve_product_code_in_cart($cart_item_data, $cart_item) {
		// Ensure $cart_item_data is always an array
		if (!is_array($cart_item_data)) {
			$cart_item_data = array();
		}
		
		// Hide from customer-facing cart and checkout if option enabled
		if ('yes' === get_option('pcfw_hide_from_customer_orders', 'no') && !is_admin()) {
			return $cart_item_data;
		}
		
		$simple_field_name = PRODUCT_CODE_FIELD_NAME;
		$txt = get_option('product_code_text', '');
		$cart_data = [];
		$product_id = $cart_item['product_id'];

		if ('yes' == get_option('product_code')) :
			if (isset($cart_item[$simple_field_name])) {
				$cart_data[] = array(
					'name'	 => $txt ? $txt : __('Product Code', 'product-code-for-woocommerce'),
					'value'	 => $cart_item[$simple_field_name],
				);
			}
		endif;

		// Second product
		if ('yes' == get_option('product_code_second') && 'yes' == get_option('product_code_second_show')) :
			$simple_field_name = PRODUCT_CODE_FIELD_NAME_SECOND;
			$txt = get_option('product_code_text_second', '');

			if (isset($cart_item[$simple_field_name])) {

				$cart_data[] = array(
					'name'	 => $txt ? $txt : __('Product Code', 'product-code-for-woocommerce'),
					'value'	 => $cart_item[$simple_field_name],
				);
			}
		endif;

		return array_merge($cart_item_data, $cart_data);
	}
	public function process_order_item($item, $cart_item_key, $values, $order) {
		$simple_field_name	 = PRODUCT_CODE_FIELD_NAME;
		$txt			 = get_option('product_code_text', '');
		if (isset($values[$simple_field_name])) {
			$item->add_meta_data(( $txt ? $txt : __('Product Code', 'product-code-for-woocommerce') ), $values[$simple_field_name], false);
		}
		$simple_field_name	 = PRODUCT_CODE_FIELD_NAME_SECOND;
		$txt			 = get_option('product_code_text_second', '');
		if (isset($values[$simple_field_name])) {
			$item->add_meta_data(( $txt ? $txt : __('Product Code', 'product-code-for-woocommerce') ), $values[$simple_field_name], false);
		}
	}

	public function get_formatted_order_item_meta_data($formatted_meta, $item) {
		// Hide from customer-facing order pages if option enabled (still shows in admin)
		if ('yes' === get_option('pcfw_hide_from_customer_orders', 'no') && !is_admin()) {
			// Filter out product code entries from the meta
			$txt = get_option('product_code_text', '');
			$txt_second = get_option('product_code_text_second', '');
			$label_primary = $txt ? $txt : __('Product Code', 'product-code-for-woocommerce');
			$label_second = $txt_second ? $txt_second : __('Product Code', 'product-code-for-woocommerce');
			
			foreach ($formatted_meta as $idx => $meta) {
				// Remove by field name constant
				if ($meta->key === PRODUCT_CODE_FIELD_NAME || $meta->key === PRODUCT_CODE_FIELD_NAME_SECOND) {
					unset($formatted_meta[$idx]);
					continue;
				}
				// Remove by label (process_order_item saves meta with label as key)
				if ($meta->key === $label_primary || $meta->key === $label_second) {
					unset($formatted_meta[$idx]);
					continue;
				}
				// Also check display_key
				if (isset($meta->display_key) && ($meta->display_key === $label_primary || $meta->display_key === $label_second)) {
					unset($formatted_meta[$idx]);
				}
			}
			return $formatted_meta;
		}
		
		$field_name	 = PRODUCT_CODE_FIELD_NAME;

		$txt		 = get_option('product_code_text', '');

		foreach ($formatted_meta as $idx => $meta) {
			if ($meta->key === $field_name) {
				return $formatted_meta;
			}
		}

		$value = $item->get_meta($field_name);
		if (empty($value)) {
			return $formatted_meta;
		}

		

		$formatted_meta[$field_name] = (object) [
			'key'		 => $field_name,
			'value'		 => $value,
			'display_key'	 => $txt ? $txt : __('Product Code', 'product-code-for-woocommerce'),
			'display_value'	 => $value,
		];


		// Second Product Item Meta
		$field_name	 = PRODUCT_CODE_FIELD_NAME_SECOND;
		$txt		 = get_option('product_code_text_second', '');
		foreach ($formatted_meta as $idx => $meta) {
			if ($meta->key === $field_name) {
				return $formatted_meta;
			}
		}

		$value = $item->get_meta($field_name);
		if (empty($value)) {
			return $formatted_meta;
		}

		$formatted_meta[$field_name] = (object) [
			'key'		 => $field_name,
			'value'		 => $value,
			'display_key'	 => $txt ? $txt : __('Product Code', 'product-code-for-woocommerce'),
			'display_value'	 => $value,
		];
		return $formatted_meta;
	}

	public function get_order_item_meta_display_key($display_key, $meta, $item) {
		if (PRODUCT_CODE_FIELD_NAME === $meta->key) {
			$txt = get_option('product_code_text', '');
			return $txt ? $txt : __('Product Code', 'product-code-for-woocommerce');
		}

		if (PRODUCT_CODE_FIELD_NAME_SECOND === $meta->key) {
			$txt = get_option('product_code_text_second', '');
			return $txt ? $txt : __('Product Code', 'product-code-for-woocommerce');
		}
		return $display_key;
	}
	public function display_product_code() {
		if ('yes' == get_option('product_code')) {
			$post	 = get_post();
			$value	 = get_post_meta($post->ID, PRODUCT_CODE_FIELD_NAME, true);
			if ($this->contains_shortcode($value)) {
				$value = do_shortcode($value); // Process the shortcode
			}
			$text	 = get_option('product_code_text', '');
			$value_second	 = get_post_meta($post->ID, PRODUCT_CODE_FIELD_NAME_SECOND, true);
			if ($this->contains_shortcode($value_second)) {
				$value_second = do_shortcode($value_second); // Process the shortcode
			}

			$text_second	 = get_option('product_code_text_second', '');
			include_once(PRODUCT_CODE_TEMPLATE_PATH . '/product-meta-row.php');
		}
	}

	/**
	 * Check if a string contains a shortcode
	 *
	 * @param string $string The string to check
	 * @return bool True if the string contains a shortcode, false otherwise
	 */
	private function contains_shortcode($string) {
		// Check if the string contains the shortcode format [shortcode_name]
		if (is_string($string) && preg_match('/\[([a-zA-Z0-9_-]+)[^\]]*\]/', $string)) {
			return true;
		}
		return false;
	}


	public function ajax_get_product_code() {
		$post_data = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS);
		$simple_field_name	 = PRODUCT_CODE_FIELD_NAME;
		if ('second' == $post_data['code_num']) {
			$simple_field_name	 = PRODUCT_CODE_FIELD_NAME_SECOND;
		}

		$value = get_post_meta((int) $post_data['product_code_id'], $simple_field_name, true);

		echo json_encode([
			'status' => !empty($value),
			'data'	 => $value
		]);

		die;
	}


	public function show_woocommerce_missing_notice() {
		echo '<div class="notice notice-error is-dismissible">
            <p>' . esc_html__('Product Code for WooCommerce Add-on requires Woocommerce plugin to be installed and activated.', 'product-code-for-woocommerce') . '</p>
        </div>';
	}

	/**
	 * Add the product code to structured data.
	 * 
	 * @param $data
	 * @return mixed
	 */
	public function structured_data_product_code($data, $product) {

		// Applies only when enable
		if ('yes' == get_option('pcfw_structure_data')) {
			$property		 = apply_filters('pcfw_structured_data_property', get_option('pcfw_structured_data_field', 'gtin'), $product);
			$product_code = get_post_meta($product->get_id(), '_product_code');

			//$product_code_second = get_post_meta( $product->get_id(), '_product_code_second' );

			$data[$property]	 = !empty($product_code) ? $product_code : 'N/A';

			//$data[ $property ][]	 = ! empty( $product_code_second )? $product_code_second : "N/A";
		}

		return $data;
	}

	public function product_code_shortcode($atts) {
		global $post;

		$atts = shortcode_atts(array(
			'id'            => '',
			'pc_label'      => get_option('product_code_text', __('Product Code:', 'product-code-for-woocommerce')),
			'pcs_label'	=> get_option('product_code_text_second', __('Product Code Second:', 'product-code-for-woocommerce')),
			'wrapper'       => is_shop() ? 'div' : 'span',
			'wrapper_code'  => 'span',
			'class_wrapper' => 'pcfw_code_wrapper',
			'class'         => 'pcfw_code',
		), $atts, 'pcfw_display_product_code');

		if (!empty($atts['id'])) {
			$product_data = get_post($atts['id']);
		} elseif (!is_null($post)) {
			$product_data = $post;
		} else {
			return '';
		}

		$product = is_object($product_data) && in_array($product_data->post_type, array(
			'product',
			'product_variation'
		), true) ? wc_setup_product_data($product_data) : false;

		if (!$product) {
			return '';
		}

		// Product Code
		$is_product_code = $product->get_meta('_product_code');
		$is_product_code_second = $product->get_meta('_product_code_second');
		$product_code = !empty($is_product_code) ? $is_product_code : 'N/A';
		$product_code_second = !empty($is_product_code_second) ? $is_product_code_second : 'N/A';

		ob_start();

		if ($product_code || ( 'yes' != get_option('product_code_hide_empty_field') && !$product_code ) || ( is_single() && $product->is_type('variable') )) :
			echo wp_kses_post(sprintf('<%1$s class="%3$s">%2$s: <%4$s class="%5$s" data-product-id="%7$s">%6$s</%4$s></%1$s>', esc_html($atts['wrapper']), esc_html($atts['pc_label']), esc_attr($atts['class_wrapper']), esc_html($atts['wrapper_code']), esc_attr($atts['class']), esc_html($product_code), $product->get_id()));
		endif;

		if ('yes' == get_option('product_code_second')) {
			if ($product_code_second || ( 'yes' != get_option('product_code_hide_empty_field') && !$product_code_second ) || ( is_single() && $product->is_type('variable') )) :
				echo wp_kses_post(sprintf('<br><%1$s class="%3$s">%2$s: <%4$s class="%5$s" data-product-id="%7$s">%6$s</%4$s></%1$s>', esc_html($atts['wrapper']), esc_html($atts['pcs_label']), esc_attr($atts['class_wrapper']), esc_html($atts['wrapper_code']), esc_attr($atts['class']), esc_html($product_code_second), $product->get_id()));
			endif;
		}

		return ob_get_clean();
	}
}
