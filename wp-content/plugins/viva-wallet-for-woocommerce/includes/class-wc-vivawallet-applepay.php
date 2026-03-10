<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Vivawallet_ApplePay
 *
 * @class   WC_Vivawallet_ApplePay
 */
class WC_Vivawallet_ApplePay {
	/**
	 * $viva_settings.
	 *
	 * @var array
	 */
	public $viva_settings;

	/**
	 * Testmode.
	 *
	 * @var bool
	 */
	public $label;

	/**
	 * Testmode.
	 *
	 * @var bool
	 */
	public $test_mode;


	/**
	 * Construct.
	 */
	public function __construct() {

		$this->viva_settings = get_option( 'woocommerce_vivawallet_native_settings', array() );
		$this->test_mode     = ( ! empty( $this->viva_settings['test_mode'] ) && 'yes' === $this->viva_settings['test_mode'] ) ? 'yes' : 'no';

		if ( empty( $this->viva_settings ) || ( isset( $this->viva_settings['enabled'] ) && 'yes' !== $this->viva_settings['enabled'] ) ) {
			return;
		}

		if ( 'yes' === $this->test_mode ) {

			if ( ! isset( $this->viva_settings['test_apple_pay'] ) || 'yes' !== $this->viva_settings['test_apple_pay'] ) {
				return;
			}

			if ( ! isset( $this->viva_settings['test_apple_pay_domain_registered'] ) || 'yes' !== $this->viva_settings['test_apple_pay_domain_registered'] ) {
				return;
			}
		} else {

			if ( ! isset( $this->viva_settings['apple_pay'] ) || 'yes' !== $this->viva_settings['apple_pay'] ) {
				return;
			}

			if ( ! isset( $this->viva_settings['apple_pay_domain_registered'] ) || 'yes' !== $this->viva_settings['apple_pay_domain_registered'] ) {
				return;
			}
		}

		if ( isset( $_GET['change_payment_method'] ) ) {
			return;
		}

		add_action( 'template_redirect', array( $this, 'start_session' ) );

		$this->label = get_bloginfo( 'name' );

		$this->init();

	}


	/**
	 * Sets the WC session
	 */
	public function start_session() {
		if ( ! is_product() || ( isset( WC()->session ) && WC()->session->has_session() ) ) {
			return;
		}
		WC()->session->set_customer_session_cookie( true );
	}

	/**
	 * Initialize hooks.
	 */
	public function init() {

		add_action( 'wp_enqueue_scripts', array( $this, 'scripts' ) );

		add_action( 'woocommerce_after_add_to_cart_quantity', array( $this, 'apple_pay_button' ), 1 );
		add_action( 'woocommerce_proceed_to_checkout', array( $this, 'apple_pay_button' ), 1 );
		add_action( 'woocommerce_checkout_before_customer_details', array( $this, 'apple_pay_button' ), 1 );

		add_action( 'wc_ajax_wc_vivawallet_get_cart_data', array( $this, 'ajax_get_cart_data' ) );
		add_action( 'wc_ajax_wc_vivawallet_get_shipping_data', array( $this, 'ajax_get_shipping_data' ) );
		add_action( 'wc_ajax_wc_vivawallet_update_shipping_data', array( $this, 'ajax_update_shipping_data' ) );
		add_action( 'wc_ajax_wc_vivawallet_create_order', array( $this, 'ajax_create_order' ) );
		add_filter( 'woocommerce_validate_postcode', array( $this, 'postal_code_validation' ), 10, 3 );
	}

