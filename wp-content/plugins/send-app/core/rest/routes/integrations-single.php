<?php
namespace Send_App\Core\Rest\Routes;

use Send_App\Core\Base\Integrations_Route_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Integrations_Single extends Integrations_Route_Base {
	public string $path = 'integrations/(?P<name>[\w-]+)';

	public function get_name(): string {
		return 'integrations-single';
	}

	public function get_methods(): array {
		return [ 'GET' ];
	}

	public function GET( \WP_REST_Request $request ) {
		return $this->request_handler( $request );
	}
	protected function format_response_data( $response ): array {
		return $response;
	}
}
