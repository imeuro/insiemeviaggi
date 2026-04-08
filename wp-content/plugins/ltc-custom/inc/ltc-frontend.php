<?php

/*****************************************
 * FRONTEND ENHANCEMENTS *
 *****************************************/

// [ FRONTEND ]
// remove jetpack Related Posts in woocommerce products page
function jetpackme_remove_related() {
    if ( class_exists( 'Jetpack_RelatedPosts' ) && is_product() ) {
        $jprp = Jetpack_RelatedPosts::init();
        $callback = array( $jprp, 'filter_add_target_to_dom' );
 
        remove_filter( 'the_content', $callback, 40 );
    }
}
add_action( 'wp', 'jetpackme_remove_related', 20 );



// [ FRONTEND ]
// Remove product data tabs
add_filter( 'woocommerce_product_tabs', 'woo_remove_product_tabs', 98 );

function woo_remove_product_tabs( $tabs ) {

    unset( $tabs['description'] );      	// Remove the description tab
    unset( $tabs['reviews'] ); 			// Remove the reviews tab
    unset( $tabs['additional_information'] );  	// Remove the additional information tab

    return $tabs;
}

// [ FRONTEND ]
// Remove ‘Add to Cart’ Button in listings
add_action( 'woocommerce_after_shop_loop_item', 'remove_add_to_cart_buttons', 1 );
function remove_add_to_cart_buttons() {
	if( is_product_category() || is_shop()) { 
		remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart');
	}
}


// [ FRONTEND ]
// custom checkout fields
add_filter( 'woocommerce_checkout_fields' , 'custom_override_checkout_fields', 0, 999 );
function custom_override_checkout_fields( $fields ) {
    $fields['billing']['billing_company']['label'] = 'Codice Fiscale';
    $fields['billing']['billing_company']['required'] = true;
    unset($fields['billing']['billing_address_2']);
    return $fields;
}

// [ FRONTEND ]
// Aggiungi acuni campi al checkout a seconda della categoria prodotto (es. vacanze-studio)
//add_action('woocommerce_checkout_fields', 'LTC_enable_custom_checkout_fields');
/*
function LTC_enable_custom_checkout_fields( $fields ) {
	//$additional_fields = get_option('wc_fields_additional');
	
	$isEnabled = ( has_product_category_in_cart( array('vacanze-studio','longform') ) ) ? 1 : 0;
	$needs_additionalForm = false; // partiamo che "NO"
	$additionalForm = '';

	if ($isEnabled === 1):
		foreach( WC()->cart->get_cart() as $cart_item ){
			$additionalForm = get_post_meta($cart_item['product_id'], 'domande_post_prenotazione', true);

			if ($additionalForm != '') {
				$needs_additionalForm = true;
				break;
			}
		}
		
	endif;


	// comunque mettimi le note in fondo
	$fields['order']['order_comments']['enabled'] = 1;
	$fields['order']['order_comments']['priority'] = 999;
	// THWCFD_Utils::update_fields('additional', $fields['order']);

	// echo '<pre>';
	// print_r($fields['order']);
	// echo '</pre>';

	return $fields;
}
*/


// [ FRONTEND ]
// prodotto obbligatoriamente con coupon ("convenzioni")
function get_post_ids_for_specific_cat($catID, $taxonomy='product_cat') {
	return get_posts(array(
		'post_type' => 'product',
    	'tax_query' => array(
	        array(
	            'taxonomy' => 'product_cat',
	            'field' => 'ID', //can be set to ID
	            'terms' => $catID //if field is ID you can reference by cat/term number
	        )
	    ),
		'fields'        => 'ids', // only get post IDs.
    ));
}
function is_coupon_valid( $coupon_code ) {
    $coupon = new \WC_Coupon( $coupon_code );   
    $discounts = new \WC_Discounts( WC()->cart );
    $response = $discounts->is_coupon_valid( $coupon );
    return is_wp_error( $response ) ? false : true;     
}

function ltc_has_required_coupon() {
	if ( ! WC()->cart ) {
		return false;
	}

	// Do not re-validate coupon rules here:
	// if WooCommerce keeps the coupon applied, it is valid for this business check.
	return count( (array) WC()->cart->get_applied_coupons() ) > 0;
}

function ltc_get_first_targeted_cart_item( $targeted_ids ) {
	if ( ! WC()->cart ) {
		return null;
	}

	foreach ( WC()->cart->get_cart() as $cart_item ) {
		if ( in_array( $cart_item['product_id'], $targeted_ids, true ) ) {
			return $cart_item;
		}
	}

	return null;
}


