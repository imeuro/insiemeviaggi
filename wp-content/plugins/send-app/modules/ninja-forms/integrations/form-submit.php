<?php
namespace Send_App\Modules\Ninja_Forms\Integrations;

use Send_App\Core\Integrations\Classes\Forms\{Form_Submit_Base, Form_Submit_Data};
use Send_App\Modules\Ninja_Forms\Classes\Forms_Data_Helper;
use Send_App\Modules\Ninja_Forms\Module;
use Send_App\Core\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Form_Submit extends Form_Submit_Base {

	protected function get_submit_hook(): string {
		return 'ninja_forms_after_submission';
	}

	/**
	 * Handle form submission
	 *
	 * @param array $form_data Form data
	 */
	public function on_ninja_form_submit( $form_data ) {
		$record = [
			'form_data' => $form_data,
			'fields' => $form_data['fields'] ?? [],
		];

		$this->on_form_submit( $record, $form_data );
	}

	protected function register_submit_hook() {
		add_action( 'ninja_forms_after_submission', [ $this, 'on_ninja_form_submit' ], 10, 1 );
	}

	public function get_integration_name(): string {
		return Module::get_name();
	}

	protected function prepare_data( $record, $handler ): ?Form_Submit_Data {

		if ( ! is_array( $record ) || empty( $record['form_data']['form_id'] ) ) {
			return null;
		}

		/** @var Module $module */
		$module = Module::get_instance();

		/** @var \Send_App\Modules\Ninja_Forms\Components\Forms $forms_component */
		$forms_component = $module->get_component( 'Forms' );

		$form_id = $record['form_data']['form_id'];
		$form_id = Forms_Data_Helper::prepare_form_id( $form_id );
		if ( $forms_component->is_disabled_form( $form_id ) ) {
			return null;
		}

		$post_data = [];
		foreach ( $record['fields'] as $field ) {
			if ( isset( $field['key'] ) && isset( $field['value'] ) ) {
				$post_data[ $field['key'] ] = $field['value'];
			}
		}

		// Hacky solution because ninja forms has no way of tracking post id either by js injection or php filters
		$referer = $_SERVER['HTTP_REFERER'] ?? home_url( '/' );   //  We default to home url if no referer is found
		$form_post_id = \url_to_postid( $referer );
		if ( ! $form_post_id ) {
			$form_post_id = 'undefined-post-id';
		}
		$post_data['post_id'] = $form_post_id;
		$form = Forms_Data_Helper::get_form_instance_by_id( $record['form_data']['form_id'] );
		$form_title = $form ? Forms_Data_Helper::get_form_title( $form ) : '';

		return new Form_Submit_Data( $this->get_integration_name(), $form_id, $post_data['post_id'], $post_data, $form_title );
	}
}
