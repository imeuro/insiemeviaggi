<?php
namespace Send_App\Core\Rest\Routes;

use Send_App\Core\Base\Integrations_Route_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Integrations_Forms extends Integrations_Route_Base {
	protected string $path = 'integrations/(?P<name>[\w-]+)/forms';

	public function get_methods(): array {
		return [ 'GET' ];
	}

	public function get_name(): string {
		return 'integrations-forms';
	}
	public function GET( \WP_REST_Request $request ) {
		return $this->request_handler( $request );
	}

	protected function format_response_data( $response_data ): array {
		return [
			'forms' => $response_data,
		];
	}
}
