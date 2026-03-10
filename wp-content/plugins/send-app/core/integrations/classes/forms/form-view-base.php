<?php
namespace Send_App\Core\Integrations\Classes\Forms;

use Send_App\Core\Utils;
use Send_App\Core\Integrations\Classes\Integration_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

abstract class Form_View_Base extends Integration_Base {
	const AJAX_ACTION = 'send-app-forms-events';
	const ACTION_VIEWED = 'viewed';
	const ACTION_ABANDONED = 'abandoned';

	const FORM_TRACKER_SCRIPT_HANDLE = '';
	const FORM_VIEWED_THRESHOLD = 0.95;
	const AJAX_ACTION_VIEWED = 'TBD_form_viewed';
	const AJAX_ACTION_ABANDONED = 'TBD_form_abandoned';

	public function sync(): bool {
		return true;
	}

	public function maybe_enqueue_scripts() {
		if ( is_admin() ) {
			return;
		}

		if ( $this->skip_enqueue() ) {
			return;
		}

		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	public function enqueue_scripts(): void {
		static $enqueued = [];

		// Once per integration is enough:
		if ( ! empty( $enqueued[ $this->get_integration_name() ] ) ) {
			return;
		}

		\wp_enqueue_script(
			static::FORM_TRACKER_SCRIPT_HANDLE,
			SEND_ASSETS_URL . $this->get_script_relative_path(),
			$this->get_script_depends(),
			SEND_VERSION,
			true
		);

		$nonce = \wp_create_nonce( static::AJAX_ACTION );

		$form_settings = [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'integration' => $this->get_integration_name(),
			'nonce' => $nonce,
			'viewedThreshold' => $this->get_viewed_threshold(),
			'viewedAction' => static::AJAX_ACTION_VIEWED,
			'abandonedAction' => static::AJAX_ACTION_ABANDONED,
			'formSelectors' => $this->get_form_selectors(),
			'debugOn' => Utils::debug_on(),
			'idPrefix' => $this->get_id_prefix(),
		];
		$js_onj_name = $this->get_js_object_name();
		\wp_add_inline_script( static::FORM_TRACKER_SCRIPT_HANDLE, "const {$js_onj_name} = " . wp_json_encode( $form_settings ), 'before' );

		$enqueued[ $this->get_integration_name() ] = true;
	}

	abstract protected function get_js_object_name(): string;

	abstract protected function get_form_selectors(): array;
	abstract protected function get_script_relative_path(): string;

	abstract protected function get_init_hook_name(): string;

	abstract public function get_integration_name(): string;

	abstract protected function is_form_disabled( $form_id ): bool;

	protected function get_viewed_threshold(): float {
		return static::FORM_VIEWED_THRESHOLD;
	}

	protected function get_id_prefix(): string {
		return '';
	}

	protected function get_script_depends(): array {
		return [];
	}

	protected function skip_enqueue(): bool {
		return false;
	}

	protected function prepare_form_id(): string {
		// PHPCS: nonce verified in the ajax handler
		return sanitize_key( $_POST['form_id'] ); // @phpcs:ignore WordPress.Security.NonceVerification.Missing
	}

	protected function prepare_page_id(): string {
		// PHPCS: nonce verified in the ajax handler
		return sanitize_key( $_POST['page_id'] ); // @phpcs:ignore WordPress.Security.NonceVerification.Missing
	}

	/**
	 * @param string $event
	 *
	 * @return array | \WP_Error
	 */
	protected function send_form_event( string $event ) {
		$form_id = $this->prepare_form_id();
		$page_id = $this->prepare_page_id();
		// PHPCS: nonce verified in the ajax handler
		$location = sanitize_key( $_POST['location'] ); // @phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( $this->is_form_disabled( $form_id ) ) {
			return [];
		}

		$page_url = Utils::get_page_url( $page_id );

		$payload = [
			'source' => [
				'method' => 'form',
				'name' => $this->get_integration_name(),
			],
			'eventType' => $event,
			'formId' => $form_id,
			'page' => [
				'pageId' => $page_id,
				'pagePath' => $page_url,
				'name' => \get_the_title( $page_id ),
				'location' => $location,
			],
		];

		return Forms_Api_Helper::post_form_event( $event, $payload );
	}

	public function form_viewed_ajax_handler() {
		\check_ajax_referer( static::AJAX_ACTION, 'nonce' );

		$response = $this->send_form_event( static::ACTION_VIEWED );

		if ( is_wp_error( $response ) ) {
			\wp_send_json_error( $response );
		}

		\wp_send_json_success( $response );
	}

	public function form_abandoned_ajax_handler() {
		\check_ajax_referer( static::AJAX_ACTION, 'nonce' );

		$response = $this->send_form_event( static::ACTION_ABANDONED );

		if ( is_wp_error( $response ) ) {
			\wp_send_json_error( $response );
		}

		\wp_send_json_success( $response );
	}

	public function register_hooks(): void {
		$init_hook_name = $this->get_init_hook_name();
		add_action( $init_hook_name, [ $this, 'maybe_enqueue_scripts' ] );

		add_action( 'wp_ajax_' . static::AJAX_ACTION_VIEWED, [ $this, 'form_viewed_ajax_handler' ] );
		add_action( 'wp_ajax_nopriv_' . static::AJAX_ACTION_VIEWED, [ $this, 'form_viewed_ajax_handler' ] );
		add_action( 'wp_ajax_' . static::AJAX_ACTION_ABANDONED, [ $this, 'form_abandoned_ajax_handler' ] );
		add_action( 'wp_ajax_nopriv_' . static::AJAX_ACTION_ABANDONED, [ $this, 'form_abandoned_ajax_handler' ] );
	}
}
