<?php

/*****************************************
 * BOOKING RELATED *
 *****************************************/


// [ BOOKING ]
// create and add download path ASAP after checkout completed
// based on:
// https://stackoverflow.com/questions/47747596/add-downloads-in-woocommerce-downloadable-product-programmatically

add_action('woocommerce_checkout_update_order_meta', 'before_checkout_create_order', 1, 2);
function before_checkout_create_order( $order_id, $data ) {
	GenerateDownloads_afterPayment( $order_id );
}

function GenerateDownloads_afterPayment( $order_id ) {
	///////////
	// **
	// TODO BETTER:
	// duplica ogni tanto i biglietti in $downloads
	// mi sembra che sia se compro più di un item, il secondo riporta anche i download per il primo.
	// ha a che fare sicuro con $downloads che dovrebbe essere svuotato quando passo al prossimo $product
	// forse ridichiarare $downloads = array() a inizio foreach di $items e contemporaneamente spostare $order->save(); dentro il foreach alla fine ?

	// per ora risolto con unique_multidim_array() 
	// per evitare doppi download della stesso item in fase di generazione:
	// in wp-content/themes/accelerate/woocommerce/emails/email-downloads.php#38
	///////////
	if ( ! $order_id )
        return;
    // Allow code execution only once 
    if( ! get_post_meta( $order_id, '_GenerateDownloads_done', true ) ) {

		$order = wc_get_order( $order_id );
		$items = $order->get_items();
		// $downloads = array();




		foreach ( $items as $item_id => $item ) {
			// echo '<pre>$item: <br><br>';
			// print_r($item->get_data());
			// echo '</pre>';

			$logger = wc_get_logger();
			$logger->info( '*++++++*' );
			//$logger->info( wc_print_r($item, true ) );

			$cart_item_data = $item->get_data();

			$product = wc_get_product($item->get_product_id());

            $logger->info( '-> ok, show me the downloads for '.$item->get_product_id() );
            $logger->info( wc_print_r($product->get_downloads(), true ) );
            $logger->info( '-> ok, but is downloadable?' );
            $logger->info( wc_print_r($product->is_downloadable(), true ) );


			if ($product->is_downloadable()) {

				$PDFfolder = $product->get_sku();
				$PDFmatrix = get_post_meta($cart_item_data['product_id'],'_product_code', true);
				$last_order_processed = get_post_meta( $cart_item_data['product_id'], 'last_order_processed', true) != '' ? get_post_meta( $cart_item_data['product_id'], 'last_order_processed', true) : 0;
				

				$cart_item_dl = '';

				$cart_item_dl = wc_get_product($cart_item_data['product_id']);
				
				// vedere se ci sono già downloads per questo item, e preservarli!!
				// $cart_item_dl->get_downloads();
				// $older_downloads = $cart_item_dl->get_downloads();
				//print_r($older_downloads);

				
				// Virtual+Downloadable item : YES
				$cart_item_dl->set_virtual( true );
				$cart_item_dl->set_downloadable( true );

				for($k=0; $k<$item['quantity']; $k++) {

					$PDFprogressive = get_post_meta($cart_item_data['product_id'],'_product_code_second', true);
					// add leading zeroes...
					$PDFprogressive_000 = str_pad($PDFprogressive,3,"0", STR_PAD_LEFT);
					// $file_url = get_site_url(null, '/wp-content/uploads/woocommerce_uploads/PDF39/' . $PDFmatrix.'_'.$PDFprogressive_000.'.pdf', 'https');
					$file_url = get_site_url(null, '/wp-content/uploads/woocommerce_uploads/'. $PDFfolder . '/' . $PDFmatrix.'_'.$PDFprogressive_000.'.pdf', 'https');
					$attachment_id = md5( $file_url );

					// Creating a download with... yes, WC_Product_Download class
					$download = new WC_Product_Download();

					$download->set_name( $PDFmatrix.'_'.$PDFprogressive_000.'.pdf' );
					$download->set_id( $attachment_id );
					$download->set_file( $file_url );

					$downloads[$attachment_id] = $download;

					// $cart_item_dl->set_download_limit( 3 ); // can be downloaded only once
					// $cart_item_dl->set_download_expiry( 7 ); // expires in a week

					update_post_meta( $cart_item_data['product_id'], '_product_code_second', $PDFprogressive+1 );

				}

				$cart_item_dl->set_downloads( $downloads );
				$cart_item_dl->save();


				if ($last_order_processed < $order_id) {
					// aggiorno last_order_processed a ordine pagato
					update_post_meta ( $cart_item_data['product_id'], 'last_order_processed', $order_id );
				}

			
			}

		}

		$order->update_meta_data( '_Order_Downloads', $downloads );

		// Flag the action as done (to avoid repetitions on reload for example)
		$order->update_meta_data( '_GenerateDownloads_done', true );
		$order->save();

	} else {
		$downloads = get_post_meta( $order_id, '_Order_Downloads', true );
	}

	return $downloads;
}


