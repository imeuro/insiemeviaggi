<?php

/*****************************************
 * BOOKING RELATED *
 *****************************************/


// [ BOOKING ]
// genera e riserva i ticket quando l'ordine entra in uno stato "impegnativo".
// Viva: processing dopo pagamento; BACS: on-hold subito dopo il checkout.
// Priorità 5: prima delle email WooCommerce (di solito 10) e prima dei fail-safe stock (50),
// così _Order_Downloads esiste già prima delle email WooCommerce (di solito prio 10).
add_action( 'woocommerce_order_status_processing', 'ltc_generate_downloads_on_order_status', 5, 2 );
add_action( 'woocommerce_order_status_on-hold', 'ltc_generate_downloads_on_order_status', 5, 2 );
add_action( 'woocommerce_order_status_completed', 'ltc_generate_downloads_on_order_status', 5, 2 );
function ltc_generate_downloads_on_order_status( $order_id, $order ) {
	GenerateDownloads_afterPayment( $order_id );
}

// [ BOOKING ]
// fail-safe: per BACS su on-hold forziamo la riduzione stock se non risulta gia' applicata.
add_action( 'woocommerce_order_status_on-hold', 'ltc_ensure_bacs_stock_reduction', 50, 2 );
function ltc_ensure_bacs_stock_reduction( $order_id, $order ) {
	if ( ! is_a( $order, 'WC_Order' ) ) {
		$order = wc_get_order( $order_id );
	}

	if ( ! $order || 'bacs' !== $order->get_payment_method() ) {
		return;
	}

	$stock_reduced = (bool) $order->get_data_store()->get_stock_reduced( $order->get_id() );
	if ( $stock_reduced ) {
		return;
	}

	wc_maybe_reduce_stock_levels( $order->get_id() );

	$stock_reduced_after = (bool) $order->get_data_store()->get_stock_reduced( $order->get_id() );
	if ( $stock_reduced_after ) {
		$order->add_order_note( 'LTC: riduzione stock forzata su ordine BACS in stato on-hold.' );
	}
}

// [ BOOKING ]
// fail-safe Viva Smart: riduzione stock su completed (flusso reale osservato nei log).
add_action( 'woocommerce_order_status_completed', 'ltc_ensure_vivacom_smart_stock_reduction', 50, 2 );
function ltc_ensure_vivacom_smart_stock_reduction( $order_id, $order ) {
	if ( ! is_a( $order, 'WC_Order' ) ) {
		$order = wc_get_order( $order_id );
	}

	if ( ! $order || 'vivacom_smart' !== $order->get_payment_method() ) {
		return;
	}

	// Idempotenza forte: eseguiamo una sola volta il fail-safe "per-riga ordine".
	if ( 'yes' === $order->get_meta( '_ltc_viva_stock_enforced', true ) ) {
		return;
	}

	$line_changes = 0;
	$line_notes   = array();

	foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
		$product = $item->get_product();
		if ( ! $product || ! $product->managing_stock() ) {
			continue;
		}

		$original_checkout_qty = (int) $item->get_meta( '_ltc_original_checkout_qty', true );
		$item_qty              = (int) $item->get_quantity();
		$qty_to_use            = $original_checkout_qty > 0 ? $original_checkout_qty : max( 0, $item_qty );

		if ( $qty_to_use < 1 ) {
			continue;
		}

		$before_stock = $product->get_stock_quantity();
		wc_update_product_stock( $product, $qty_to_use, 'decrease' );
		$after_product = wc_get_product( $product->get_id() );
		$after_stock   = $after_product ? $after_product->get_stock_quantity() : null;

		if ( $before_stock !== $after_stock ) {
			$line_changes++;
		}

		$line_notes[] = sprintf(
			'item:%d product:%d qty:%d stock:%s->%s',
			(int) $item_id,
			(int) $product->get_id(),
			(int) $qty_to_use,
			( null === $before_stock ? 'null' : (string) $before_stock ),
			( null === $after_stock ? 'null' : (string) $after_stock )
		);
	}

	$stock_reduced_flag = (bool) $order->get_data_store()->get_stock_reduced( $order->get_id() );
	if ( ! $stock_reduced_flag ) {
		wc_maybe_reduce_stock_levels( $order->get_id() );
	}

	$order->update_meta_data( '_ltc_viva_stock_enforced', 'yes' );
	$order->save();

	$order->add_order_note(
		'LTC: verifica/riduzione stock su completed (vivacom_smart). Righe aggiornate: ' .
		$line_changes .
		'. Dettagli: ' . implode( ' | ', $line_notes )
	);
}

