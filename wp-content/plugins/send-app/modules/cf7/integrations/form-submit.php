<?php
namespace Send_App\Modules\CF7\Integrations;

use Send_App\Core\Integrations\Classes\Forms\{Form_Submit_Base, Form_Submit_Data};
use Send_App\Modules\CF7\Classes\Forms_Data_Helper;
use Send_App\Modules\CF7\Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Form_Submit extends Form_Submit_Base {

	protected function get_submit_hook(): string {
		return 'wpcf7_submit';
	}

	public function get_integration_name(): string {
		return Module::get_name();
	}

	protected function prepare_data( $record, $handler ): ?Form_Submit_Data {
		if ( ! $record instanceof \WPCF7_ContactForm ) {
			return null;
		}

		/** @var Module $module */
		$module = Module::get_instance();

		/** @var \Send_App\Modules\CF7\Components\Forms $forms_component */
		$forms_component = $module->get_component( 'Forms' );

		$form_id = $handler['contact_form_id'] ?? '';
		if ( empty( $form_id ) ) {
			return null;
		}

		$form_id = Forms_Data_Helper::prepare_form_id( $form_id );
		if ( $forms_component->is_disabled_form( $form_id ) ) {
			return null;
		}

		$post_data = [];
		foreach ( $_POST as $key => $value ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$post_data[ $key ] = sanitize_text_field( $value );
		}

		$form_post_id = $post_data['_wpcf7_container_post'] ?? '';

		// cleanup:
		unset( $post_data['_wpcf7'], $post_data['_wpcf7_container_post'], $post_data['_wpcf7_version'], $post_data['_wpcf7_locale'], $post_data['_wpcf7_unit_tag'], $post_data['_wpcf7_posted_data_hash'] );

		$form_title = Forms_Data_Helper::get_form_title( $record );

		return new Form_Submit_Data( $module::get_name(), $form_id, $form_post_id, $post_data, $form_title );
	}
}
