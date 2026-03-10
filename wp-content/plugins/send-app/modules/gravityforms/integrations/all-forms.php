<?php

namespace Send_App\Modules\Gravityforms\Integrations;

use Send_App\Core\Integrations\Classes\Forms\All_Forms_Base;
use Send_App\Modules\Gravityforms\Classes\Forms_Data_Helper;
use Send_App\Modules\Gravityforms\Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class All_Forms extends All_Forms_Base {

	protected function get_integration_name(): string {
		return Module::get_name();
	}

	protected function prepare_data(): ?array {
		$forms = Forms_Data_Helper::get_published_forms();
		if ( empty( $forms ) ) {
			return null;
		}

		$forms_data = [];
		$module     = Module::get_name();

		foreach ( $forms as $form ) {
			$form_id = Forms_Data_Helper::normalize_form_id( $form );
			$fields_data = Forms_Data_Helper::get_form_fields( $form );

			$fields = [];
			foreach ( $fields_data as $index => $field ) {
				// Skip fields that shouldn't be tracked (like page breaks, sections, etc.)
				if ( in_array( $field['type'] ?? '', [ 'page', 'section', 'html' ], true ) ) {
					continue;
				}

				$fields[] = [
					'type'        => $this->map_field_type( $field['type'] ?? 'text' ),
					'fieldName'   => $field['label'] ?? '',
					'orderInForm' => $index,
					'isRequired'  => ! empty( $field['isRequired'] ),
				];
			}

			$forms_data[] = [
				'source'   => [
					'method' => 'form',
					'name'   => $module,
				],
				'formId'   => $form_id,
				'formName' => Forms_Data_Helper::get_form_title( $form ),
				'fields'   => $fields,
			];
		}

		return $forms_data;
	}

	/**
	 * Map Gravity Forms field types to standard field types
	 *
	 * @param string $gf_type
	 * @return string
	 */
	private function map_field_type( string $gf_type ): string {
		$type_mapping = [
			'text'     => 'text',
			'textarea' => 'textarea',
			'email'    => 'email',
			'phone'    => 'tel',
			'number'   => 'number',
			'select'   => 'select',
			'radio'    => 'radio',
			'checkbox' => 'checkbox',
			'date'     => 'date',
			'time'     => 'time',
			'website'  => 'url',
			'fileupload' => 'file',
			'name'     => 'text',
			'address'  => 'text',
		];

		return $type_mapping[ $gf_type ] ?? 'text';
	}
}