// [ BOOKING ]
// gestisce l'ordine cancellato:
// la quantità a amagazzino viene aggiornata in automatico, ma rimettiamo in vendita i biglietti 
add_action( 'woocommerce_order_status_cancelled', 'respawn_tickets', 
21, 1 );
function respawn_tickets( $order_id ) {

	$downloads 				= get_post_meta( $order_id, '_Order_Downloads', true );
	$unique_downloads 		= unique_multidim_array($downloads,'id');
	
	$order = wc_get_order( $order_id );
	$items = $order->get_items();
	$order_item = [];

	$respawned = 0;

	// LOAD THE WC LOGGER
	$logger = wc_get_logger();
	$logger->info( '==================' );
	$logger->info( "---> Status for order ".$order_id.": ".$order->get_status() );
	$logger->info( "---> listing respawned tickets # from order ".$order_id.": " );


	foreach ($unique_downloads as $reserved_ticket) {
		// recupero dati necessari scomponendo la url del file
		// metodo non elegante ma d'altra parte è così
		$basepath 			= str_replace($reserved_ticket['name'],'',$reserved_ticket['file']);
		$basepath 			= str_replace(get_site_url(null,"/","https"),ABSPATH,$basepath);
		$ticket_matrix 	= strstr($reserved_ticket['name'], '_', true);
		// $logger->info( "ABSPATH: ".ABSPATH );
		// $logger->info( "basepath: ".$basepath );

		// cerco il ticket con numero più alto
		// e mi preparo per generare il prossimo
		$files= glob($basepath.$ticket_matrix.'_*.pdf');
		sort($files); // sort the files from lowest to highest, alphabetically
		// $logger->info( wc_print_r($files, true ) );
		$last_ticket	= array_pop($files); // return the last element of the array
		$last_ticket = str_replace($basepath,'',$last_ticket); 
		// $logger->info( "possibly highest ticket: ".$last_ticket );

		$Hinum 	= str_replace($ticket_matrix.'_','',$last_ticket);
		$Hinum 	= str_replace('.pdf','',$Hinum);
		$ticket_respawned = $ticket_matrix.'_'.str_pad( intval($Hinum) + 1, 3, '0', STR_PAD_LEFT).'.pdf';
		// $logger->info( "possibly next ticket: ".$ticket_respawned );


		if ( file_exists($basepath.$reserved_ticket['name']) ) {
			
			// create new ticket and put them in queue to be sold
			copy($basepath.$reserved_ticket['name'], $basepath.$ticket_respawned);

			// deactivate previously reserved ticket
			rename($basepath.$reserved_ticket['name'], $basepath."_".$reserved_ticket['name']);
			$logger->info( $reserved_ticket['name']. " --> ".$ticket_respawned );

			$respawned++;
		} else {
			$logger->info( $reserved_ticket['name']." --> Errore! File non trovato." );
		}
		
	}

	$logger->info( $respawned . " of " . count($unique_downloads) . " tickets were respawned" );


}






// [ BOOKING ]
// aggiunge meta data se l'ordine contiene item in categoria "vacanze studio" o "longform" 
// array('vacanze-studio','longform')
// mi serve poi per export dati...
add_action('woocommerce_checkout_create_order', 'add_flag_to_order', 20, 2);
function add_flag_to_order( $order, $data ) {
	if ( has_product_category_in_cart( array('vacanze-studio','longform') ) ) :
    	$order->update_meta_data( '_Order_Flag', 'longform' );
    endif;
}