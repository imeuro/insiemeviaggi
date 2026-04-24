<?php

/*****************************************
 * TICKETS - Generazione e gestione biglietti
 *****************************************/

defined( 'ABSPATH' ) || exit;


/* Hook: genera biglietti quando l'ordine entra in completed o on-hold (BACS).
 * Priorita 5: prima delle email WooCommerce (priorita 10),
 * cosi _Order_Downloads esiste gia quando il filtro email_attachments viene eseguito. */
add_action( 'woocommerce_order_status_completed', 'iv_generate_tickets_on_status', 5, 2 );
add_action( 'woocommerce_order_status_on-hold', 'iv_generate_tickets_on_status', 5, 2 );

function iv_generate_tickets_on_status( $order_id, $order ) {
	iv_generate_order_tickets( $order_id );
}


/* Hook: respawn biglietti su cancellazione/fallimento ordine. */
add_action( 'woocommerce_order_status_cancelled', 'iv_respawn_tickets', 21, 1 );
add_action( 'woocommerce_order_status_failed', 'iv_respawn_tickets', 21, 1 );


/* Hook: flag ordine longform per export dati. */
add_action( 'woocommerce_checkout_create_order', 'iv_add_flag_to_order', 20, 2 );

function iv_add_flag_to_order( $order, $data ) {
	if ( iv_has_product_category_in_cart( array( 'vacanze-studio', 'longform' ) ) ) {
		$order->update_meta_data( '_Order_Flag', 'longform' );
	}
}


/*****************************************
 * LOGGING DIAGNOSTICO FLUSSO ORDINE
 *****************************************/

function iv_debug_log( $message, $context = array() ) {
	$logger = wc_get_logger();
	$data   = ! empty( $context ) ? ' | ' . wp_json_encode( $context ) : '';
	$logger->info( $message . $data, array( 'source' => 'iv-order-debug' ) );
}

// #region agent log — cattura qty originale dal cart al momento del checkout (prio 5)
add_action( 'woocommerce_checkout_create_order_line_item', 'iv_capture_checkout_qty', 5, 4 );
function iv_capture_checkout_qty( $item, $cart_item_key, $values, $order ) {
	$checkout_qty = isset( $values['quantity'] ) ? (int) $values['quantity'] : (int) $item->get_quantity();
	$checkout_qty = max( 0, $checkout_qty );
	$item->update_meta_data( '_iv_original_checkout_qty', $checkout_qty );

	$product     = wc_get_product( isset( $values['product_id'] ) ? (int) $values['product_id'] : 0 );
	$stock_before = $product ? $product->get_stock_quantity() : 'N/A';

	iv_debug_log( 'line_item_prio5', array(
		'hypothesisId'  => 'A,F',
		'order_id'      => $order->get_id(),
		'product_id'    => isset( $values['product_id'] ) ? (int) $values['product_id'] : 0,
		'cart_qty'      => isset( $values['quantity'] ) ? (int) $values['quantity'] : 'N/A',
		'item_qty'      => (int) $item->get_quantity(),
		'saved_orig'    => $checkout_qty,
		'stock_before_order' => $stock_before,
	) );
}
// #endregion

// #region agent log — verifica qty a fine di tutti gli hook create_order_line_item (prio 999)
add_action( 'woocommerce_checkout_create_order_line_item', 'iv_debug_late_line_item', 999, 4 );
function iv_debug_late_line_item( $item, $cart_item_key, $values, $order ) {
	iv_debug_log( 'line_item_prio999', array(
		'hypothesisId'  => 'F',
		'order_id'      => $order->get_id(),
		'product_id'    => isset( $values['product_id'] ) ? (int) $values['product_id'] : 0,
		'cart_qty'      => isset( $values['quantity'] ) ? (int) $values['quantity'] : 'N/A',
		'item_qty'      => (int) $item->get_quantity(),
		'orig_qty'      => (int) $item->get_meta( '_iv_original_checkout_qty' ),
	) );
}
// #endregion

