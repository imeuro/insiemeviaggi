<?php
namespace Send_App\Core\Rest\Routes\Legacy;

use Send_App\Core\Connect\Classes\Connect_Route_Base;
use Send_App\Core\Integrations\Options\Disabled_Forms_Option;
use Send_App\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Integrations_Forms_Status extends Connect_Route_Base {
	protected string $path = 'integrations/(?P<name>[\w-]+)/forms/(?P<form_id>[\w-]+)/status';

	public function get_methods(): array {
		return [ 'POST' ];
	}

	public function get_name(): string {
		return 'legacy-integrations-forms-status';
	}

	public function get_endpoint(): string {
		// Remove the connect path for now.
		return $this->get_path();
	}

	public function post_args(): array {
		return [
			'name' => [
				'sanitize_callback' => function ( $input ) {
					return sanitize_text_field( $input );
				},
			],
			'form_id' => [
				'sanitize_callback' => function ( $input ) {
					return sanitize_text_field( $input );
				},
			],
			'trackingEnabled' => [
				'required' => true,
				'type' => 'boolean',
			],
		];
	}

	/**
	 * @param \WP_REST_Request $request
	 *
	 * @return bool
	 */
	public function permission_callback( \WP_REST_Request $request ): bool {
		$nonce = $request->get_header( 'X-WP-Nonce' ) ?? '';

		return true === $this->verify_nonce( $nonce, static::NONCE_NAME );
	}

	public function POST( \WP_REST_Request $request ) {
		$valid = $this->verify_capability();
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$name = $request->get_param( 'name' );

		$integration_manager = Plugin::instance()->get_integrations_manager();

		$module = $integration_manager->get_integration_module( $name );

		if ( is_null( $module ) ) {
			return $this->respond_error_json( [
				'message' => sprintf( 'The module %s does not exist', $name ),
				'code'    => 'module_does_not_exist',
			] );
		}

		$forms_component = $module->get_component( 'Forms' );

		if ( is_null( $forms_component ) ) {
			return $this->respond_error_json( [
				'message' => sprintf( 'The module %s does not have a forms component', $name ),
				'code'    => 'module_does_not_have_forms_component',
			] );
		}

		$form_id   = $request->get_param( 'form_id' );
		$form_data = $forms_component->get_form_info_legacy( $form_id );

		if ( empty( $form_data ) ) {
			return $this->respond_error_json( [
				'message' => sprintf( 'No form with ID %s', $form_id ),
				'code'    => 'form_does_not_exist',
			] );
		}

		$enable_tracking = $request->get_param( 'trackingEnabled' );

		if ( $enable_tracking ) {
			Disabled_Forms_Option::remove( $form_id, $name );
		} else {
			Disabled_Forms_Option::add( $form_id, $name );
		}

		$form_data = $forms_component->get_form_info_legacy( $form_id );

		return $this->respond_success_json( [
			'forms' => $form_data,
		] );
	}
}
