<?php

/*****************************************
 * BACKEND ENHANCEMENTS
 *****************************************/

defined( 'ABSPATH' ) || exit;


/*****************************************
 * COLONNA BIGLIETTI IN LISTA ORDINI
 *****************************************/

add_filter( 'manage_edit-shop_order_columns', 'iv_custom_shop_order_column', 20 );
function iv_custom_shop_order_column( $columns ) {
	$reordered_columns = array();

	foreach ( $columns as $key => $column ) {
		$reordered_columns[ $key ] = $column;
		if ( 'order_status' === $key ) {
			$reordered_columns['ticket_codes'] = __( 'Biglietti', 'insiemeviaggi-ecommerce' );
		}
	}

	return $reordered_columns;
}

add_action( 'manage_shop_order_posts_custom_column', 'iv_orders_list_column_content', 20, 2 );
function iv_orders_list_column_content( $column, $post_id ) {
	if ( 'ticket_codes' !== $column ) {
		return;
	}

	$downloads = get_post_meta( $post_id, '_Order_Downloads', true );
	if ( ! empty( $downloads ) ) {
		echo '<p class="ticket_codes"><small>';
		foreach ( $downloads as $ticket ) {
			echo esc_html( $ticket['name'] ) . '<br/>';
		}
		echo '</small></p>';
	} else {
		echo '<small style="line-height: 1.25; display: inline-block;">biglietto da assegnare e da inviare al cliente</small>';
	}
}


/*****************************************
 * DETTAGLIO BIGLIETTI IN RIEPILOGO ORDINE
 *****************************************/

add_action( 'woocommerce_admin_order_data_after_shipping_address', 'iv_print_ticket_number' );
function iv_print_ticket_number( $order ) {
	echo '<div class="clear"></div>';
	$order_id  = $order->get_id();
	$downloads = get_post_meta( $order_id, '_Order_Downloads', true );

	if ( $downloads ) {
		echo '<h3>Biglietti assegnati al cliente</h3>';
		echo '<p>';
		foreach ( $downloads as $ticket ) {
			$basepath = str_replace( $ticket['name'], '', $ticket['file'] );
			$sku      = str_replace( get_site_url() . '/wp-content/uploads/woocommerce_uploads/', '', $basepath );
			echo esc_html( rtrim( $sku, '/' ) . ' / ' . $ticket['name'] ) . '<br/>';
		}
		echo '</p>';
		echo '<div class="clear"></div>';
	}

	if ( $order->get_used_coupons() ) {
		$coupons_count = count( $order->get_used_coupons() );
		echo '<div class="clear"></div>';
		echo '<h3>Codici sconto utilizzati:</h3> ';
		echo '<p>';
		$i = 1;
		foreach ( $order->get_used_coupons() as $coupon ) {
			echo esc_html( $coupon );
			if ( $i < $coupons_count ) {
				echo ', ';
			}
			$i++;
		}
		echo '</p>';
	}
}


/*****************************************
 * FORZA PASSWORD WOOCOMMERCE MENO RIGIDA
 *****************************************/

add_filter( 'woocommerce_min_password_strength', 'iv_reduce_min_strength_requirement' );
function iv_reduce_min_strength_requirement( $strength ) {
	return 2;
}


/*****************************************
 * FALLBACK ROUTE EXPORT ANALYTICS
 *****************************************/

add_action( 'rest_api_init', 'iv_register_wc_analytics_export_fallback', 99 );
function iv_register_wc_analytics_export_fallback() {
	if ( ! class_exists( '\Automattic\WooCommerce\Admin\API\Reports\Export\Controller' ) ) {
		return;
	}

	$server = rest_get_server();
	if ( ! $server ) {
		return;
	}

	$routes    = $server->get_routes();
	$route_key = '/wc-analytics/reports/(?P<type>[a-z]+)/export';
	if ( isset( $routes[ $route_key ] ) ) {
		return;
	}

	$controller = new \Automattic\WooCommerce\Admin\API\Reports\Export\Controller();
	$controller->register_routes();
}


