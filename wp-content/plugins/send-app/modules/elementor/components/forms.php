<?php
namespace Send_App\Modules\Elementor\Components;

use Send_App\Modules\Elementor\Module;
use Send_App\Modules\Elementor\Classes\Forms_Data_Helper;
use Send_App\Core\Integrations\Classes\Forms\Forms_Component_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Forms extends Forms_Component_Base {
	protected function get_name(): string {
		return Module::get_name();
	}

	/**
	 * Return all forms for the Elementor integration.
	 *
	 * @param array $response
	 * @param ?\WP_REST_Request $request
	 *
	 * @return array
	 */
	public function get_all_forms( array $response, ?\WP_REST_Request $request = null ): array {
		$forms_data = [];

		$posts_with_forms = Forms_Data_Helper::get_post_ids_with_forms();
		$posts_with_forms_data = Forms_Data_Helper::get_forms_from_post_ids( $posts_with_forms );

		$forms_posts = [];
		// flip the array to get the aggregation by form id:
		foreach ( $posts_with_forms_data as $post_id => $forms ) {
			foreach ( $forms as $form ) {
				if ( empty( $forms_data[ $form['id'] ] ) ) {
					$forms_data[ $form['id'] ] = $this->create_form_info( $form );
				}
				$forms_posts[ $form['id'] ]['page_ids'][] = strval( $post_id );
			}
		}

		foreach ( $forms_data as $form_id => $form_data ) {
			$forms_data[ $form_id ]['page_ids'] = array_unique( $forms_posts[ $form_id ]['page_ids'] );
		}

		if ( empty( $forms_data ) ) {
			$response[ $this->get_name() ] = new \WP_Error( 'no_forms_data', sprintf( '[%s] No forms data', Module::get_name() ) );
			return $response;
		}

		return $response + $forms_data;
	}

	/**
	 * Create details for a single form.
	 *
	 * @param $form_object
	 * @return array
	 */
	protected function create_form_info( $form_object ): array {
		return [
			'id' => $form_object['id'],
			'name' => $form_object['settings']['form_name'],
			'tracking_enabled' => ! $this->is_disabled_form( $form_object['id'] ),
			'integration' => $this->get_name(),
		];
	}

	protected function extract_form_by_external_id( string $form_id ) {
		$form_instances = Forms_Data_Helper::get_form_instances_by_form_id( $form_id );
		foreach ( $form_instances as $post_id => $form ) {
			return $form;
		}

		return null;
	}

	/**
	 * Returns details for a single Form
	 *
	 * @param string $form_id
	 * @return array
	 */
	public function get_form_info_legacy( string $form_id ): array {
		$parts = explode( '-', $form_id );

		$forms = [];
		if ( 2 === count( $parts ) ) {
			$form_id = $parts[1];
			$forms = Forms_Data_Helper::get_forms_for_post_id( $parts[0] );
		} elseif ( 1 === count( $parts ) ) {
			$forms = Forms_Data_Helper::get_form_instances_by_form_id( $form_id );
		}

		$form_info = [];

		foreach ( $forms as $form ) {
			if ( $form['id'] === $form_id ) {
				$form_info = $this->create_form_info( $form );
			}
		}

		return $form_info;
	}
}
