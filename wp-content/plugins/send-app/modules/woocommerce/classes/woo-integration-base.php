<?php
namespace Send_App\Modules\Woocommerce\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Send_App\Core\Integrations\Classes\Async_Integration_Base;
use Send_App\Modules\Woocommerce\Module;

/**
 * class Woo_Sync_Base
 **/
abstract class Woo_Integration_Base extends Async_Integration_Base {

	protected function send_data( array $data, string $trigger, string $action ) {
		return API_Helper::send_event_data( $data, $trigger, static::ENTITY, $action );
	}

	protected function send_initial_sync_data( array $data, string $trigger ) {
		$data['chunkLimit'] = $this->get_limit();
		$data['totalChunks'] = $this->get_max_pages();
		$data['totalItems'] = $this->get_total_items();
		$data['currentChunk'] = $this->get_current_page();

		return API_Helper::send_initial_sync_data( $data, $trigger, static::ENTITY );
	}

	public function maybe_register_wc_hooks(): void {
		if ( Module::get_instance()->is_enabled() ) {
			$this->register_wc_hooks();
		}
	}

	public function register_wc_hooks(): void {
		$this->register_as_hooks();
	}

	public function register_hooks(): void {
		parent::register_hooks();
		add_action( 'woocommerce_loaded', [ $this, 'maybe_register_wc_hooks' ] );
	}

	public function sync(): bool {
		return $this->register_sync( true );
	}
}
