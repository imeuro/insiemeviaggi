<?php
/**
 * Blocks support
 *
 * @package   VivaComSmartForWooCommerce
 */

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

defined( 'ABSPATH' ) || exit;

/**
 * WC_Vivacom_Smart_Blocks_Support
 *
 * @since 1.0.0
 */
final class WC_Vivacom_Smart_Blocks_Support extends AbstractPaymentMethodType {
	/**
	 * Name of the payment method.
	 *
	 * @var string
	 */
	protected $name = 'vivacom_smart';

	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_vivacom_smart_settings', array() );
	}

	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active() {
		return ! empty( $this->settings['enabled'] ) && 'yes' === $this->settings['enabled'];
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {

		$asset_path   = WC_VIVA_COM_SMART_PLUGIN_PATH . '/build/index.asset.php';
		$version      = WC_VIVA_COM_SMART_VERSION;
		$dependencies = array();

		if ( file_exists( $asset_path ) ) {
			$asset        = require $asset_path;
			$version      = is_array( $asset ) && isset( $asset['version'] ) ? $asset['version'] : $version;
			$dependencies = is_array( $asset ) && isset( $asset['dependencies'] ) ? $asset['dependencies'] : $version;
		}

		wp_register_script(
			'wc-vivacom-smart-payment-blocks-integration',
			WC_VIVA_COM_SMART_PLUGIN_URL . '/build/index.js',
			$dependencies,
			$version,
			true
		);
		return array( 'wc-vivacom-smart-payment-blocks-integration' );
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		return array(
			'title'       => $this->get_setting( 'title' ),
			'description' => $this->get_setting( 'description' ),
			'supports'    => $this->get_supported_features(),
			'logo_url'    => WC_VIVA_COM_SMART_PLUGIN_URL . '/includes/assets/vivacom.png',
		);
	}

	/**
	 * Returns an array of supported features.
	 *
	 * @return string[]
	 */
	public function get_supported_features() {
		$payment_gateways = WC()->payment_gateways->payment_gateways();
		return $payment_gateways['vivacom_smart']->supports;
	}
}
