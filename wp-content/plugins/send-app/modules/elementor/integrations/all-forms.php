<?php
namespace Send_App\Modules\Elementor\Integrations;

use Send_App\Modules\Elementor\Classes\Forms_Data_Helper;
use Send_App\Modules\Elementor\Module;
use Send_App\Core\Integrations\Classes\Forms\All_Forms_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class All_Forms extends All_Forms_Base {
	protected function get_integration_name(): string {
		return Module::get_name();
	}

	protected function prepare_data(): ?array {
		$posts_with_forms = Forms_Data_Helper::get_post_ids_with_forms();
		$posts_with_forms_data = Forms_Data_Helper::get_forms_from_post_ids( $posts_with_forms );

		if ( empty( $posts_with_forms_data ) ) {
			return null;
		}

		$forms_data = [];

		$module_name = Module::get_name();

		foreach ( $posts_with_forms_data as $post_id => $forms ) {
			foreach ( $forms as $form ) {
				$form_fields = [];

				foreach ( $form['settings']['form_fields'] as $field_index => $field ) {
					$form_fields[] = [
						'type' => $field['field_type'] ?? 'text',
						'fieldName' => $field['field_label'] ?? '',
						'orderInForm' => $field_index,
						'isRequired' => isset( $field['required'] ) && 'true' === $field['required'],
					];
				}

				$forms_data[] = [
					'source' => [
						'method' => 'form',
						'name' => $module_name,
					],
					'formId' => $post_id . '-' . $form['id'],
					'formName' => $form['settings']['form_name'],
					'fields' => $form_fields,
				];
			}
		}

		return $forms_data;
	}
}
