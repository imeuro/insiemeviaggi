<?php

/*****************************************
 * STOCK - Riserva biglietti tra checkout e pagamento
 *****************************************/

defined( 'ABSPATH' ) || exit;


/* Lo stock viene scalato solo a completed (vedi iv-tickets.php), da 1 a 7 minuti dopo il checkout.
 * In questa finestra usiamo la riserva nativa di WooCommerce (tabella wc_reserved_stock): gli ordini
 * pending trattengono lo stock e il checkout successivo lo vede gia ridotto.
 * Se "Mantieni scorte (minuti)" e vuoto/0 la riserva non scatta mai: qui forziamo un minimo. */
add_filter( 'woocommerce_order_hold_stock_minutes', 'iv_force_stock_hold_minutes', 10, 1 );

function iv_force_stock_hold_minutes( $minutes ) {
	if ( (int) $minutes > 0 ) {
		return (int) $minutes;
	}
	return (int) apply_filters( 'iv_stock_hold_minutes', 60 );
}


/**
 * Biglietti realmente assegnabili per un prodotto (PDF liberi), o null se non e' un prodotto a biglietti.
 *
 * @param WC_Product $product Prodotto.
 * @return int|null
 */
function iv_get_product_available_tickets( $product ) {
	if ( ! $product || ! $product->is_downloadable() ) {
		return null;
	}

	$product_id = $product->get_id();
	$matrix     = (string) get_post_meta( $product_id, '_product_code', true );
	if ( '' === $matrix || '' === (string) $product->get_sku() ) {
		return null;
	}

	$from = (int) get_post_meta( $product_id, '_product_code_second', true );

	return iv_count_available_ticket_files( $product->get_sku(), $matrix, max( 1, $from ) );
}


/**
 * Messaggi di errore per le righe del carrello che superano i biglietti disponibili
 * (PDF liberi meno quanto trattenuto dagli ordini pending di altri clienti).
 *
 * @param WC_Cart $cart Carrello.
 * @return string[]
 */
function iv_get_cart_ticket_shortage_messages( $cart ) {
	$required = array();
	$products = array();

	foreach ( $cart->get_cart() as $values ) {
		$product = isset( $values['data'] ) ? $values['data'] : null;
		if ( ! $product instanceof WC_Product ) {
			continue;
		}
		$product_id = $product->get_id();
		$required[ $product_id ] = ( isset( $required[ $product_id ] ) ? $required[ $product_id ] : 0 ) + (int) $values['quantity'];
		$products[ $product_id ] = $product;
	}

	$session_order_id = ( function_exists( 'WC' ) && WC()->session ) ? absint( WC()->session->get( 'order_awaiting_payment' ) ) : 0;
	$messages         = array();

	foreach ( $products as $product_id => $product ) {
		$available = iv_get_product_available_tickets( $product );
		if ( null === $available ) {
			continue;
		}

		$held = wc_get_held_stock_quantity( $product, $session_order_id );
		if ( $required[ $product_id ] + $held > $available ) {
			$messages[] = sprintf(
				'Siamo spiacenti, non ci sono abbastanza biglietti disponibili per "%1$s" (disponibili: %2$d).',
				$product->get_name(),
				max( 0, $available - $held )
			);
		}
	}

	return $messages;
}


/* Checkout classico: blocca l'invio dell'ordine. */
add_action( 'woocommerce_check_cart_items', 'iv_check_cart_ticket_availability' );

function iv_check_cart_ticket_availability() {
	if ( ! WC()->cart ) {
		return;
	}
	foreach ( iv_get_cart_ticket_shortage_messages( WC()->cart ) as $message ) {
		wc_add_notice( $message, 'error' );
	}
}


/* Checkout a blocchi / Store API: stessa validazione. */
add_action( 'woocommerce_store_api_cart_errors', 'iv_store_api_ticket_availability', 10, 2 );

function iv_store_api_ticket_availability( $errors, $cart ) {
	foreach ( iv_get_cart_ticket_shortage_messages( $cart ) as $message ) {
		$errors->add( 'iv_tickets_unavailable', $message );
	}
}
