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
    if ( ! is_a( $order, 'WC_Order' ) || ! isset( $email_id ) || !$wc_email->is_customer_email() || $order->get_status() != 'completed' ) {
        return $attachments;
    }
  	$order_id 				= $order->get_order_number();
  	// $downloads             	= $order->get_downloadable_items();
  	$downloads             	= get_post_meta( $order_id, '_Order_Downloads', true );
  	$unique_downloads 		= unique_multidim_array($downloads,'id');

	// LOG SOME STUFF
	$logger->info( '==================' );
	$logger->info( "---> Status for order ".$order_id.": ".$order->get_status() );
	$logger->info( wc_print_r($downloads, true ) );
	$logger->info( wc_print_r($unique_downloads, true ) );
	$logger->info( wc_print_r($order->get_downloadable_items(), true ) );
	$logger->info( "---> EMAIL ATTACHMENTS for order #".$order_id.": " );
	
  	if ( empty($downloads) ) {
        return $attachments;
    }

  	foreach ($unique_downloads as $download) {

		$DL_path = parse_url($download["file"], PHP_URL_PATH);
		$DL_path = ltrim($DL_path, '/');

		$logger->info( wc_print_r(ABSPATH . $DL_path, true ) );

  		$attachments[] = ABSPATH . $DL_path;

  	}

	return $attachments;
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

	$downloads_generated = (bool) $order->get_meta( '_GenerateDownloads_done', true );
	$order_downloads     = $order->get_meta( '_Order_Downloads', true );
	if ( ! $downloads_generated || empty( $order_downloads ) ) {
		return;
	}

	$order->update_meta_data( '_ltc_auto_completed_done', 'yes' );
	$order->save();

	$order->update_status( 'completed', 'LTC: auto-completamento Viva dopo verifica email processing, stock e ticket.' );
}


// [ EMAIL ]
// aggiungo codici sconto utilizzati e cod.biglietto riservato
add_action('woocommerce_email_customer_details', 'email_order_user_meta', 30, 3 );
function email_order_user_meta( $order, $sent_to_admin, $plain_text ) {
  	$order_id 				= $order->get_order_number();
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