	/**
	 * Gets the product info
	 */
	public function get_product() {

		$gateways = WC()->payment_gateways->get_available_payment_gateways();

		if ( ! is_product() ) {
			return false;
		}

		global $post;

		$product = wc_get_product( $post->ID );

		if ( 'variable' === $product->get_type() ) {
			$attributes = wc_clean( wp_unslash( $_GET ) );

			$data_store   = WC_Data_Store::load( 'product' );
			$variation_id = $data_store->find_matching_product_variation( $product, $attributes );

			if ( ! empty( $variation_id ) ) {
				$product = wc_get_product( $variation_id );
			}
		}

		$data  = array();
		$items = array();

		$items[] = array(
			'label'  => $product->get_name(),
			'amount' => $product->get_price(),
		);

		if ( wc_tax_enabled() ) {
			$items[] = array(
				'label'   => __( 'Tax', 'viva-wallet-for-woocommerce' ),
				'amount'  => 0,
				'pending' => true,
			);
		}

		if ( wc_shipping_enabled() && $product->needs_shipping() ) {
			$items[] = array(
				'label'   => __( 'Shipping', 'viva-wallet-for-woocommerce' ),
				'amount'  => 0,
				'pending' => true,
			);

			$data['shippingMethods'] = array(
				'id'     => 'pending',
				'label'  => __( 'Pending', 'viva-wallet-for-woocommerce' ),
				'detail' => '',
				'amount' => 0,
			);
		}

		$data['lineItems'] = $items;
		$data['total']     = array(
			'label'   => $this->label,
			'amount'  => $product->get_price(),
			'pending' => true,
		);

		$data['requestShipping'] = ( wc_shipping_enabled() && $product->needs_shipping() );
		$data['currency']        = get_woocommerce_currency();
		$data['country_code']    = substr( get_option( 'woocommerce_default_country' ), 0, 2 );

		return $data;
	}

	/**
	 * Postal code validation.
	 *
	 * @param string|bool $valid Valid.
	 * @param string      $postcode Postcode.
	 * @param string      $country Country.
	 *
	 * @return bool
	 */
	public function postal_code_validation( $valid, $postcode, $country ) {
		$gateways = WC()->payment_gateways->get_available_payment_gateways();

		if ( ! isset( $gateways['vivawallet_native'] ) ) {
			return $valid;
		}

		if ( isset( $_POST['payment_request_type'], $_POST['payment_request_type_nonce'] ) && ! wp_verify_nonce( sanitize_key( $_POST['payment_request_type_nonce'] ), 'payment_request_type_action' ) ) {
			return;
		}

		$payment_request_type = isset( $_POST['payment_request_type'] ) ? wc_clean( wp_unslash( $_POST['payment_request_type'] ) ) : '';

		if ( 'apple_pay' !== $payment_request_type ) {
			return $valid;
		}

		if ( 'GB' === $country ) {
			return true;
		}

		return $valid;
	}

