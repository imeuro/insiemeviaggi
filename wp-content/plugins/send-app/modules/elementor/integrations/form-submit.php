<?php
namespace Send_App\Modules\Elementor\Integrations;

use Send_App\Core\Integrations\Classes\Forms\{
	Form_Submit_Base,
	Form_Submit_Data,
};
use Send_App\Modules\Elementor\Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Form_Submit extends Form_Submit_Base {

	const EVENT = 'submitted';

	public function sync(): bool {
		return true;
	}

	protected function get_submit_hook(): string {
		return 'elementor_pro/forms/new_record';
	}

	public function get_integration_name(): string {
		return Module::get_name();
	}

	protected function prepare_data( $record, $handler ): ?Form_Submit_Data {
		/** @var Module $module */
		$module = Module::get_instance();

		if ( $module->is_elementor_preview() ) {
			return null;
		}

		if ( ! $record instanceof \ElementorPro\Modules\Forms\Classes\Form_Record ) {
			return null;
		}

		/** @var \Send_App\Modules\Elementor\Components\Forms $forms_component */
		$forms_component = $module->get_component( 'Forms' );

		$form_id = $record->get_form_settings( 'id' );
		$element_id = $handler->get_current_form()['id'];

		// Maybe it's a global form:
		if ( $element_id !== $form_id ) {
			$form_id = $element_id;
		}

		if ( $forms_component->is_disabled_form( $form_id ) ) {
			return null;
		}

		$form_post_id = $record->get_form_settings( 'form_post_id' );
		$post_id = $_POST['queried_id'] ?? $form_post_id; // @phpcs:ignore WordPress.Security.NonceVerification.Missing

		$fields = $record->get( 'sent_data' );
		$elementor_plugin = $module->get_elementor_plugin();
		$document = $elementor_plugin->documents->get( $post_id );
		$document_type = $document ? $document->get_name() : '';

		$meta = $record->get( 'meta' );
		if ( empty( $meta ) || ! is_array( $meta ) ) {
			$meta = null;
		}

		return new Form_Submit_Data( $module::get_name(), $form_id, $post_id, $fields, $record->get_form_settings( 'form_name' ), $document_type, $meta );
	}
}
