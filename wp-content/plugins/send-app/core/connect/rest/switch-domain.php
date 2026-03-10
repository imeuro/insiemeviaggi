<?php
namespace Send_App\Core\Connect\Rest;

use Send_App\Core\Connect\Classes\{
	Connect_Route_Base,
	Data,
	Service
};

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Switch_Domain extends Connect_Route_Base {
	public string $path = 'switch_domain';

	public function get_methods(): array {
		return [ 'POST' ];
	}

	public function get_name(): string {
		return 'switch_domain';
	}

	public function POST( \WP_REST_Request $request ) {
		$valid = $this->verify_capability();
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		try {
			$client_id = Data::get_client_id();

			if ( ! $client_id ) {
				return $this->respond_error_json( [
					'message' => __( 'Client ID not found', 'send-app' ),
					'code' => 'ignore_error',
				] );
			}

			Service::update_redirect_uri();

			return $this->respond_success_json( [ 'message' => __( 'Domain updated!', 'send-app' ) ] );
		} catch ( \Throwable $t ) {
			return $this->respond_error_json( [
				'message' => $t->getMessage(),
				'code' => 'internal_server_error',
			] );
		}
	}
}