	/**
	 * Scripts.
	 */
	public function scripts() {
		global $wp;

		if ( isset( $_GET['pay_for_order'], $_GET['pay_for_order_nonce'] ) && ! wp_verify_nonce( sanitize_key( $_GET['pay_for_order_nonce'] ), 'pay_for_order_action' ) ) {
			return;
		}

		if ( ! is_product() && ! is_cart() && empty( $_GET['pay_for_order'] ) ) {
			return;
		}

		if ( ! is_ssl() ) {
			$error = __( 'Viva Wallet - Apple Pay: This site is not SSL protected. Please protect your domain to accept Apple Pay payments, or disable Apple Pay from Viva Wallet plugin settings." ', 'viva-wallet-for-woocommerce' );
			wc_add_notice( $error, 'error' );
			return;
		}

		$source_code = ( 'yes' === $this->test_mode ) ? $this->viva_settings['test_source_code'] : $this->viva_settings['source_code'];

		$token = WC_Vivawallet_Credentials::get_authentication_token( $this->test_mode, 'front' );

		if ( empty( $token ) ) {
			$error = __( 'Viva Wallet: Your credentials are NOT valid. Apple Pay could not be loaded. Please check your credentials!', 'viva-wallet-for-woocommerce' );
			wc_add_notice( $error, 'error' );
			return;
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && true === SCRIPT_DEBUG ? '' : '.min';

		wp_register_style( 'vivawallet_styles_apple_pay', plugins_url( '/assets/css/vivawallet-styles-apple-pay' . $suffix . '.css', __FILE__ ), array(), WC_VIVAWALLET_VERSION );
		wp_enqueue_style( 'vivawallet_styles_apple_pay' );

		wp_register_script( 'woocommerce_vivawallet_apple_pay', plugins_url( '/assets/js/apple-pay-vivawallet' . $suffix . '.js', __FILE__ ), array( 'jquery' ), WC_VIVAWALLET_VERSION, true );

		wp_localize_script(
			'woocommerce_vivawallet_apple_pay',
			'vivawallet_apple_pay_params',
			array(

				'token'            => $token,
				'sourceCode'       => $source_code,

				'ajax_url'         => WC_AJAX::get_endpoint( '%%endpoint%%' ),

				'checkout'         => array(
					'url'            => wc_get_checkout_url(),
					'currency_code'  => get_woocommerce_currency(),
					'country_code'   => substr( get_option( 'woocommerce_default_country' ), 0, 2 ),
					'needs_shipping' => WC()->cart->needs_shipping() ? 'yes' : 'no',
				),
				'is_product'       => is_product(),
				'product'          => $this->get_product(),

				'scriptUrl'        => WC_Vivawallet_Helper::get_api_url( $this->test_mode ),
				'applePayTokenUrl' => WC_Vivawallet_Helper::get_api_url_endpoint( $this->test_mode, WC_Vivawallet_Helper::ENDPOINT_APPLE_PAY_TOKEN ),

				'security'         => array(
					'payment'         => wp_create_nonce( 'wc-vivawallet-apple-pay-request' ),
					'shipping'        => wp_create_nonce( 'wc-vivawallet-apple-pay-request-shipping' ),
					'update_shipping' => wp_create_nonce( 'wc-vivawallet-update-shipping-method' ),
					'checkout'        => wp_create_nonce( 'woocommerce-process_checkout' ),
				),

			)
		);

		wp_enqueue_script( 'woocommerce_vivawallet_apple_pay', '', array(), WC_VIVAWALLET_VERSION, true );
	}

	/**
	 * Display the Apple Pay button.
	 */
	public function apple_pay_button() {
		$gateways = WC()->payment_gateways->get_available_payment_gateways();
		if ( ! isset( $gateways['vivawallet_native'] ) ) {
			return;
		}

		if ( isset( $_GET['pay_for_order'], $_GET['pay_for_order_nonce'] ) && ! wp_verify_nonce( sanitize_key( $_GET['pay_for_order_nonce'] ), 'pay_for_order_action' ) ) {
			return;
		}

		if ( ! is_cart() && empty( $_GET['pay_for_order'] ) ) {
			return;
		}

		if ( ! $this->enable_apple_pay_button() ) {
			return;
		}
		?>

	<div id="VWapplePayBut" style="clear:both;padding-top:1.5em; display:none;">
			<button id="apple-pay-button" class="" ></button>
		</div>
		<?php

	}
	/**
	 * Ajax get cart data.
	 *
	 * @return boolean Result.
	 */
	private function enable_apple_pay_button() {

		// Subscriptions are not supported.
		if ( class_exists( 'WC_Subscriptions_Order' ) && WC_Subscriptions_Cart::cart_contains_subscription() ) {
			return false;
		}

		return true;

	}

	/**
	 * Ajax get cart data.
	 */
	public function ajax_get_cart_data() {
		check_ajax_referer( 'wc-vivawallet-apple-pay-request', 'security' );

		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}

		WC()->cart->calculate_totals();

		$currency = get_woocommerce_currency();

		// Set mandatory payment details.
		$data = array(
			'shipping_required' => WC()->cart->needs_shipping(),
			'order_data'        => array(
				'currency'     => $currency,
				'country_code' => substr( get_option( 'woocommerce_default_country' ), 0, 2 ),
			),
		);

		$data['order_data'] += $this->build_items();
		wp_send_json( $data );

	}

	/**
	 * Ajax get shipping data.
	 */
	public function ajax_get_shipping_data() {
		check_ajax_referer( 'wc-vivawallet-apple-pay-request-shipping', 'security' );

		$shipping_address = filter_input_array(
			INPUT_POST,
			array(
				'country'   => FILTER_SANITIZE_STRING,
				'state'     => FILTER_SANITIZE_STRING,
				'postcode'  => FILTER_SANITIZE_STRING,
				'city'      => FILTER_SANITIZE_STRING,
				'address'   => FILTER_SANITIZE_STRING,
				'address_2' => FILTER_SANITIZE_STRING,
			)
		);
		$product_options  = filter_input_array( INPUT_POST, array( 'is_product' => FILTER_SANITIZE_STRING ) );

		$data = $this->get_shipping_data( $shipping_address );
		wp_send_json( $data );
	}

