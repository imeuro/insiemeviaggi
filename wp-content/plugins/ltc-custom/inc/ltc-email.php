<?php 

/*****************************************
 * EMAIL RELATED *
 *****************************************/

/**
 * Converte i download salvati su ordine (array o WC_Product_Download) in array uniformi per id/file/name.
 *
 * @param mixed $downloads Meta _Order_Downloads.
 * @return array<int, array{id:string,file:string,name:string}>
 */
function ltc_normalize_order_downloads_rows( $downloads ) {
	if ( empty( $downloads ) || ! is_array( $downloads ) ) {
		return array();
	}

	$rows = array();

	foreach ( $downloads as $d ) {
		if ( is_array( $d ) && isset( $d['id'] ) ) {
			$rows[] = array(
				'id'   => (string) $d['id'],
				'file' => isset( $d['file'] ) ? (string) $d['file'] : '',
				'name' => isset( $d['name'] ) ? (string) $d['name'] : '',
			);
			continue;
		}

		if ( is_object( $d ) && is_a( $d, 'WC_Product_Download' ) ) {
			$rows[] = array(
				'id'   => (string) $d->get_id(),
				'file' => (string) $d->get_file(),
				'name' => (string) $d->get_name(),
			);
		}
	}

	return $rows;
}

/**
 * True se ogni biglietto in _Order_Downloads ha file PDF presente su disco.
 *
 * @param WC_Order $order Ordine.
 * @return bool
 */
function ltc_order_ticket_pdf_files_ready( $order ) {
	if ( ! is_a( $order, 'WC_Order' ) ) {
		return false;
	}

	if ( ! $order->has_downloadable_item() ) {
		return true;
	}

	$downloads = $order->get_meta( '_Order_Downloads', true );
	if ( empty( $downloads ) || ! is_array( $downloads ) ) {
		return false;
	}

	$normalized_rows  = ltc_normalize_order_downloads_rows( $downloads );
	$unique_downloads = unique_multidim_array( $normalized_rows, 'id' );

	if ( empty( $unique_downloads ) ) {
		return false;
	}

	foreach ( $unique_downloads as $download ) {
		if ( empty( $download['file'] ) ) {
			return false;
		}

		$DL_path = parse_url( $download['file'], PHP_URL_PATH );
		if ( empty( $DL_path ) ) {
			return false;
		}

		$attachment_path = ABSPATH . ltrim( $DL_path, '/' );
		if ( ! file_exists( $attachment_path ) ) {
			return false;
		}
	}

	return true;
}

/**
 * Pianifica un solo invio "Ordine completato" quando i PDF sono pronti (evita il primo invio vuoto).
 *
 * @param int $order_id ID ordine.
 * @param int $attempt  Tentativo (max ~20).
 */
function ltc_schedule_completed_email_when_ready( $order_id, $attempt = 0 ) {
	$order_id = (int) $order_id;
	$attempt  = (int) $attempt;

	if ( $order_id < 1 ) {
		return;
	}

	if ( $attempt > 20 ) {
		wc_get_logger()->error(
			'LTC: PDF biglietti non pronti dopo i tentativi; ordine #' . $order_id,
			array( 'source' => 'ltc-email' )
		);
		return;
	}

	$hook = 'ltc_send_customer_completed_when_ready';
	$args = array( $order_id, $attempt );

	if ( function_exists( 'as_next_scheduled_action' ) && as_next_scheduled_action( $hook, $args, 'ltc-email' ) ) {
		return;
	}

	if ( wp_next_scheduled( $hook, $args ) ) {
		return;
	}

	$delay = ( 0 === $attempt ) ? 3 : 5;

	if ( function_exists( 'as_schedule_single_action' ) ) {
		as_schedule_single_action( time() + $delay, $hook, $args, 'ltc-email' );
	} else {
		wp_schedule_single_event( time() + $delay, $hook, $args );
	}
}

/**
 * Invio ritardato della mail "Ordine completato" (solo quando i PDF esistono).
 *
 * @param int $order_id ID ordine.
 * @param int $attempt  Tentativo.
 */
