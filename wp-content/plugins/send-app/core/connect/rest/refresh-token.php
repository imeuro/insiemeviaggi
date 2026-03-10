<?php
namespace Send_App\Core\Connect\Rest;

use Send_App\Core\Connect\Classes\{
	Connect_Route_Base,
	Data,
	Service,
	Utils
};

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Refresh_Token extends Connect_Route_Base {
	public string $path = 'refresh-token';

	public function get_methods(): array {
		return [ 'POST' ];
	}

	public function get_name(): string {
		return 'refresh-token';
	}

	public function POST( \WP_REST_Request $request ) {
		$valid = $this->verify_capability();
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		if ( ! Utils::is_connected() ) {
			return $this->respond_error_json( [
				'message' => esc_html__( 'You are not connected', 'send-app' ),
				'code' => 'forbidden',
			] );
		}

		try {
			Service::refresh_token();

			$token = Data::get_access_token();
			return $this->respond_success_json( [
				'rawToken' => $token,
				'token' => 'Bearer ' . $token,
			] );
		} catch ( \Exception $e ) {
			return $this->respond_error_json( [
				'message' => $e->getMessage(),
				'code' => 'internal_server_error',
			] );
		}
	}
}
