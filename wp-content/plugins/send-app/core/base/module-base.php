<?php
namespace Send_App\Core\Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * class Module_Base
 *
 * base class for Modules that hold and manage components
 */
abstract class Module_Base {

	protected static array $instances = [];

	protected array $components = [];

	/**
	 * @return string
	 */
	abstract public static function get_name(): string;


	/**
	 * @return void
	 */
	public function register_hooks(): void {}

	/**
	 * @return void
	 */
	public function register_rest_hooks(): void {}

	/**
	 * @return array
	 */
	protected function components_list(): array {
		return [];
	}

	/**
	 * @return bool
	 */
	public static function is_active(): bool {
		return apply_filters( 'send_app/modules/' . static::get_name() . '/is-active', true );
	}

	/**
	 * Class name.
	 *
	 * Retrieve the name of the class.
	 * @access public
	 * @static
	 */
	public static function class_name(): string {
		return get_called_class();
	}

	/**
	 * Get module component.
	 *
	 * Retrieve the module component.
	 * @access public
	 *
	 * @param string $id Component ID.
	 *
	 * @return mixed An instance of the component, or `null` if the component
	 *               doesn't exist.
	 */
	public function get_component( string $id ) {
		if ( isset( $this->components[ $id ] ) ) {
			return $this->components[ $id ];
		}

		return null;
	}

	/**
	 * Retrieve the namespace of the class
	 *
	 * @static
	 * @access public
	 *
	 * @return string
	 */
	public static function namespace_name(): string {
		$class_name = static::class_name();
		return substr( $class_name, 0, strrpos( $class_name, '\\' ) );
	}


	/**
	 * Add module component.
	 *
	 * Add new component to the current module.
	 * @access public
	 *
	 * @param string $id Component ID.
	 * @param mixed  $instance An instance of the component.
	 */
	public function add_component( string $id, $instance ) {
		$this->components[ $id ] = $instance;
	}

	/**
	 * Adds an array of components.
	 * Assumes namespace structure contains `\Components\`
	 *
	 * @access protected
	 *
	 * @param ?array $components_ids => component's class name.
	 * @return void
	 */
	protected function register_components( ?array $components_ids = null ): void {
		if ( empty( $components_ids ) ) {
			$components_ids = $this->components_list();
		}
		$namespace = static::namespace_name();
		foreach ( $components_ids as $component_id ) {
			$class_name = $namespace . '\\Components\\' . $component_id;
			$this->add_component( $component_id, new $class_name() );
		}
	}

	protected function register_routes() {}

	/**
	 * @return Module_Base
	 */
	public static function get_instance(): Module_Base {
		$class_name = get_called_class();

		if ( ! isset( static::$instances[ $class_name ] ) ) {
			static::$instances[ $class_name ] = new $class_name();
		}

		return static::$instances[ $class_name ];
	}

	public function __clone() {
		_doing_it_wrong( __FUNCTION__, 'Something went wrong.', '1.0.0' );
	}

	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, 'Something went wrong.', '1.0.0' );
	}

	public function init() {
		$this->register_hooks();
		$this->register_components();
		$this->register_routes();
	}

	protected function __construct() {}
}
