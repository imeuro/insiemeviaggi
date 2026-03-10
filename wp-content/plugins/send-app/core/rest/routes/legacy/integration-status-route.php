<?php

namespace Send_App\Core\Rest\Routes\Legacy;

use Send_App\Core\Rest\Routes\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Integration_Status_Route extends Integrations {
	public string $path = '/get-integrations-status';

	public function get_name(): string {
		return 'legacy-get-integrations-status';
	}

	/**
	 * @param \WP_REST_Request $request
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function GET( \WP_REST_Request $request ) {
		$valid = $this->verify_capability();
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		$result = $this->get_integrations_status( $request );
		if ( is_wp_error( $result ) ) {
			return $this->respond_error_json( $result );
		}

		return $this->respond_success_json( $result );
	}

	public function respond_success_json( $data = [] ): \WP_REST_Response {
		return new \WP_REST_Response( $data, 200 );
	}

	private function get_integrations_status( $request ) {
		static $all_sources = [
			'cf7',
			'elementor',
			'wpforms',
			'woocommerce',
		];

		$available_integrations = parent::GET( $request );
		if ( is_wp_error( $available_integrations ) ) {
			return $available_integrations;
		}

		$data = $available_integrations->get_data();
		$status = [];
		foreach ( $all_sources as $id ) {
			$status[ $id ] = [
				'installed' => false,
				'activated' => false,
			];
			foreach ( $data as $name => $integration ) {
				if ( $name === $id ) {
					$status[ $id ]['installed'] = true;
					$status[ $id ]['activated'] = $integration['enabled'];
				}
			}
		}

		return $status;
	}
}
