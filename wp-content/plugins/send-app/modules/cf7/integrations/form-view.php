<?php
namespace Send_App\Modules\CF7\Integrations;

use Send_App\Core\Integrations\Classes\Forms\Form_View_Base;
use Send_App\Modules\CF7\Classes\Forms_Data_Helper;
use Send_App\Modules\CF7\Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Form_View extends Form_View_Base {
	const FORM_TRACKER_SCRIPT_HANDLE = 'send-app-cf7-form-tracker';
	const AJAX_ACTION_VIEWED = 'send_app_cf7_form_viewed';
	const AJAX_ACTION_ABANDONED = 'send_app_cf7_form_abandoned';

	protected function is_form_disabled( $form_id ): bool {
		/** @var Module $module */
		$module = Module::get_instance();

		/** @var \Send_App\Modules\CF7\Components\Forms $forms_component */
		$forms_component = $module->get_component( 'Forms' );

		return $forms_component->is_disabled_form( $form_id );
	}

	protected function get_id_prefix(): string {
		return Forms_Data_Helper::FORM_ID_PREFIX;
	}


	protected function get_js_object_name(): string {
		return 'eSendCf7FormsSettings';
	}

	protected function get_form_selectors(): array {
		return [ 'form.wpcf7-form' ];
	}

	protected function get_script_relative_path(): string {
		return 'js/send-app-cf7-form-tracker.js';
	}

	protected function get_init_hook_name(): string {
		return 'wpcf7_init';
	}

	public function get_integration_name(): string {
		return Module::get_name();
	}
	protected function get_script_depends(): array {
		return [ 'contact-form-7' ];
	}
}