// #region agent log — intercetta metadata_exists() durante save_item_data per _qty
add_filter( 'get_order_item_metadata', 'iv_intercept_metadata_exists_for_qty', 1, 5 );
function iv_intercept_metadata_exists_for_qty( $check, $object_id, $meta_key, $single, $meta_type ) {
	if ( '_qty' !== $meta_key ) {
		return $check;
	}

	static $checked_items = array();
	if ( isset( $checked_items[ $object_id ] ) ) {
		return $check;
	}
	$checked_items[ $object_id ] = true;

	global $wpdb;

	$all_meta = $wpdb->get_results( $wpdb->prepare(
		"SELECT meta_id, meta_key, meta_value FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE order_item_id = %d ORDER BY meta_id ASC",
		$object_id
	), ARRAY_A );

	$item_row = $wpdb->get_row( $wpdb->prepare(
		"SELECT order_item_id, order_item_name, order_item_type, order_id FROM {$wpdb->prefix}woocommerce_order_items WHERE order_item_id = %d",
		$object_id
	), ARRAY_A );

	iv_debug_log( 'META_EXISTS_INTERCEPT', array(
		'hypothesisId'     => 'ORPHAN_META',
		'item_id'          => $object_id,
		'item_row'         => $item_row,
		'all_meta_rows'    => $all_meta,
		'meta_count'       => count( $all_meta ),
		'filter_check_in'  => $check,
		'backtrace'        => iv_compact_backtrace( 12 ),
	) );

	return $check;
}
// #endregion

// #region agent log — traccia OGNI scrittura di _qty a livello DB con backtrace
add_action( 'added_order_item_meta', 'iv_trace_qty_meta_added', 10, 4 );
function iv_trace_qty_meta_added( $mid, $object_id, $meta_key, $meta_value ) {
	if ( '_qty' !== $meta_key ) {
		return;
	}
	$trace = iv_compact_backtrace( 20 );
	iv_debug_log( 'DB_qty_ADDED', array(
		'hypothesisId' => 'ROOT',
		'meta_id'      => $mid,
		'item_id'      => $object_id,
		'value'        => $meta_value,
		'backtrace'    => $trace,
	) );
}

add_action( 'updated_order_item_meta', 'iv_trace_qty_meta_updated', 10, 4 );
function iv_trace_qty_meta_updated( $meta_id, $object_id, $meta_key, $meta_value ) {
	if ( '_qty' !== $meta_key ) {
		return;
	}
	$trace = iv_compact_backtrace( 20 );
	iv_debug_log( 'DB_qty_UPDATED', array(
		'hypothesisId' => 'ROOT',
		'meta_id'      => $meta_id,
		'item_id'      => $object_id,
		'value'        => $meta_value,
		'backtrace'    => $trace,
	) );
}

function iv_compact_backtrace( $limit = 15 ) {
	$raw   = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, $limit );
	$lines = array();
	foreach ( $raw as $i => $frame ) {
		$file = isset( $frame['file'] ) ? basename( $frame['file'] ) : '?';
		$line = isset( $frame['line'] ) ? $frame['line'] : '?';
		$cls  = isset( $frame['class'] ) ? $frame['class'] . '::' : '';
		$fn   = isset( $frame['function'] ) ? $frame['function'] : '?';
		$lines[] = "#{$i} {$file}:{$line} {$cls}{$fn}";
	}
	return $lines;
}
// #endregion

// #region FIX — pulizia meta orfani e forzatura _qty corretta su item appena creato
add_action( 'woocommerce_new_order_item', 'iv_fix_orphan_meta_on_create', 5, 3 );
function iv_fix_orphan_meta_on_create( $item_id, $item, $order_id ) {
	if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
		return;
	}

	global $wpdb;
	$table = $wpdb->prefix . 'woocommerce_order_itemmeta';

	$orig_qty = (int) $item->get_meta( '_iv_original_checkout_qty' );
	if ( $orig_qty < 1 ) {
		$orig_qty = max( 1, (int) $item->get_quantity() );
	}

	$db_qty = $wpdb->get_var( $wpdb->prepare(
		"SELECT meta_value FROM {$table} WHERE order_item_id = %d AND meta_key = '_qty'",
		$item_id
	) );

	$was_orphan = false;
	if ( null !== $db_qty && (int) $db_qty !== $orig_qty ) {
		$was_orphan = true;

		wc_update_order_item_meta( $item_id, '_qty', $orig_qty );

		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$table} WHERE order_item_id = %d AND meta_key = '_reduced_stock'",
			$item_id
		) );

		wp_cache_delete( $item_id, 'order_item_meta' );
	}

	iv_debug_log( 'FIX_orphan_check', array(
		'hypothesisId' => 'FIX',
		'item_id'      => $item_id,
		'order_id'     => $order_id,
		'orig_qty'     => $orig_qty,
		'db_qty_before' => $db_qty,
		'was_orphan'   => $was_orphan,
	) );
}
// #endregion

