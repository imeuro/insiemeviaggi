<?php
/**
 * Plugin Name: InsiemeViaggi – Elementor Submissions fix
 * Description: Assicura che le tabelle delle submission Elementor esistano e aiuta a diagnosticare errori di salvataggio.
 * Version: 1.1
 */

defined( 'ABSPATH' ) || exit;

const INSEMEVIAGGI_EP_SUBMISSIONS_OPTION = 'elementor_submissions_db_version';
const INSEMEVIAGGI_EP_SUBMISSIONS_TABLE  = 'e_submissions';

/**
 * Se la tabella delle submission non esiste, resetta la versione DB così Migration::install() ricrea le tabelle.
 */
function insiemeviaggi_ensure_submissions_tables() {
	global $wpdb;

	$migration_class = 'ElementorPro\Modules\Forms\Submissions\Database\Migration';
	if ( ! class_exists( $migration_class ) ) {
		return;
	}

	$table = $wpdb->prefix . INSEMEVIAGGI_EP_SUBMISSIONS_TABLE;
	$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

	if ( empty( $exists ) ) {
		delete_option( INSEMEVIAGGI_EP_SUBMISSIONS_OPTION );
	}

	$migration_class::install();
}

/**
 * Forza la creazione/aggiornamento delle tabelle Elementor Submissions.
 * Se la tabella non esiste (es. mai creata o eliminata), resetta la versione e riesegue le migration.
 */
add_action( 'init', function () {
	if ( ! class_exists( 'ElementorPro\Plugin' ) ) {
		return;
	}
	insiemeviaggi_ensure_submissions_tables();
}, 9999 );

/**
 * Stessa verifica in admin_init (per richieste admin-ajax e dashboard).
 */
add_action( 'admin_init', function () {
	insiemeviaggi_ensure_submissions_tables();
}, 1 );

/**
 * Diagnostica: logga eventuale errore DB dopo l’invio di un form (solo se WP_DEBUG_LOG attivo).
 */
add_action( 'elementor_pro/forms/new_record', function () {
	global $wpdb;
	if ( ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG || empty( $wpdb->last_error ) ) {
		return;
	}
	error_log(
		'[Elementor Submissions] Possibile errore DB dopo invio form: ' . $wpdb->last_error
	);
}, 999 );
