<?php

namespace Send_App\Modules\Elementor\Classes;

use Send_App\Modules\Elementor\Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Forms_Data_Helper {

	/**
	 * @param array $post_ids
	 * @param bool $force
	 *
	 * @return array
	 */
	public static function get_forms_from_post_ids( array $post_ids, bool $force = false ): array {
		static $forms = null;
		if ( ! $force && ! is_null( $forms ) ) {
			return $forms;
		}

		$forms = [];
		foreach ( $post_ids as $post_id ) {
			$post_forms = self::get_forms_for_post_id( $post_id );
			if ( ! empty( $post_forms ) ) {
				$forms[ $post_id ] = $post_forms;
			}
		}

		return $forms;
	}

	public static function get_forms_for_post_id( int $post_id ): array {
		$forms = [];

		$elementor_plugin = Module::get_instance()->get_elementor_plugin();
		$document = $elementor_plugin->documents->get( $post_id );

		if ( ! $document ) {
			return $forms;
		}

		if ( $document->is_revision() || $document->is_trash() ) {
			return $forms;
		}

		$data = $document->get_elements_data();

		if ( empty( $data ) ) {
			return $forms;
		}

		$elementor_plugin->db->iterate_data( $data, function ( $element ) use ( &$forms ) {
			if ( empty( $element['widgetType'] ) ) {
				return;
			}

			if ( 'form' === $element['widgetType'] ) {
				$forms[] = $element;
				return;
			}

			if ( ! empty( $element['templateID'] ) ) {
				$form = self::get_form_by_template_id( $element['templateID'] );
				if ( $form ) {
					$forms[] = $form;
				}
			}
		} );

		return $forms;
	}

	public static function get_form_by_template_id( $template_id ): ?array {
		$elementor_plugin = Module::get_instance()->get_elementor_plugin();
		$template = $elementor_plugin->documents->get( $template_id );

		if ( empty( $template ) ) {
			return null;
		}

		$template_elements = $template->get_elements_data();
		if ( empty( $template_elements[0] ) ) {
			return null;
		}

		return $template_elements[0];
	}

	public static function find_form_element_recursive( $elements, $form_id ): ?array {
		foreach ( $elements as $element ) {
			if ( $form_id === $element['id'] ) {
				return $element;
			}

			if ( ! empty( $element['elements'] ) ) {
				$element = self::find_form_element_recursive( $element['elements'], $form_id );

				if ( $element ) {
					return $element;
				}
			}
		}

		return null;
	}

	public static function get_post_ids_with_forms(): array {
		global $wpdb;

		return $wpdb->get_col(
			'SELECT `post_id` FROM `' . $wpdb->postmeta . '` WHERE `meta_key` = "_elementor_data" AND `meta_value` LIKE \'%"widgetType":"form"%\';'
		);
	}

	/**
	 * will return a nested array, top-level is by post-id and for each post, the related forms
	 *
	 * @param string $form_id
	 *
	 * @return array
	 */
	public static function get_form_instances_by_form_id( string $form_id ): array {
		$post_ids = self::get_post_ids_with_forms();
		$all_forms = self::get_forms_from_post_ids( $post_ids );
		$instances = [];

		foreach ( $all_forms as $post_id => $forms_in_post ) {
			foreach ( $forms_in_post as $form ) {
				if ( $form['id'] === $form_id ) {
					$instances[ $post_id ] = $form;
				}
			}
		}

		return $instances;
	}
}
