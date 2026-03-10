<?php
namespace Send_App\Core\Rest\Routes\Legacy;

use Send_App\Core\Base\Integrations_Route_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Integrations_Connect_Disconnect extends Integrations_Route_Base {
	public string $path = 'integrations/(?P<name>[\w-]+)/(?P<action>connect|disconnect)';

	public function get_name(): string {
		return 'legacy-integrations-connect-disconnect';
	}

	public function get_methods(): array {
		return [ 'POST' ];
	}

	public function POST( \WP_REST_Request $request ) {
		return $this->request_handler( $request );
	}

	/**
	 * @param mixed $response
	 *
	 * @return array
	 */
	protected function format_response_data( $response ): array {
		$name = $this->request->get_param( 'name' );
		$action = $this->request->get_param( 'action' );

		if ( is_array( $response ) ) {
			$response['name'] = $name;
		} else {
			$response = [
				'name' => $name,
				'is_enabled' => 'connect' === $action ? $response : ! $response,
			];
		}

		return $response;
	}
}