// #region agent log — SQL raw + ALL meta check dopo creazione singolo item
add_action( 'woocommerce_new_order_item', 'iv_check_item_after_create', 10, 3 );
function iv_check_item_after_create( $item_id, $item, $order_id ) {
	if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
		return;
	}
	global $wpdb;

	$all_meta = $wpdb->get_results( $wpdb->prepare(
		"SELECT meta_id, meta_key, meta_value FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE order_item_id = %d ORDER BY meta_id ASC",
		$item_id
	), ARRAY_A );

	$qty_rows = array_filter( $all_meta, function( $row ) {
		return '_qty' === $row['meta_key'];
	} );

	$item_changes = $item->get_changes();
	$item_obj_read = $item->get_object_read();

	iv_debug_log( 'new_item_raw_check', array(
		'hypothesisId'      => 'ORPHAN_META',
		'item_id'           => $item_id,
		'order_id'          => $order_id,
		'item_obj_qty'      => (int) $item->get_quantity(),
		'item_obj_read'     => $item_obj_read,
		'item_changes'      => $item_changes,
		'qty_rows_in_db'    => array_values( $qty_rows ),
		'total_meta_count'  => count( $all_meta ),
		'all_meta_keys'     => array_unique( array_column( $all_meta, 'meta_key' ) ),
		'all_meta_ids'      => array_column( $all_meta, 'meta_id' ),
	) );
}
// #endregion

// #region agent log — check prima del save
add_action( 'woocommerce_before_order_object_save', 'iv_check_before_order_save', 1, 2 );
function iv_check_before_order_save( $order, $data_store ) {
	if ( ! is_a( $order, 'WC_Order' ) ) {
		return;
	}

	static $first_save_logged = false;
	if ( $first_save_logged ) {
		return;
	}

	$ds_class = get_class( $data_store );
	$items_info = array();
	foreach ( $order->get_items() as $item ) {
		$items_info[] = array(
			'has_id'      => $item->get_id() > 0,
			'item_id'     => $item->get_id(),
			'product_id'  => $item->get_product_id(),
			'qty'         => (int) $item->get_quantity(),
			'obj_read'    => $item->get_object_read(),
			'changes'     => $item->get_changes(),
		);
	}

	global $wpdb;
	$auto_inc = $wpdb->get_var(
		"SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$wpdb->prefix}woocommerce_order_items'"
	);

	$orphan_check = null;
	if ( $auto_inc ) {
		$orphan_check = $wpdb->get_results( $wpdb->prepare(
			"SELECT order_item_id, meta_key, meta_value FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE order_item_id >= %d ORDER BY order_item_id, meta_id LIMIT 20",
			(int) $auto_inc
		), ARRAY_A );
	}

	iv_debug_log( 'before_order_save', array(
		'hypothesisId'       => 'ORPHAN_META',
		'order_id'           => $order->get_id(),
		'data_store'         => $ds_class,
		'items'              => $items_info,
		'next_auto_inc'      => $auto_inc,
		'orphan_meta_at_aic' => $orphan_check,
	) );

	$first_save_logged = true;
}
// #endregion

// #region agent log — check dopo il primo save dell'ordine
add_action( 'woocommerce_after_order_object_save', 'iv_check_after_order_save', 1, 2 );
function iv_check_after_order_save( $order, $data_store ) {
	if ( ! is_a( $order, 'WC_Order' ) ) {
		return;
	}

	static $logged_count = 0;
	if ( $logged_count >= 2 ) {
		return;
	}
	$logged_count++;

	$items_info = array();
	foreach ( $order->get_items() as $item ) {
		$iid = $item->get_id();
		$raw_qty = null;
		if ( $iid > 0 ) {
			global $wpdb;
			$raw_qty = $wpdb->get_var( $wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE order_item_id = %d AND meta_key = '_qty'",
				$iid
			) );
		}
		$items_info[] = array(
			'item_id'     => $iid,
			'product_id'  => $item->get_product_id(),
			'obj_qty'     => (int) $item->get_quantity(),
			'raw_db_qty'  => $raw_qty,
			'obj_read'    => $item->get_object_read(),
			'changes'     => $item->get_changes(),
		);
	}
	iv_debug_log( 'after_order_save', array(
		'hypothesisId' => 'ORPHAN_META',
		'order_id'     => $order->get_id(),
		'items'        => $items_info,
	) );
}
// #endregion

