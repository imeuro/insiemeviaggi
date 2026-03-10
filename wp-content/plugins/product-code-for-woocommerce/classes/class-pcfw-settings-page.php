<?php

namespace Artiosmedia\WC_Product_Code;

class PCFW_Settings_Page {

	const MENU_SLUG = 'woocommerce';

	public static $instance;

		public function __construct() {
			if (!is_admin()) {
				return;
			}
			add_action('admin_menu', [$this, 'admin_menu'], 90);
			add_filter('plugin_action_links_' . plugin_basename(PRODUCT_CODE_PATH . 'product-code-for-woocommerce.php'), [$this, 'plugin_settings_link']);
			add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_styles']);
			add_action('admin_init', [$this, 'save_settings']);
			add_action('wp_ajax_pcfw_support_notification', [$this, 'pcfw_support_notification']);
		}

		public static function get_instance() {
			if (!isset(self::$instance)) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		public function admin_menu() {
			add_submenu_page(
				self::MENU_SLUG,
				esc_html__('Product Code', 'product-code-for-woocommerce'),
				esc_html__('Product Code', 'product-code-for-woocommerce'),
				'manage_options',
				'pcfw_settings',
				[$this, 'render_settings_page']
			);
		}

		public function plugin_settings_link($links) {
			return array_merge(array(
				'<a href="' . admin_url('admin.php?page=pcfw_settings') . '">' . esc_html__('Settings', 'product-code-for-woocommerce') . '</a>'
			), $links);
		}

		public function enqueue_admin_styles($hook) {
			if ('woocommerce_page_pcfw_settings' !== $hook) {
				return;
			}

			wp_enqueue_style(
				'pcfw-settings-page',
				PRODUCT_CODE_URL . '/assets/css/settings-page.css',
				[],
				PRODUCT_CODE_VERSION
			);
		}

		public function save_settings() {
			if (!isset($_POST['pcfw_submit']) || !current_user_can('manage_options')) {
				return;
			}

			if (!wp_verify_nonce($_POST['pcfw_settings_nonce'], 'pcfw_save_settings')) {
				return;
			}

			// Primary Product Code settings
			update_option('product_code', isset($_POST['product_code']) ? 'yes' : 'no');
			update_option('hide_product_code_on_user_side', isset($_POST['hide_product_code_on_user_side']) ? 'yes' : 'no');
			update_option('pcfw_hide_from_customer_orders', isset($_POST['pcfw_hide_from_customer_orders']) ? 'yes' : 'no');
			update_option('product_code_text', sanitize_text_field($_POST['product_code_text'] ?? 'Product Code'));
			update_option('product_code_quik_edit_text', sanitize_text_field($_POST['product_code_quik_edit_text'] ?? 'Code'));

			// Secondary Product Code settings
			update_option('product_code_second_show', isset($_POST['product_code_second_show']) ? 'yes' : 'no');
			update_option('product_code_second', isset($_POST['product_code_second']) ? 'yes' : 'no');
			update_option('hide_second_product_code_on_user_side', isset($_POST['hide_second_product_code_on_user_side']) ? 'yes' : 'no');
			update_option('product_code_text_second', sanitize_text_field($_POST['product_code_text_second'] ?? 'Product Code 2'));
			update_option('product_code_quik_edit_text_second', sanitize_text_field($_POST['product_code_quik_edit_text_second'] ?? 'Code 2'));

			// Advanced settings
			update_option('product_code_hide_empty_field', isset($_POST['product_code_hide_empty_field']) ? 'yes' : 'no');
			update_option('pcfw_structure_data', isset($_POST['pcfw_structure_data']) ? 'yes' : 'no');
			update_option('pcfw_structured_data_field', sanitize_text_field($_POST['pcfw_structured_data_field'] ?? 'gtin'));
			update_option('product_code_for_admin', isset($_POST['product_code_for_admin']) ? 'yes' : 'no');
			update_option('pcfw_hide_wc_gtin_field', isset($_POST['pcfw_hide_wc_gtin_field']) ? 'yes' : 'no');
			update_option('pcfw_delete_data_on_uninstall', isset($_POST['pcfw_delete_data_on_uninstall']) ? 'yes' : 'no');

			add_settings_error('pcfw_settings', 'settings_updated', __('Settings saved successfully.', 'product-code-for-woocommerce'), 'updated');
		}

		/**
		 * Support button notification handler
		 * Sends email notification when user clicks Support button
		 */
		public function pcfw_support_notification() {
			check_ajax_referer('pcfw_admin_nonce', 'nonce');
			
			if (!current_user_can('manage_options')) {
				wp_send_json_error(['message' => 'Permission denied']);
				return;
			}
			
			global $wpdb;
			
			$site_url = home_url();
			$site_name = html_entity_decode(get_bloginfo('name'), ENT_QUOTES, 'UTF-8');
			$support_url = 'https://wordpress.org/support/plugin/product-code-for-woocommerce/';
			
			// Environment info
			$theme = wp_get_theme();
			$parent_theme = $theme->parent() ? $theme->parent()->get('Name') . ' ' . $theme->parent()->get('Version') : 'None';
			$wc_version = defined('WC_VERSION') ? WC_VERSION : 'Not Active';
			$memory_limit = ini_get('memory_limit');
			$max_execution = ini_get('max_execution_time');
			$upload_max = ini_get('upload_max_filesize');
			$post_max = ini_get('post_max_size');
			$server_software = isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'Unknown';
			$https = is_ssl() ? 'Yes' : 'No';
			$multisite = is_multisite() ? 'Yes' : 'No';
			
			// Active plugins
			$active_plugins = get_option('active_plugins', []);
			$plugin_list = [];
			foreach ($active_plugins as $plugin) {
				$plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin, false, false);
				if (!empty($plugin_data['Name'])) {
					$plugin_list[] = $plugin_data['Name'] . ' ' . $plugin_data['Version'];
				}
			}
			$plugins_formatted = implode(', ', $plugin_list);
			
			$to = 'contact@artiosmedia.com';
			$subject = 'PCFW Support Request: ' . $site_name;
			
			$message = "A user has clicked the support button in Product Code for WooCommerce.\n\n";
			$message .= "Site: {$site_name}\n";
			$message .= "URL: {$site_url}\n";
			$message .= "Admin Email: " . get_option('admin_email') . "\n";
			$message .= "Time: " . current_time('mysql') . "\n\n";
			$message .= "── Environment ──────────────────────\n";
			$message .= "WordPress: " . get_bloginfo('version') . "\n";
			$message .= "PHP: " . phpversion() . "\n";
			$message .= "MySQL: " . $wpdb->db_version() . "\n";
			$message .= "Server: {$server_software}\n";
			$message .= "HTTPS: {$https}\n";
			$message .= "Multisite: {$multisite}\n\n";
			$message .= "── Theme ───────────────────────────\n";
			$message .= "Active: " . $theme->get('Name') . ' ' . $theme->get('Version') . "\n";
			$message .= "Parent: {$parent_theme}\n\n";
			$message .= "── WooCommerce ─────────────────────\n";
			$message .= "Version: {$wc_version}\n\n";
			$message .= "── PHP Settings ────────────────────\n";
			$message .= "Memory Limit: {$memory_limit}\n";
			$message .= "Max Execution: {$max_execution}s\n";
			$message .= "Upload Max: {$upload_max}\n";
			$message .= "Post Max: {$post_max}\n\n";
			$message .= "── Active Plugins ──────────────────\n";
			$message .= "{$plugins_formatted}\n\n";
			$message .= "─────────────────────────────────────\n";
			$message .= "They have been directed to the <a href=\"{$support_url}\">plugins support forum</a>.";
			
			$headers = [
				'Content-Type: text/html; charset=UTF-8'
			];
			
			// Convert newlines to <br> for HTML email
			$message = nl2br($message);
			
			wp_mail($to, $subject, $message, $headers);
			
			wp_send_json_success(['message' => 'Notification sent']);
		}