function ltc_send_customer_completed_when_ready_callback( $order_id, $attempt = 0 ) {
	$order_id = (int) $order_id;
	$attempt  = (int) $attempt;

	if ( $order_id < 1 ) {
		return;
	}

	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return;
	}

	if ( 'yes' === $order->get_meta( '_ltc_customer_completed_email_sent_once', true ) ) {
		return;
	}

	if ( 'completed' !== $order->get_status() ) {
		return;
	}

	if ( $order->has_downloadable_item() && function_exists( 'GenerateDownloads_afterPayment' ) ) {
		GenerateDownloads_afterPayment( $order_id );
	}

	$order = wc_get_order( $order_id );
	if ( ! $order || ! ltc_order_ticket_pdf_files_ready( $order ) ) {
		ltc_schedule_completed_email_when_ready( $order_id, $attempt + 1 );
		return;
	}

	foreach ( WC()->mailer()->get_emails() as $email ) {
		if ( 'customer_completed_order' === $email->id ) {
			$email->trigger( $order_id, $order );
			break;
		}
	}
}

add_action( 'ltc_send_customer_completed_when_ready', 'ltc_send_customer_completed_when_ready_callback', 10, 2 );

// [ EMAIL ]
// Un solo invio "Ordine completato" al cliente, solo con PDF pronti.
// Usa transient come lock atomico cross-request: due HTTP concorrenti (webhook + return URL)
// non possono entrambi superare il gate, perché il transient è visibile a tutti i processi
// nel momento in cui viene scritto in wp_options (a differenza dell'order meta in cache).
add_filter( 'woocommerce_email_enabled_customer_completed_order', 'ltc_gate_completed_email_until_downloads_ready', 5, 3 );
function ltc_gate_completed_email_until_downloads_ready( $enabled, $order, $email = null ) {
	$logger = wc_get_logger();

	if ( ! $enabled || ! is_a( $order, 'WC_Order' ) ) {
		return $enabled;
	}

	$order_id = $order->get_id();

	// Lock cross-request: il primo processo che scrive il transient "vince".
	$lock_key = 'ltc_completed_email_lock_' . $order_id;
	if ( get_transient( $lock_key ) ) {
		$logger->info( 'LTC gate: lock attivo per ordine #' . $order_id . ', blocco email duplicata.', array( 'source' => 'ltc-email' ) );
		return false;
	}
	set_transient( $lock_key, 1, 300 );

	// Rileggiamo dal DB.
	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return false;
	}

	// Backup: se il flag ordine esiste già (es. invio precedente) blocchiamo.
	if ( 'yes' === $order->get_meta( '_ltc_customer_completed_email_sent_once', true ) ) {
		$logger->info( 'LTC gate: email completato già inviata per ordine #' . $order_id . ', blocco.', array( 'source' => 'ltc-email' ) );
		return false;
	}

	if ( ! $order->has_downloadable_item() ) {
		$order->update_meta_data( '_ltc_customer_completed_email_sent_once', 'yes' );
		$order->save();
		return $enabled;
	}

	if ( function_exists( 'GenerateDownloads_afterPayment' ) ) {
		GenerateDownloads_afterPayment( $order_id );
	}

	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return false;
	}

	if ( ltc_order_ticket_pdf_files_ready( $order ) ) {
		$order->update_meta_data( '_ltc_customer_completed_email_sent_once', 'yes' );
		$order->save();
		$logger->info( 'LTC gate: PDF pronti, autorizzo email completato per ordine #' . $order_id, array( 'source' => 'ltc-email' ) );
		return $enabled;
	}

	// PDF non pronti: rilascia il lock (il retry lo riacquisirà) e pianifica.
	delete_transient( $lock_key );
	$logger->info( 'LTC gate: PDF non pronti, pianifico retry per ordine #' . $order_id, array( 'source' => 'ltc-email' ) );
	ltc_schedule_completed_email_when_ready( $order_id, 0 );

	return false;
}

