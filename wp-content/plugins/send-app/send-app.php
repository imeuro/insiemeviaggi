<?php
/**
 * Plugin Name: Send - Email and SMS marketing for WordPress and for WooCommerce
 * Description: The all-in-one Email & SMS Marketing Platform for WordPress
 * Plugin URI: https://send2.co/?utm_source=wp-plugins&utm_campaign=plugin-uri&utm_medium=wp-dash
 * Author: Elementor.com
 * Author URI: https://elementor.com/?utm_source=wp-plugins&utm_campaign=author-uri&utm_medium=wp-dash
 * Version: 1.6.2
 * License: GPL-3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * Text Domain: send-app
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'SEND_VERSION', '1.6.2' );

define( 'SEND__FILE__', __FILE__ );
define( 'SEND_PLUGIN_BASE', plugin_basename( SEND__FILE__ ) );
define( 'SEND_PATH', plugin_dir_path( SEND__FILE__ ) );

define( 'SEND_URL', plugins_url( '/', SEND__FILE__ ) );
define( 'SEND_ASSETS_PATH', plugin_dir_path( SEND__FILE__ ) . 'assets/' );
define( 'SEND_ASSETS_URL', SEND_URL . 'assets/' );

define( 'SEND_WP_MINIMUM_VERSION', '6.3' );
define( 'SEND_PHP_MINIMUM_VERSION', '7.4' );

if ( ! version_compare( PHP_VERSION, SEND_PHP_MINIMUM_VERSION, '>=' ) ) {
	add_action( 'admin_notices', 'send_app_fail_php_version' );
} elseif ( ! version_compare( get_bloginfo( 'version' ), SEND_WP_MINIMUM_VERSION, '>=' ) ) {
	add_action( 'admin_notices', 'send_app_fail_wp_version' );
} else {
	require SEND_PATH . 'plugin.php';
}

function send_app_fail_php_version() {
	$message = sprintf(
		/* translators: 1: `<h3>` opening tag, 2: `</h3>` closing tag, 3: PHP version. 4: Link opening tag, 5: Link closing tag. */
		esc_html__( '%1$s Send isn’t running because PHP is outdated.%2$s Update to PHP version %3$s and get back to creating! %4$sShow me how%5$s', 'send-app' ),
		'<h3>',
		'</h3>',
		SEND_PHP_MINIMUM_VERSION,
		'<a href="https://go.elementor.com/wp-dash-update-php/" target="_blank">',
		'</a>'
	);
	$html_message = sprintf( '<div class="error">%s</div>', wpautop( $message ) );
	echo wp_kses_post( $html_message );
}

function send_app_fail_wp_version() {
	$message = sprintf(
		/* translators: 1: `<h3>` opening tag, 2: `</h3>` closing tag, 3: WordPress version. 4: Link opening tag, 5: Link closing tag. */
		esc_html__( '%1$s Send app isn’t running because WordPress is outdated.%2$s Update to WordPress version %3$s and get back to creating! %4$sShow me how%5$s', 'send-app' ),
		'<h3>',
		'</h3>',
		SEND_WP_MINIMUM_VERSION,
		'<a href="https://go.elementor.com/wp-dash-update-wp/" target="_blank">',
		'</a>'
	);
	$html_message = sprintf( '<div class="error">%s</div>', wpautop( $message ) );
	echo wp_kses_post( $html_message );
}
