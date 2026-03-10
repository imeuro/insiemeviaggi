<?php

namespace Send_App\Modules\CF7\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Forms_Data_Helper {
	const FORM_ID_PREFIX = 'cf7-';

	/**
	 * @return ?\WPCF7_ContactForm[]
	 */
	public static function get_published_forms(): ?array {
		static $forms = null;

		if ( ! is_null( $forms ) ) {
			return $forms;
		}

		$forms = \WPCF7_ContactForm::find( [
			'post_status' => 'publish',
		] );

		return $forms;
	}

	/**
	 * @param string $form_id form-id should be in the CF7 format, without send's prefix
	 *
	 * @return \WPCF7_ContactForm|null
	 */
	public static function get_published_form( string $form_id ): ?\WPCF7_ContactForm {
		try {
			$contact_form_object = \wpcf7_contact_form( $form_id );
		} catch ( \Exception $e ) {
			return null;
		}

		return $contact_form_object;
	}

	public static function get_form_id( \WPCF7_ContactForm $form ): string {
		return strval( $form->id() );
	}

	public static function get_form_title( \WPCF7_ContactForm $form ): string {
		return $form->title();
	}

	public static function get_form_fields( \WPCF7_ContactForm $form ): array {
		return $form->scan_form_tags();
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

	public static function normalize_form_id( \WPCF7_ContactForm $form ): string {
		return self::prepare_form_id( self::get_form_id( $form ) );
	}
}
