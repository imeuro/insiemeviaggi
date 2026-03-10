<?php

namespace Send_App\Modules\WP_Forms\Classes;

use Send_App\Core\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Helper class for handling WPForms data operations
 */
class Forms_Data_Helper {
	const FORM_ID_PREFIX = 'wpf-';

	/**
	 * Get all published WPForms
	 *
	 * @return array|null Array of published forms or null if none found
	 */
	public static function get_published_forms(): ?array {
		static $forms = null;

		if ( ! is_null( $forms ) ) {
			return $forms;
		}

		// Use WPForms' built-in function to get all forms
		$wp_form_obj = \wpforms()->obj( 'form' );

		if ( ! $wp_form_obj ) {
			return null;
		}

		$forms = $wp_form_obj->get( '', [
			'post_status' => 'publish',
			'orderby'     => 'title',
			'order'       => 'ASC',
		] );

		// return type might be `false` if no forms are found:
		$forms = is_array( $forms ) ? $forms : null;

		return $forms;
	}

	/**
	 * Get form ID from WP_Post object
	 *
	 * @param \WP_Post $form The form post object
	 * @return string The form ID
	 */
	public static function get_form_id( \WP_Post $form ): string {
		return strval( $form->ID );
	}

	/**
	 * Prepare form ID with prefix
	 *
	 * @param int|string $form_id The form ID to prepare
	 * @return string The prepared form ID
	 */
	public static function prepare_form_id( $form_id ): string {
		return self::FORM_ID_PREFIX . $form_id;
	}

	/**
	 * Get form title from WP_Post object
	 *
	 * @param \WP_Post $form The form post object
	 * @return string The form title
	 */
	public static function get_form_title( \WP_Post $form ): string {
		return $form->post_title;
	}

	/**
	 * Extract form ID from formatted ID string
	 *
	 * @param string $formatted_form_id The formatted form ID
	 * @return string The extracted form ID
	 */
	public static function extract_form_id( string $formatted_form_id ): string {
		if ( 0 === strpos( $formatted_form_id, self::FORM_ID_PREFIX ) ) {
			return substr( $formatted_form_id, strlen( self::FORM_ID_PREFIX ) );
		}
		return $formatted_form_id;
	}

	/**
	 * Normalize form ID for a given form
	 *
	 * @param \WP_Post $form The form post object
	 * @return string The normalized form ID
	 */
	public static function normalize_form_id( \WP_Post $form ): string {
		return self::prepare_form_id( self::get_form_id( $form ) );
	}

	/**
	 * @param string $form_id
	 *
	 * @return \WP_Post | false
	 */
	public static function get_form_instance_by_id( string $form_id ) {
		$wp_form_obj = \wpforms()->obj( 'form' );
		return $wp_form_obj->get( $form_id );
	}

	public static function get_form_fields( \WP_Post $form ): array {
		$fields = \wpforms_get_form_fields( $form );
		return empty( $fields ) ? [] : $fields;
	}
}