// #region agent log — verifica qty finale post-save (prio 20)
add_action( 'woocommerce_checkout_order_processed', 'iv_verify_qty_post_save', 20, 3 );
function iv_verify_qty_post_save( $order_id, $posted_data, $order ) {
	global $wpdb;
	$items_info = array();
	foreach ( $order->get_items() as $item ) {
		$iid = $item->get_id();
		$raw_qty = $wpdb->get_var( $wpdb->prepare(
			"SELECT meta_value FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE order_item_id = %d AND meta_key = '_qty'",
			$iid
		) );
		$items_info[] = array(
			'item_id'     => $iid,
			'product_id'  => $item->get_product_id(),
			'obj_qty'     => (int) $item->get_quantity(),
			'raw_db_qty'  => $raw_qty,
			'orig_qty'    => (int) $item->get_meta( '_iv_original_checkout_qty', true ),
		);
	}

	iv_debug_log( 'post_save_verify', array(
		'hypothesisId'  => 'ROOT_VERIFY',
		'order_id'      => $order_id,
		'status'        => $order->get_status(),
		'items'         => $items_info,
	) );
}
// #endregion

// #region agent log — verifica qty e stock ad ogni cambio status
add_action( 'woocommerce_order_status_changed', 'iv_debug_status_changed', 20, 4 );
function iv_debug_status_changed( $order_id, $from, $to, $order ) {
	$stock_reduced = (bool) $order->get_data_store()->get_stock_reduced( $order_id );
	$items_data    = array();
	foreach ( $order->get_items() as $item ) {
		$product = $item->get_product();
		$items_data[] = array(
			'item_id'       => $item->get_id(),
			'product_id'    => $item->get_product_id(),
			'qty'           => (int) $item->get_quantity(),
			'orig_qty'      => (int) $item->get_meta( '_iv_original_checkout_qty', true ),
			'manage_stock'  => $product ? $product->managing_stock() : 'N/A',
			'stock_qty'     => $product ? $product->get_stock_quantity() : 'N/A',
		);
	}
	iv_debug_log( 'order_status_changed', array(
		'hypothesisId'   => 'C,D,E',
		'order_id'       => $order_id,
		'from'           => $from,
		'to'             => $to,
		'payment'        => $order->get_payment_method(),
		'stock_reduced'  => $stock_reduced,
		'downloads_done' => (bool) $order->get_meta( '_GenerateDownloads_done', true ),
		'items'          => $items_data,
	) );
}
// #endregion

// #region stock policy — disabilita riduzione core Woo su Viva Smart
add_filter( 'woocommerce_payment_complete_reduce_order_stock', 'iv_control_reduce_order_stock_for_vivacom_smart', 10, 2 );
function iv_control_reduce_order_stock_for_vivacom_smart( $trigger_reduce, $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return $trigger_reduce;
	}

	if ( 'vivacom_smart' !== $order->get_payment_method() ) {
		return $trigger_reduce;
	}

	// Per Viva Smart demandiamo la riduzione stock al flusso deterministico IV su completed.
	return false;
}
// #endregion

