<?php

/*****************************************
 * EMAIL - Allegati biglietti, BCC, gate
 *****************************************/

defined( 'ABSPATH' ) || exit;


/*****************************************
 * NORMALIZZAZIONE DOWNLOADS
 *****************************************/

/**
 * Converte _Order_Downloads (array eterogeneo o WC_Product_Download) in array uniforme.
 *
 * @param mixed $downloads Meta _Order_Downloads.
 * @return array<int, array{id:string,file:string,name:string}>
 */
function iv_normalize_order_downloads_rows( $downloads ) {
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


/*****************************************
 * VERIFICA PDF PRONTI
 *****************************************/

/**
 * True se ogni biglietto in _Order_Downloads ha il file PDF presente su disco.
 *
 * @param WC_Order $order Ordine.
 * @return bool
 */
function iv_order_tickets_ready( $order ) {
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

	$normalized       = iv_normalize_order_downloads_rows( $downloads );
	$unique_downloads = iv_unique_multidim_array( $normalized, 'id' );

	if ( empty( $unique_downloads ) ) {
		return false;
	}

	foreach ( $unique_downloads as $download ) {
		if ( empty( $download['file'] ) ) {
			return false;
		}

		$dl_path = parse_url( $download['file'], PHP_URL_PATH );
		if ( empty( $dl_path ) ) {
			return false;
		}

		$attachment_path = ABSPATH . ltrim( $dl_path, '/' );
		if ( ! file_exists( $attachment_path ) ) {
			return false;
		}
	}

	return true;
}


/*****************************************
 * GATE: blocca email completed se ticket non pronti
 *****************************************/

add_filter( 'woocommerce_email_enabled_customer_completed_order', 'iv_gate_completed_email', 5, 3 );

/**
 * Blocca l'invio della email "ordine completato" se:
 * - i biglietti PDF non sono ancora pronti, oppure
 * - l'email e' gia stata inviata in precedenza.
 *
 * Se i PDF non sono pronti, schedula un retry.
 */
function iv_gate_completed_email( $enabled, $order, $email = null ) {
	if ( ! $enabled || ! is_a( $order, 'WC_Order' ) ) {
		return $enabled;
	}

	$order_id = $order->get_id();

	if ( 'yes' === $order->get_meta( '_iv_completed_email_sent', true ) ) {
		return false;
	}

	if ( ! $order->has_downloadable_item() ) {
		return $enabled;
	}

	iv_generate_order_tickets( $order_id );

	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return false;
	}

	if ( iv_order_tickets_ready( $order ) ) {
		return $enabled;
	}

	wc_get_logger()->info(
		'IV gate: PDF non pronti per ordine #' . $order_id . ', pianifico retry.',
		array( 'source' => 'iv-email' )
	);
	iv_schedule_completed_email_retry( $order_id, 0 );

	return false;
}


/*****************************************
 * PREVENZIONE DUPLICATI
 *****************************************/

add_filter( 'woocommerce_email_enabled_customer_completed_order', 'iv_prevent_duplicate_completed_email', 15, 3 );

function iv_prevent_duplicate_completed_email( $enabled, $order, $email = null ) {
	if ( ! $enabled || ! is_a( $order, 'WC_Order' ) ) {
		return $enabled;
	}

	if ( 'yes' === $order->get_meta( '_iv_completed_email_sent', true ) ) {
		return false;
	}

	return $enabled;
}

add_action( 'woocommerce_email_sent', 'iv_mark_completed_email_sent', 10, 3 );

function iv_mark_completed_email_sent( $sent, $email_id, $email ) {
	if ( ! $sent || 'customer_completed_order' !== $email_id ) {
		return;
	}

	if ( ! is_object( $email ) || ! isset( $email->object ) || ! is_a( $email->object, 'WC_Order' ) ) {
		return;
	}

	$order = $email->object;

	if ( 'yes' === $order->get_meta( '_iv_completed_email_sent', true ) ) {
		return;
	}

	$order->update_meta_data( '_iv_completed_email_sent', 'yes' );
	$order->save();
}


/*****************************************
 * RETRY SCHEDULATO
 *****************************************/

