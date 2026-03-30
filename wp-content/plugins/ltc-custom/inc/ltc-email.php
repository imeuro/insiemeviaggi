<?php 

/*****************************************
 * EMAIL RELATED *
 *****************************************/


// [ EMAIL ]
// aggiungo pdf acquistati come allegato
add_filter( 'woocommerce_email_attachments', 'attach_to_wc_emails', 10, 4);
function attach_to_wc_emails( $attachments, $email_id, $order, $wc_email ) {
	// LOAD THE WC LOGGER
	$logger = wc_get_logger();

	// Avoiding errors and problems
	if ( ! is_a( $order, 'WC_Order' ) || ! isset( $email_id ) || ! $wc_email->is_customer_email() ) {
		return $attachments;
	}

	// Allego i PDF solo per email clienti legate ai download.
	$supported_email_ids = array(
		'customer_completed_order',
	);

	if ( ! in_array( $email_id, $supported_email_ids, true ) ) {
		return $attachments;
	}

	$order_id = $order->get_id();
	$downloads = get_post_meta( $order_id, '_Order_Downloads', true );

	// Robustezza: se i download non sono ancora pronti, proviamo a generarli al volo.
	if ( ( empty( $downloads ) || ! is_array( $downloads ) ) && $order->has_downloadable_item() && function_exists( 'GenerateDownloads_afterPayment' ) ) {
		GenerateDownloads_afterPayment( $order_id );
		$downloads = get_post_meta( $order_id, '_Order_Downloads', true );
	}

	if ( empty( $downloads ) || ! is_array( $downloads ) ) {
		// Fallback WooCommerce: usa i downloadable items nativi se disponibili.
		$downloads = $order->get_downloadable_items();
	}

	if ( empty( $downloads ) || ! is_array( $downloads ) ) {
		return $attachments;
	}

	// LOG SOME STUFF
	$logger->info( '==================' );
	$logger->info( "---> Status for order ".$order_id.": ".$order->get_status() );
	$logger->info( wc_print_r( $downloads, true ) );
	$logger->info( wc_print_r($order->get_downloadable_items(), true ) );
	$logger->info( "---> EMAIL ATTACHMENTS for order #".$order_id.": " );
	
	$seen_paths = array();
	foreach ( $downloads as $download ) {
		$file_url = '';
		$file_name = '';

		if ( is_object( $download ) ) {
			if ( method_exists( $download, 'get_file' ) ) {
				$file_url = (string) $download->get_file();
			}
			if ( method_exists( $download, 'get_name' ) ) {
				$file_name = (string) $download->get_name();
			}
		} elseif ( is_array( $download ) ) {
			$file_url = isset( $download['file']['file'] ) ? (string) $download['file']['file'] : ( isset( $download['file'] ) ? (string) $download['file'] : '' );
			$file_name = isset( $download['download_name'] ) ? (string) $download['download_name'] : '';
		}

		if ( '' === $file_url ) {
			continue;
		}

		$DL_path = parse_url( $file_url, PHP_URL_PATH );
		if ( empty( $DL_path ) ) {
			continue;
		}

		$DL_path = ltrim( $DL_path, '/' );
		$attachment_path = ABSPATH . $DL_path;

		// Evita allegati inesistenti.
		if ( isset( $seen_paths[ $attachment_path ] ) ) {
			continue;
		}

		if ( ! file_exists( $attachment_path ) ) {
			$logger->info( '--> ticket file missing: ' . $attachment_path . ( '' !== $file_name ? ' (' . $file_name . ')' : '' ) );
			continue;
		}

		$logger->info( wc_print_r( $attachment_path, true ) );
		$attachments[] = $attachment_path;
		$seen_paths[ $attachment_path ] = true;
	}

	return $attachments;
}

// [ EMAIL ]
// evita invii doppi della "ordine completato" allo stesso cliente.
add_filter( 'woocommerce_email_enabled_customer_completed_order', 'ltc_prevent_duplicate_completed_email', 10, 2 );
function ltc_prevent_duplicate_completed_email( $enabled, $order ) {
	if ( ! $enabled || ! is_a( $order, 'WC_Order' ) ) {
		return $enabled;
	}

	// Per Viva: la completed deve partire solo quando stock e allegati sono pronti.
	if ( 'vivacom_smart' === $order->get_payment_method() && $order->has_downloadable_item() ) {
		$downloads_generated = (bool) $order->get_meta( '_GenerateDownloads_done', true );
		$order_downloads     = $order->get_meta( '_Order_Downloads', true );
		$stock_reduced       = (bool) $order->get_data_store()->get_stock_reduced( $order->get_id() );

		if ( ! $downloads_generated || empty( $order_downloads ) || ! $stock_reduced ) {
			return false;
		}
	}

	$already_sent = $order->get_meta( '_ltc_customer_completed_email_sent_once', true );
	if ( 'yes' === $already_sent ) {
		return false;
	}

	return $enabled;
}

// [ EMAIL ] 
// invia email "ordine completato" anche a admin
add_filter( 'woocommerce_email_recipient_customer_completed_order', 'your_email_recipient_filter_function', 10, 2);

function your_email_recipient_filter_function($recipient, $object) {
    $recipient = $recipient . ', booking@insiemeviaggi.com';
    //$recipient = $recipient . ', ominodiwordpress@meuro.dev';
    return $recipient;
}

// [ EMAIL ] 
// *** TEMPORANEAMENTEH *** 
// invia tutte le email anche a me!!
function woo_cc_all_emails() {
  return 'Bcc: ominodiwordpress@meuro.dev' . "\r\n";
}
add_filter('woocommerce_email_headers', 'woo_cc_all_emails' );


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
		ltc_maybe_auto_complete_viva_order( $order );
		return;
	}

	$order->update_meta_data( '_ltc_processing_email_sent_at', current_time( 'mysql' ) );
	$order->save();

	// Dopo invio mail "processing" possiamo verificare e chiudere in sicurezza.
	ltc_maybe_auto_complete_viva_order( $order );
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

// [ EMAIL ]
// auto-completa ordini Viva solo dopo verifiche di sicurezza.
add_action( 'woocommerce_order_status_processing', 'ltc_auto_complete_viva_processing_order', 999, 2 );
function ltc_auto_complete_viva_processing_order( $order_id, $order ) {
	if ( ! is_a( $order, 'WC_Order' ) ) {
		$order = wc_get_order( $order_id );
	}

	if ( ! $order ) {
		return;
	}

	ltc_maybe_auto_complete_viva_order( $order );
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

	if ( ! $order->is_paid() ) {
		return;
	}

	$stock_reduced = (bool) $order->get_data_store()->get_stock_reduced( $order->get_id() );
	if ( ! $stock_reduced ) {
		return;
	}

	// Se l'ordine contiene prodotti scaricabili (biglietti PDF) richiediamo che i download siano pronti.
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
  	$order_id 				= $order->get_id();
  	// $downloads             	= $order->get_downloadable_items();
  	$downloads             	= get_post_meta( $order_id, '_Order_Downloads', true );
  	$unique_downloads 		= unique_multidim_array($downloads,'id');


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
				$ticket_code = str_ireplace('.pdf', '', $download['name']);
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

// [ EMAIL / FRONTEND ]
// nasconde metadati tecnici di sconto dalla visualizzazione (es. pagina ordine ricevuto).
add_filter( 'woocommerce_hidden_order_itemmeta', 'ltc_hide_discount_itemmeta_keys', 10, 1 );
function ltc_hide_discount_itemmeta_keys( $hidden ) {
	$hidden[] = 'discount_amount';
	$hidden[] = 'discount_amount_tax';

	return $hidden;
}

