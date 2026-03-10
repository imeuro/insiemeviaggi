<?php
namespace Send_App\Core\Base;

use Send_App\Core\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

abstract class Integrations_Route_Base extends Route_Base {
	public const NONCE_NAME = 'wp_rest';

	protected ?\WP_REST_Request $request = null;

	/**
	 * @param mixed $response_data
	 *
	 * @return array
	 */
	abstract protected function format_response_data( $response_data ): array;

	protected function get_unknown_module_error( string $name ): \WP_Error {
		return new \WP_Error(
			'no_integration',
			/* translators: %s the Module name */
			sprintf( __( 'Module "%s" does not exist', 'send-app' ), $name ),
			[ 'status' => \WP_Http::NOT_FOUND ]
		);
	}

	/**
	 * assumed more POST than GET
	 * @return string[]
	 */
	public function get_methods(): array {
		return [ 'POST' ];
	}

	public function post_args(): array {
		return [
			'name' => [
				'sanitize_callback' => function ( $input ) {
					return sanitize_text_field( $input );
				},
			],
		];
	}

	public function get_args(): array {
		return [
			'name' => [
				'sanitize_callback' => function ( $input ) {
					return sanitize_text_field( $input );
				},
			],
		];
	}

	/**
	 * Template method for request handling,
	 * assuming the actual processing will be done by hooking to the filter
	 *
	 * @param \WP_REST_Request $request
	 *
	 * @return \WP_Error|\WP_REST_Response
	 */
	protected function request_handler( \WP_REST_Request $request ) {
		$valid = $this->verify_capability();
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$this->request = $request;

		// Get the module name from the request.
		$name = $request->get_param( 'name' );

		$filter_name = $this->get_filter_name();

		$unknown_integration_error = $this->get_unknown_module_error( $name );
		try {
			/**
			 * Filter the integration to allow module specific response.
			 */
			$response = apply_filters( $filter_name, [], $request );

		} catch ( \Throwable $t ) {
			Logger::error( '[' . $t->getCode() . '] ' . $t->getMessage() );
			return $this->respond_error_json( new \WP_Error(
				$t->getCode(),
				$t->getMessage(),
				[ 'status' => $t->getCode() ]
			) );
		}

		if ( empty( $response ) ) {
			return $this->respond_error_json( $unknown_integration_error );
		}

		if ( is_wp_error( $response ) ) {
			return $this->respond_error_json( $response );
		}

		$response_data = $this->format_response_data( $response );

		return $this->respond_success_json( $response_data );
	}

	/**
	 * @return string
	 */
	protected function get_filter_name(): string {
		// Turn the route name into a filter name:
		return str_replace( '/' . $this->namespace . '/', 'send_app/rest/', $this->request->get_route() );
	}
}