// [ EMAIL ]
// Allega i PDF biglietto alla mail "Ordine completato".
// L'oggetto $order passato dal filtro è spesso stale (cache in-memory WC): rileggiamo SEMPRE dal DB.
add_filter( 'woocommerce_email_attachments', 'attach_to_wc_emails', 10, 4);
function attach_to_wc_emails( $attachments, $email_id, $order, $wc_email ) {
	$logger = wc_get_logger();

	if ( ! is_a( $order, 'WC_Order' ) || ! isset( $email_id ) || ! $wc_email->is_customer_email() ) {
		return $attachments;
	}

	$supported_email_ids = array(
		'customer_completed_order',
	);

	if ( ! in_array( $email_id, $supported_email_ids, true ) ) {
		return $attachments;
	}

	$order_id = $order->get_id();

	// Rileggiamo l'ordine dal DB: l'oggetto dal filtro ha meta stale (non vede _Order_Downloads).
	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return $attachments;
	}

	$downloads = $order->get_meta( '_Order_Downloads', true );

	if ( ( empty( $downloads ) || ! is_array( $downloads ) ) && $order->has_downloadable_item() && function_exists( 'GenerateDownloads_afterPayment' ) ) {
		GenerateDownloads_afterPayment( $order_id );
		$order     = wc_get_order( $order_id );
		$downloads = $order ? $order->get_meta( '_Order_Downloads', true ) : null;
	}

	if ( empty( $downloads ) || ! is_array( $downloads ) ) {
		return $attachments;
	}

	$normalized_rows  = ltc_normalize_order_downloads_rows( $downloads );
	$unique_downloads = unique_multidim_array( $normalized_rows, 'id' );

	$logger->info( '==================' );
	$logger->info( '---> Status for order ' . $order_id . ': ' . $order->get_status() );
	$logger->info( wc_print_r( $downloads, true ) );
	$logger->info( wc_print_r( $unique_downloads, true ) );
	$logger->info( '---> EMAIL ATTACHMENTS for order #' . $order_id . ' (' . $email_id . '): ' );

	if ( empty( $unique_downloads ) ) {
		return $attachments;
	}

	foreach ( $unique_downloads as $download ) {
		if ( empty( $download['file'] ) ) {
			continue;
		}

		$DL_path = parse_url( $download['file'], PHP_URL_PATH );
		if ( empty( $DL_path ) ) {
			continue;
		}

		$attachment_path = ABSPATH . ltrim( $DL_path, '/' );

		if ( ! file_exists( $attachment_path ) ) {
			$logger->info( '--> ticket file missing: ' . $attachment_path );
			continue;
		}

		$logger->info( wc_print_r( $attachment_path, true ) );
		$attachments[] = $attachment_path;
	}

	return $attachments;
}

// [ EMAIL ]
// evita invii doppi della "ordine completato" allo stesso cliente (dopo il gate sui PDF, prio 5).
add_filter( 'woocommerce_email_enabled_customer_completed_order', 'ltc_prevent_duplicate_completed_email', 15, 3 );
function ltc_prevent_duplicate_completed_email( $enabled, $order, $email = null ) {
	if ( ! $enabled || ! is_a( $order, 'WC_Order' ) ) {
		return $enabled;
	}

	$already_sent = $order->get_meta( '_ltc_customer_completed_email_sent_once', true );
	if ( 'yes' === $already_sent ) {
		return false;
	}

	return $enabled;
}

// [ EMAIL ]
// "Ordine completato" solo al cliente (il merchant riceve "Nuovo ordine" dalle impostazioni WC).
add_filter( 'woocommerce_email_recipient_customer_completed_order', 'ltc_customer_completed_recipient_customer_only', 10, 2 );

function ltc_customer_completed_recipient_customer_only( $recipient, $object ) {
	return $recipient;
}

// [ EMAIL ]
// Nuovo ordine al merchant: assicura che l'email sia abilitata (WooCommerce > Impostazioni > Email > Nuovo ordine).
add_filter( 'woocommerce_email_enabled_new_order', 'ltc_ensure_new_order_email_enabled_for_merchant', 999, 3 );
function ltc_ensure_new_order_email_enabled_for_merchant( $enabled, $object = null, $email = null ) {
	if ( ! apply_filters( 'ltc_force_enable_new_order_email', true ) ) {
		return $enabled;
	}

	return true;
}