// enable 'coupon-warning' notice type
add_filter('woocommerce_notice_types', function ($notice_types) {
    $notice_types[] =   "coupon-warning";
    return $notice_types;
});

add_action( 'woocommerce_check_cart_items', 'mandatory_coupon_for_specific_items' );
function mandatory_coupon_for_specific_items() {
	// On checkout we show only the hard-blocking validation error (no extra warning).
	if ( is_checkout() && ! is_cart() ) {
		return;
	}

	$targeted_ids = get_post_ids_for_specific_cat(85,'product_cat'); // The targeted product ids (in this array) 
	$coupon_applied = ltc_has_required_coupon();
	$targeted_item  = ltc_get_first_targeted_cart_item( $targeted_ids );

	if ( $targeted_item && ! $coupon_applied ) {
		// Blur prices in cart/checkout summary and disable checkout CTA from cart page.
		echo "<script>document.addEventListener('DOMContentLoaded', (event) => { const cartprices = document.querySelectorAll('.woocommerce-cart-form bdi, .cart_totals bdi'); Array.from(cartprices).forEach((el)=>{ el.classList.add('xyz');});const proceedLink=document.querySelector('.wc-proceed-to-checkout > a'); if(proceedLink){proceedLink.removeAttribute('href');}});</script>";
		wc_add_notice( sprintf( 'Per acquistare "%s" è necessario inserire un codice promozionale.', $targeted_item['data']->get_name() ), 'coupon-warning' );
	}
}

add_action( 'woocommerce_after_checkout_validation', 'ltc_block_checkout_without_required_coupon', 20, 2 );
function ltc_block_checkout_without_required_coupon( $data, $errors ) {
	$targeted_ids    = get_post_ids_for_specific_cat( 85, 'product_cat' );
	$targeted_item   = ltc_get_first_targeted_cart_item( $targeted_ids );
	$coupon_applied  = ltc_has_required_coupon();

	if ( ! $targeted_item || $coupon_applied ) {
		return;
	}

	if ( is_a( $errors, 'WP_Error' ) ) {
		$errors->add(
			'ltc_required_coupon_missing',
			sprintf( 'Per acquistare "%s" è necessario inserire un codice promozionale valido.', $targeted_item['data']->get_name() )
		);
	}
}

function filter_woocommerce_add_to_cart_fragments(array $array): array
{

	$array['#blurpricer'] = <<<HTML
	<script id=blurpricer>
		var isDiscount = document.querySelectorAll('.cart-discount');
		var cartprices = document.querySelectorAll('.woocommerce-cart-form bdi, .cart_totals bdi');
		if (isDiscount.length === 0) {
			Array.from(cartprices).forEach((el)=>{ el.classList.add('xyz');});
			console.debug('blurred');
		} else {
			Array.from(cartprices).forEach((el)=>{ el.classList.remove('xyz');});
			console.debug('DE-blurred');
		}
	</script>
	HTML;
	return $array;

};

add_filter('woocommerce_add_to_cart_fragments', 'filter_woocommerce_add_to_cart_fragments', 0, 1);

// [ FRONTEND ]
// tipo di pagamento in base a categoria prodotto
// add_filter('woocommerce_available_payment_gateways', 'conditional_payment_gateways', 10, 1);
function conditional_payment_gateways( $available_gateways ) {
    // Not in backend (admin)
    if( is_admin() ) 
        return $available_gateways;

    $isBACSonly = ( has_product_category_in_cart( array('vacanze-studio','longform') ) ) ? 1 : 0;

    // Remove Viva Smart payment gateway for these products
    if($isBACSonly === 1)
        unset($available_gateways['vivacom_smart']); // unset 'vivacom_smart'
    // Remove Bank wire (Bacs) payment gateway for subscription products
    // if($prod_subscription)
    //     unset($available_gateways['bacs']); // unset 'bacs'

    return $available_gateways;
}


// [ FRONTEND ]
/* custom translation file:
 * Replace 'textdomain' with your plugin's textdomain. e.g. 'woocommerce'. 
 * File to be named, for example, yourtranslationfile-en_GB.mo
 * File to be placed, for example, wp-content/lanaguages/textdomain/yourtranslationfile-en_GB.mo
 */
add_filter( 'load_textdomain_mofile', 'load_custom_plugin_translation_file', 10, 2 );
function load_custom_plugin_translation_file( $mofile, $domain ) {
  if ( 'woocommerce' === $domain ) {
    $mofile = WP_LANG_DIR . '/'.$domain.'/ltc_woocommerce-' . get_locale() . '.mo';
  }
  return $mofile;
}