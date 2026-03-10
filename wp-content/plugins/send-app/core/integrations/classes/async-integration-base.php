<?php
namespace Send_App\Core\Integrations\Classes;

use Send_App\Core\Options_Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * class Async_Integration_Base
 *
 * Base class for integrations that sync using the Action-Scheduler
 **/
abstract class Async_Integration_Base extends Integration_Base {
	protected int $limit = 100;

	protected int $current_page = 0;

	protected int $max_pages = -1;

	protected int $total_items = -1;

	const OPTION_KEY_PREFIX_CURRENT_PAGE = 'send_app_sync_current_page_';
	const OPTION_KEY_PREFIX_PROGRESS_PERCENT = 'send_app_sync_progress_percent_';
	const OPTION_KEY_PREFIX_TOTAL = 'send_app_sync_total_';
	const OPTION_KEY_PREFIX_MAX_PAGES = 'send_app_sync_max_pages_';

	const SYNC_HOOK_PREFIX = 'send_app_sync_';
	const ASYNC_UPDATE_HOOK_PREFIX = 'send_app/async/update/';

	const ENTITY = '';

	const SYNC_GROUP = 'send_app_initial_sync';
	const ASYNC_UPDATE_GROUP = 'send_app_async_update';
	const ACTION_UPDATE_ERROR = 'update_error';

	const GET_NEXT_PAGE_ERROR_CODE = 'get_next_page_error';
	const ASYNC_UPDATE_ERROR_CODE = 'async_update_error';
	const ASYNC_SYNC_ERROR_CODE = 'async_sync_error';

	public function get_limit(): int {
		return $this->limit;
	}

	public function set_limit( int $limit ) {
		$this->limit = $limit;
	}

	public function get_current_page(): int {
		return $this->current_page;
	}

	public function set_current_page( int $current ) {
		$this->current_page = $current;
	}

	public function get_max_pages(): int {
		return $this->max_pages;
	}

	public function set_max_pages( int $max_pages ) {
		$this->max_pages = $max_pages;
	}

	public function get_total_items(): int {
		return $this->total_items;
	}

	public function set_total_items( int $total_items ) {
		$this->total_items = $total_items;
	}

	public function is_sync_in_progress(): bool {
		if ( \as_has_scheduled_action( $this->get_sync_hook_name(), null, static::SYNC_GROUP ) ) {
			return true;
		}

		$current_page = Options_Utils::get_option( static::OPTION_KEY_PREFIX_CURRENT_PAGE . static::ENTITY );
		$max_pages = Options_Utils::get_option( static::OPTION_KEY_PREFIX_MAX_PAGES . static::ENTITY );

		return $max_pages && $current_page && ( $current_page <= $max_pages );
	}

	/**
	 * @param bool $first
	 *
	 * @return bool
	 */
	public function register_sync( bool $first = false ): bool {
		return 0 !== \as_schedule_single_action( time() + 5, $this->get_sync_hook_name(), [ 'first' => $first ], static::SYNC_GROUP );
	}

	/**
	 * @param bool $first
	 *
	 * @return bool|\WP_Error
	 */
	public function fetch_and_sync( bool $first ) {
		// refresh current-page:
		$option_value = Options_Utils::get_option( static::OPTION_KEY_PREFIX_CURRENT_PAGE . static::ENTITY, $this->current_page );
		$this->set_current_page( intval( $option_value ) + 1 );

		if ( ! $first && ( $this->get_max_pages() < $this->get_current_page() ) ) {
			// delete temp data & bail:
			$this->cleanup();
			return true;
		}

		// run the data query:
		$entity_items = $this->get_next_page( $first );

		if ( is_wp_error( $entity_items ) ) {
			return $this->report_error( $entity_items );
		}

		if ( $first ) {
			// save max-pages & total-items:
			$this->update_sync_data();
		}
		// save the current page & progress %:
		$this->update_progress_data();

		// format & send:
		$this->process( $entity_items );
		$this->register_sync();
		return true;
	}

	/**
	 * @param bool $first
	 *
	 * @return void
	 */
	public function fetch_and_sync_action( bool $first ): void {
		$this->fetch_and_sync( $first );
	}

	/**
	 * @param int $id
	 * @param string $trigger
	 * @param string $action
	 *
	 * @return mixed|\WP_Error
	 */
	public function async_update( int $id, string $trigger, string $action ) {
		$data = $this->get_sync_data( $id );
		if ( empty( $data ) ) {
			$error = new \WP_Error( static::ASYNC_UPDATE_ERROR_CODE, static::ENTITY . ' not found. id: ' . $id, [
				'trigger' => $trigger,
				'action' => $action,
			] );
			return $this->report_error( $error, $trigger );
		}
		return $this->send_data( $data, $trigger, $action );
	}

	/**
	 * @param int $id
	 * @param string $trigger
	 * @param string $action
	 *
	 * @return void
	 */
	public function async_update_action( int $id, string $trigger, string $action ): void {
		$this->async_update( $id, $trigger, $action );
	}