// [ EMAIL ]
// BCC di debug: inoltra tutte le email WooCommerce anche a me.
add_filter( 'woocommerce_email_headers', 'woo_cc_all_emails' );
function woo_cc_all_emails() {
	return 'Bcc: ominodiwordpress@meuro.dev' . "\r\n";
}
// Forza BCC a booking@insiemeviaggi.com solo per la email "ordine completato" (customer_completed_order)
add_filter( 'woocommerce_email_headers', 'ltc_bcc_booking_completed_email_only', 20, 3 );
function ltc_bcc_booking_completed_email_only( $headers, $email_id = null, $order = null ) {
	if ( $email_id !== 'customer_completed_order' ) {
		return $headers;
	}
	$extra_bcc = 'Bcc: booking@insiemeviaggi.com' . "\r\n";

	if ( is_string( $headers ) ) {
		return $headers . $extra_bcc;
	}
	if ( is_array( $headers ) ) {
		$headers[] = 'Bcc: booking@insiemeviaggi.com';
		return $headers;
	}
	return $headers;
}



// [ EMAIL ]
// traccia l'invio della mail "ordine in lavorazione" per ordini Viva.
add_action( 'woocommerce_email_sent', 'ltc_track_processing_order_email_sent', 10, 3 );
function ltc_track_processing_order_email_sent( $sent, $email_id, $email ) {
	if ( ! $sent || 'customer_processing_order' !== $email_id ) {
		return;
	}

	if ( ! is_object( $email ) || ! isset( $email->object ) || ! is_a( $email->object, 'WC_Order' ) ) {
		return;
	}

	$order = $email->object;

	if ( 'vivacom_smart' !== $order->get_payment_method() ) {
		return;
	}

	if ( $order->get_meta( '_ltc_processing_email_sent_at', true ) ) {
		$order_refreshed = wc_get_order( $order->get_id() );
		if ( $order_refreshed ) {
			ltc_maybe_auto_complete_viva_order( $order_refreshed );
		}
		return;
	}

	$order->update_meta_data( '_ltc_processing_email_sent_at', current_time( 'mysql' ) );
	$order->save();

	// Dopo invio mail "processing" possiamo verificare e chiudere in sicurezza.
	$order = wc_get_order( $order->get_id() );
	if ( $order ) {
		ltc_maybe_auto_complete_viva_order( $order );
	}
}

// [ EMAIL ]
// marca la completed email come inviata per prevenire eventuali duplicati futuri.
add_action( 'woocommerce_email_sent', 'ltc_mark_completed_order_email_sent', 20, 3 );
function ltc_mark_completed_order_email_sent( $sent, $email_id, $email ) {
	if ( ! $sent || 'customer_completed_order' !== $email_id ) {
		return;
	}

	if ( ! is_object( $email ) || ! isset( $email->object ) || ! is_a( $email->object, 'WC_Order' ) ) {
		return;
	}

	$order = $email->object;

	if ( 'yes' === $order->get_meta( '_ltc_customer_completed_email_sent_once', true ) ) {
		return;
	}

	$order->update_meta_data( '_ltc_customer_completed_email_sent_once', 'yes' );
	$order->save();
}

function ltc_maybe_auto_complete_viva_order( $order ) {
	if ( ! is_a( $order, 'WC_Order' ) || 'vivacom_smart' !== $order->get_payment_method() ) {
		return;
	}

	if ( 'processing' !== $order->get_status() ) {
		return;
	}

	if ( 'yes' === $order->get_meta( '_ltc_auto_completed_done', true ) ) {
		return;
	}

	// Se la mail completato è già partita non ri-completiamo (evita ciclo completed→processing→completed).
	if ( 'yes' === $order->get_meta( '_ltc_customer_completed_email_sent_once', true ) ) {
		return;
	}

	if ( ! $order->is_paid() ) {
		return;
	}

	$fresh = wc_get_order( $order->get_id() );
	if ( $fresh ) {
		$order = $fresh;
	}

	$stock_reduced = (bool) $order->get_data_store()->get_stock_reduced( $order->get_id() );
	if ( ! $stock_reduced ) {
		return;
	}

	if ( $order->has_downloadable_item() ) {
		$downloads_generated = (bool) $order->get_meta( '_GenerateDownloads_done', true );
		$order_downloads     = $order->get_meta( '_Order_Downloads', true );

		if ( ! $downloads_generated || empty( $order_downloads ) ) {
			return;
		}
	}

	$order->update_meta_data( '_ltc_auto_completed_done', 'yes' );
	$order->save();

	$order->update_status( 'completed', 'LTC: auto-completamento Viva dopo verifica email processing, stock e ticket.' );
}