/*****************************************
 * EXPORT CLIENTI - MENU E PAGINA ADMIN
 *****************************************/

add_action( 'admin_menu', 'iv_add_admin_menu' );
add_action( 'admin_init', 'iv_settings_init' );
add_action( 'admin_enqueue_scripts', 'iv_enqueue_admin_css', 10 );
add_action( 'admin_enqueue_scripts', 'iv_enqueue_wc_admin_analytics_export_script', 20 );
add_action( 'admin_post_iv_export_orders_report', 'iv_export_orders_report' );
add_action( 'admin_post_iv_download_clienti_csv', 'iv_download_clienti_csv' );

add_action( 'admin_init', 'iv_protect_customer_data_files' );

function iv_protect_customer_data_files() {
	$upload_dir   = wp_upload_dir();
	$uploads_path = $upload_dir['basedir'];

	if ( ! is_dir( $uploads_path ) || ! is_writable( $uploads_path ) ) {
		return;
	}

	$htaccess_path    = $uploads_path . '/.htaccess';
	$htaccess_content = "# Protezione file customer-data.*\n";
	$htaccess_content .= "# Questi file contengono dati sensibili e devono essere accessibili solo tramite endpoint WordPress autenticati\n";
	$htaccess_content .= "# File generato automaticamente dal plugin insiemeviaggi-ecommerce\n\n";

	$htaccess_content .= "<FilesMatch \"^(customer-data\\.(json|csv|txt))$\">\n";
	$htaccess_content .= "    Require all denied\n";
	$htaccess_content .= "    <IfModule !mod_authz_core.c>\n";
	$htaccess_content .= "        Order deny,allow\n";
	$htaccess_content .= "        Deny from all\n";
	$htaccess_content .= "    </IfModule>\n";
	$htaccess_content .= "</FilesMatch>\n";

	$existing_content = '';
	if ( file_exists( $htaccess_path ) ) {
		$existing_content = file_get_contents( $htaccess_path );
	}

	if ( $existing_content !== $htaccess_content ) {
		file_put_contents( $htaccess_path, $htaccess_content, LOCK_EX );
	}
}

add_action( 'iv_weekly_action', 'iv_refresh_csv' );
function iv_refresh_csv() {
	global $api_url, $csv_filename;
	iv_generate_csv_from_api( $api_url, $csv_filename, true );
}

wp_clear_scheduled_hook( 'LTC_daily_action' );
wp_clear_scheduled_hook( 'LTC_weekly_action' );

if ( ! wp_next_scheduled( 'iv_weekly_action' ) ) {
	$next_monday_3am = strtotime( 'next Monday 3:00' );
	wp_schedule_event( $next_monday_3am, 'weekly', 'iv_weekly_action' );
}


function iv_add_admin_menu() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	add_menu_page(
		'Export Clienti',
		'Export Clienti',
		'manage_options',
		'iv-export-clienti',
		'iv_options_page',
		'dashicons-database-export',
		70
	);
}

function iv_enqueue_admin_css( $hook_suffix ) {
	if ( 'toplevel_page_iv-export-clienti' === $hook_suffix ) {
		wp_enqueue_style( 'iv-admin-custom-css', IV_PLUGIN_URL . 'assets/iv-admin-custom.css' );
	}
}

