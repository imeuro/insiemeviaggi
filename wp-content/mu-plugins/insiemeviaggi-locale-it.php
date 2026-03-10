<?php
/**
 * Plugin Name: InsiemeViaggi – Lingua italiana
 * Description: Forza sito e WooCommerce in italiano. Must-use: si carica prima di tutti i plugin.
 * Version: 1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Imposta la lingua prima che qualsiasi plugin la legga.
 * get_locale() usa global $locale se già impostato.
 */
global $locale;
$locale = 'it_IT';

add_filter( 'pre_determine_locale', function () {
	return 'it_IT';
}, 0 );

add_filter( 'locale', function () {
	return 'it_IT';
}, 0 );

add_filter( 'determine_locale', function () {
	return 'it_IT';
}, 0 );

add_filter( 'plugin_locale', function ( $locale, $domain ) {
	if ( 'woocommerce' === $domain ) {
		return 'it_IT';
	}
	return $locale;
}, 0, 2 );

/**
 * Forza il file di traduzione WooCommerce verso l'italiano.
 * WordPress 6.5+ usa .l10n.php; il filtro load_textdomain_mofile intercetta il .mo,
 * load_translation_file intercetta anche il path .l10n.php.
 */
add_filter( 'load_textdomain_mofile', function ( $mofile, $domain ) {
	if ( 'woocommerce' !== $domain ) {
		return $mofile;
	}
	$it_mo = WP_LANG_DIR . '/plugins/woocommerce-it_IT.mo';
	return is_readable( $it_mo ) ? $it_mo : $mofile;
}, 999, 2 );

add_filter( 'load_translation_file', function ( $file, $domain, $locale ) {
	if ( 'woocommerce' !== $domain ) {
		return $file;
	}
	if ( 'it_IT' === $locale ) {
		return $file;
	}
	$it_php = WP_LANG_DIR . '/plugins/woocommerce-it_IT.l10n.php';
	$it_mo  = WP_LANG_DIR . '/plugins/woocommerce-it_IT.mo';
	if ( substr( $file, -10 ) === '.l10n.php' && is_readable( $it_php ) ) {
		return $it_php;
	}
	if ( substr( $file, -3 ) === '.mo' && is_readable( $it_mo ) ) {
		return $it_mo;
	}
	return $file;
}, 999, 3 );

/**
 * In admin: imposta la lingua del profilo utente a it_IT
 * così get_user_locale() restituisce italiano.
 */
add_action( 'admin_init', function () {
	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		return;
	}
	if ( get_user_meta( $user_id, 'locale', true ) === 'it_IT' ) {
		return;
	}
	update_user_meta( $user_id, 'locale', 'it_IT' );
	clean_user_cache( $user_id );
	wp_set_current_user( $user_id );
}, 0 );
