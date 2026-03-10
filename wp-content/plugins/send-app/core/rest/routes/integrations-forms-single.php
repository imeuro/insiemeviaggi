<?php
namespace Send_App\Core\Rest\Routes;

use Send_App\Core\Base\Integrations_Route_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Integrations_Forms_Single extends Integrations_Route_Base {
	protected string $path = 'integrations/(?P<name>[\w-]+)/forms/(?P<form_id>[\w-]+)';

	public function get_methods(): array {
		return [ 'POST' ];
	}

	public function POST( \WP_REST_Request $request ) {
		return $this->request_handler( $request );
	}

	public function get_name(): string {
		return 'integrations-forms-single';
	}

	public function post_args(): array {
		$args = parent::post_args();
		$args['form_id'] = [
			'sanitize_callback' => function ( $input ) {
				return sanitize_text_field( $input );
			},
		];
		$args['trackingEnabled'] = [
			'required' => true,
			'type' => 'boolean',
		];

		return $args;
	}

	protected function get_filter_name(): string {
		$filter_name = parent::get_filter_name();
		$form_id = $this->request->get_param( 'form_id' );
		return str_replace( $form_id, 'by-id', $filter_name );
	}

	protected function format_response_data( $response_data ): array {
		return [
			'forms' => $response_data,
		];
	}
}
