<?php
namespace Send_App\Core\Rest\Routes;

use Send_App\Core\Base\Integrations_Route_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Integrations_Actions extends Integrations_Route_Base {
	public string $path = 'integrations/(?P<name>[\w-]+)/actions/(?P<action>enable|disable|sync)';

	public function get_name(): string {
		return 'integrations-actions';
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
				$action => $response,
			];
		}

		return $response;
	}
}
