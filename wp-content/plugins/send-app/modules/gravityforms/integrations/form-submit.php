<?php
namespace Send_App\Modules\Gravityforms\Integrations;

use Send_App\Core\Integrations\Classes\Forms\{Form_Submit_Base, Form_Submit_Data};
use Send_App\Modules\Gravityforms\Classes\Forms_Data_Helper;
use Send_App\Modules\Gravityforms\Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Form_Submit extends Form_Submit_Base {

	protected function get_submit_hook(): string {
		return 'gform_after_submission';
	}

	public function get_integration_name(): string {
		return Module::get_name();
	}

	protected function prepare_data( $record, $handler ): ?Form_Submit_Data {
		// In Gravity Forms, $record is the entry array and $handler is the form array
		$entry = $record;
		$form = $handler;

		if ( ! is_array( $entry ) || ! is_array( $form ) ) {
			return null;
		}

		$form_id = $form['id'] ?? '';
		if ( empty( $form_id ) ) {
			return null;
		}

		/** @var Module $module */
		$module = Module::get_instance();

		/** @var \Send_App\Modules\Gravityforms\Components\Forms $forms_component */
		$forms_component = $module->get_component( 'Forms' );
		$formatted_form_id = Forms_Data_Helper::prepare_form_id( $form_id );
		if ( $forms_component->is_disabled_form( $formatted_form_id ) ) {
			return null;
		}

		// Extract form data from entry, excluding meta fields
		$post_data = [];
		foreach ( $entry as $key => $value ) {
			// Skip Gravity Forms internal fields (numeric keys are field IDs, others are meta)
			if ( is_numeric( $key ) && ! empty( $value ) ) {
				// Get field label for better data structure
				$field = \GFAPI::get_field( $form, $key );
				$field_label = $field ? $field->label : "field_{$key}";
				$post_data[ $field_label ] = sanitize_text_field( $value );
			}
		}

		// Get post ID from entry or current page
		$form_post_id = $entry['post_id'] ?? '';
		if ( empty( $form_post_id ) ) {
			// Fallback to current post ID
			$form_post_id = get_the_ID() ? get_the_ID() : '';
		}

		$form_title = Forms_Data_Helper::get_form_title( $form );

		return new Form_Submit_Data( $module::get_name(), $formatted_form_id, $form_post_id, $post_data, $form_title );
	}
}
