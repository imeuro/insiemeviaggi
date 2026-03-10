<?php

/*****************************************
 * BACKEND ENHANCEMENTS *
 *****************************************/

// [ BACKEND ]
// stampo codici biglietti per singolo ordine in lista ordini
add_filter( 'manage_edit-shop_order_columns', 'custom_shop_order_column', 20 );
function custom_shop_order_column($columns) {
    $reordered_columns = array();

    // Inserting columns to a specific location
    foreach( $columns as $key => $column){
        $reordered_columns[$key] = $column;
        if( $key ==  'order_status' ){
            // Inserting after "Status" column
            $reordered_columns['ticket_codes'] = __( 'Biglietti','theme_domain');
        }
    }
    return $reordered_columns;
}
add_action( 'manage_shop_order_posts_custom_column' , 'custom_orders_list_column_content', 20, 2 );
function custom_orders_list_column_content( $column, $post_id ) {
    switch ( $column )
    {
        case 'ticket_codes' :
            // Get custom post meta data
            $downloads = get_post_meta( $post_id, '_Order_Downloads', true );
            if(!empty($downloads)){
            	echo '<p class="ticket_codes"><small>';
				foreach($downloads as $ticket) {
					//print_r($ticket);
					echo $ticket["name"].'<br/>';
				}
				echo '</small></p>';
            }
            // no downloads - most likely a preorder
            else {
                echo '<small style="line-height: 1.25; display: inline-block;">biglietto da assegnare e da inviare al cliente</small>';
            }

            break;


        // case 'gigi' :
        	//...
            // break;
    }
}




// [ BACKEND ]
// stampo codici biglietti e codici utilizzati in riepilogo ordine
add_action( 'woocommerce_admin_order_data_after_shipping_address', 'ltc_PrintTicketNumber' );

function ltc_PrintTicketNumber( $order ) {
	echo '<div class="clear"></div>';
	//print_r($order->get_id());
	$order_id = $order->get_id();
	$downloads = get_post_meta( $order_id, '_Order_Downloads', true );

	if ($downloads) {
		echo '<h3>Biglietti assegnati al cliente</h3>';
		echo '<p>';
		foreach($downloads as $ticket) {
			//print_r($ticket);
			$basepath 	= str_replace($ticket['name'],'',$ticket['file']);
			$sku		= str_replace(get_site_url().'/wp-content/uploads/woocommerce_uploads/','',$basepath);

			echo rtrim($sku,"/"). ' / ' .$ticket["name"].'<br/>';
		}
		echo '</p>';
		echo '<div class="clear"></div>';
	}

	// eventuali codici sconto
	if( $order->get_used_coupons() ) {
    	$coupons_count = count( $order->get_used_coupons() );
		echo '<div class="clear"></div>';
        echo '<h3>Codici sconto utilizzati:</h3> ';
        $i = 1;
        echo '<p>';
        foreach( $order->get_used_coupons() as $coupon) {
	        echo $coupon;
	        if( $i < $coupons_count )
	        	echo ', ';
	        $i++;
        }
        echo '</p>';
    }	
}



// [ BACKEND ]
// password per gli utenti meno difficile

/**
* Reduce the strength requirement on the woocommerce password.
* 3 = Strong  ...  0 = Very Weak
*/
function reduce_woocommerce_min_strength_requirement( $strength ) {
  return 2;
}
add_filter( 'woocommerce_min_password_strength', 'reduce_woocommerce_min_strength_requirement' );


