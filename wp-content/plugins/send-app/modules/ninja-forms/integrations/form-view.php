<?php
namespace Send_App\Modules\Ninja_Forms\Integrations;

use Send_App\Core\Integrations\Classes\Forms\Form_View_Base;
use Send_App\Modules\Ninja_Forms\Classes\Forms_Data_Helper;
use Send_App\Modules\Ninja_Forms\Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Form_View extends Form_View_Base {
	const FORM_TRACKER_SCRIPT_HANDLE = 'send-app-ninja-forms-form-tracker';
	const AJAX_ACTION_VIEWED = 'send_app_ninja_forms_form_viewed';
	const AJAX_ACTION_ABANDONED = 'send_app_ninja_forms_form_abandoned';

	protected function is_form_disabled( $form_id ): bool {
		/** @var Module $module */
		$module = Module::get_instance();

		/** @var \Send_App\Modules\Ninja_Forms\Components\Forms $forms_component */
		$forms_component = $module->get_component( 'Forms' );

		return $forms_component->is_disabled_form( $form_id );
	}

	protected function get_id_prefix(): string {
		return Forms_Data_Helper::FORM_ID_PREFIX;
	}

	protected function get_js_object_name(): string {
		return 'eSendNinjaFormsSettings';
	}

	protected function get_form_selectors(): array {
		return [ '.nf-form-wrap form' ];
	}

	protected function get_script_relative_path(): string {
		return 'js/send-app-ninja-forms-form-tracker.js';
	}

	protected function get_init_hook_name(): string {
		return 'ninja_forms_loaded';
	}

	public function get_integration_name(): string {
		return Module::get_name();
	}

	protected function get_script_depends(): array {
		return [ 'nf-front-end' ];
	}
}