	/**
	 * Get shipping data
	 *
	 * @param array $shipping_address Shipping address.
	 *
	 * @throws Exception Exception.
	 * @return array|integer
	 */
	public function get_shipping_data( $shipping_address ) {
		try {

			$data = array();

			$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );
			$this->calculate_shipping( $shipping_address );

			$packages = WC()->shipping->get_packages();

			if ( ! empty( $packages ) && WC()->customer->has_calculated_shipping() ) {
				foreach ( $packages as $package_key => $package ) {
					if ( empty( $package['rates'] ) ) {
						throw new Exception( __( 'Unable to find shipping method for address.', 'viva-wallet-for-woocommerce' ) );
					}

					foreach ( $package['rates'] as $key => $rate ) {
						$data['shipping_methods'][] = array(
							'identifier' => $rate->id,
							'label'      => $rate->label,
							'detail'     => '',
							'amount'     => absint( wc_format_decimal( ( (float) $rate->cost * 100 ), wc_get_price_decimals() ) ) / 100,
						);
					}
				}
			} else {
				throw new Exception( __( 'Unable to find shipping method for address.', 'viva-wallet-for-woocommerce' ) );
			}

			if ( isset( $data['shipping_methods'][0] ) ) {
				if ( isset( $chosen_shipping_methods[0] ) ) {
					$chosen_method_id = $chosen_shipping_methods[0];
					$compare_shipping = function ( $x, $y ) use ( $chosen_method_id ) {
						if ( $x['identifier'] === $chosen_method_id ) {
							return -1;
						}
						if ( $y['identifier'] === $chosen_method_id ) {
							return 1;
						}
						return 0;
					};
					usort( $data['shipping_methods'], $compare_shipping );
				}

				$first_shipping_method_id = $data['shipping_methods'][0]['identifier'];
				$this->update_shipping_data( array( $first_shipping_method_id ) );
			}

			WC()->cart->calculate_totals();

			$data          += $this->build_items();
			$data['result'] = 'success';
		} catch ( Exception $e ) {
			$data          += $this->build_items();
			$data['result'] = 'shipping_address_not_valid';
		}

