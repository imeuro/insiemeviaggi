<?php

namespace Send_App\Modules\Woocommerce\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Send_App\Core\API;
use Send_App\Core\Utils;
use Send_App\Modules\Woocommerce\Module;

/**
 * class API_Helper
 **/
class API_Helper {
	// TODO: is this the right place for this method?
	public static function get_updates_endpoint( $action, $entity = null ): string {
		return is_null( $entity ) ? "woo/metadata/{$action}" : "woo/metadata/{$entity}/{$action}";
	}

	public static function get_event_endpoint( $action, $entity = null ): string {
		return is_null( $entity ) ? "woo/event/{$action}" : "woo/event/{$entity}/{$action}";
	}

	public static function get_initial_sync_endpoint( $entity ): string {
		return 'woo/sync/' . $entity;
	}

	public static function send_meta_data( $payload, $data_type, $entity, $action, $method = 'POST' ) {
		$request_payload = [
			'siteUrl' => Utils::get_current_host(),
			'dataType' => $data_type,
			'data' => $payload,
		];

		return API::make_request( $method, static::get_updates_endpoint( $action, $entity ), $request_payload );
	}

	public static function send_event_data( $payload, $data_type, $entity, $action, $method = 'POST' ) {
		$request_payload = [
			'siteUrl' => Utils::get_current_host(),
			'dataType' => $data_type,
			'data' => $payload,
		];

		return API::make_request( $method, static::get_event_endpoint( $action, $entity ), $request_payload );
	}

	public static function send_initial_sync_data( $payload, $data_type, string $entity, $method = 'POST' ) {
		$request_payload = [
			'siteUrl' => Utils::get_current_host(),
			'dataType' => $data_type,
			'data' => $payload,
		];

		return API::make_request( $method, static::get_initial_sync_endpoint( $entity ), $request_payload );
	}

	public static function update_site_currency() {
		$currency = 'USD';
		if ( function_exists( 'get_woocommerce_currency' ) ) {
			$currency = get_woocommerce_currency();
		}
		$request_payload = [
			'currency' => $currency,
		];
		return API::make_request( 'POST', 'woo/update-currency', $request_payload );
	}

	public static function send_woo_email( $email_type ) {
		API::make_request( 'POST', API_ENDPOINT . '/woo/send-email', [ 'action' => 'sync-finished' ] );
	}
}