	protected function get_progress_data() {
		$current_page = Options_Utils::get_option( static::OPTION_KEY_PREFIX_CURRENT_PAGE . static::ENTITY, 1 );
		$this->set_current_page( intval( $current_page ) );
	}

	protected function update_sync_data() {
		Options_Utils::update_option( static::OPTION_KEY_PREFIX_TOTAL . static::ENTITY, $this->get_total_items() );
		Options_Utils::update_option( static::OPTION_KEY_PREFIX_MAX_PAGES . static::ENTITY, $this->get_max_pages() );
	}

	protected function update_progress_data() {
		$progress = 0 < $this->get_max_pages() ? intval( ( $this->get_current_page() / $this->get_max_pages() ) * 100 ) : 1;
		Options_Utils::update_option( static::OPTION_KEY_PREFIX_CURRENT_PAGE . static::ENTITY, $this->get_current_page() );
		Options_Utils::update_option( static::OPTION_KEY_PREFIX_PROGRESS_PERCENT . static::ENTITY, $progress );
	}

	protected function cleanup() {
		$options = $this->get_option_names_prefixes();

		foreach ( $options as $option ) {
			Options_Utils::delete_option( $option . static::ENTITY );
		}
	}

	protected function get_sync_hook_name(): string {
		return static::SYNC_HOOK_PREFIX . static::ENTITY;
	}

	protected function get_async_update_hook_name(): string {
		return static::ASYNC_UPDATE_HOOK_PREFIX . static::ENTITY;
	}

	protected function report_error( \WP_Error $error, $trigger = 'initial_sync' ): \WP_Error {
		wc_get_logger()->error( sprintf( '%1$s | code: %2$s | message: %3$s', static::ENTITY, $error->get_error_code(), $error->get_error_message() ) );

		$error_array = [
			'code' => $error->get_error_code(),
			'reason' => $error->get_error_message(),
			'data' => $error->get_error_data(),
		];
		if ( 'initial_sync' === $trigger ) {
			$this->send_initial_sync_data( [ 'error' => $error_array ], 'initial sync error' );
		} else {
			$this->send_data( [
				'error' => $error_array,
			], $trigger, static::ACTION_UPDATE_ERROR );
		}

		return $error;
	}

	protected function add_scheduled_action( $hook, $args, $group, $time = null ) {
		\as_schedule_single_action( $time ?? time() + 1, $hook, $args, $group );
	}

	public function register_hooks(): void {
		add_action( 'init', [ $this, 'init' ], 9 );
	}

	/**
	 * Register the action-scheduler related hooks
	 *
	 * Each derived class should call this method according to its own logic,
	 * however, it is recommended to call it as early as possible
	 *
	 * @return void
	 */
	protected function register_as_hooks() {
		add_action( $this->get_sync_hook_name(), [ $this, 'fetch_and_sync_action' ], 10, 1 );
		add_action( $this->get_async_update_hook_name(), [ $this, 'async_update_action' ], 10, 3 );
	}

	protected function process( array $items ) {
		$this->send_initial_sync_data( [ 'items' => wp_json_encode( $items ) ], 'sync data (activate plugin)' );
	}

	protected function get_option_names_prefixes(): array {
		return [
			static::OPTION_KEY_PREFIX_CURRENT_PAGE,
			static::OPTION_KEY_PREFIX_MAX_PAGES,
			static::OPTION_KEY_PREFIX_PROGRESS_PERCENT,
			static::OPTION_KEY_PREFIX_TOTAL,
		];
	}

	public function init() {
		$option_value = Options_Utils::get_option( static::OPTION_KEY_PREFIX_CURRENT_PAGE . static::ENTITY, $this->current_page );
		$this->current_page = intval( $option_value );

		$option_value = Options_Utils::get_option( static::OPTION_KEY_PREFIX_TOTAL . static::ENTITY, $this->total_items );
		$this->total_items = intval( $option_value );

		$option_value = Options_Utils::get_option( static::OPTION_KEY_PREFIX_MAX_PAGES . static::ENTITY, $this->max_pages );
		$this->max_pages = intval( $option_value );
	}

	/**
	 * @param bool $is_first
	 *
	 * @return \WP_Error|array
	 */
	abstract protected function get_next_page( bool $is_first );

	/**
	 * @param mixed|int $entity_object either the actual object or its numeric id
	 *
	 * @return array
	 */
	abstract protected function get_sync_data( $entity_object ): array;

	/**
	 * @param array $data
	 * @param string $trigger
	 * @param string $action
	 *
	 * @return mixed
	 */
	abstract protected function send_data( array $data, string $trigger, string $action );

	/**
	 * @param array $data
	 * @param string $trigger
	 *
	 * @return mixed
	 */
	abstract protected function send_initial_sync_data( array $data, string $trigger );
}
