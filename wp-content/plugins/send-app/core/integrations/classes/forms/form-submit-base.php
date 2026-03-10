<?php
namespace Send_App\Core\Integrations\Classes\Forms;

use Send_App\Core\Integrations\Classes\Integration_Base;
use Send_App\Core\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

abstract class Form_Submit_Base extends Integration_Base {

	const EVENT = 'submitted';

	abstract protected function get_submit_hook(): string;

	abstract public function get_integration_name(): string;

	abstract protected function prepare_data( $record, $handler ): ?Form_Submit_Data;

	protected function get_async_submit_hook(): string {
		return Forms_Api_Helper::get_async_hook_name( $this->get_integration_name(), static::EVENT );
	}

	public function on_form_submit( $record, $handler ) {

		if ( is_null( $handler ) ) {
			return null;
		}
		// prepare the data synchronically in order to keep it the same as the one submitted
		$form_data = $this->prepare_data( $record, $handler );

		if ( is_null( $form_data ) ) {
			return null;
		}

		$payload_data = $form_data->get_data();

		$response = Forms_Api_Helper::schedule_async_form_event( $this->get_async_submit_hook(), $payload_data );

		if ( true !== $response ) {
			Logger::error( sprintf( 'Failed to send %s submission', $this->get_integration_name() ) );
		}
	}

	public function async_submit( $payload ): void {
		$response = Forms_Api_Helper::post_form_event( self::EVENT, $payload );

		if ( \is_wp_error( $response ) ) {
			Logger::error(
				sprintf( 'Failed to send %s submission: %s',
					$this->get_integration_name(),
					wp_json_encode( $response->get_error_messages() )
				)
			);
		}
	}

	public function sync(): bool {
		return true;
	}

	protected function register_submit_hook() {
		$submit_hook = $this->get_submit_hook();
		add_action( $submit_hook, [ $this, 'on_form_submit' ], 100000, 2 );
	}

	public function register_hooks(): void {
		$this->register_submit_hook();
		$async_submit_hook = $this->get_async_submit_hook();
		add_action( $async_submit_hook, [ $this, 'async_submit' ], 10, 1 );
	}
}