/**
 * Schedula un singolo tentativo di invio email completed.
 *
 * @param int $order_id ID ordine.
 * @param int $attempt  Numero tentativo corrente.
 */
function iv_schedule_completed_email_retry( $order_id, $attempt = 0 ) {
	$order_id = (int) $order_id;
	$attempt  = (int) $attempt;

	if ( $order_id < 1 || $attempt > 10 ) {
		if ( $attempt > 10 ) {
			wc_get_logger()->error(
				'IV: PDF biglietti non pronti dopo 10 tentativi per ordine #' . $order_id,
				array( 'source' => 'iv-email' )
			);
		}
		return;
	}

	$hook = 'iv_retry_completed_email';
	$args = array( $order_id, $attempt );

	if ( function_exists( 'as_next_scheduled_action' ) && as_next_scheduled_action( $hook, $args, 'iv-email' ) ) {
		return;
	}

	if ( wp_next_scheduled( $hook, $args ) ) {
		return;
	}

	$delay = ( 0 === $attempt ) ? 3 : 5;

	if ( function_exists( 'as_schedule_single_action' ) ) {
		as_schedule_single_action( time() + $delay, $hook, $args, 'iv-email' );
	} else {
		wp_schedule_single_event( time() + $delay, $hook, $args );
	}
}

add_action( 'iv_retry_completed_email', 'iv_retry_completed_email_callback', 10, 2 );

/**
 * Callback del retry: verifica che i PDF siano pronti e ri-triggera l'email.
 *
 * @param int $order_id ID ordine.
 * @param int $attempt  Numero tentativo.
 */
function iv_retry_completed_email_callback( $order_id, $attempt = 0 ) {
	$order_id = (int) $order_id;
	$attempt  = (int) $attempt;

	if ( $order_id < 1 ) {
		return;
	}

	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return;
	}

	if ( 'yes' === $order->get_meta( '_iv_completed_email_sent', true ) ) {
		return;
	}

	if ( 'completed' !== $order->get_status() ) {
		return;
	}

	iv_generate_order_tickets( $order_id );

	$order = wc_get_order( $order_id );
	if ( ! $order || ! iv_order_tickets_ready( $order ) ) {
		iv_schedule_completed_email_retry( $order_id, $attempt + 1 );
		return;
	}

	foreach ( WC()->mailer()->get_emails() as $email ) {
		if ( 'customer_completed_order' === $email->id ) {
			$email->trigger( $order_id, $order );
			break;
		}
	}
}


/*****************************************
 * ALLEGATI PDF
 *****************************************/

add_filter( 'woocommerce_email_attachments', 'iv_attach_tickets_to_email', 10, 4 );

/**
 * Allega i PDF biglietto alla mail "Ordine completato".
 */
function iv_attach_tickets_to_email( $attachments, $email_id, $order, $wc_email ) {
	if ( ! is_a( $order, 'WC_Order' ) || ! isset( $email_id ) ) {
		return $attachments;
	}

	if ( is_object( $wc_email ) && method_exists( $wc_email, 'is_customer_email' ) && ! $wc_email->is_customer_email() ) {
		return $attachments;
	}

	if ( 'customer_completed_order' !== $email_id ) {
		return $attachments;
	}

	$order_id = $order->get_id();

	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return $attachments;
	}

	$downloads = $order->get_meta( '_Order_Downloads', true );

	if ( ( empty( $downloads ) || ! is_array( $downloads ) ) && $order->has_downloadable_item() ) {
		iv_generate_order_tickets( $order_id );
		$order     = wc_get_order( $order_id );
		$downloads = $order ? $order->get_meta( '_Order_Downloads', true ) : null;
	}

	if ( empty( $downloads ) || ! is_array( $downloads ) ) {
		return $attachments;
	}

	$normalized       = iv_normalize_order_downloads_rows( $downloads );
	$unique_downloads = iv_unique_multidim_array( $normalized, 'id' );

	if ( empty( $unique_downloads ) ) {
		return $attachments;
	}

	$logger = wc_get_logger();
	$logger->info( '---> Allegati email per ordine #' . $order_id . ' (' . $email_id . ')' );

	foreach ( $unique_downloads as $download ) {
		if ( empty( $download['file'] ) ) {
			continue;
		}

		$dl_path = parse_url( $download['file'], PHP_URL_PATH );
		if ( empty( $dl_path ) ) {
			continue;
		}

		$attachment_path = ABSPATH . ltrim( $dl_path, '/' );

		if ( ! file_exists( $attachment_path ) ) {
			$logger->info( '--> ticket file mancante: ' . $attachment_path );
			continue;
		}

		$attachments[] = $attachment_path;
	}

	return $attachments;
}