// [ BOOKING ]
// debug temporaneo flusso ordine: quantita'/totali/status per Viva e BACS.
function ltc_order_debug_log( $message, $context = array() ) {
	$logger = wc_get_logger();
	$data   = ! empty( $context ) ? ' | ' . wp_json_encode( $context ) : '';
	$logger->info( $message . $data, array( 'source' => 'ltc-order-debug' ) );
}

/**
 * Acquire an atomic per-product lock for ticket sequence updates.
 * Uses add_option (unique option_name) to avoid race conditions across requests/processes.
 *
 * @param int $product_id Product ID.
 * @param int $wait_seconds Max seconds to wait for lock.
 * @return string|false Lock key on success, false on timeout.
 */
function ltc_acquire_product_ticket_lock( $product_id, $wait_seconds = 8 ) {
	$product_id = (int) $product_id;
	if ( $product_id < 1 ) {
		return false;
	}

	$lock_key = 'ltc_ticket_seq_lock_' . $product_id;
	$deadline = microtime( true ) + max( 1, (int) $wait_seconds );

	do {
		if ( add_option( $lock_key, (string) time(), '', 'no' ) ) {
			return $lock_key;
		}

		usleep( 150000 ); // 150ms backoff
	} while ( microtime( true ) < $deadline );

	return false;
}

/**
 * Release per-product ticket lock.
 *
 * @param string $lock_key Lock key returned by ltc_acquire_product_ticket_lock().
 * @return void
 */
function ltc_release_product_ticket_lock( $lock_key ) {
	if ( empty( $lock_key ) ) {
		return;
	}

	delete_option( (string) $lock_key );
}

/**
 * Check whether a ticket filename is already reserved in order meta _Order_Downloads.
 * Works with classic postmeta and (if present) HPOS orders meta table.
 *
 * @param string $ticket_name Ticket file name (e.g. DITRAP_635.pdf).
 * @param int    $exclude_order_id Optional order ID to exclude.
 * @return bool
 */
function ltc_is_ticket_name_already_reserved( $ticket_name, $exclude_order_id = 0 ) {
	global $wpdb;

	$ticket_name = trim( (string) $ticket_name );
	if ( '' === $ticket_name ) {
		return false;
	}

	$exclude_order_id = (int) $exclude_order_id;
	$like             = '%' . $wpdb->esc_like( $ticket_name ) . '%';

	$postmeta_sql = "SELECT meta_id FROM {$wpdb->postmeta} WHERE meta_key = '_Order_Downloads' AND meta_value LIKE %s";
	$postmeta_args = array( $like );

	if ( $exclude_order_id > 0 ) {
		$postmeta_sql   .= ' AND post_id <> %d';
		$postmeta_args[] = $exclude_order_id;
	}

	$found = $wpdb->get_var( $wpdb->prepare( $postmeta_sql . ' LIMIT 1', $postmeta_args ) );
	if ( ! empty( $found ) ) {
		return true;
	}

	$hpos_meta_table = $wpdb->prefix . 'wc_orders_meta';
	$table_exists    = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $hpos_meta_table ) ) === $hpos_meta_table );

	if ( ! $table_exists ) {
		return false;
	}

	$hpos_sql  = "SELECT id FROM {$hpos_meta_table} WHERE meta_key = '_Order_Downloads' AND meta_value LIKE %s";
	$hpos_args = array( $like );

	if ( $exclude_order_id > 0 ) {
		$hpos_sql   .= ' AND order_id <> %d';
		$hpos_args[] = $exclude_order_id;
	}

	$found_hpos = $wpdb->get_var( $wpdb->prepare( $hpos_sql . ' LIMIT 1', $hpos_args ) );
	return ! empty( $found_hpos );
}

function ltc_is_debug_target_order( $order ) {
	if ( ! is_a( $order, 'WC_Order' ) ) {
		return false;
	}

	$payment_method = $order->get_payment_method();
	if ( ! in_array( $payment_method, array( 'vivacom_smart', 'bacs' ), true ) ) {
		return false;
	}

	return true;
}

