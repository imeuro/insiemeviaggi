<?php
namespace Send_App\Modules\Sitedata\Components;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Send_App\Core\{API, Logger};
use Send_App\Modules\Sitedata\Classes\Sitedata_Helper;

class Site_Data_Sync {

	private const CONNECTED_EVENT_SENT = 'send_app_connected_event_sent';
	protected const ASYNC_UPDATE_HOOK_PREFIX = 'send_app/async/sitedata/';
	const SYNC_GROUP = 'send_app/async/sitedata';

	const ENDPOINT = 'sites';

	protected function get_async_registered_hook_name(): string {
		return static::ASYNC_UPDATE_HOOK_PREFIX . 'connected';
	}

	protected function get_async_updated_hook_name(): string {
		return static::ASYNC_UPDATE_HOOK_PREFIX . 'updated';
	}

	public function schedule_on_registered() {
		\as_schedule_single_action(
			time() + 5,
			$this->get_async_registered_hook_name(),
			[],
			static::SYNC_GROUP
		);
	}

	public function schedule_on_updated(): void {
		\as_schedule_single_action(
			time() + 5,
			$this->get_async_updated_hook_name(),
			[],
			static::SYNC_GROUP
		);
	}

	public function trigger_sitedata_update( $old_value = null, $new_value = null ): void {
		static $has_triggered = false;

		if ( $has_triggered ) {
			return;
		}

		$has_triggered = true;
		do_action( 'send_app/sitedata/updated' );
	}

	public function async_on_change(): void {
		$metadata = Sitedata_Helper::get_site_metadata();

		$response = API::make_request( 'PATCH', self::ENDPOINT, $metadata );

		if ( is_wp_error( $response ) ) {
			Logger::error(
				sprintf('Failed to send site metadata event: %s',
					$response->get_error_message()
				)
			);
		}
	}

	public function __construct() {
		add_action( 'send_app/site/on_registered', [ $this, 'schedule_on_registered' ], 10, 0 );
		add_action( 'send_app/sitedata/updated', [ $this, 'schedule_on_updated' ], 10, 0 );
		add_action( $this->get_async_registered_hook_name(), [ $this, 'async_on_change' ], 10, 0 );
		add_action( $this->get_async_updated_hook_name(), [ $this, 'async_on_change' ], 10, 0 );

		add_action( 'update_option_blogname', [ $this, 'trigger_sitedata_update' ], 9999, 2 );
		add_action( 'update_option_blogdescription', [ $this, 'trigger_sitedata_update' ], 9999, 2 );
		add_action( 'update_option_WPLANG', [ $this, 'trigger_sitedata_update' ], 9999, 2 );
		add_action( 'update_option_timezone_string', [ $this, 'trigger_sitedata_update' ], 9999, 2 );
		add_action( 'update_option_gmt_offset', [ $this, 'trigger_sitedata_update' ], 9999, 2 );
	}
}
