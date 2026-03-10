<?php

namespace Send_App\Modules\CF7\Integrations;

use Send_App\Core\Integrations\Classes\Forms\All_Forms_Base;
use Send_App\Modules\CF7\Classes\Forms_Data_Helper;
use Send_App\Modules\CF7\Module;

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
			$tags    = Forms_Data_Helper::get_form_fields( $form );

			$fields = [];
			foreach ( $tags as $index => $tag ) {
				$fields[] = [
					'type'        => $tag->basetype ?? 'text',
					'fieldName'   => $tag->name ?? '',
					'orderInForm' => $index,
					'isRequired'  => in_array( 'required', $tag->options, true ),
				];
			}

			$forms_data[] = [
				'source'   => [
					'method' => 'form',
					'name'   => $module,
				],
				'formId'   => $form_id,
				'formName' => $form->title(),
				'fields'   => $fields,
			];
		}

		return $forms_data;
	}
}
