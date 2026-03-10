<?php
namespace Send_App\Core\Integrations\Classes\Forms;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Send_App\Core\Integrations\Options\Disabled_Forms_Option;

/**
 * class Forms_Component
 */
abstract class Forms_Component_Base {

	abstract protected function get_name(): string;

	/**
	 * Return all forms for a module.
	 *
	 * @param array $response
	 * @param ?\WP_REST_Request $request
	 *
	 * @return array
	 */
	abstract public function get_all_forms( array $response, ?\WP_REST_Request $request = null ): array;

	/**
	 * Return information for a specific form for a module.
	 *
	 * @param string $form_id
	 *
	 * @return array | \WP_Error
	 */
	abstract public function get_form_info_legacy( string $form_id );

	/**
	 * @param mixed $form_object
	 *
	 * @return array
	 */
	abstract protected function create_form_info( $form_object ): array;

	/**
	 * @param string $form_id
	 *
	 * @return null|mixed - use `null` if form is not found
	 */
	abstract protected function extract_form_by_external_id( string $form_id );

	/**
	 * Return information for a specific form for a module.
	 *
	 * @param array $response
	 * @param \WP_REST_Request $request
	 *
	 * @return array
	 * @throws \Throwable
	 */
	public function get_form_info( array $response, \WP_REST_Request $request ): array {
		$form_id = $request->get_param( 'form_id' );
		if ( empty( $form_id ) ) {
			throw new \Exception( 'Missing form_id param', 400 );
		}

		$form_object = $this->extract_form_by_external_id( $form_id );

		if ( empty( $form_object ) ) {
			throw new \Exception( 'Form not found', 404 );
		}

		$enable_tracking = $request->get_param( 'trackingEnabled' );

		if ( $enable_tracking ) {
			Disabled_Forms_Option::remove( $form_id, $this->get_name() );
		} else {
			Disabled_Forms_Option::add( $form_id, $this->get_name() );
		}
		return $this->create_form_info( $form_object );
	}

	public function get_disabled_forms(): array {
		return Disabled_Forms_Option::get_all( $this->get_name() );
	}

	public function is_disabled_form( string $form_id ): bool {
		return in_array( $form_id, $this->get_disabled_forms(), true );
	}

	public function __construct() {
		$filter_prefix = 'send_app/rest/integrations/' . $this->get_name() . '/forms';
		add_filter( $filter_prefix, [ $this, 'get_all_forms' ], 10, 2 );
		add_filter( $filter_prefix . '/by-id', [ $this, 'get_form_info' ], 10, 2 );
	}
}
