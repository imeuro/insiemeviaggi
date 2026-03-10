<?php
namespace Send_App\Core\Rest\Routes;

use Send_App\Core\Base\Integrations_Route_Base;
use Send_App\Core\Integrations_Manager;
use Send_App\Core\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Integrations_Forms_All extends Integrations_Route_Base {
	protected string $path = 'integrations/forms/all';

	public function get_methods(): array {
		return [ 'GET' ];
	}

	public function get_name(): string {
		return 'integrations-forms-all';
	}
	public function GET( \WP_REST_Request $request ) {
		return $this->request_handler( $request );
	}

	protected function request_handler( \WP_REST_Request $request ) {
		$valid = $this->verify_capability();
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$this->request = $request;

		$integration_modules = Integrations_Manager::get_instance()->get_integration_modules();

		$all_forms = [];
		try {
			/**
			 * Filter the integration to allow module specific response.
			 */
			foreach ( $integration_modules as $integration_module ) {
				$filter_name = 'send_app/rest/integrations/' . $integration_module->get_name() . '/forms';
				$integration_forms = apply_filters( $filter_name, [], $request );
				// Bail on error:
				if ( is_wp_error( $integration_forms ) ) {
					return $this->respond_error_json( $integration_forms );
				}

				$all_forms = array_merge( $all_forms, $integration_forms );
			}
		} catch ( \Throwable $t ) {
			Logger::error( '[' . $t->getCode() . '] ' . $t->getMessage() );
			return $this->respond_error_json( new \WP_Error(
				$t->getCode(),
				$t->getMessage(),
				[ 'status' => $t->getCode() ]
			) );
		}

		if ( is_wp_error( $all_forms ) ) {
			return $this->respond_error_json( $all_forms );
		}

		$response_data = $this->format_response_data( $all_forms );

		return $this->respond_success_json( $response_data );
	}
	protected function format_response_data( $response_data ): array {
		return [
			'forms' => $response_data,
		];
	}
}