/*****************************************
 * BCC HEADERS
 *****************************************/

add_filter( 'woocommerce_email_headers', 'iv_bcc_completed_email', 10, 3 );

/**
 * Aggiunge BCC a ominodiwordpress@meuro.dev e booking@insiemeviaggi.com
 * solo per l'email "ordine completato".
 */
function iv_bcc_completed_email( $headers, $email_id = null, $order = null ) {
	if ( 'customer_completed_order' !== $email_id ) {
		return $headers;
	}

	$bcc_line  = 'Bcc: ominodiwordpress@meuro.dev' . "\r\n";
	$bcc_line .= 'Bcc: booking@insiemeviaggi.com' . "\r\n";

	if ( is_string( $headers ) ) {
		return $headers . $bcc_line;
	}

	if ( is_array( $headers ) ) {
		$headers[] = 'Bcc: ominodiwordpress@meuro.dev';
		$headers[] = 'Bcc: booking@insiemeviaggi.com';
		return $headers;
	}

	return $headers;
}


/*****************************************
 * CONTENUTO EMAIL: codici biglietto e coupon
 *****************************************/

add_action( 'woocommerce_email_customer_details', 'iv_email_order_ticket_details', 30, 3 );

function iv_email_order_ticket_details( $order, $sent_to_admin, $plain_text ) {
	$order_id = $order->get_id();

	$fresh_order = wc_get_order( $order_id );
	if ( $fresh_order ) {
		$order = $fresh_order;
	}

	if ( 'cancelled' === $order->get_status() ) {
		return;
	}

	$downloads        = $order->get_meta( '_Order_Downloads', true );
	$normalized       = iv_normalize_order_downloads_rows( $downloads );
	$unique_downloads = iv_unique_multidim_array( $normalized, 'id' );

	if ( ! empty( $unique_downloads ) ) {
		$ticket_count = count( $unique_downloads );
		echo '<p><strong>Biglietti acquistati (' . $ticket_count . '):</strong><br>';
		foreach ( $unique_downloads as $download ) {
			$ticket_name = isset( $download['name'] ) ? $download['name'] : '';
			$ticket_code = str_ireplace( '.pdf', '', $ticket_name );
			echo esc_html( $ticket_code ) . '<br>';
		}
		echo '</p>';
	}

	if ( $order->get_used_coupons() ) {
		$coupons_count = count( $order->get_used_coupons() );
		echo '<p><strong>Codici sconto utilizzati (' . $coupons_count . '):</strong><br>';
		foreach ( $order->get_used_coupons() as $coupon ) {
			echo esc_html( $coupon ) . '<br>';
		}
		echo '</p>';
	}
}


/*****************************************
 * NASCONDI META SCONTO DALLA VISUALIZZAZIONE
 *****************************************/

add_filter( 'woocommerce_hidden_order_itemmeta', 'iv_hide_discount_itemmeta_keys', 10, 1 );
function iv_hide_discount_itemmeta_keys( $hidden ) {
	$hidden[] = 'discount_amount';
	$hidden[] = 'discount_amount_tax';
	return $hidden;
}

add_filter( 'woocommerce_order_item_get_formatted_meta_data', 'iv_hide_discount_meta_from_display', 10, 2 );
function iv_hide_discount_meta_from_display( $formatted_meta, $item ) {
	$keys_to_hide = array( 'discount_amount', 'discount_amount_tax' );

	foreach ( $formatted_meta as $meta_id => $meta ) {
		if ( in_array( $meta->key, $keys_to_hide, true ) ) {
			unset( $formatted_meta[ $meta_id ] );
		}
	}

	return $formatted_meta;
}