// [ BACKEND ]
// fallback per registrare la route export dei report analytics
add_action( 'rest_api_init', 'ltc_register_wc_analytics_export_fallback', 99 );
function ltc_register_wc_analytics_export_fallback() {
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


// [ BACKEND ]
// export utenti coi campi che ci servono a noi
// vedi ./ltc-export-data.php

add_action( 'admin_menu', 'ltc_add_admin_menu' );
add_action( 'admin_init', 'ltc_settings_init' );
add_action( 'admin_enqueue_scripts', 'ltc_enqueue_css', 10 );
add_action( 'admin_enqueue_scripts', 'ltc_enqueue_wc_admin_analytics_export_script', 20 );
add_action( 'admin_post_ltc_export_orders_report', 'ltc_export_orders_report' );
add_action( 'admin_post_ltc_download_clienti_csv', 'ltc_download_clienti_csv' );

// Protezione file customer-data tramite .htaccess
add_action( 'admin_init', 'ltc_protect_customer_data_files' );

/**
 * Protegge i file customer-data.* bloccando l'accesso diretto tramite .htaccess
 * I file rimangono accessibili tramite endpoint WordPress autenticati
 */
function ltc_protect_customer_data_files() {
	// Ottieni il percorso della cartella uploads
	$upload_dir = wp_upload_dir();
	$uploads_path = $upload_dir['basedir'];
	
	// Verifica che la cartella uploads esista e sia scrivibile
	if ( ! is_dir( $uploads_path ) || ! is_writable( $uploads_path ) ) {
		return;
	}
	
	$htaccess_path = $uploads_path . '/.htaccess';
	$htaccess_content = "# Protezione file customer-data.*\n";
	$htaccess_content .= "# Questi file contengono dati sensibili e devono essere accessibili solo tramite endpoint WordPress autenticati\n";
	$htaccess_content .= "# File generato automaticamente dal plugin LTC Custom\n\n";
	
	// Blocca l'accesso diretto ai file customer-data.*
	$htaccess_content .= "<FilesMatch \"^(customer-data\\.(json|csv|txt))$\">\n";
	// Compatibilità Apache 2.4+
	$htaccess_content .= "    Require all denied\n";
	// Compatibilità Apache 2.2 (fallback)
	$htaccess_content .= "    <IfModule !mod_authz_core.c>\n";
	$htaccess_content .= "        Order deny,allow\n";
	$htaccess_content .= "        Deny from all\n";
	$htaccess_content .= "    </IfModule>\n";
	$htaccess_content .= "</FilesMatch>\n";
	
	// Scrivi il file .htaccess solo se il contenuto è diverso da quello esistente
	$existing_content = '';
	if ( file_exists( $htaccess_path ) ) {
		$existing_content = file_get_contents( $htaccess_path );
	}
	
	// Se il contenuto è diverso o il file non esiste, aggiornalo
	if ( $existing_content !== $htaccess_content ) {
		file_put_contents( $htaccess_path, $htaccess_content, LOCK_EX );
	}
}

add_action('LTC_weekly_action', 'refreshCSV');
function refreshCSV() {
	global $api_url;
	global $csv_filename;
	// retrieve data from API and generate csv in batches
	ltc_generate_csv_from_api($api_url, $csv_filename, true);
}
// clear scheduled action
wp_clear_scheduled_hook('LTC_daily_action');
// schedule action weekly
if ( ! wp_next_scheduled( 'LTC_weekly_action' ) ) {
	// Eseguire ogni settimana il lunedì alle 3:00 del mattino
	$next_monday_3am = strtotime('next Monday 3:00');
	wp_schedule_event( $next_monday_3am, 'weekly', 'LTC_weekly_action' );
}



function ltc_add_admin_menu(  ) { 
	// Solo gli amministratori possono vedere questa pagina
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	
	add_menu_page( 'Export Clienti', 'Export Clienti', 'manage_options', 'lts-export-clienti', 'lts_options_page', 'dashicons-database-export', 70 );

}

function ltc_enqueue_css( $hook_suffix ) {
    if( 'toplevel_page_lts-export-clienti' === $hook_suffix ) {       
        wp_enqueue_style('ltc-admin-custom-css', plugins_url('../assets/ltc-admin-custom.css',__FILE__));
    }
}

function ltc_enqueue_wc_admin_analytics_export_script( $hook_suffix ) {
	if ( 'woocommerce_page_wc-admin' !== $hook_suffix ) {
		return;
	}

	if ( empty( $_GET['page'] ) || 'wc-admin' !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}

	$path = isset( $_GET['path'] ) ? sanitize_text_field( wp_unslash( $_GET['path'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	if ( '/analytics/orders' !== $path ) {
		return;
	}

	wp_enqueue_script(
		'ltc-wc-analytics-export',
		plugins_url( '../assets/ltc-wc-analytics-export.js', __FILE__ ),
		array( 'wp-api-fetch' ),
		'1.0.0',
		true
	);

	wp_localize_script(
		'ltc-wc-analytics-export',
		'LTC_WC_ANALYTICS_EXPORT',
		array(
			'restUrl'        => esc_url_raw( rest_url() ),
			'restNonce'      => wp_create_nonce( 'wp_rest' ),
			'adminPostUrl'   => esc_url_raw( admin_url( 'admin-post.php' ) ),
			'exportNonce'    => wp_create_nonce( 'ltc_export_orders_report' ),
			'pollIntervalMs' => 3000,
			'maxPollMs'      => 180000,
			'reportType'     => 'orders',
		)
	);
}

function ltc_export_orders_report() {
	if ( ! current_user_can( 'view_woocommerce_reports' ) ) {
		wp_die( esc_html__( 'Permessi insufficienti.', 'woocommerce' ) );
	}

	$nonce = isset( $_POST['ltc_export_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ltc_export_nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'ltc_export_orders_report' ) ) {
		wp_die( esc_html__( 'Nonce non valida.', 'woocommerce' ) );
	}

	$raw_args    = isset( $_POST['report_args'] ) ? wp_unslash( $_POST['report_args'] ) : '';
	$report_args = json_decode( $raw_args, true );
	if ( ! is_array( $report_args ) ) {
		$report_args = array();
	}

	$export_id = str_replace( '.', '', microtime( true ) );
	$export_id = (string) sanitize_file_name( $export_id );

	// Prima preparazione per ottenere total_rows senza scrivere file
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

	// Genera tutti i batch usando lo stesso exporter
	for ( $page = 1; $page <= $num_batches; $page++ ) {
		$batch_args = array_merge( $report_args, array( 'page' => $page ) );
		$exporter->set_report_args( $batch_args );
		$exporter->set_page( $page );
		$exporter->generate_file();
	}

	$exporter->export();
	exit;
}

/**
 * Endpoint sicuro per il download del CSV clienti
 * Verifica che l'utente sia un amministratore prima di servire il file
 */
function ltc_download_clienti_csv() {
	// Verifica che l'utente sia loggato
	if ( ! is_user_logged_in() ) {
		wp_die( esc_html__( 'Devi essere loggato per scaricare questo file.', 'woocommerce' ) );
	}
	
	// Verifica che l'utente sia un amministratore
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Non hai i permessi per scaricare questo file.', 'woocommerce' ) );
	}
	
	// Verifica il nonce (per POST usa _wpnonce)
	check_admin_referer( 'ltc_download_clienti_csv' );
	
	global $csv_filename;
	
	// Verifica che il file esista
	if ( ! file_exists( $csv_filename ) ) {
		wp_die( esc_html__( 'File non trovato.', 'woocommerce' ) );
	}
	
	// Imposta gli header per il download
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="customer-data.csv"' );
	header( 'Content-Length: ' . filesize( $csv_filename ) );
	header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
	header( 'Pragma: public' );
	
	// Leggi e invia il file
	readfile( $csv_filename );
	exit;
}

function ltc_settings_init() { 

	register_setting( 'pluginPage', 'lts_settings' );

	add_settings_section(
		'lts_pluginPage_section', 
		__( 'Pagina di Esportazione dati Clienti', 'woocommerce' ), 
		'lts_settings_section_callback', 
		'pluginPage'
	);


}

function lts_settings_section_callback() { 
	global $csv_url, $csv_filename;

	date_default_timezone_set('Europe/Rome');
	$nextRun = date('j M @ H:i', wp_next_scheduled( 'LTC_weekly_action' ));
	
	// Controlla se il file CSV esiste ed è stato aggiornato meno di 24 ore fa
	$csv_recent = false;
	$csv_age_hours = null;
	$csv_age_formatted = '';
	if (file_exists($csv_filename)) {
		$file_mtime = filemtime($csv_filename);
		$csv_age_seconds = time() - $file_mtime;
		$csv_age_hours = $csv_age_seconds / 3600;
		
		// Formatta in ore e minuti
		$hours = floor($csv_age_hours);
		$minutes = floor(($csv_age_seconds % 3600) / 60);
		
		if ($hours > 0 && $minutes > 0) {
			$csv_age_formatted = $hours . ' ore e ' . $minutes . ' minuti';
		} elseif ($hours > 0) {
			$csv_age_formatted = $hours . ' ore';
		} elseif ($minutes > 0) {
			$csv_age_formatted = $minutes . ' minuti';
		} else {
			$csv_age_formatted = 'meno di un minuto';
		}
		
		if ($csv_age_hours < 24) {
			$csv_recent = true;
		}
	}
	
	// Controlla se la generazione è in corso
	$is_generating = isset($_GET['regenerate_csv']) && $_GET['regenerate_csv'] == 'true';
	
	// Abilita il download se il file esiste ed è recente (<24h), ma NON durante la generazione
	$disabled = ($is_generating || !$csv_recent) ? ' disabled' : '';
	$regenerate_disabled = $is_generating ? ' disabled' : '';
	
	$csv_info = '';
	if ($csv_recent && $csv_age_formatted !== '' && !$is_generating) {
		$csv_info = '<br><small style="color: #46b450;">CSV recente (aggiornato ' . $csv_age_formatted . ' fa)</small>';
	} elseif (file_exists($csv_filename) && $csv_age_formatted !== '' && !$is_generating) {
		$csv_info = '<br><small style="color: #dc3232;">CSV obsoleto (aggiornato ' . $csv_age_formatted . ' fa - si consiglia di rigenerare)</small>';
	}
	
	// Avviso durante la generazione
	$generation_notice = '';
	if ($is_generating) {
		$generation_notice = '<div class="notice notice-info" style="margin: 15px 0; padding: 12px; background-color: #fff3cd; border-left: 4px solid #ffb900;"><p><strong>⏳ Generazione lista clienti in corso...</strong><br>Attendere il completamento. Questa operazione può richiedere alcuni minuti. Non chiudere questa pagina.</p></div>';
	}
	
	// Genera nonce per il form POST di download
	$download_nonce = wp_create_nonce( 'ltc_download_clienti_csv' );
	
	echo '<div id="ltc_section_header">';
	echo "<script>
	function GETcsv(){
		// Crea e invia un form POST per il download (più affidabile del GET)
		const form = document.createElement('form');
		form.method = 'POST';
		form.action = '".esc_js(admin_url('admin-post.php'))."';
		form.target = '_blank';
		
		const actionInput = document.createElement('input');
		actionInput.type = 'hidden';
		actionInput.name = 'action';
		actionInput.value = 'ltc_download_clienti_csv';
		form.appendChild(actionInput);
		
		const nonceInput = document.createElement('input');
		nonceInput.type = 'hidden';
		nonceInput.name = '_wpnonce';
		nonceInput.value = '".esc_js($download_nonce)."';
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
					// Crea o mostra il messaggio di generazione in corso
					let noticeDiv = document.getElementById('ltc-generation-notice');
					if (!noticeDiv) {
						noticeDiv = document.createElement('div');
						noticeDiv.id = 'ltc-generation-notice';
						noticeDiv.className = 'notice notice-info';
						noticeDiv.style.cssText = 'margin: 15px 0; padding: 12px; background-color: #fff3cd; border-left: 4px solid #ffb900;';
						noticeDiv.innerHTML = '<p><strong>⏳ Generazione lista clienti in corso...</strong><br>Attendere il completamento. Questa operazione può richiedere alcuni minuti. Non chiudere questa pagina.</p>';
						const sectionHeader = document.getElementById('ltc_section_header');
						if (sectionHeader) {
							sectionHeader.insertBefore(noticeDiv, sectionHeader.querySelector('.page_agisci'));
						}
					}
					noticeDiv.style.display = 'block';
					
					// Disabilita i pulsanti
					const buttons = form.querySelectorAll('button');
					buttons.forEach(function(btn) {
						btn.disabled = true;
					});
					
					// Cambia il testo del bottone per feedback visivo
					if (regenerateBtn) {
						regenerateBtn.innerHTML = '&nbsp;&nbsp;Generazione in corso...';
					}
				}
			});
		}
	})();
	</script>";
	echo __( '<p class="page_spiega">Da questa pagina è possibile eseguire il download della lista clienti e relativi dettagli in formato CSV, importabile in excel.<br><small>Prossima generazione automatica: '.$nextRun.'</small>'.$csv_info.'</p>', 'woocommerce' );
	echo $generation_notice;
	echo '<div class="page_agisci"><input type="hidden" name="page" value="lts-export-clienti" />
	<input type="hidden" name="regenerate_csv" value="true" />
	<button'.$regenerate_disabled.' type="submit" value="regenerate_csv" class="button button-large button-primary dashicons-before dashicons-update">&nbsp;&nbsp;Aggiorna Lista Clienti</button>
	<button'.$disabled.' type="button" onclick="GETcsv()" class="button button-large button-primary dashicons-before dashicons-download">&nbsp;&nbsp;Download Lista Clienti (CSV)</button></div>';
	echo '</div>';

}

