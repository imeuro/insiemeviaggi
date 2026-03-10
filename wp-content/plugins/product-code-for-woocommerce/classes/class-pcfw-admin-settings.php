<?php

namespace Artiosmedia\WC_Product_Code;

class PCFW_Admin_Settings {

	public function actions() {

		// update_option( "product_code_db_updated",false);
		add_action('admin_enqueue_scripts', [$this, 'enqueue']);
		add_action('woocommerce_product_options_inventory_product_data', [
			$this,
			'add_inventory_field'
		]);
		add_action('woocommerce_process_product_meta', [$this, 'save_product_code_meta']);
		add_action('woocommerce_product_after_variable_attributes', [
			$this,
			'add_variation_field'
		], 10, 3);
		add_action(
			'woocommerce_save_product_variation',
			[$this, 'save_variation_field'],
			10,
			2
		);

		// add_action( 'woocommerce_product_quick_edit_end', [ $this, 'add_quick_edit_field' ] );
		add_action('admin_notices', array($this, 'admin_notice'));
		add_action('wp_ajax_product_code_dismiss_notice', array($this, 'dismiss_notice'));
		add_action('wp_ajax_product_code_review_clicked', array($this,'review_clicked'));


		// Will be removed in next update
		add_action('wp_ajax_product_code_update_database', array($this, 'update_database'));


		//add product code to admin column
		add_action('manage_edit-product_columns', array($this, 'product_column'));
		add_action('manage_posts_custom_column', array($this, 'product_column_value'));

		//add product code to quick edit
		add_action('woocommerce_product_quick_edit_start', array($this, 'pcfw_woocommerce_product_quick_edit_start'));
		add_action('woocommerce_product_quick_edit_save', array($this, 'pcfw_woocommerce_product_quick_edit_save'));


		add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));

		add_filter('manage_edit-product_sortable_columns', array($this, 'sortable_columns'));

		add_action('pre_get_posts', array($this, 'sortable_column_query'));
	}

	public function enqueue() {
		$screen = get_current_screen();
		wp_enqueue_script('product-code-admin-generic', sprintf(
			'%s/assets/js/generic_admin.js',
			PRODUCT_CODE_URL
		), array('jquery'), PRODUCT_CODE_VERSION);

		wp_localize_script('product-code-admin-generic', 'PRODUCT_CODE_ADMIN', array('ajax' => admin_url('admin-ajax.php')));

		// Localize for support button notification (only needed on settings page but safe to include always)
		wp_localize_script('product-code-admin-generic', 'pcfw', [
			'ajaxurl' => admin_url('admin-ajax.php'),
			'nonce'   => wp_create_nonce('pcfw_admin_nonce'),
		]);

		if ($screen && 'product' === $screen->post_type) {

			wp_enqueue_script('wc_product_code_admin', PRODUCT_CODE_URL . '/assets/js/' .
				'stl_admin_custom.js', ['jquery'], PRODUCT_CODE_VERSION);
			wp_localize_script('wc_product_code_admin', 'i18n', array('product_code_title' =>  __('Product code', 'product-code-for-woocommerce')));

			add_action('admin_footer', array($this, 'inline_javascript'));
		}
	}

	public function update_database() {

		// Check if the user has the required capability
		check_ajax_referer('product_code_update_nonce', '_ajax_nonce');

		if (current_user_can('manage_options')) {
			global $wpdb;

			$results = $wpdb->get_results("UPDATE {$wpdb->prefix}postmeta SET meta_key = '_product_code' WHERE meta_key = '_product_code_variant'", ARRAY_A);
			$results2 = $wpdb->get_results("UPDATE {$wpdb->prefix}postmeta SET meta_key = '_product_code_second' WHERE meta_key = '_product_code_variant_second'", ARRAY_A);

			if (is_array($results) && is_array($results2)) {
				esc_html_e('Successfully Updated Redirecting back....', 'product-code-for-woocommerce');
				$results2 = $wpdb->get_results("DELETE FROM {$wpdb->prefix}postmeta WHERE meta_key = '_product_code_variant'", ARRAY_A);
				update_option('product_code_db_updated', true);
			} else {
				esc_html_e(
					'Failed to update please retry later, Redirecting back... ',
					'product-code-for-woocommerce'
				);
			}

			echo "<script>
              window.setTimeout(function() {
                  window.location.replace('" . esc_url_raw(admin_url('admin.php?page=wc-settings&tab=products&section=product_code_settings')) .
				"');
              }, 3000);
          </script>";
		}

		die();
	}

	public function admin_notice() {
		

		if (!is_plugin_active('product-code-for-woocommerce/product-code-for-woocommerce.php')) {
			return;
		}
	
		$user_id = get_current_user_id();
		$pcfw_clicked = get_user_meta($user_id, 'pcfw_notice_clicked', true);
		$last_pcfw_time = get_user_meta($user_id, 'pcfw_notice_last_time', true);
		$current_time = time();
		$thirty_days = 30 * DAY_IN_SECONDS;
	
		// If the review was clicked, never show again
		if ($pcfw_clicked) {
			return;
		}

		// If no timestamp exists (fresh install), set it now and wait 30 days
		if (!$last_pcfw_time) {
			update_user_meta($user_id, 'pcfw_notice_last_time', $current_time);
			return;
		}

		// If 30 days have NOT passed, do NOT show the notice
		if (($current_time - $last_pcfw_time) < $thirty_days) {
			return;
		}

		echo '<div class="notice notice-info is-dismissible product_code_notice" id="pcfw_review_notice">
        <p>How do you like <strong>Product Code for WooCommerce</strong>? Your feedback assures the continued maintenance of this plugin! <a id="pcfw_review_link" class="button button-primary" href="https://wordpress.org/plugins/product-code-for-woocommerce/#reviews" target="_blank">Leave Feedback</a></p>
        </div>';
	}

	public function dismiss_notice() {
		$user_id = get_current_user_id();
		update_user_meta($user_id, 'pcfw_notice_last_time', time());
		wp_die();
	}

	public function review_clicked() {

		$user_id = get_current_user_id();
		update_user_meta($user_id, 'pcfw_notice_clicked', 1);
		wp_die();
	}

	/*

	public function dismiss_notice() {
		$post_data = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS);

		if (current_user_can('manage_options')) {
			if (!empty(sanitize_text_field($post_data['dismissed_final']))) {
				update_option('product_code_notice_dismiss', null);
			} else {
				update_option('product_code_notice_dismiss', gmdate('Y-m-d', strtotime('+30 days')));
			}
			wp_send_json(array('status' => true));
		}
	}
	
	public function admin_notice() {
		$last_dismissed = get_option('product_code_notice_dismiss');

		if ($last_dismissed && current_time('timestamp') >= strtotime($last_dismissed)) {
			echo '<div class="notice notice-info is-dismissible" id="product_code_notice">
            <p>How do you like <strong>Product Code for WooCommerce</strong>? Your feedback assures the continued maintenance of this plugin! <a id="pcfw-feedback-done" class="button button-primary" href="https://wordpress.org/plugins/product-code-for-woocommerce/#reviews" target="_blank">Leave Feedback</a></p>
            </div>';
		}
	}
	*/

	public function add_inventory_field() {
		global $post;
		$product = wc_get_product($post->ID);
		$label = $this->get_field_title_text();
		$label_second = $this->get_second_field_title_text();
		$displaySecond = get_option('product_code_second_show');

		$html = '';

		if (!$product->is_type('variable')) {
			$html .= woocommerce_wp_text_input([
				'id' => PRODUCT_CODE_FIELD_NAME,
				'label' => $label,
				'desc_tip' => true,
				'description' => sprintf(
					/* translators: 1 for label */
					__('%s refers to a company\'s unique internal product identifier, needed for online product fulfillment.', 'product-code-for-woocommerce'),
					$label
				),
				'value' => get_post_meta($post->ID, PRODUCT_CODE_FIELD_NAME, true)
			]);

			if ('yes' == $displaySecond) {

				$html .= woocommerce_wp_text_input([
					'id' => PRODUCT_CODE_FIELD_NAME_SECOND,
					'label' => $label_second,
					'desc_tip' => true,
					'description' => sprintf(
						/* translators: 1 for label */
						__('%s refers to a company\'s unique internal product identifier, needed for online product fulfillment.', 'product-code-for-woocommerce'),
						$label_second
					),
					'value' => get_post_meta($post->ID, PRODUCT_CODE_FIELD_NAME_SECOND, true)
				]);
			}
		}
		return $html;
	}

	public function save_product_code_meta() {
		$post_data = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS);
		global $post;

		if (
			'product' == $post->post_type && !empty($post_data['woocommerce_meta_nonce']) &&
			wp_verify_nonce($post_data['woocommerce_meta_nonce'], 'woocommerce_save_data')
		) {
			$field_name = PRODUCT_CODE_FIELD_NAME;

			if (!empty($post_data[$field_name])) {
				$code = sanitize_text_field($post_data[$field_name]);

				$add_meta_result = add_post_meta($post->ID, $field_name, $code, true);
				if (!$add_meta_result) {
					update_post_meta($post->ID, $field_name, $code);
				}
			} else {
				delete_post_meta($post->ID, $field_name);
			}

			// Saving Second Field Product Meta
			$field_name = PRODUCT_CODE_FIELD_NAME_SECOND;
			if (!empty($post_data[$field_name])) {
				$code = sanitize_text_field($post_data[$field_name]);
				if (!add_post_meta($post->ID, $field_name, $code, true)) {
					update_post_meta($post->ID, $field_name, $code);
				}
			} else {
				delete_post_meta($post->ID, $field_name);
			}
		}
		return;
	}

	public function add_variation_field($i, $arr, $variation) {
		$field_name = PRODUCT_CODE_FIELD_NAME;
		$code = get_post_meta($variation->ID, $field_name, true);
		$field_name_second = PRODUCT_CODE_FIELD_NAME_SECOND;
		$code_second = get_post_meta($variation->ID, $field_name_second, true);
		$displaySecond = get_option('product_code_second_show');
		require(PRODUCT_CODE_TEMPLATE_PATH . '/variation-field.php');
		return;
	}

	public function save_variation_field($variation_id, $i) {
		$post_data = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS);

		$field_name = PRODUCT_CODE_FIELD_NAME;
		$form_field_name = sprintf('%s_%d', PRODUCT_CODE_FIELD_NAME, $i);

		if (!empty($post_data[$form_field_name])) {
			$code = sanitize_text_field($post_data[$form_field_name]);
			if (!add_post_meta($variation_id, $field_name, $code, true)) {
				update_post_meta($variation_id, $field_name, $code);
			}
		} else {
			delete_post_meta($variation_id, $field_name);
		}

		// Second Field Saving
		$field_name = PRODUCT_CODE_FIELD_NAME_SECOND;
		$form_field_name = sprintf('%s_%d', PRODUCT_CODE_FIELD_NAME_SECOND, $i);

		if (!empty($post_data[$form_field_name])) {
			$code = sanitize_text_field($post_data[$form_field_name]);
			if (!add_post_meta($variation_id, $field_name, $code, true)) {
				update_post_meta($variation_id, $field_name, $code);
			}
		} else {
			delete_post_meta($variation_id, $field_name);
		}

		return;
	}

	public function add_quick_edit_field() {
		require_once(PRODUCT_CODE_TEMPLATE_PATH . '/quick-edit-text-field.php');
	}

	/**
	 * Fetch field title text to be displayed at backend and frondend
	 */

	public function get_field_title_text() {
		$field_title = get_option('product_code_text');
		if ($field_title) {
			return $field_title;
		}

		return __('Product code', 'product-code-for-woocommerce');
	}

	public function get_second_field_title_text() {
		$field_title = get_option('product_code_text_second');
		if ($field_title) {
			return $field_title;
		}

		return __('Product code 2', 'product-code-for-woocommerce');
	}

	public function get_edit_field_title_text() {
		$field_title = get_option('product_code_quik_edit_text');
		if ($field_title) {
			return $field_title;
		}

		return __('Code', 'product-code-for-woocommerce');
	}

	public function get_second_edit_field_title_text() {
		$field_title = get_option('product_code_quik_edit_text_second');
		if ($field_title) {
			return $field_title;
		}

		return __('Code 2', 'product-code-for-woocommerce');
	}


	/**
	 * Add product code to admin column
	 *
	 * @since    1.2.9
	 */
	public function product_column($columns) {
		$new_columns = [];
		foreach ($columns as $key => $value) {
			$new_columns[$key] = $value;

			if ('sku' === $key) {

				$label = $this->get_edit_field_title_text();
				$label_second = $this->get_second_edit_field_title_text();

				$new_columns['pcw_product_code'] = $label;
				if ('yes' == get_option('product_code_second_show')) {
					$new_columns['pcw_product_code_2'] =  $label_second;
				}
			}
		}

		return $new_columns;
	}

	public function product_column_value($column_name) {
		global $post;

		if (is_object($post) && isset($post->post_type) && 'product' === $post->post_type && 'pcw_product_code' === $column_name) {

			$product = wc_get_product($post->ID);
			$type = $product->get_type();
			if ('variable' === $type) {
				$product_code = '';
			} else {
				$product_code = get_post_meta($post->ID, PRODUCT_CODE_FIELD_NAME, true);
			}

			if (empty(trim($product_code))) {
				echo '-';
			} else {
				echo esc_html($product_code);
			}
			?>
			<div style="display:none;" class="hidden" id="pcfw_woocommerce_inline_<?php echo absint($post->ID); ?>">
				<div class="product_code_val"><?php echo esc_html(get_post_meta($post->ID, PRODUCT_CODE_FIELD_NAME, true)); ?></div>
				<div class="product_code_2_val"><?php echo esc_html(get_post_meta($post->ID, PRODUCT_CODE_FIELD_NAME_SECOND, true)); ?></div>
			<?php
		}

		if (is_object($post) && isset($post->post_type) && 'product' === $post->post_type && 'pcw_product_code_2' === $column_name) {

			$product = wc_get_product($post->ID);
			$type = $product->get_type();
			if ('variable' === $type) {
				$product_code = '';
			} else {
				$product_code = get_post_meta($post->ID, PRODUCT_CODE_FIELD_NAME_SECOND, true);
			}

			if (empty(trim($product_code))) {
				echo '-';
			} else {
				echo esc_html($product_code);
			}
		}
	}

	public function inline_javascript() {
		?>
			<script>
				jQuery(function() {
					jQuery(document).ready(function() {
						// -------------------------------------------------------------
						//  Change product code toggle text
						// -------------------------------------------------------------
						if (jQuery('body').hasClass('post-type-product')) {
							if (jQuery('input#pcw_product_code-hide').length > 0) {
								document.getElementById('pcw_product_code-hide').parentNode.childNodes[1].textContent = '<?php esc_html_e('Product code', 'product-code-for-woocommerce'); ?>';
							}
						}

						if (jQuery('body').hasClass('post-type-product')) {
							if (jQuery('input#pcw_product_code_2-hide').length > 0) {
								document.getElementById('pcw_product_code_2-hide').parentNode.childNodes[1].textContent = '<?php esc_html_e('Product code 2', 'product-code-for-woocommerce'); ?>';
							}
						}
					});
				});
			</script>
		<?php
	}

	public function pcfw_woocommerce_product_quick_edit_start() {

		if (get_option('product_code_second_show') == 'yes') {
			$show_product_code_2 = true;
			$product_one_input_style = '';
		} else {
			$show_product_code_2 = false;
			$product_one_input_style = 'width: 99% !important';
		}
		$label = $this->get_field_title_text();
		$label_second = $this->get_second_field_title_text();
		?>
			<label class="product_code_1_label">
				<span class="title product_code_title"><?php echo esc_html($label); ?></span>
				<span class="input-text-wrap">
					<input style="<?php echo esc_attr($product_one_input_style); ?>" type="text" name="<?php echo esc_html(PRODUCT_CODE_FIELD_NAME); ?>" class="text product_code_field" value="">
				</span>
			</label>
			<?php if ($show_product_code_2) { ?>
				<label class="product_code_2_label">
					<span class="title product_code_title_2">&nbsp; <?php echo esc_html($label_second); ?>&nbsp;</span>
					<span class="input-text-wrap">
						<input type="text" name="<?php echo esc_attr(PRODUCT_CODE_FIELD_NAME_SECOND); ?>" class="text product_code_field" value="">
					</span>
				</label>
			<?php } ?>
			<br class="product_code_clear clear" />
	<?php
	}

	public function pcfw_woocommerce_product_quick_edit_save($product) {
		$post_data = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS);

		$product_id = $product->get_id();

		$field_name = PRODUCT_CODE_FIELD_NAME;
		if (!empty($post_data[$field_name])) {
			$code = sanitize_text_field($post_data[$field_name]);
			if (!add_post_meta($product_id, $field_name, $code, true)) {
				update_post_meta($product_id, $field_name, $code);
			}
		} else {
			delete_post_meta($product_id, $field_name);
		}

		// Saving Second Field Product Meta
		$field_name = PRODUCT_CODE_FIELD_NAME_SECOND;
		if (!empty($post_data[$field_name])) {
			$code = sanitize_text_field($post_data[$field_name]);
			if (!add_post_meta($product_id, $field_name, $code, true)) {
				update_post_meta($product_id, $field_name, $code);
			}
		} else {
			delete_post_meta($product_id, $field_name);
		}
	}

	public function admin_enqueue_scripts() {
		if ('woocommerce_page_wc-settings' != get_current_screen()->id || !isset($_GET['section']) || 'product_code_settings' !== $_GET['section']) {
			return;
		}

		wp_enqueue_style('product-code-woocommerce', PRODUCT_CODE_URL . '/assets/css/dashboard.css');
	}

	/**
	 * Make product code column sortable
	 * 
	 * @since 1.4.3
	 * @return array
	 */
	public function sortable_columns($columns) {
		$columns['pcw_product_code'] = 'product_code';
		$columns['pcw_product_code_2'] = 'product_code_2';
		return $columns;
	}

	/**
	 * Sortable product table
	 * 
	 * @since 1.4.3
	 * @return void
	 */
	public function sortable_column_query($query) {
		if (!isset($_GET['post_type'])) {
			return;
		}

		if (!is_admin() || 'product' != $_GET['post_type']) {
			return;
		}

		if (isset($_GET['orderby']) && ( 'product_code' == $_GET['orderby'] || 'product_code_2' == $_GET['orderby'] )) {
			$meta_key = 'product_code_2' == $_GET['orderby'] ? '_product_code_second' : '_product_code';

			$meta_query = array(
				'relation' => 'OR',
				array(
					'key' => $meta_key,
					'compare' => 'NOT EXISTS',
				),
				array(
					'key' => $meta_key,
				),
			);

			$query->set('meta_query', $meta_query);
			$query->set('orderby', 'meta_value');
		}
	}
}
