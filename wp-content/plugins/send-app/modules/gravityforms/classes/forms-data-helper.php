<?php

namespace Send_App\Modules\Gravityforms\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Forms_Data_Helper {
	const FORM_ID_PREFIX = 'gravityforms-';

	/**
	 * @return ?array
	 */
	public static function get_published_forms(): ?array {
		static $forms = null;

		if ( ! is_null( $forms ) ) {
			return $forms;
		}

		if ( ! class_exists( 'GFAPI' ) ) {
			return [];
		}

		$forms = \GFAPI::get_forms( true, false );

		return $forms;
	}

	/**
	 * @param string $form_id form-id should be in the Gravity Forms format, without send's prefix
	 *
	 * @return ?array
	 */
	public static function get_published_form( string $form_id ): ?array {
		if ( ! class_exists( 'GFAPI' ) ) {
			return null;
		}

		try {
			$form = \GFAPI::get_form( intval( $form_id ) );
			return $form ? $form : null;
		} catch ( \Exception $e ) {
			return null;
		}
	}

	public static function get_form_id( array $form ): string {
		return strval( $form['id'] ?? '' );
	}

	public static function get_form_title( array $form ): string {
		return $form['title'] ?? '';
	}

	public static function get_form_fields( array $form ): array {
		return $form['fields'] ?? [];
	}

	/**
	 * @param int | string $form_id
	 *
	 * @return string
	 */
	public static function prepare_form_id( $form_id ): string {
		return self::FORM_ID_PREFIX . $form_id;
	}

	public static function extract_form_id( string $formatted_form_id ): string {

		if ( 0 === strpos( $formatted_form_id, self::FORM_ID_PREFIX ) ) {
			return substr( $formatted_form_id, strlen( self::FORM_ID_PREFIX ) );
		}
		return $formatted_form_id;
	}

	public static function normalize_form_id( array $form ): string {
		return self::prepare_form_id( self::get_form_id( $form ) );
	}
}