// #region agent log — stock reduction forzata con qty corretta
add_action( 'woocommerce_order_status_completed', 'iv_ensure_correct_stock_reduction', 50, 2 );
function iv_ensure_correct_stock_reduction( $order_id, $order ) {
	if ( ! is_a( $order, 'WC_Order' ) ) {
		$order = wc_get_order( $order_id );
	}
	if ( ! $order ) {
		return;
	}

	// Idempotenza forte: evita riesecuzioni sullo stesso ordine.
	if ( 'yes' === $order->get_meta( '_iv_stock_enforced', true ) ) {
		return;
	}

	$stock_reduced_flag = (bool) $order->get_data_store()->get_stock_reduced( $order_id );
	$items_detail       = array();
	$has_stock_items    = false;
	$has_reduction_work = false;
	$reduction_failed   = false;

	foreach ( $order->get_items() as $item ) {
		$product = $item->get_product();
		if ( ! $product || ! $product->managing_stock() ) {
			$items_detail[] = array(
				'product_id'          => $item->get_product_id(),
				'item_qty'            => (int) $item->get_quantity(),
				'orig_qty'            => (int) $item->get_meta( '_iv_original_checkout_qty', true ),
				'expected_qty'        => 0,
				'line_reduced_stock'  => (int) $item->get_meta( '_reduced_stock', true ),
				'missing_qty'         => 0,
				'manage_stock'        => false,
				'stock_now'           => $product ? $product->get_stock_quantity() : 'N/A',
			);
			continue;
		}

		$has_stock_items  = true;
		$orig_qty         = (int) $item->get_meta( '_iv_original_checkout_qty', true );
		$item_qty         = (int) $item->get_quantity();
		$expected_qty     = $orig_qty > 0 ? $orig_qty : max( 0, $item_qty );
		$reduced_qty      = (int) $item->get_meta( '_reduced_stock', true );
		$missing_qty      = max( 0, $expected_qty - $reduced_qty );
		$stock_before     = $product->get_stock_quantity();

		if ( $missing_qty > 0 ) {
			$has_reduction_work = true;
			$updated_stock      = wc_update_product_stock( $product, $missing_qty, 'decrease' );
			if ( is_wp_error( $updated_stock ) ) {
				$reduction_failed = true;
			} else {
				$item->update_meta_data( '_reduced_stock', $expected_qty );
				$item->save();
			}

			$product_after = wc_get_product( $product->get_id() );
			$stock_after   = $product_after ? $product_after->get_stock_quantity() : 'N/A';

			iv_debug_log( 'stock_forced_reduction', array(
				'hypothesisId'  => 'C',
				'order_id'      => $order_id,
				'product_id'    => $product->get_id(),
				'qty_reduced'   => $missing_qty,
				'stock_before'  => $stock_before,
				'stock_after'   => $stock_after,
				'expected_qty'  => $expected_qty,
				'reduced_before'=> $reduced_qty,
			) );
		}

		$items_detail[] = array(
			'product_id'          => $item->get_product_id(),
			'item_qty'            => $item_qty,
			'orig_qty'            => $orig_qty,
			'expected_qty'        => $expected_qty,
			'line_reduced_stock'  => $reduced_qty,
			'missing_qty'         => $missing_qty,
			'manage_stock'        => true,
			'stock_now'           => $stock_before,
		);
	}

	iv_debug_log( 'ensure_stock_entry', array(
		'hypothesisId'       => 'C,D',
		'order_id'           => $order_id,
		'payment'            => $order->get_payment_method(),
		'stock_reduced_flag' => $stock_reduced_flag,
		'has_stock_items'    => $has_stock_items,
		'has_reduction_work' => $has_reduction_work,
		'reduction_failed'   => $reduction_failed,
		'items'              => $items_detail,
	) );

	if ( $has_stock_items && ! $reduction_failed ) {
		$order->get_data_store()->set_stock_reduced( $order_id, true );
		if ( $has_reduction_work ) {
			$order->add_order_note( 'IV: stock riallineato in modo deterministico per-riga (delta mancante applicato).' );
		} else {
			$order->add_order_note( 'IV: stock già allineato per-riga; flag ordine riallineato.' );
		}
	}

	$order->update_meta_data( '_iv_stock_enforced', 'yes' );
	$order->save();
}
// #endregion

// #region agent log — protezione stock su transizione completed→processing
add_action( 'woocommerce_order_status_changed', 'iv_protect_stock_on_bogus_transition', 5, 4 );
function iv_protect_stock_on_bogus_transition( $order_id, $from, $to, $order ) {
	if ( 'completed' !== $from || 'processing' !== $to ) {
		return;
	}

	iv_debug_log( 'bogus_completed_to_processing', array(
		'hypothesisId' => 'stock_restore',
		'order_id'     => $order_id,
		'payment'      => $order->get_payment_method(),
	) );

	$stock_reduced = (bool) $order->get_data_store()->get_stock_reduced( $order_id );
	if ( $stock_reduced ) {
		add_filter( 'woocommerce_can_restore_order_stock', '__return_false', 999 );

		add_action( 'woocommerce_order_status_changed', function() {
			remove_filter( 'woocommerce_can_restore_order_stock', '__return_false', 999 );
		}, 6 );
	}
}
// #endregion


/*****************************************
 * GENERAZIONE BIGLIETTI
 *****************************************/

/**
 * Genera e assegna i biglietti PDF a un ordine.
 * Idempotente: non rigenera se _GenerateDownloads_done e' gia impostato.
 *
 * @param int $order_id ID ordine.
 * @return array|null Array di WC_Product_Download assegnati, o null.
 */
