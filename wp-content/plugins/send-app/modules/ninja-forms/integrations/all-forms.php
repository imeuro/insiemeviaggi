<?php

namespace Send_App\Modules\Ninja_Forms\Integrations;

use Send_App\Core\Integrations\Classes\Forms\All_Forms_Base;
use Send_App\Modules\Ninja_Forms\Classes\Forms_Data_Helper;
use Send_App\Modules\Ninja_Forms\Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Handles retrieval and processing of all Ninja Forms forms
 */
class All_Forms extends All_Forms_Base {

	/**
	 * Get the integration name
	 *
	 * @return string The integration name
	 */
	protected function get_integration_name(): string {
		return Module::get_name();
	}

	/**
	 * Prepare form data for integration
	 *
	 * @return array|null Array of form data or null if no forms found
	 */
	protected function prepare_data(): ?array {
		$forms = Forms_Data_Helper::get_published_forms();
		if ( empty( $forms ) ) {
			return null;
		}

		$integration_name = $this->get_integration_name();

		$forms_data = [];
		foreach ( $forms as $form ) {
			$form_id = Forms_Data_Helper::normalize_form_id( $form );
			$form_fields = Forms_Data_Helper::get_form_fields( $form );

			$fields = [];
			foreach ( $form_fields as $index => $form_field ) {
				$fields[] = [
					'type' => $form_field->get_setting( 'type' ),
					'fieldName' => $form_field->get_setting( 'label' ),
					'orderInForm' => $form_field->get_setting( 'order' ),
					'isRequired' => ( '1' === ( $form_field->get_setting( 'required' ) ?? '0' ) ),
				];
			}

			$forms_data[] = [
				'source' => [
					'method' => 'form',
					'name'   => $integration_name,
				],
				'formId' => $form_id,
				'formName' => Forms_Data_Helper::get_form_title( $form ),
				'fields' => $fields,
			];
		}

		return $forms_data;
	}
}
