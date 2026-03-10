<?php
namespace Send_App\Modules\WP_Forms\Integrations;

use Send_App\Core\Integrations\Classes\Forms\{Form_Submit_Base, Form_Submit_Data};
use Send_App\Modules\WP_Forms\Classes\Forms_Data_Helper;
use Send_App\Modules\WP_Forms\Module;
use Send_App\Core\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Form_Submit extends Form_Submit_Base {

	protected function get_submit_hook(): string {
		return 'wpforms_process_complete';
	}

	/**
	 * @param array $fields Fields data.
	 * @param array $entry Form submission raw data ($_POST).
	 * @param array $form_data Form data.
	 * @param int $entry_id Entry ID.
	 */
	public function wp_form_on_form_submit( array $fields, array $entry, array $form_data, int $entry_id ) {
		$record = [
			'fields' => $fields,
			'entry' => $entry,
			'entry_id' => $entry_id,
			'form_data' => $form_data,
		];

		$this->on_form_submit( $record, $form_data );
	}

	protected function register_submit_hook() {
		add_action( 'wpforms_process_entry_saved', [ $this, 'wp_form_on_form_submit' ], 100000, 4 );
	}

	public function get_integration_name(): string {
		return Module::get_name();
	}

	protected function prepare_data( $record, $handler ): ?Form_Submit_Data {
		if ( ! is_array( $record ) || empty( $record['form_data']['id'] ) ) {
			return null;
		}

		/** @var Module $module */
		$module = Module::get_instance();

		/** @var \Send_App\Modules\Wp_Forms\Components\Forms $forms_component */
		$forms_component = $module->get_component( 'Forms' );

		$form_id = $record['form_data']['id'];
		$form_id = Forms_Data_Helper::prepare_form_id( $form_id );
		if ( $forms_component->is_disabled_form( $form_id ) ) {
			return null;
		}

		$post_data = [];
		foreach ( $record['fields'] as $field ) {
			if ( isset( $field['name'] ) && isset( $field['value'] ) ) {
				$post_data[ $field['name'] ] = $field['value'];
			}
		}

		// Add hidden fields
		$post_data['page_id'] = $record['form_data']['entry_meta']['page_id'] ?? '';
		$post_data['page_title'] = \get_the_title( $record['form_data']['entry_meta']['page_id'] ) ?? '';
		$post_data['page_url'] = \get_permalink( $record['form_data']['entry_meta']['page_id'] ) ?? '';
		$post_data['url_referer'] = \wp_get_referer() ?? '';

		$form_title = \get_the_title( $form_id ) ?? '';

		return new Form_Submit_Data( $this->get_integration_name(), $form_id, $post_data['page_id'], $post_data, $form_title );
	}
}