		return $data;
	}

	/**
	 * Ajax update shipping data.
	 */
	public function ajax_update_shipping_data() {
		check_ajax_referer( 'wc-vivawallet-update-shipping-method', 'security' );

		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}

		$shipping_methods = filter_input( INPUT_POST, 'shipping_method', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );

		$this->update_shipping_data( $shipping_methods );

		WC()->cart->calculate_totals();

		$product_view_options = filter_input_array( INPUT_POST, array( 'is_product' => FILTER_SANITIZE_STRING ) );

		$data           = array();
		$data          += $this->build_items();
		$data['result'] = 'success';

		wp_send_json( $data );
	}

	/**
	 * Update shipping data.
	 *
	 * @param array $shipping_methods Shipping methods.
	 */
	public function update_shipping_data( $shipping_methods ) {
		$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );

		if ( is_array( $shipping_methods ) ) {
			foreach ( $shipping_methods as $i => $value ) {
				$chosen_shipping_methods[ $i ] = wc_clean( $value );
			}
		}
		WC()->session->set( 'chosen_shipping_methods', $chosen_shipping_methods );
	}


	/**
	 * Ajax create order.
	 */
	public function ajax_create_order() {
		if ( WC()->cart->is_empty() ) {
			wp_send_json_error( __( 'Empty cart', 'viva-wallet-for-woocommerce' ) );
		}

		if ( ! defined( 'WOOCOMMERCE_CHECKOUT' ) ) {
			define( 'WOOCOMMERCE_CHECKOUT', true );
		}

		// todo fix this.
		$_POST['billing_state']  = '';
		$_POST['shipping_state'] = '';

		WC()->checkout()->process_checkout();

		die( 0 );
	}


	/**
	 * Calculate shipping.
	 *
	 * @param array $address Address.
	 */
	protected function calculate_shipping( $address = array() ) {
		$country   = $address['country'];
		$state     = $address['state'];
		$postcode  = $address['postcode'];
		$city      = $address['city'];
		$address_1 = $address['address'];
		$address_2 = $address['address_2'];
		$wc_states = WC()->countries->get_states( $country );

		if ( 2 < strlen( $state ) && ! empty( $wc_states ) && ! isset( $wc_states[ $state ] ) ) {
			$state = array_search( ucwords( strtolower( $state ) ), $wc_states, true );
		}

		WC()->shipping->reset_shipping();

		if ( $postcode && WC_Validation::is_postcode( $postcode, $country ) ) {
			$postcode = wc_format_postcode( $postcode, $country );
		}

		if ( $country ) {
			WC()->customer->set_location( $country, $state, $postcode, $city );
			WC()->customer->set_shipping_location( $country, $state, $postcode, $city );
		} else {
			WC()->customer->set_billing_address_to_base();
			WC()->customer->set_shipping_address_to_base();
		}

		WC()->customer->set_calculated_shipping( true );
		WC()->customer->save();

		$packages = array();

		$packages[0]['contents'] = WC()->cart->get_cart();

		$packages[0]['destination']['country']   = $country;
		$packages[0]['destination']['state']     = $state;
		$packages[0]['destination']['postcode']  = $postcode;
		$packages[0]['destination']['city']      = $city;
		$packages[0]['destination']['address']   = $address_1;
		$packages[0]['destination']['address_2'] = $address_2;

		$packages[0]['contents_cost']   = 0;
		$packages[0]['applied_coupons'] = WC()->cart->applied_coupons;
		$packages[0]['user']['ID']      = get_current_user_id();

		foreach ( WC()->cart->get_cart() as $item ) {
			if ( $item['data']->needs_shipping() ) {
				if ( isset( $item['line_total'] ) ) {
					$packages[0]['contents_cost'] += $item['line_total'];
				}
			}
		}

		$packages = apply_filters( 'woocommerce_cart_shipping_packages', $packages );

		WC()->shipping->calculate_shipping( $packages );
	}




	/**
	 * Builds the line items to pass to Payment Request
	 */
	protected function build_items() {
		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}

		$items     = array();
		$subtotal  = 0;
		$discounts = 0;

		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			$amount         = $cart_item['line_subtotal'];
			$subtotal      += $cart_item['line_subtotal'];
			$quantity_label = 1 < $cart_item['quantity'] ? ' (x' . $cart_item['quantity'] . ')' : '';

			$product_name = $cart_item['data']->get_name();

			$item = array(
				'label'  => $product_name . $quantity_label,
				'amount' => absint( wc_format_decimal( ( (float) $amount * 100 ), wc_get_price_decimals() ) ) / 100,
			);

			$items[] = $item;
		}

		$applied_coupons = array_values( WC()->cart->get_coupon_discount_totals() );

		foreach ( $applied_coupons as $amount ) {
			$discounts += (float) $amount;
		}

		$discounts   = wc_format_decimal( $discounts, WC()->cart->dp );
		$tax         = wc_format_decimal( WC()->cart->tax_total + WC()->cart->shipping_tax_total, WC()->cart->dp );
		$shipping    = wc_format_decimal( WC()->cart->shipping_total, WC()->cart->dp );
		$items_total = wc_format_decimal( WC()->cart->cart_contents_total, WC()->cart->dp ) + $discounts;
		$order_total = WC()->cart->get_total( false );

		if ( wc_tax_enabled() ) {
			$items[] = array(
				'label'  => esc_html( __( 'Tax', 'viva-wallet-for-woocommerce' ) ),
				'amount' => absint( wc_format_decimal( ( (float) $tax * 100 ), wc_get_price_decimals() ) ) / 100,
			);
		}

		if ( WC()->cart->needs_shipping() ) {
			$items[] = array(
				'label'  => esc_html( __( 'Shipping', 'viva-wallet-for-woocommerce' ) ),
				'amount' => absint( wc_format_decimal( ( (float) $shipping * 100 ), wc_get_price_decimals() ) ) / 100,
			);
		}

		if ( WC()->cart->has_discount() ) {
			$items[] = array(
				'label'  => esc_html( __( 'Discount', 'viva-wallet-for-woocommerce' ) ),
				'amount' => absint( wc_format_decimal( ( (float) $discounts * 100 ), wc_get_price_decimals() ) ) / 100,
			);
		}

		$cart_fees = WC()->cart->get_fees();

		foreach ( $cart_fees as $key => $fee ) {
			$items[] = array(
				'label'  => $fee->name,
				'amount' => absint( wc_format_decimal( ( (float) $fee->amount * 100 ), wc_get_price_decimals() ) ) / 100,
			);

		}

		return array(
			'lineItems' => $items,
			'total'     => array(
				'label'   => $this->label,
				'amount'  => $order_total,
				'pending' => false,
			),
		);
	}
}


new WC_Vivawallet_ApplePay();
