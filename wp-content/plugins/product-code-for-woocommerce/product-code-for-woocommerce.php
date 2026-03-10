<?php

/**
 * @package              PRODUCT_CODE
 * @wordpress-plugin
 * 
 * Plugin Name:          Product Code for WooCommerce
 * Plugin URI:           http://wordpress.org/plugins/product-code-for-woocommerce
 * Description:          Plugin provides a unique internal product identifier in addition to the GTIN, EAN, SKU and UPC throughout the order process. A secondary product code field can be activated from setup.
 * Version:              1.5.11
 * Author:               Artios Media
 * Author URI:           http://www.artiosmedia.com
 * Assisting Developer:  Arafat Rahman
 * Copyright:            © 2018-2026 Artios Media (email: contact@artiosmedia.com).
 * License:              GNU General Public License v3.0
 * License URI:          http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:          product-code-for-woocommerce
 * Domain Path:          /languages
 * Tested up to:         6.9
 * Requires at least:    5.8
 * WC requires at least: 6.5.0
 * WC tested up to:      10.4.3
 * Requires PHP:         7.4
 * PHP tested up to:     8.3.29
 */

namespace Artiosmedia\WC_Product_Code;

define('PRODUCT_CODE_URL', plugins_url('', __FILE__));
define('PRODUCT_CODE_PATH', plugin_dir_path(__FILE__));
define('PRODUCT_CODE_FIELD_NAME', '_product_code');
define('PRODUCT_CODE_FIELD_NAME_SECOND', '_product_code_second');


define('PRODUCT_CODE_TEMPLATE_PATH', __DIR__ . '/templates');

define('PRODUCT_CODE_VERSION', '1.5.11');
define('PRODUCT_CODE_DB_VERSION', '1.5.11');

load_plugin_textdomain('product-code-for-woocommerce', false, basename(dirname(__FILE__)) . '/languages');

if (!class_exists('PCFW_Services')) {
	include(PRODUCT_CODE_PATH . 'classes/class-pcfw-services.php');
}

if (!class_exists('PCFW_Admin_Settings')) {
	include(PRODUCT_CODE_PATH . 'classes/class-pcfw-admin-settings.php');
}

if (!class_exists('PCFW_Settings_Page')) {
	include(PRODUCT_CODE_PATH . 'classes/class-pcfw-settings-page.php');
}

if (!class_exists('PCFW_Wc_Export_Filter')) {
	include(PRODUCT_CODE_PATH . 'modules/export/pcfw-export-support.php');
}

new PCFW_Services();
PCFW_Settings_Page::get_instance();

register_activation_hook(__FILE__, function () {
	// Must Be deleted on other update ,
	delete_option('product_code_update');

	$show_product = get_option('product_code');
	if (!$show_product) {
		add_option('product_code', 'yes');
	}

	add_option('product_code_text', 'Product Code');

	//update_option('product_code_notice_dismiss', gmdate('Y-m-d', strtotime('+30 days')));
});

register_deactivation_hook(__FILE__, function () {
	// Settings preserved on deactivation
	// Data cleanup only occurs on uninstall if user opts in
});

add_action('before_woocommerce_init', function () {
	// Check if the FeaturesUtil class exists in the \Automattic\WooCommerce\Utilities namespace.
	if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
		// Declare compatibility with custom order tables using the FeaturesUtil class.
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
	}
});

// run on upgrade
add_action('admin_init', function () {

	$plugin_options = get_site_option('product_code_info');
	$upgrade_db = get_option('product_code_db_updated');

	if (PRODUCT_CODE_VERSION === '1.0.6' && !$upgrade_db) {
		//update_option('product_code_notice_dismiss', gmdate('Y-m-d', strtotime('+30 days')));
		update_option('product_code_update', true);
	}

	if (PRODUCT_CODE_VERSION === '1.0.7') {
		$product_text = get_option('product_code_text');
		if (!$product_text) {
			update_option('product_code_text', 'Product Code');
		}
	}
	/* Change the order of code and resolved offset error */
	// update the version value
	$product_code_info = array(
		'version'    => PRODUCT_CODE_VERSION,
		'db_version'     => PRODUCT_CODE_DB_VERSION
	);
	update_site_option('product_code_info', $product_code_info);
	/* Change the order of code and resolved offset error */

	// Check if postmeta data is changed to the new name.
	global $wpdb;
	$results = $wpdb->get_results("SELECT * from {$wpdb->prefix}postmeta WHERE meta_key='_product_code_variant'");

	// If plugin version is less then 1.2.0 then upgrade and if not update database after version upgrade still display the notice.
	if (( $plugin_options && isset($plugin_options['version']) && $plugin_options['version'] < '1.2.2' && !$upgrade_db ) || !empty($results)) {
		add_action('admin_notices', function () {
			
			$url = wp_nonce_url(
				admin_url('admin-ajax.php?action=product_code_update_database'),
				'product_code_update_nonce',
				'_ajax_nonce'
			);


			$notice_text = __('<strong>NOTICE! Product Code For WooCommerce</strong> must update your database to modify meta fields. Consider backing up database first.', 'product-code-for-woocommerce');
			?>
			<div class="warning notice">
				<p><?php echo wp_kses_post($notice_text); ?> <a class="button button-primary" href="<?php echo esc_url($url); ?>" target="_blank"><?php esc_html_e('Update Now', 'product-code-for-woocommerce'); ?></a></p>
			</div>
<?php
		});
	}
});

// Include files for search
add_filter('init', function () {
	include_once PRODUCT_CODE_PATH . 'modules/search/pcfw-filter-search.php';
	include_once(ABSPATH . 'wp-admin/includes/plugin.php');
	if (!is_admin() && is_plugin_active('relevanssi/relevanssi.php') || !function_exists('wc_clean')) {
		include_once PRODUCT_CODE_PATH . 'modules/search/pcfw-relevanssi-product-code-search.php';
	} else if (!is_admin() && is_plugin_active('searchwp/searchwp.php') && is_plugin_active('searchwp-woocommerce/searchwp-woocommerce.php')) {
		include_once PRODUCT_CODE_PATH . 'modules/search/pcfw-searchwp-product-code-search.php';
	} else {
		include_once PRODUCT_CODE_PATH . 'modules/search/pcfw-product-code-search.php';
	}
}, 11);


add_action('save_post', function ($post_id, $post) {
	if ('product' != $post->post_type) {
		return;
	}
	$product = wc_get_product($post_id);
	$type = $product->get_type();
	global $post;
	if ('variable' == $type) {
		$variations = $product->get_available_variations();
		$variations_id = wp_list_pluck($variations, 'variation_id');
		delete_post_meta($post_id, '_product_code');
		foreach ($variations_id as $var) {
			$ProductCode = get_post_meta($var, '_product_code', true);
			add_post_meta($post_id, '_product_code', $ProductCode);
		}
	}
}, 10, 2);
