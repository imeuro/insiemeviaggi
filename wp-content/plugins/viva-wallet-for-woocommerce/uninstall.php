<?php
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
 * Remove ALL Vivawallet settings.
 */
// Delete options.
delete_option( 'woocommerce_vivawallet_native_settings' );

delete_option( 'woocommerce_vivawallet-ideal_settings' );
delete_option( 'woocommerce_vivawallet-p24_settings' );
delete_option( 'woocommerce_vivawallet-payu_settings' );
delete_option( 'woocommerce_vivawallet-multibanco_settings' );
delete_option( 'woocommerce_vivawallet-giropay_settings' );
delete_option( 'woocommerce_vivawallet-directpay_settings' );
delete_option( 'woocommerce_vivawallet-eps_settings' );
delete_option( 'woocommerce_vivawallet-wechatpay_settings' );
delete_option( 'woocommerce_vivawallet-bitpay_settings' );