function iv_generate_order_tickets( $order_id ) {
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

	$lock_key = 'iv_gen_tickets_' . $order_id;
	if ( get_transient( $lock_key ) ) {
		return null;
	}
	set_transient( $lock_key, 1, 60 );

	$order = wc_get_order( $order_id );
	if ( ! $order || $order->get_meta( '_GenerateDownloads_done', true ) ) {
		delete_transient( $lock_key );
		return $order ? $order->get_meta( '_Order_Downloads', true ) : null;
	}

	$items     = $order->get_items();
	$downloads = array();
	$logger    = wc_get_logger();

	foreach ( $items as $item_id => $item ) {
		$product = wc_get_product( $item->get_product_id() );

		if ( ! $product || ! $product->is_downloadable() ) {
			continue;
		}

		$cart_item_data = $item->get_data();
		$product_id     = (int) $cart_item_data['product_id'];
		$pdf_folder     = $product->get_sku();
		$pdf_matrix     = get_post_meta( $product_id, '_product_code', true );
		$pdf_sku        = (string) $pdf_folder;

		$original_checkout_qty = (int) $item->get_meta( '_iv_original_checkout_qty', true );
		$item_qty              = (int) $item->get_quantity();
		$qty                   = $original_checkout_qty > 0 ? $original_checkout_qty : max( 0, $item_qty );

		// #region agent log — qty usata per generazione ticket
		iv_debug_log( 'generate_tickets_qty', array(
			'hypothesisId'  => 'A',
			'order_id'      => $order_id,
			'product_id'    => $product_id,
			'item_qty'      => $item_qty,
			'orig_qty'      => $original_checkout_qty,
			'qty_used'      => $qty,
		) );
		// #endregion

		if ( $qty < 1 ) {
			continue;
		}

		$product_lock_key = iv_acquire_product_ticket_lock( $product_id, 10 );
		if ( false === $product_lock_key ) {
			$logger->error( 'IV: lock timeout per progressivo ticket', array(
				'source'     => 'iv-tickets',
				'order_id'   => $order_id,
				'product_id' => $product_id,
			) );
			delete_transient( $lock_key );
			return null;
		}

		try {
			$next_progressive = (int) get_post_meta( $product_id, '_product_code_second', true );
			if ( $next_progressive < 1 ) {
				$next_progressive = 1;
			}

			for ( $k = 0; $k < $qty; $k++ ) {
				$attempts        = 0;
				$ticket_assigned = false;

				while ( $attempts < 5000 ) {
					$progressive_str = str_pad( (string) $next_progressive, 3, '0', STR_PAD_LEFT );
					$ticket_name     = $pdf_matrix . '_' . $progressive_str . '.pdf';

					if ( iv_is_ticket_already_reserved( $pdf_sku, (string) $pdf_matrix, $progressive_str, $order_id ) ) {
						iv_debug_log( 'ticket_collision_detected', array(
							'hypothesisId' => 'UNIQ_V2',
							'order_id'     => $order_id,
							'product_id'   => $product_id,
							'sku'          => $pdf_sku,
							'matrix'       => (string) $pdf_matrix,
							'progressive'  => $progressive_str,
							'ticket_name'  => $ticket_name,
						) );
						$next_progressive++;
						$attempts++;
						continue;
					}

					$file_rel_path = '/wp-content/uploads/woocommerce_uploads/' . $pdf_folder . '/' . $ticket_name;
					$file_abs_path = ABSPATH . ltrim( $file_rel_path, '/' );

					if ( ! file_exists( $file_abs_path ) ) {
						iv_debug_log( 'ticket_missing_file', array(
							'hypothesisId' => 'UNIQ_V2',
							'order_id'     => $order_id,
							'product_id'   => $product_id,
							'sku'          => $pdf_sku,
							'matrix'       => (string) $pdf_matrix,
							'progressive'  => $progressive_str,
							'ticket_name'  => $ticket_name,
							'file_abs_path'=> $file_abs_path,
						) );
						$next_progressive++;
						$attempts++;
						continue;
					}

					$file_url      = get_site_url( null, $file_rel_path, 'https' );
					$attachment_id = md5( $file_url );

					$unique_key = iv_build_ticket_unique_key( $pdf_sku, (string) $pdf_matrix, $progressive_str );
					$downloads[ $attachment_id ] = array(
						'id'            => $attachment_id,
						'name'          => $ticket_name,
						'file'          => $file_url,
						'sku'           => $pdf_sku,
						'matrix'        => (string) $pdf_matrix,
						'progressive'   => $progressive_str,
						'iv_unique_key' => $unique_key,
					);
					$next_progressive++;
					$ticket_assigned = true;
					break;
				}

				if ( ! $ticket_assigned ) {
					$logger->error( 'IV: impossibile assegnare ticket dopo troppi tentativi', array(
						'source'     => 'iv-tickets',
						'order_id'   => $order_id,
						'product_id' => $product_id,
						'qty_index'  => $k,
					) );
					delete_transient( $lock_key );
					return null;
				}
			}

			update_post_meta( $product_id, '_product_code_second', $next_progressive );

			$last_order = (int) get_post_meta( $product_id, 'last_order_processed', true );
			if ( $last_order < $order_id ) {
				update_post_meta( $product_id, 'last_order_processed', $order_id );
			}
		} finally {
			iv_release_product_ticket_lock( $product_lock_key );
		}
	}

	$order->update_meta_data( '_Order_Downloads', $downloads );
	$order->update_meta_data( '_GenerateDownloads_done', true );
	$order->save();

	delete_transient( $lock_key );

	return $downloads;
}