add_action( 'woocommerce_checkout_create_order_line_item', 'ltc_debug_checkout_line_item', 20, 4 );
function ltc_debug_checkout_line_item( $item, $cart_item_key, $values, $order ) {
	if ( ! ltc_is_debug_target_order( $order ) ) {
		return;
	}

	ltc_order_debug_log(
		'checkout_create_order_line_item',
		array(
			'order_id'       => $order->get_id(),
			'payment_method' => $order->get_payment_method(),
			'cart_item_key'  => $cart_item_key,
			'product_id'     => isset( $values['product_id'] ) ? (int) $values['product_id'] : 0,
			'quantity'       => isset( $values['quantity'] ) ? (int) $values['quantity'] : 0,
			'line_subtotal'  => isset( $values['line_subtotal'] ) ? (float) $values['line_subtotal'] : 0,
			'line_total'     => isset( $values['line_total'] ) ? (float) $values['line_total'] : 0,
			'item_total'     => (float) $item->get_total(),
			'item_subtotal'  => (float) $item->get_subtotal(),
		)
	);
}

add_action( 'woocommerce_checkout_create_order_line_item', 'ltc_capture_checkout_original_qty_meta', 5, 4 );
function ltc_capture_checkout_original_qty_meta( $item, $cart_item_key, $values, $order ) {
	$checkout_qty = isset( $values['quantity'] ) ? (int) $values['quantity'] : (int) $item->get_quantity();
	$checkout_qty = max( 0, $checkout_qty );
	$item->update_meta_data( '_ltc_original_checkout_qty', $checkout_qty );
}

add_action( 'woocommerce_checkout_order_processed', 'ltc_debug_checkout_order_processed', 20, 3 );
function ltc_debug_checkout_order_processed( $order_id, $posted_data, $order ) {
	if ( ! ltc_is_debug_target_order( $order ) ) {
		return;
	}

	$items = array();
	foreach ( $order->get_items() as $item ) {
		$items[] = array(
			'item_id'      => $item->get_id(),
			'product_id'   => $item->get_product_id(),
			'name'         => $item->get_name(),
			'qty'          => (int) $item->get_quantity(),
			'checkout_qty' => (int) $item->get_meta( '_ltc_original_checkout_qty', true ),
			'line_total'   => (float) $item->get_total(),
			'line_subtotal'=> (float) $item->get_subtotal(),
		);
	}

	ltc_order_debug_log(
		'checkout_order_processed',
		array(
			'order_id'       => $order_id,
			'payment_method' => $order->get_payment_method(),
			'status'         => $order->get_status(),
			'order_total'    => (float) $order->get_total(),
			'discount_total' => (float) $order->get_discount_total(),
			'coupons'        => $order->get_coupon_codes(),
			'items'          => $items,
		)
	);
}

add_action( 'woocommerce_order_status_changed', 'ltc_debug_order_status_changed', 20, 4 );
function ltc_debug_order_status_changed( $order_id, $from, $to, $order ) {
	if ( ! ltc_is_debug_target_order( $order ) ) {
		return;
	}

	$stock_reduced = (bool) $order->get_data_store()->get_stock_reduced( $order_id );

	ltc_order_debug_log(
		'order_status_changed',
		array(
			'order_id'       => $order_id,
			'payment_method' => $order->get_payment_method(),
			'from'           => $from,
			'to'             => $to,
			'order_total'    => (float) $order->get_total(),
			'stock_reduced'  => $stock_reduced,
			'downloads_done' => (bool) $order->get_meta( '_GenerateDownloads_done', true ),
			'downloads_count'=> count( (array) $order->get_meta( '_Order_Downloads', true ) ),
		)
	);
}