function lts_options_page() { 
	// Verifica che l'utente sia un amministratore
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Non hai i permessi per accedere a questa pagina.', 'woocommerce' ) );
	}
	
	global $api_url, $json_filename, $csv_filename, $csv_url, $ABSPATH;

		?>
		
		<form action='./admin.php' method='GET' name="gencsv" >
			<?php
			settings_fields( 'pluginPage' );
			do_settings_sections( 'pluginPage' );

			global $api_url;
			global $json_filename;
			global $csv_filename;
			global $csv_url;

			// Se la generazione è stata richiesta, eseguila e poi reindirizza
			if (isset($_GET['regenerate_csv']) && $_GET['regenerate_csv'] == 'true') {
				// Verifica nuovamente i permessi prima di generare il CSV
				if ( ! current_user_can( 'manage_options' ) ) {
					wp_die( esc_html__( 'Non hai i permessi per eseguire questa operazione.', 'woocommerce' ) );
				}
				
				// retrieve data from API and generate csv in batches
				ltc_generate_csv_from_api($api_url, $csv_filename, true);
				
				// Reindirizza alla stessa pagina senza il parametro regenerate_csv per rimuovere il messaggio di "generazione in corso"
				$redirect_url = admin_url('admin.php?page=lts-export-clienti&csv_generated=1');
				wp_redirect($redirect_url);
				exit;
			}
			
			// Mostra messaggio di successo se la generazione è appena stata completata
			if (isset($_GET['csv_generated']) && $_GET['csv_generated'] == '1') {
				echo '<div class="notice notice-success is-dismissible" style="margin: 15px 0;"><p><strong>✓ Lista clienti generata con successo!</strong></p></div>';
			}

			// display csv preview
			if (file_exists($csv_filename)) {
				// La funzione csvPreview usa GETcsv() che ora usa il form POST
				csvPreview($csv_filename);
			}

			?>

		</form>
		<?php

}