function iv_enqueue_wc_admin_analytics_export_script( $hook_suffix ) {
	if ( 'woocommerce_page_wc-admin' !== $hook_suffix ) {
		return;
	}

	if ( empty( $_GET['page'] ) || 'wc-admin' !== $_GET['page'] ) {
		return;
	}

	$path = isset( $_GET['path'] ) ? sanitize_text_field( wp_unslash( $_GET['path'] ) ) : '';
	if ( '/analytics/orders' !== $path ) {
		return;
	}

	wp_enqueue_script(
		'iv-wc-analytics-export',
		IV_PLUGIN_URL . 'assets/iv-wc-analytics-export.js',
		array( 'wp-api-fetch' ),
		'1.0.0',
		true
	);

	wp_localize_script(
		'iv-wc-analytics-export',
		'IV_WC_ANALYTICS_EXPORT',
		array(
			'restUrl'        => esc_url_raw( rest_url() ),
			'restNonce'      => wp_create_nonce( 'wp_rest' ),
			'adminPostUrl'   => esc_url_raw( admin_url( 'admin-post.php' ) ),
			'exportNonce'    => wp_create_nonce( 'iv_export_orders_report' ),
			'pollIntervalMs' => 3000,
			'maxPollMs'      => 180000,
			'reportType'     => 'orders',
		)
	);
}

function iv_export_orders_report() {
	if ( ! current_user_can( 'view_woocommerce_reports' ) ) {
		wp_die( esc_html__( 'Permessi insufficienti.', 'woocommerce' ) );
	}

	$nonce = isset( $_POST['iv_export_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['iv_export_nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'iv_export_orders_report' ) ) {
		wp_die( esc_html__( 'Nonce non valida.', 'woocommerce' ) );
	}

	$raw_args    = isset( $_POST['report_args'] ) ? wp_unslash( $_POST['report_args'] ) : '';
	$report_args = json_decode( $raw_args, true );
	if ( ! is_array( $report_args ) ) {
		$report_args = array();
	}

	$export_id = str_replace( '.', '', microtime( true ) );
	$export_id = (string) sanitize_file_name( $export_id );

	$exporter = new \Automattic\WooCommerce\Admin\ReportCSVExporter( 'orders', $report_args );
	$exporter->set_filename( "wc-orders-report-export-{$export_id}" );
	$exporter->set_page( 1 );
	$exporter->prepare_data_to_export();

	$total_rows  = $exporter->get_total_rows();
	$batch_limit = $exporter->get_limit();
	$num_batches = $batch_limit > 0 ? (int) ceil( $total_rows / $batch_limit ) : 0;

	if ( 0 === $num_batches ) {
		wp_die( esc_html__( 'Nessun dato da esportare.', 'woocommerce' ) );
	}

	for ( $page = 1; $page <= $num_batches; $page++ ) {
		$batch_args = array_merge( $report_args, array( 'page' => $page ) );
		$exporter->set_report_args( $batch_args );
		$exporter->set_page( $page );
		$exporter->generate_file();
	}

	$exporter->export();
	exit;
}

function iv_download_clienti_csv() {
	if ( ! is_user_logged_in() ) {
		wp_die( esc_html__( 'Devi essere loggato per scaricare questo file.', 'woocommerce' ) );
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Non hai i permessi per scaricare questo file.', 'woocommerce' ) );
	}

	check_admin_referer( 'iv_download_clienti_csv' );

	global $csv_filename;

	if ( ! file_exists( $csv_filename ) ) {
		wp_die( esc_html__( 'File non trovato.', 'woocommerce' ) );
	}

	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="customer-data.csv"' );
	header( 'Content-Length: ' . filesize( $csv_filename ) );
	header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
	header( 'Pragma: public' );

	readfile( $csv_filename );
	exit;
}

function iv_settings_init() {
	register_setting( 'pluginPage', 'iv_settings' );

	add_settings_section(
		'iv_pluginPage_section',
		__( 'Pagina di Esportazione dati Clienti', 'woocommerce' ),
		'iv_settings_section_callback',
		'pluginPage'
	);
}