// [ BOOKING ]
// fallback Viva: se il webhook/gateway non processano l'ordine, lo completiamo noi sul return success.
// Priorità 999: gira DOPO l'handler del gateway plugin, così interviene solo se l'ordine è ancora pending.
add_action( 'woocommerce_api_wc_vivacom_smart_success', 'ltc_viva_success_fallback_complete_order', 999 );
function ltc_viva_success_fallback_complete_order() {
	if ( empty( $_GET['t'] ) || empty( $_GET['s'] ) ) {
		return;
	}

	if ( ! class_exists( 'WC_Vivacom_Smart_Helpers' ) ) {
		return;
	}

	$transaction_id = sanitize_text_field( wp_unslash( $_GET['t'] ) );
	$order_code     = sanitize_text_field( wp_unslash( $_GET['s'] ) );

	global $wpdb;
	$wc_order_id = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT woocommerce_order_id FROM {$wpdb->prefix}viva_com_smart_wc_checkout_orders WHERE vivacom_order_code = %s ORDER BY date_add DESC LIMIT 1",
			$order_code
		)
	);

	if ( empty( $wc_order_id ) ) {
		return;
	}

	// Lock: impedisce esecuzioni concorrenti (webhook + return URL in parallelo).
	$lock_key = 'ltc_viva_lock_' . $wc_order_id;
	if ( get_transient( $lock_key ) ) {
		return;
	}
	set_transient( $lock_key, 1, 120 );

	// Ri-leggiamo l'ordine dopo il lock per avere lo stato aggiornato.
	$order = wc_get_order( $wc_order_id );
	if ( ! $order || 'vivacom_smart' !== $order->get_payment_method() ) {
		delete_transient( $lock_key );
		return;
	}

	if ( 'pending' !== $order->get_status() ) {
		delete_transient( $lock_key );
		return;
	}

	$viva_settings         = get_option( 'woocommerce_vivacom_smart_settings' );
	$environment           = ( isset( $viva_settings['test_mode'] ) && 'yes' === $viva_settings['test_mode'] ) ? 'demo' : 'live';
	$bearer_authentication = WC_Vivacom_Smart_Helpers::get_bearer_authentication( $environment );

	if ( ! $bearer_authentication->hasValidToken() ) {
		delete_transient( $lock_key );
		return;
	}

	$transaction_response = WC_Vivacom_Smart_Helpers::get_transaction( $bearer_authentication, $transaction_id );
	if ( empty( $transaction_response ) ) {
		delete_transient( $lock_key );
		return;
	}

	if ( empty( $transaction_response->orderCode ) || empty( $transaction_response->statusId ) ) {
		delete_transient( $lock_key );
		return;
	}

	if ( (string) $transaction_response->orderCode !== $order_code || 'F' !== (string) $transaction_response->statusId ) {
		delete_transient( $lock_key );
		return;
	}

	$order->payment_complete( $transaction_id );
	$order->add_order_note( 'LTC: ordine completato via fallback return success Viva (webhook non ricevuto in tempo).' );
	$order->save();
}

