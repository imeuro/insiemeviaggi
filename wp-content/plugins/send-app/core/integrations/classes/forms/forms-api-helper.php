<?php

namespace Send_App\Core\Integrations\Classes\Forms;

use Send_App\Core\API;
use Send_App\Core\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Forms_Api_Helper {
	const SYNC_HOOK_PREFIX = 'send_app_sync_form_';
	const SYNC_GROUP = 'send_app_sync_forms';

	public static function post_form_event( $event_name, $payload_data ) {
		return API::make_request( 'POST', 'events/form/' . $event_name, $payload_data );
	}

	public static function get_async_hook_name( string $entity, string $event_name ): string {
		return static::SYNC_HOOK_PREFIX . $entity . '_' . $event_name;
	}
	public static function schedule_async_form_event( string $hook, array $payload_data ): bool {
		return 0 !== \as_schedule_single_action( time() + 5, $hook, [ 'payload' => $payload_data ], static::SYNC_GROUP );
	}

	public static function post_forms_sync( $payload_data ) {
		return API::make_request( 'POST', 'forms', $payload_data );
	}
}