		public function render_settings_page() {
			if (!current_user_can('manage_options')) {
				return;
			}

			settings_errors('pcfw_settings');
			?>

			<div class="wrap pcfw-new">

				<h1><?php echo esc_html__('Product Code Settings', 'product-code-for-woocommerce'); ?></h1>
				<div class="wp-clearfix"></div>

				<form method="post" action="">

					<div class="pcfw-admin-ui">
						<div class="pcfw-postbox-container">
							<div id="poststuff">
								<div class="pcfw-section postbox">
									<div class="postbox-header">
										<h2 class="hndle ui-sortable-handle">
											<?php echo esc_html__('Configurations', 'product-code-for-woocommerce'); ?>
										</h2>
									</div>
									<div class="inside">
										<div class="main">

											<div class="pcfw-taxonomy-content">

												<table class="form-table pcfw-table">

													<!-- PRIMARY PRODUCT CODE SECTION -->
													<tr valign="top" class="pcfw-section-header">
														<th colspan="2">
															<h3><?php echo esc_html__('Primary Product Code', 'product-code-for-woocommerce'); ?></h3>
														</th>
													</tr>

													<tr valign="top">
														<th scope="row"><label for="product_code"><?php echo esc_html__('Enable Product Code', 'product-code-for-woocommerce'); ?></label></th>
														<td>
															<input type="checkbox" id="product_code" class="checkinput-box" name="product_code" value="1" <?php checked(get_option('product_code'), 'yes'); ?>>
															<span class="description checkinput-description"><?php echo esc_html__('Enable product code display across your store.', 'product-code-for-woocommerce'); ?></span>
															<span class="pcfw-tooltip">
																<span class="dashicons dashicons-editor-help"></span>
																<span class="tooltiptext"><?php echo esc_html__('Master switch. When enabled, codes appear on product pages, cart, checkout, and order receipts.', 'product-code-for-woocommerce'); ?></span>
															</span>
														</td>
													</tr>

													<tr valign="top">
														<th scope="row"><label for="hide_product_code_on_user_side"><?php echo esc_html__('Hide on Product Pages', 'product-code-for-woocommerce'); ?></label></th>
														<td>
															<input type="checkbox" id="hide_product_code_on_user_side" class="checkinput-box" name="hide_product_code_on_user_side" value="1" <?php checked(get_option('hide_product_code_on_user_side'), 'yes'); ?>>
															<span class="description checkinput-description"><?php echo esc_html__('Hide from single product pages only.', 'product-code-for-woocommerce'); ?></span>
															<span class="pcfw-tooltip">
																<span class="dashicons dashicons-editor-help"></span>
																<span class="tooltiptext"><?php echo esc_html__('Code still appears in cart, checkout, and order receipts.', 'product-code-for-woocommerce'); ?></span>
															</span>
														</td>
													</tr>

													<tr valign="top">
														<th scope="row"><label for="pcfw_hide_from_customer_orders"><?php echo esc_html__('Hide from Customer Orders', 'product-code-for-woocommerce'); ?></label></th>
														<td>
															<input type="checkbox" id="pcfw_hide_from_customer_orders" class="checkinput-box" name="pcfw_hide_from_customer_orders" value="1" <?php checked(get_option('pcfw_hide_from_customer_orders'), 'yes'); ?>>
															<span class="description checkinput-description"><?php echo esc_html__('Hide from cart, checkout, and order confirmations.', 'product-code-for-woocommerce'); ?></span>
															<span class="pcfw-tooltip">
																<span class="dashicons dashicons-editor-help"></span>
																<span class="tooltiptext"><?php echo esc_html__('Codes are still saved to orders and visible in admin, invoices, and packing slips. Ideal for internal codes like bin numbers.', 'product-code-for-woocommerce'); ?></span>
															</span>
														</td>
													</tr>

													<tr valign="top">
														<th scope="row">
															<label for="product_code_text"><?php echo esc_html__('Field Title', 'product-code-for-woocommerce'); ?></label>
														</th>
														<td>
															<span class="pcfw-input-wrap">
																<input type="text" id="product_code_text" name="product_code_text" value="<?php echo esc_attr(get_option('product_code_text', 'Product Code')); ?>" maxlength="12">
																<span class="pcfw-tooltip">
																	<span class="dashicons dashicons-editor-help"></span>
																	<span class="tooltiptext"><?php echo esc_html__('Label shown to customers on product pages and in cart. Maximum 12 characters.', 'product-code-for-woocommerce'); ?></span>
																</span>
															</span>
														</td>
													</tr>

													<tr valign="top">
														<th scope="row">
															<label for="product_code_quik_edit_text"><?php echo esc_html__('Products Column Title', 'product-code-for-woocommerce'); ?></label>
														</th>
														<td>
															<span class="pcfw-input-wrap">
																<input type="text" id="product_code_quik_edit_text" name="product_code_quik_edit_text" value="<?php echo esc_attr(get_option('product_code_quik_edit_text', 'Code')); ?>" maxlength="12">
																<span class="pcfw-tooltip">
																	<span class="dashicons dashicons-editor-help"></span>
																	<span class="tooltiptext"><?php echo esc_html__('Column header in admin Products list and Quick Edit. Maximum 12 characters.', 'product-code-for-woocommerce'); ?></span>
																</span>
															</span>
														</td>
													</tr>

													<!-- SECONDARY PRODUCT CODE SECTION -->
													<tr valign="top" class="pcfw-section-header">
														<th colspan="2">
															<h3><?php echo esc_html__('Secondary Product Code', 'product-code-for-woocommerce'); ?></h3>
														</th>
													</tr>

													<tr valign="top">
														<th scope="row"><label for="product_code_second_show"><?php echo esc_html__('Enable Second Field', 'product-code-for-woocommerce'); ?></label></th>
														<td>
															<input type="checkbox" id="product_code_second_show" class="checkinput-box" name="product_code_second_show" value="1" <?php checked(get_option('product_code_second_show'), 'yes'); ?>>
															<span class="description checkinput-description"><?php echo esc_html__('Add a second product code field in admin.', 'product-code-for-woocommerce'); ?></span>
															<span class="pcfw-tooltip">
																<span class="dashicons dashicons-editor-help"></span>
																<span class="tooltiptext"><?php echo esc_html__('Adds a second code field to the product editor. Useful for alternate SKUs, supplier codes, or bin locations.', 'product-code-for-woocommerce'); ?></span>
															</span>
														</td>
													</tr>

													<tr valign="top">
														<th scope="row"><label for="product_code_second"><?php echo esc_html__('Show Second Code', 'product-code-for-woocommerce'); ?></label></th>
														<td>
															<input type="checkbox" id="product_code_second" class="checkinput-box" name="product_code_second" value="1" <?php checked(get_option('product_code_second'), 'yes'); ?>>
															<span class="description checkinput-description"><?php echo esc_html__('Display second code to customers.', 'product-code-for-woocommerce'); ?></span>
															<span class="pcfw-tooltip">
																<span class="dashicons dashicons-editor-help"></span>
																<span class="tooltiptext"><?php echo esc_html__('Shows second code on product pages, cart, checkout, and receipts. Leave unchecked to keep it admin-only.', 'product-code-for-woocommerce'); ?></span>
															</span>
														</td>
													</tr>

													<tr valign="top">
														<th scope="row"><label for="hide_second_product_code_on_user_side"><?php echo esc_html__('Hide on Product Pages', 'product-code-for-woocommerce'); ?></label></th>
														<td>
															<input type="checkbox" id="hide_second_product_code_on_user_side" class="checkinput-box" name="hide_second_product_code_on_user_side" value="1" <?php checked(get_option('hide_second_product_code_on_user_side'), 'yes'); ?>>
															<span class="description checkinput-description"><?php echo esc_html__('Hide from single product pages only.', 'product-code-for-woocommerce'); ?></span>
															<span class="pcfw-tooltip">
																<span class="dashicons dashicons-editor-help"></span>
																<span class="tooltiptext"><?php echo esc_html__('Code still appears in cart, checkout, and order receipts.', 'product-code-for-woocommerce'); ?></span>
															</span>
														</td>
													</tr>

													<tr valign="top">
														<th scope="row">
															<label for="product_code_text_second"><?php echo esc_html__('Second Field Title', 'product-code-for-woocommerce'); ?></label>
														</th>
														<td>
															<span class="pcfw-input-wrap">
																<input type="text" id="product_code_text_second" name="product_code_text_second" value="<?php echo esc_attr(get_option('product_code_text_second', 'Product Code 2')); ?>" maxlength="14">
																<span class="pcfw-tooltip">
																	<span class="dashicons dashicons-editor-help"></span>
																	<span class="tooltiptext"><?php echo esc_html__('Label shown to customers on product pages and in cart. Maximum 14 characters.', 'product-code-for-woocommerce'); ?></span>
																</span>
															</span>
														</td>
													</tr>

													<tr valign="top">
														<th scope="row">
															<label for="product_code_quik_edit_text_second"><?php echo esc_html__('Second Column Title', 'product-code-for-woocommerce'); ?></label>
														</th>
														<td>
															<span class="pcfw-input-wrap">
																<input type="text" id="product_code_quik_edit_text_second" name="product_code_quik_edit_text_second" value="<?php echo esc_attr(get_option('product_code_quik_edit_text_second', 'Code 2')); ?>" maxlength="14">
																<span class="pcfw-tooltip">
																	<span class="dashicons dashicons-editor-help"></span>
																	<span class="tooltiptext"><?php echo esc_html__('Column header in admin Products list and Quick Edit. Maximum 14 characters.', 'product-code-for-woocommerce'); ?></span>
																</span>
															</span>
														</td>
													</tr>

													<!-- ADVANCED SETTINGS SECTION -->
													<tr valign="top" class="pcfw-section-header">
														<th colspan="2">
															<h3><?php echo esc_html__('Advanced Settings', 'product-code-for-woocommerce'); ?></h3>
														</th>
													</tr>

													<tr valign="top">
														<th scope="row"><label for="product_code_hide_empty_field"><?php echo esc_html__('Hide Field When Empty', 'product-code-for-woocommerce'); ?></label></th>
														<td>
															<input type="checkbox" id="product_code_hide_empty_field" class="checkinput-box" name="product_code_hide_empty_field" value="1" <?php checked(get_option('product_code_hide_empty_field'), 'yes'); ?>>
															<span class="description checkinput-description"><?php echo esc_html__('Hide code display when no value is set.', 'product-code-for-woocommerce'); ?></span>
															<span class="pcfw-tooltip">
																<span class="dashicons dashicons-editor-help"></span>
																<span class="tooltiptext"><?php echo esc_html__('Prevents "N/A" from appearing on product pages when a product has no code assigned.', 'product-code-for-woocommerce'); ?></span>
															</span>
														</td>
													</tr>

													<tr valign="top">
														<th scope="row"><label for="pcfw_structure_data"><?php echo esc_html__('Enable Structured Data', 'product-code-for-woocommerce'); ?></label></th>
														<td>
															<input type="checkbox" id="pcfw_structure_data" class="checkinput-box" name="pcfw_structure_data" value="1" <?php checked(get_option('pcfw_structure_data'), 'yes'); ?>>
															<span class="description checkinput-description"><?php echo esc_html__('Add product code to schema.org markup.', 'product-code-for-woocommerce'); ?></span>
															<span class="pcfw-tooltip">
																<span class="dashicons dashicons-editor-help"></span>
																<span class="tooltiptext"><?php echo esc_html__('Includes product code in structured data for search engines. Improves SEO for products with GTINs, ISBNs, or MPNs.', 'product-code-for-woocommerce'); ?></span>
															</span>
														</td>
													</tr>

													<tr valign="top">
														<th scope="row">
															<label for="pcfw_structured_data_field"><?php echo esc_html__('Structured Data Type', 'product-code-for-woocommerce'); ?></label>
														</th>
														<td>
															<span class="pcfw-input-wrap">
																<?php $current_value = get_option('pcfw_structured_data_field', 'gtin'); ?>
																<select id="pcfw_structured_data_field" name="pcfw_structured_data_field">
																	<option value="gtin" <?php selected($current_value, 'gtin'); ?>>gtin</option>
																	<option value="gtin8" <?php selected($current_value, 'gtin8'); ?>>gtin8</option>
																	<option value="gtin12" <?php selected($current_value, 'gtin12'); ?>>gtin12</option>
																	<option value="gtin13" <?php selected($current_value, 'gtin13'); ?>>gtin13</option>
																	<option value="gtin14" <?php selected($current_value, 'gtin14'); ?>>gtin14</option>
																	<option value="isbn" <?php selected($current_value, 'isbn'); ?>>isbn</option>
																	<option value="mpn" <?php selected($current_value, 'mpn'); ?>>mpn</option>
																</select>
																<span class="pcfw-tooltip">
																	<span class="dashicons dashicons-editor-help"></span>
																	<span class="tooltiptext"><?php echo esc_html__('GTIN: barcodes (UPC/EAN). ISBN: books. MPN: manufacturer part numbers. Choose based on your code type.', 'product-code-for-woocommerce'); ?></span>
																</span>
															</span>
														</td>
													</tr>

													<tr valign="top">
														<th scope="row"><label for="product_code_for_admin"><?php echo esc_html__('Admin Only', 'product-code-for-woocommerce'); ?></label></th>
														<td>
															<input type="checkbox" id="product_code_for_admin" class="checkinput-box" name="product_code_for_admin" value="1" <?php checked(get_option('product_code_for_admin'), 'yes'); ?>>
															<span class="description checkinput-description"><?php echo esc_html__('Only admins see codes on product pages.', 'product-code-for-woocommerce'); ?></span>
															<span class="pcfw-tooltip">
																<span class="dashicons dashicons-editor-help"></span>
																<span class="tooltiptext"><?php echo esc_html__('Logged-in administrators will see codes on product pages; all other visitors will not.', 'product-code-for-woocommerce'); ?></span>
															</span>
														</td>
													</tr>

													<tr valign="top">
														<th scope="row"><label for="pcfw_hide_wc_gtin_field"><?php echo esc_html__('Hide WooCommerce GTIN', 'product-code-for-woocommerce'); ?></label></th>
														<td>
															<input type="checkbox" id="pcfw_hide_wc_gtin_field" class="checkinput-box" name="pcfw_hide_wc_gtin_field" value="1" <?php checked(get_option('pcfw_hide_wc_gtin_field', 'yes'), 'yes'); ?>>
															<span class="description checkinput-description"><?php echo esc_html__('Hide the built-in WooCommerce Unique ID field.', 'product-code-for-woocommerce'); ?></span>
															<span class="pcfw-tooltip">
																<span class="dashicons dashicons-editor-help"></span>
																<span class="tooltiptext"><?php echo esc_html__('WooCommerce 9.2+ added a GTIN field. Default is set to \'hide\' to avoid confusion.', 'product-code-for-woocommerce'); ?></span>
															</span>
														</td>
													</tr>

													<tr valign="top">
														<th scope="row"><label for="pcfw_delete_data_on_uninstall"><?php echo esc_html__('Delete Data on Uninstall', 'product-code-for-woocommerce'); ?></label></th>
														<td>
															<input type="checkbox" id="pcfw_delete_data_on_uninstall" class="checkinput-box" name="pcfw_delete_data_on_uninstall" value="1" <?php checked(get_option('pcfw_delete_data_on_uninstall'), 'yes'); ?>>
															<span class="description checkinput-description"><span class="pcfw-warning-text"><?php echo esc_html__('WARNING:', 'product-code-for-woocommerce'); ?></span> <?php echo esc_html__('Permanently deletes all codes when plugin is uninstalled.', 'product-code-for-woocommerce'); ?></span>
														</td>
													</tr>

												</table>

											</div>
											<div class="clear"></div>

											<!-- Submit buttons inside panel -->
											<div class="pcfw-submit-inside-panel">
												<div class="submit-buttons">
													<?php wp_nonce_field('pcfw_save_settings', 'pcfw_settings_nonce'); ?>
													<input type="submit" class="button-primary pcfw-settings-submit" name="pcfw_submit" value="<?php echo esc_attr__('Save Settings', 'product-code-for-woocommerce'); ?>" />
													<a href="https://wordpress.org/support/plugin/product-code-for-woocommerce/" target="_blank" class="button button-secondary pcfw-support-btn"><?php esc_html_e('Support', 'product-code-for-woocommerce'); ?></a>
													<a href="https://wordpress.org/support/plugin/product-code-for-woocommerce/reviews/#new-post" target="_blank" class="button button-secondary"><?php esc_html_e('Leave Review', 'product-code-for-woocommerce'); ?></a>
												</div>
												<p class="pcfw-donation-text">
													<?php esc_html_e('This plugin is free, but your donation aids orphans:', 'product-code-for-woocommerce'); ?>
													<a href="https://www.zeffy.com/en-US/donation-form/your-donation-makes-a-difference-6" target="_blank" class="button button-secondary"><?php esc_html_e('I Want to Help', 'product-code-for-woocommerce'); ?></a>
												</p>
											</div>

										</div>
									</div>
								</div>

							</div>
						</div>

					</div>

					<div class="clear"></div>

				</form>

			</div><!-- End .wrap -->

			<div class="clear"></div>

			<?php
		}
	}