function GenerateDownloads_afterPayment( $order_id ) {
	if ( ! $order_id ) {
		return null;
	}

	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return null;
	}

	if ( $order->get_meta( '_GenerateDownloads_done', true ) ) {
		return $order->get_meta( '_Order_Downloads', true );
	}

	// Lock: impedisce che due processi concorrenti (webhook + return) generino biglietti doppi
	// e incrementino _product_code_second due volte (consumando numeri di biglietto).
	$lock_key = 'ltc_gen_dl_' . $order_id;
	if ( get_transient( $lock_key ) ) {
		return null;
	}
	set_transient( $lock_key, 1, 60 );

	// Double-check dopo aver acquisito il lock.
	$order = wc_get_order( $order_id );
	if ( ! $order || $order->get_meta( '_GenerateDownloads_done', true ) ) {
		delete_transient( $lock_key );
		return $order ? $order->get_meta( '_Order_Downloads', true ) : null;
	}

	$items     = $order->get_items();
	$downloads = array();
	$logger    = wc_get_logger();

	foreach ( $items as $item_id => $item ) {
		$logger->info( '*++++++*' );

		$cart_item_data = $item->get_data();
		$product        = wc_get_product( $item->get_product_id() );

		$logger->info( '-> ok, show me the downloads for ' . $item->get_product_id() );
		$logger->info( wc_print_r( $product->get_downloads(), true ) );
		$logger->info( '-> ok, but is downloadable?' );
		$logger->info( wc_print_r( $product->is_downloadable(), true ) );

		if ( ! $product->is_downloadable() ) {
			continue;
		}

		$PDFfolder            = $product->get_sku();
		$PDFmatrix            = get_post_meta( $cart_item_data['product_id'], '_product_code', true );
		$last_order_processed = get_post_meta( $cart_item_data['product_id'], 'last_order_processed', true );
		$last_order_processed = ( '' !== $last_order_processed ) ? (int) $last_order_processed : 0;

		$original_checkout_qty = (int) $item->get_meta( '_ltc_original_checkout_qty', true );
		$item_qty              = (int) $item->get_quantity();
		$qty_to_use            = $original_checkout_qty > 0 ? $original_checkout_qty : max( 0, $item_qty );

		$product_lock_key = ltc_acquire_product_ticket_lock( (int) $cart_item_data['product_id'], 10 );
		if ( false === $product_lock_key ) {
			$logger->error(
				'LTC ticket lock timeout: impossibile riservare progressivo univoco',
				array(
					'source'     => 'ltc-order-debug',
					'order_id'   => $order_id,
					'item_id'    => $item_id,
					'product_id' => (int) $cart_item_data['product_id'],
				)
			);
			delete_transient( $lock_key );
			return null;
		}

		try {
			$next_progressive = (int) get_post_meta( $cart_item_data['product_id'], '_product_code_second', true );
			if ( $next_progressive < 1 ) {
				$next_progressive = 1;
			}

			for ( $k = 0; $k < $qty_to_use; $k++ ) {
				$attempts_for_ticket = 0;
				$ticket_assigned     = false;

				while ( $attempts_for_ticket < 5000 ) {
					$PDFprogressive_000 = str_pad( (string) $next_progressive, 3, '0', STR_PAD_LEFT );
					$ticket_name        = $PDFmatrix . '_' . $PDFprogressive_000 . '.pdf';

					if ( ltc_is_ticket_name_already_reserved( $ticket_name, $order_id ) ) {
						$logger->warning(
							'LTC ticket già riservato, salto progressivo',
							array(
								'source'       => 'ltc-order-debug',
								'order_id'     => $order_id,
								'product_id'   => (int) $cart_item_data['product_id'],
								'ticket_name'  => $ticket_name,
								'progressive'  => $next_progressive,
							)
						);
						$next_progressive++;
						$attempts_for_ticket++;
						continue;
					}

					$file_rel_path = '/wp-content/uploads/woocommerce_uploads/' . $PDFfolder . '/' . $ticket_name;
					$file_abs_path = ABSPATH . ltrim( $file_rel_path, '/' );

					if ( ! file_exists( $file_abs_path ) ) {
						$logger->warning(
							'LTC ticket file non trovato, salto progressivo',
							array(
								'source'      => 'ltc-order-debug',
								'order_id'    => $order_id,
								'product_id'  => (int) $cart_item_data['product_id'],
								'ticket_name' => $ticket_name,
								'path'        => $file_abs_path,
							)
						);
						$next_progressive++;
						$attempts_for_ticket++;
						continue;
					}

					$file_url      = get_site_url( null, $file_rel_path, 'https' );
					$attachment_id = md5( $file_url );

					$download = new WC_Product_Download();
					$download->set_name( $ticket_name );
					$download->set_id( $attachment_id );
					$download->set_file( $file_url );

					$downloads[ $attachment_id ] = $download;
					$next_progressive++;
					$ticket_assigned = true;
					break;
				}

				if ( ! $ticket_assigned ) {
					$logger->error(
						'LTC impossibile assegnare ticket univoco dopo troppi tentativi',
						array(
							'source'     => 'ltc-order-debug',
							'order_id'   => $order_id,
							'product_id' => (int) $cart_item_data['product_id'],
							'qty_index'  => $k,
						)
					);
					delete_transient( $lock_key );
					return null;
				}
			}

			update_post_meta( $cart_item_data['product_id'], '_product_code_second', $next_progressive );

			if ( $last_order_processed < $order_id ) {
				update_post_meta( $cart_item_data['product_id'], 'last_order_processed', $order_id );
			}
		} finally {
			ltc_release_product_ticket_lock( $product_lock_key );
		}
	}

	$order->update_meta_data( '_Order_Downloads', $downloads );
	$order->update_meta_data( '_GenerateDownloads_done', true );
	$order->save();

	delete_transient( $lock_key );

	return $downloads;
}


// [ BOOKING ]
// gestisce l'ordine cancellato:
// la quantità a amagazzino viene aggiornata in automatico, ma rimettiamo in vendita i biglietti 
add_action( 'woocommerce_order_status_cancelled', 'respawn_tickets', 
21, 1 );
add_action( 'woocommerce_order_status_failed', 'respawn_tickets', 21, 1 );
function respawn_tickets( $order_id ) {

	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return;
	}

	$downloads        = $order->get_meta( '_Order_Downloads', true );
	$normalized       = function_exists( 'ltc_normalize_order_downloads_rows' ) ? ltc_normalize_order_downloads_rows( $downloads ) : array();
	$unique_downloads = ! empty( $normalized ) ? unique_multidim_array( $normalized, 'id' ) : array();
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