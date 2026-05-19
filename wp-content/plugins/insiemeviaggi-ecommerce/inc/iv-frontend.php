<?php

/*****************************************
 * FRONTEND ENHANCEMENTS
 *****************************************/

defined( 'ABSPATH' ) || exit;


/* Rimuove Jetpack Related Posts nelle pagine prodotto WooCommerce. */
add_action( 'wp', 'iv_remove_jetpack_related', 20 );
function iv_remove_jetpack_related() {
	if ( class_exists( 'Jetpack_RelatedPosts' ) && is_product() ) {
		$jprp     = Jetpack_RelatedPosts::init();
		$callback = array( $jprp, 'filter_add_target_to_dom' );
		remove_filter( 'the_content', $callback, 40 );
	}
}


/* Rimuove i tab prodotto (descrizione, recensioni, info aggiuntive). */
add_filter( 'woocommerce_product_tabs', 'iv_remove_product_tabs', 98 );
function iv_remove_product_tabs( $tabs ) {
	unset( $tabs['description'] );
	unset( $tabs['reviews'] );
	unset( $tabs['additional_information'] );
	return $tabs;
}


/* Rimuove il pulsante "Aggiungi al carrello" nelle liste prodotto. */
add_action( 'woocommerce_after_shop_loop_item', 'iv_remove_add_to_cart_buttons', 1 );
function iv_remove_add_to_cart_buttons() {
	if ( is_product_category() || is_shop() ) {
		remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart' );
	}
}


/* Campi checkout personalizzati. */
add_filter( 'woocommerce_checkout_fields', 'iv_custom_checkout_fields', 0, 999 );
function iv_custom_checkout_fields( $fields ) {
	$fields['billing']['billing_company']['label']    = 'Codice Fiscale';
	$fields['billing']['billing_company']['required'] = true;
	unset( $fields['billing']['billing_address_2'] );
	return $fields;
}


/*****************************************
 * COUPON OBBLIGATORIO (tutti i prodotti)
 *****************************************/

function iv_cart_has_items() {
	if ( ! WC()->cart ) {
		return false;
	}

	return ! WC()->cart->is_empty();
}

function iv_has_required_coupon() {
	if ( ! WC()->cart ) {
		return false;
	}

	return count( (array) WC()->cart->get_applied_coupons() ) > 0;
}

function iv_get_first_cart_item() {
	if ( ! WC()->cart || WC()->cart->is_empty() ) {
		return null;
	}

	foreach ( WC()->cart->get_cart() as $cart_item ) {
		return $cart_item;
	}

	return null;
}


add_filter( 'woocommerce_notice_types', function ( $notice_types ) {
	$notice_types[] = 'coupon-warning';
	return $notice_types;
} );

add_action( 'woocommerce_check_cart_items', 'iv_mandatory_coupon_for_cart_items' );
function iv_mandatory_coupon_for_cart_items() {
	if ( is_checkout() && ! is_cart() ) {
		return;
	}

	if ( ! iv_cart_has_items() || iv_has_required_coupon() ) {
		return;
	}

	$cart_item    = iv_get_first_cart_item();
	$product_name = ( $cart_item && isset( $cart_item['data'] ) )
		? $cart_item['data']->get_name()
		: __( 'i prodotti selezionati', 'insiemeviaggi-ecommerce' );

	echo "<script>document.addEventListener('DOMContentLoaded', (event) => { const cartprices = document.querySelectorAll('.woocommerce-cart-form bdi, .cart_totals bdi'); Array.from(cartprices).forEach((el)=>{ el.classList.add('xyz');});const proceedLink=document.querySelector('.wc-proceed-to-checkout > a'); if(proceedLink){proceedLink.removeAttribute('href');}});</script>";
	wc_add_notice(
		sprintf( 'Per acquistare "%s" è necessario inserire un codice promozionale.', $product_name ),
		'coupon-warning'
	);
}

add_action( 'woocommerce_after_checkout_validation', 'iv_block_checkout_without_required_coupon', 20, 2 );
function iv_block_checkout_without_required_coupon( $data, $errors ) {
	if ( ! iv_cart_has_items() || iv_has_required_coupon() ) {
		return;
	}

	$cart_item    = iv_get_first_cart_item();
	$product_name = ( $cart_item && isset( $cart_item['data'] ) )
		? $cart_item['data']->get_name()
		: __( 'i prodotti selezionati', 'insiemeviaggi-ecommerce' );

	if ( ! is_a( $errors, 'WP_Error' ) ) {
		return;
	}

	$errors->add(
		'iv_required_coupon_missing',
		sprintf(
			'Per acquistare "%s" è necessario inserire un codice promozionale valido.',
			$product_name
		)
	);
}

add_filter( 'woocommerce_add_to_cart_fragments', 'iv_blur_prices_fragment', 0, 1 );
function iv_blur_prices_fragment( array $array ): array {
	$array['#blurpricer'] = <<<HTML
	<script id=blurpricer>
		var isDiscount = document.querySelectorAll('.cart-discount');
		var cartprices = document.querySelectorAll('.woocommerce-cart-form bdi, .cart_totals bdi');
		if (isDiscount.length === 0) {
			Array.from(cartprices).forEach((el)=>{ el.classList.add('xyz');});
		} else {
			Array.from(cartprices).forEach((el)=>{ el.classList.remove('xyz');});
		}
	</script>
	HTML;
	return $array;
}


/*****************************************
 * TRADUZIONI CUSTOM WOOCOMMERCE
 *****************************************/

add_filter( 'load_textdomain_mofile', 'iv_load_custom_translation_file', 10, 2 );
function iv_load_custom_translation_file( $mofile, $domain ) {
	if ( 'woocommerce' === $domain ) {
		$mofile = WP_LANG_DIR . '/' . $domain . '/ltc_woocommerce-' . get_locale() . '.mo';
	}
	return $mofile;
}
