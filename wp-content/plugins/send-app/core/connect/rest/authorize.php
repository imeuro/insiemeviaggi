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

class Authorize extends Connect_Route_Base {
	public string $path = 'authorize';

	public function get_methods(): array {
		return [ 'POST' ];
	}

	public function get_name(): string {
		return 'authorize';
	}

	public function POST( \WP_REST_Request $request ) {
		$valid = $this->verify_capability();
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		if ( Utils::is_connected() ) {
			return $this->respond_error_json( [
				'message' => esc_html__( 'You are already connected', 'send-app' ),
				'code' => 'forbidden',
			] );
		}

		try {
			$client_id = Data::get_client_id();

			if ( ! $client_id ) {
				$client_id = Service::register_client();
			}

			if ( ! Utils::is_valid_home_url() ) {
				Service::update_redirect_uri();
			}

			$authorize_url = Utils::get_authorize_url( $client_id );

			/**
			 * Filter the authorize URL.
			 * @param string $authorize_url The authorize URL.
			 */
			$authorize_url = apply_filters( 'send_app/connect/authorize_url', $authorize_url );

			return $this->respond_success_json( $authorize_url );
		} catch ( \Throwable $t ) {
			return $this->respond_error_json( [
				'message' => $t->getMessage(),
				'code' => 'internal_server_error',
			] );
		}
	}
}