/*****************************************
 * RESPAWN BIGLIETTI (ordini annullati)
 *****************************************/

/**
 * Rimette in vendita i biglietti di un ordine annullato/fallito:
 * copia il PDF con un nuovo progressivo e disabilita l'originale.
 *
 * @param int $order_id ID ordine.
 */
function iv_respawn_tickets( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return;
	}

	$downloads        = $order->get_meta( '_Order_Downloads', true );
	$normalized       = function_exists( 'iv_normalize_order_downloads_rows' ) ? iv_normalize_order_downloads_rows( $downloads ) : array();
	$unique_downloads = ! empty( $normalized ) ? iv_unique_multidim_array( $normalized, 'id' ) : array();

	$logger    = wc_get_logger();
	$respawned = 0;

	$logger->info( '==================' );
	$logger->info( '---> Respawn tickets per ordine #' . $order_id . ' (status: ' . $order->get_status() . ')' );

	foreach ( $unique_downloads as $reserved_ticket ) {
		$basepath      = str_replace( $reserved_ticket['name'], '', $reserved_ticket['file'] );
		$basepath      = str_replace( get_site_url( null, '/', 'https' ), ABSPATH, $basepath );
		$ticket_matrix = strstr( $reserved_ticket['name'], '_', true );

		$files = glob( $basepath . $ticket_matrix . '_*.pdf' );
		sort( $files );
		$last_ticket = array_pop( $files );
		$last_ticket = str_replace( $basepath, '', $last_ticket );

		$hi_num           = str_replace( $ticket_matrix . '_', '', $last_ticket );
		$hi_num           = str_replace( '.pdf', '', $hi_num );
		$ticket_respawned = $ticket_matrix . '_' . str_pad( intval( $hi_num ) + 1, 3, '0', STR_PAD_LEFT ) . '.pdf';

		if ( file_exists( $basepath . $reserved_ticket['name'] ) ) {
			copy( $basepath . $reserved_ticket['name'], $basepath . $ticket_respawned );
			rename( $basepath . $reserved_ticket['name'], $basepath . '_' . $reserved_ticket['name'] );
			$logger->info( $reserved_ticket['name'] . ' --> ' . $ticket_respawned );
			$respawned++;
		} else {
			$logger->info( $reserved_ticket['name'] . ' --> Errore! File non trovato.' );
		}
	}

	$logger->info( $respawned . ' di ' . count( $unique_downloads ) . ' biglietti rigenerati' );
}


/*****************************************
 * LOCK ATOMICO PER PRODOTTO
 *****************************************/

/**
 * Acquisisce un lock atomico per-prodotto per aggiornamenti sequenza ticket.
 * Usa add_option (option_name univoco) per evitare race condition.
 *
 * @param int $product_id  ID prodotto.
 * @param int $wait_seconds Secondi massimi di attesa.
 * @return string|false Lock key in caso di successo, false se timeout.
 */
function iv_acquire_product_ticket_lock( $product_id, $wait_seconds = 8 ) {
	$product_id = (int) $product_id;
	if ( $product_id < 1 ) {
		return false;
	}

	$lock_key = 'iv_ticket_seq_lock_' . $product_id;
	$deadline = microtime( true ) + max( 1, (int) $wait_seconds );

	do {
		if ( add_option( $lock_key, (string) time(), '', 'no' ) ) {
			return $lock_key;
		}
		usleep( 150000 );
	} while ( microtime( true ) < $deadline );

	return false;
}