function iv_settings_section_callback() {
	global $csv_url, $csv_filename;

	date_default_timezone_set( 'Europe/Rome' );
	$next_run = date( 'j M @ H:i', wp_next_scheduled( 'iv_weekly_action' ) );

	$csv_recent        = false;
	$csv_age_formatted = '';
	if ( file_exists( $csv_filename ) ) {
		$file_mtime      = filemtime( $csv_filename );
		$csv_age_seconds = time() - $file_mtime;
		$csv_age_hours   = $csv_age_seconds / 3600;

		$hours   = floor( $csv_age_hours );
		$minutes = floor( ( $csv_age_seconds % 3600 ) / 60 );

		if ( $hours > 0 && $minutes > 0 ) {
			$csv_age_formatted = $hours . ' ore e ' . $minutes . ' minuti';
		} elseif ( $hours > 0 ) {
			$csv_age_formatted = $hours . ' ore';
		} elseif ( $minutes > 0 ) {
			$csv_age_formatted = $minutes . ' minuti';
		} else {
			$csv_age_formatted = 'meno di un minuto';
		}

		if ( $csv_age_hours < 24 ) {
			$csv_recent = true;
		}
	}

	$is_generating       = isset( $_GET['regenerate_csv'] ) && 'true' === $_GET['regenerate_csv'];
	$disabled            = ( $is_generating || ! $csv_recent ) ? ' disabled' : '';
	$regenerate_disabled = $is_generating ? ' disabled' : '';

	$csv_info = '';
	if ( $csv_recent && '' !== $csv_age_formatted && ! $is_generating ) {
		$csv_info = '<br><small style="color: #46b450;">CSV recente (aggiornato ' . $csv_age_formatted . ' fa)</small>';
	} elseif ( file_exists( $csv_filename ) && '' !== $csv_age_formatted && ! $is_generating ) {
		$csv_info = '<br><small style="color: #dc3232;">CSV obsoleto (aggiornato ' . $csv_age_formatted . ' fa - si consiglia di rigenerare)</small>';
	}

	$generation_notice = '';
	if ( $is_generating ) {
		$generation_notice = '<div class="notice notice-info" style="margin: 15px 0; padding: 12px; background-color: #fff3cd; border-left: 4px solid #ffb900;"><p><strong>Generazione lista clienti in corso...</strong><br>Attendere il completamento. Questa operazione può richiedere alcuni minuti. Non chiudere questa pagina.</p></div>';
	}

	$download_nonce = wp_create_nonce( 'iv_download_clienti_csv' );

	echo '<div id="iv_section_header">';
	echo "<script>
	function GETcsv(){
		const form = document.createElement('form');
		form.method = 'POST';
		form.action = '" . esc_js( admin_url( 'admin-post.php' ) ) . "';
		form.target = '_blank';

		const actionInput = document.createElement('input');
		actionInput.type = 'hidden';
		actionInput.name = 'action';
		actionInput.value = 'iv_download_clienti_csv';
		form.appendChild(actionInput);

		const nonceInput = document.createElement('input');
		nonceInput.type = 'hidden';
		nonceInput.name = '_wpnonce';
		nonceInput.value = '" . esc_js( $download_nonce ) . "';
		form.appendChild(nonceInput);

		document.body.appendChild(form);
		form.submit();
		document.body.removeChild(form);
	}
	(function() {
		const form = document.querySelector('form[name=\"gencsv\"]');
		if (form) {
			form.addEventListener('submit', function(e) {
				const regenerateBtn = form.querySelector('button[value=\"regenerate_csv\"]');
				if (regenerateBtn && !regenerateBtn.disabled) {
					let noticeDiv = document.getElementById('iv-generation-notice');
					if (!noticeDiv) {
						noticeDiv = document.createElement('div');
						noticeDiv.id = 'iv-generation-notice';
						noticeDiv.className = 'notice notice-info';
						noticeDiv.style.cssText = 'margin: 15px 0; padding: 12px; background-color: #fff3cd; border-left: 4px solid #ffb900;';
						noticeDiv.innerHTML = '<p><strong>Generazione lista clienti in corso...</strong><br>Attendere il completamento. Questa operazione può richiedere alcuni minuti. Non chiudere questa pagina.</p>';
						const sectionHeader = document.getElementById('iv_section_header');
						if (sectionHeader) {
							sectionHeader.insertBefore(noticeDiv, sectionHeader.querySelector('.page_agisci'));
						}
					}
					noticeDiv.style.display = 'block';

					const buttons = form.querySelectorAll('button');
					buttons.forEach(function(btn) {
						btn.disabled = true;
					});

					if (regenerateBtn) {
						regenerateBtn.innerHTML = '&nbsp;&nbsp;Generazione in corso...';
					}
				}
			});
		}
	})();
	</script>";
	echo __( '<p class="page_spiega">Da questa pagina è possibile eseguire il download della lista clienti e relativi dettagli in formato CSV, importabile in excel.<br><small>Prossima generazione automatica: ' . $next_run . '</small>' . $csv_info . '</p>', 'woocommerce' );
	echo $generation_notice;
	echo '<div class="page_agisci"><input type="hidden" name="page" value="iv-export-clienti" />
	<input type="hidden" name="regenerate_csv" value="true" />
	<button' . $regenerate_disabled . ' type="submit" value="regenerate_csv" class="button button-large button-primary dashicons-before dashicons-update">&nbsp;&nbsp;Aggiorna Lista Clienti</button>
	<button' . $disabled . ' type="button" onclick="GETcsv()" class="button button-large button-primary dashicons-before dashicons-download">&nbsp;&nbsp;Download Lista Clienti (CSV)</button></div>';
	echo '</div>';
}

