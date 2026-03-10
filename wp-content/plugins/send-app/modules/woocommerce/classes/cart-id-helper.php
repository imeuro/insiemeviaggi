<?php

namespace Send_App\Modules\Woocommerce\Classes;

use Send_App\Core\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Cart_Id_Helper {
	const CART_ID_TIMESTAMP_SESSION_KEY = 'send_app_cart_id_timestamp';
	const CUSTOM_CART_ID_SESSION_KEY = 'send_app_custom_cart_id';
	const CART_ID_TTL = 3600;

	const CART_ID_PREFIX = 'cart_';

	public static function create_or_get_cart_id() {
		// Check if a custom cart ID exists and if it's still valid
		if ( ! WC()->session ) {
			return 'no-cart';
		}

		$custom_cart_id = WC()->session->get( self::CUSTOM_CART_ID_SESSION_KEY );
		$cart_id_timestamp = WC()->session->get( self::CART_ID_TIMESTAMP_SESSION_KEY );

		if ( ! $custom_cart_id || ! $cart_id_timestamp || ( time() - $cart_id_timestamp ) > self::CART_ID_TTL ) {
			// Generate a new custom cart ID and store it with the current timestamp
			$site_url_hash = substr( sha1( Utils::get_current_host() ), 0, 8 );
			$new_cart_id = uniqid( self::CART_ID_PREFIX ) . '_' . $site_url_hash;
			WC()->session->set( self::CUSTOM_CART_ID_SESSION_KEY, $new_cart_id );
			WC()->session->set( self::CART_ID_TIMESTAMP_SESSION_KEY, time() );

			return $new_cart_id;
		}

		// Return the existing custom cart ID
		return $custom_cart_id;
	}

	public static function maybe_extend_cart_id_session_ttl() {
		if ( WC()->session->get( self::CUSTOM_CART_ID_SESSION_KEY ) ) {
			// Get the timestamp when the cart-id was last updated
			$cart_id_timestamp = WC()->session->get( self::CART_ID_TIMESTAMP_SESSION_KEY );
			// Check if the current time is within the TTL
			if ( ( time() - $cart_id_timestamp ) < self::CART_ID_TTL ) {
				WC()->session->set( self::CART_ID_TIMESTAMP_SESSION_KEY, time() );
			}
		}
	}

	public static function reset_cart_id_in_session() {
		if ( ! WC()->session ) {
			return;
		}

		WC()->session->set( self::CUSTOM_CART_ID_SESSION_KEY, null );
		WC()->session->set( self::CART_ID_TIMESTAMP_SESSION_KEY, null );
	}

	public static function revoke_cart_id() {
		if ( ! WC()->session ) {
			return;
		}

		WC()->session->set( self::CART_ID_TIMESTAMP_SESSION_KEY, time() - self::CART_ID_TTL );
	}
}
