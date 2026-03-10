<?php
namespace Send_App\Modules\Elementor\Integrations;

use Send_App\Core\Integrations\Classes\Forms\Form_View_Base;
use Send_App\Modules\Elementor\Classes\Forms_Data_Helper;
use Send_App\Modules\Elementor\Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Form_View extends Form_View_Base {
	const FORM_TRACKER_SCRIPT_HANDLE = 'send-app-elementor-form-tracker';
	const AJAX_ACTION_VIEWED = 'send_app_elementor_form_viewed';
	const AJAX_ACTION_ABANDONED = 'send_app_elementor_form_abandoned';

	protected function is_form_disabled( $form_id ): bool {
		/** @var Module $module */
		$module = Module::get_instance();

		/** @var \Send_App\Modules\Elementor\Components\Forms $forms_component */
		$forms_component = $module->get_component( 'Forms' );

		return $forms_component->is_disabled_form( $form_id );
	}

	protected function get_js_object_name(): string {
		return 'eSendElementorFormsSettings';
	}

	protected function get_form_selectors(): array {
		return [ 'form.elementor-form', 'form.ehp-form' ];
	}

	protected function get_script_relative_path(): string {
		return 'js/send-app-elementor-form-tracker.js';
	}

	protected function get_init_hook_name(): string {
		return 'elementor/frontend/after_register_scripts';
	}

	protected function prepare_form_id(): string {
		$form_id = parent::prepare_form_id();
		$template_id = sanitize_key( $_POST['template_id'] ); //@phpcs:ignore WordPress.Security.NonceVerification.Missing
		$page_id = sanitize_key( $_POST['page_id'] );  // @phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( empty( $template_id ) || ( $template_id === $page_id ) ) {
			return $form_id;
		}

		$elementor_plugin = Module::get_instance()->get_elementor_plugin();

		$document = $elementor_plugin->documents->get( $template_id );
		if ( ! $document ) {
			return $form_id;
		}

		$form = Forms_Data_Helper::find_form_element_recursive( $document->get_elements_data(), $form_id );
		if ( empty( $form ) ) {
			return $form_id;
		}
		if ( empty( $form['templateID'] ) ) {
			return $form['id'];
		}

		$form = Forms_Data_Helper::get_form_by_template_id( $form['templateID'] );
		if ( ! empty( $form ) ) {
			return $form['id'];
		}

		return $form_id;
	}

	public function get_integration_name(): string {
		return Module::get_name();
	}
}