function iv_options_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Non hai i permessi per accedere a questa pagina.', 'woocommerce' ) );
	}

	global $api_url, $json_filename, $csv_filename, $csv_url, $ABSPATH;

	?>
	<form action='./admin.php' method='GET' name="gencsv">
		<?php
		settings_fields( 'pluginPage' );
		do_settings_sections( 'pluginPage' );

		global $api_url, $json_filename, $csv_filename, $csv_url;

		if ( isset( $_GET['regenerate_csv'] ) && 'true' === $_GET['regenerate_csv'] ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'Non hai i permessi per eseguire questa operazione.', 'woocommerce' ) );
			}

			iv_generate_csv_from_api( $api_url, $csv_filename, true );

			$redirect_url = admin_url( 'admin.php?page=iv-export-clienti&csv_generated=1' );
			wp_redirect( $redirect_url );
			exit;
		}

		if ( isset( $_GET['csv_generated'] ) && '1' === $_GET['csv_generated'] ) {
			echo '<div class="notice notice-success is-dismissible" style="margin: 15px 0;"><p><strong>Lista clienti generata con successo!</strong></p></div>';
		}

		if ( file_exists( $csv_filename ) ) {
			iv_csv_preview( $csv_filename );
		}
		?>
	</form>
	<?php
}


/*****************************************
 * DATI PRODOTTO: TAB NON USATE (WooCommerce admin)
 *****************************************/

add_filter( 'woocommerce_product_data_tabs', 'iv_remove_unused_product_data_tabs', 100 );

/**
 * Rimuove tab "Dati prodotto" non necessarie (Spedizione, Attributi, Avanzate).
 * La tab "Articoli collegati" (linked_product) resta visibile di default; per nasconderla:
 * add_filter( 'iv_product_data_remove_linked_product_tab', '__return_true' );
 *
 * @param array<string, array<string, mixed>> $tabs Tab WooCommerce.
 * @return array<string, array<string, mixed>>
 */
function iv_remove_unused_product_data_tabs( $tabs ) {
	if ( ! is_array( $tabs ) ) {
		return $tabs;
	}

	unset( $tabs['shipping'], $tabs['attribute'], $tabs['advanced'] );

	if ( apply_filters( 'iv_product_data_remove_linked_product_tab', false ) ) {
		unset( $tabs['linked_product'] );
	}

	return $tabs;
}
