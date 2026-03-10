<?php

namespace Send_App\Modules\Ninja_Forms\Classes;

use Send_App\Core\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Helper class for handling Ninja Forms data operations
 */
class Forms_Data_Helper {
	const FORM_ID_PREFIX = 'nf-';

	/**
	 * Get all published Ninja Forms
	 *
	 * @return array|null Array of published forms or null if none found
	 */
	public static function get_published_forms(): array {
		static $forms = null;

		if ( ! is_null( $forms ) ) {
			return $forms;
		}

		// Get all forms using Ninja Forms' form collection
		$forms = \Ninja_Forms()->form()->get_forms();

		return is_array( $forms ) ? $forms : [];
	}

	/**
	 * Get form ID from Ninja Forms form object
	 *
	 * @param \NF_Database_Models_Form $form The form object
	 * @return string The form ID
	 */
	public static function get_form_id( $form ): string {
		return strval( $form->get_id() );
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
	 * Get form title from Ninja Forms form object
	 *
	 * @param \NF_Database_Models_Form $form The form object
	 * @return string The form title
	 */
	public static function get_form_title( $form ): string {
		return $form->get_setting( 'title' );
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
	 * @param \NF_Database_Models_Form $form The form object
	 * @return string The normalized form ID
	 */
	public static function normalize_form_id( $form ): string {
		return self::prepare_form_id( self::get_form_id( $form ) );
	}

	/**
	 * Get form instance by ID
	 *
	 * @param string $form_id
	 * @return \NF_Database_Models_Form|false
	 */
	public static function get_form_instance_by_id( string $form_id ) {
		return \Ninja_Forms()->form( $form_id )->get();
	}

	/**
	 * Get form fields
	 *
	 * @param \NF_Database_Models_Form $form The form object
	 * @return array Array of form fields
	 */
	public static function get_form_fields( $form ): array {
		$fields = $form->get_fields();
		return empty( $fields ) ? [] : $fields;
	}
}