/**
 * Rilascia il lock per-prodotto.
 *
 * @param string $lock_key Lock key da iv_acquire_product_ticket_lock().
 */
function iv_release_product_ticket_lock( $lock_key ) {
	if ( empty( $lock_key ) ) {
		return;
	}
	delete_option( (string) $lock_key );
}


/*****************************************
 * VERIFICA UNICITA TICKET
 *****************************************/

/**
 * Costruisce la chiave univoca canonica ticket.
 *
 * @param string $sku         SKU prodotto.
 * @param string $matrix      Matrice ticket.
 * @param string $progressive Progressivo ticket (zero-padded).
 * @return string
 */
function iv_build_ticket_unique_key( $sku, $matrix, $progressive ) {
	$sku         = trim( (string) $sku );
	$matrix      = trim( (string) $matrix );
	$progressive = trim( (string) $progressive );
	if ( '' === $sku || '' === $matrix || '' === $progressive ) {
		return '';
	}
	return $sku . '|' . $matrix . '|' . $progressive;
}

/**
 * Verifica unicita esclusivamente per SKU+matrice+progressivo.
 *
 * @param string $sku              SKU prodotto.
 * @param string $matrix           Matrice ticket.
 * @param string $progressive      Progressivo ticket.
 * @param int    $exclude_order_id Ordine da escludere.
 * @return bool
 */
function iv_is_ticket_already_reserved( $sku, $matrix, $progressive, $exclude_order_id = 0 ) {
	global $wpdb;

	$unique_key = iv_build_ticket_unique_key( $sku, $matrix, $progressive );
	$exclude_order_id = (int) $exclude_order_id;

	if ( '' !== $unique_key ) {
		$uk_like = '%' . $wpdb->esc_like( $unique_key ) . '%';

		$postmeta_sql  = "SELECT meta_id FROM {$wpdb->postmeta} WHERE meta_key = '_Order_Downloads' AND meta_value LIKE %s";
		$postmeta_args = array( $uk_like );
		if ( $exclude_order_id > 0 ) {
			$postmeta_sql   .= ' AND post_id <> %d';
			$postmeta_args[] = $exclude_order_id;
		}
		$found = $wpdb->get_var( $wpdb->prepare( $postmeta_sql . ' LIMIT 1', $postmeta_args ) );
		if ( ! empty( $found ) ) {
			iv_debug_log( 'ticket_collision_unique_key', array(
				'hypothesisId' => 'UNIQ_V2',
				'unique_key'   => $unique_key,
				'source'       => 'postmeta',
			) );
			return true;
		}

		$hpos_meta_table = $wpdb->prefix . 'wc_orders_meta';
		$table_exists    = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $hpos_meta_table ) ) === $hpos_meta_table );
		if ( $table_exists ) {
			$hpos_sql  = "SELECT id FROM {$hpos_meta_table} WHERE meta_key = '_Order_Downloads' AND meta_value LIKE %s";
			$hpos_args = array( $uk_like );
			if ( $exclude_order_id > 0 ) {
				$hpos_sql   .= ' AND order_id <> %d';
				$hpos_args[] = $exclude_order_id;
			}
			$found_hpos = $wpdb->get_var( $wpdb->prepare( $hpos_sql . ' LIMIT 1', $hpos_args ) );
			if ( ! empty( $found_hpos ) ) {
				iv_debug_log( 'ticket_collision_unique_key', array(
					'hypothesisId' => 'UNIQ_V2',
					'unique_key'   => $unique_key,
					'source'       => 'hpos',
				) );
				return true;
			}
		}
	}

	return false;
}

/**
 * Verifica se un nome file ticket e' gia riservato in _Order_Downloads di un altro ordine.
 * Supporta sia postmeta classico che HPOS.
 *
 * @param string $ticket_name     Nome file (es. DITRAP_635.pdf).
 * @param int    $exclude_order_id Ordine da escludere dal controllo.
 * @return bool
 */
function iv_is_ticket_name_already_reserved( $ticket_name, $exclude_order_id = 0 ) {
	global $wpdb;

	$ticket_name = trim( (string) $ticket_name );
	if ( '' === $ticket_name ) {
		return false;
	}

	$exclude_order_id = (int) $exclude_order_id;
	$like             = '%' . $wpdb->esc_like( $ticket_name ) . '%';

	$postmeta_sql  = "SELECT meta_id FROM {$wpdb->postmeta} WHERE meta_key = '_Order_Downloads' AND meta_value LIKE %s";
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
