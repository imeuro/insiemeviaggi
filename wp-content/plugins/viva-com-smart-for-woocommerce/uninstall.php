<?php
/**
 * Uninstall script for the Viva.com Smart for WooCommerce plugin.
 *
 * This script is executed when the plugin is uninstalled via WordPress.
 * It ensures that all relevant settings and options related to the plugin
 * are removed from the database.
 *
 * @package VivaCom_Smart_For_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Check for plugin uninstall
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove ALL Vivacom settings.
 */
// Delete options.
delete_option( 'woocommerce_vivacom_smart_settings' );