// [ EMAIL ]
// aggiungo codici sconto utilizzati e cod.biglietto riservato
add_action('woocommerce_email_customer_details', 'email_order_user_meta', 30, 3 );
function email_order_user_meta( $order, $sent_to_admin, $plain_text ) {
  	$order_id = $order->get_id();

	// L'oggetto $order dal template email ha meta stale: rileggiamo dal DB.
	$fresh_order = wc_get_order( $order_id );
	if ( $fresh_order ) {
		$order = $fresh_order;
	}

  	$downloads  = $order->get_meta( '_Order_Downloads', true );
  	$normalized = ltc_normalize_order_downloads_rows( $downloads );
  	$unique_downloads = unique_multidim_array( $normalized, 'id' );


  	if($order->get_status() != 'cancelled') {
	  	// LOAD THE WC LOGGER
		$logger = wc_get_logger();
		$logger->info( '==================' );
		$logger->info( "---> Status for order ".$order_id.": ".$order->get_status() );
		$logger->info( "---> listing reserved tickets # for order ".$order_id.": " );
		// $logger->info( wc_print_r($downloads, true ) );
		// $logger->info( wc_print_r($unique_downloads, true ) );
		// $logger->info( wc_print_r($order->get_downloadable_items(), true ) );


	  	if (!empty($unique_downloads)) :
	  		$ticket_count = count( $unique_downloads );
			echo '<p><strong>Biglietti acquistati (' . $ticket_count . '):</strong><br>';
			foreach ($unique_downloads as $download) {
				$ticket_name = isset( $download['name'] ) ? $download['name'] : '';
				$ticket_code = str_ireplace( '.pdf', '', $ticket_name );
				echo $ticket_code.'<br>';

				$logger->info( wc_print_r($ticket_code, true ) );
			}
			echo '</p>';
		endif;

		if( $order->get_used_coupons() ) :
			$coupons_count = count( $order->get_used_coupons() );
			echo '<p><strong>Codici sconto utilizzati (' . $coupons_count . '):</strong><br>';
			$i = 1;
			$coupons_list = '';
			foreach( $order->get_used_coupons() as $coupon) {
			    echo $coupon.'<br>';
			}
			echo $coupons_list . '</p>';
		endif;
	}
}

// [ EMAIL / FRONTEND / BACKEND ]
// nasconde metadati tecnici di sconto dalla visualizzazione.

// Backend admin (pagina dettaglio ordine).
add_filter( 'woocommerce_hidden_order_itemmeta', 'ltc_hide_discount_itemmeta_keys', 10, 1 );
function ltc_hide_discount_itemmeta_keys( $hidden ) {
	$hidden[] = 'discount_amount';
	$hidden[] = 'discount_amount_tax';

	return $hidden;
}

// Frontend (order-received, my-account) e email: rimuove le righe di meta visibili al cliente.
add_filter( 'woocommerce_order_item_get_formatted_meta_data', 'ltc_hide_discount_meta_from_display', 10, 2 );
function ltc_hide_discount_meta_from_display( $formatted_meta, $item ) {
	$keys_to_hide = array( 'discount_amount', 'discount_amount_tax' );

	foreach ( $formatted_meta as $meta_id => $meta ) {
		if ( in_array( $meta->key, $keys_to_hide, true ) ) {
			unset( $formatted_meta[ $meta_id ] );
		}
	}

	return $formatted_meta;
}

