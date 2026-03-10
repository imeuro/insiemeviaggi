<?php
namespace Send_App\Core\Integrations\Classes\Forms;

use Send_App\Core\Logger;
use Send_App\Core\Integrations\Classes\Integration_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

abstract class All_Forms_Base extends Integration_Base {
	abstract protected function get_integration_name(): string;

	abstract protected function prepare_data(): ?array;

	public function sync(): bool {
		$forms_data = $this->prepare_data();

		if ( empty( $forms_data ) ) {
			return true;
		}

		$payload_data = [
			'forms' => $forms_data,
		];

		$response = Forms_Api_Helper::post_forms_sync( $payload_data );

		if ( is_wp_error( $response ) ) {
			Logger::error(
				sprintf( 'Failed to send %s submission: %s',
					$this->get_integration_name(),
					wp_json_encode( $response->get_error_messages() )
				)
			);
			return false;
		}

		return true;
	}
}
