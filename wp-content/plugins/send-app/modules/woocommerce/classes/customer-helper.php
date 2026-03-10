<?php
namespace Send_App\Modules\Woocommerce\Classes;

use Send_App\Core\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Customer_Helper {
	const NON_USER_COOKIE_NAME = 'send_app_non_user_purchase';
	public static function get_customer_data_by_order( \WC_Order $order ): array {
		$customer_id = $order->get_customer_id();

		$customer = [
			'billing_info' => [
				'email' => $order->get_billing_email(),
				'phone' => $order->get_billing_phone(),
				'first_name' => $order->get_billing_first_name(),
				'last_name' => $order->get_billing_last_name(),
				'zip' => $order->get_billing_postcode(),
				'address_1' => $order->get_billing_address_1(),
				'address_2' => $order->get_billing_address_2(),
				'city' => $order->get_billing_city(),
				'state' => $order->get_billing_state(),
				'country' => $order->get_billing_country(),
			],
			'shipping_info' => [
				'phone' => $order->get_shipping_phone(),
				'first_name' => $order->get_shipping_first_name(),
				'last_name' => $order->get_shipping_last_name(),
				'zip' => $order->get_shipping_postcode(),
				'address_1' => $order->get_shipping_address_1(),
				'address_2' => $order->get_shipping_address_2(),
				'city' => $order->get_shipping_city(),
				'state' => $order->get_shipping_state(),
				'country' => $order->get_shipping_country(),
			],
		];

		// 0 means guest:
		if ( 0 < $customer_id ) {
			$user = get_user_by( 'ID', $customer_id );
			if ( $user ) {
				$customer['user'] = self::get_user_data( $user );
			}
		}

		return self::sanitize_customer_info( $customer );
	}

	public static function get_customer_data_by_user( $user ): array {
		if ( 0 >= $user->ID ) {
			return [
				'user'          => null,
				'billing_info'  => null,
				'shipping_info' => null,
			];
		}

		$customer = new \WC_Customer( $user->ID );

		$customer_data = [
			'billing_info' => [
				'email' => $customer->get_billing_email(),
				'phone' => $customer->get_billing_phone(),
				'first_name' => $customer->get_billing_first_name(),
				'last_name' => $customer->get_billing_last_name(),
				'zip' => $customer->get_billing_postcode(),
				'address_1' => $customer->get_billing_address_1(),
				'address_2' => $customer->get_billing_address_2(),
				'city' => $customer->get_billing_city(),
				'state' => $customer->get_billing_state(),
				'country' => $customer->get_billing_country(),
			],
			'shipping_info' => [
				'email' => '', //there is no shipping email at the customer level, send empty to align to data structure.
				'phone' => $customer->get_shipping_phone(),
				'first_name' => $customer->get_shipping_first_name(),
				'last_name' => $customer->get_shipping_last_name(),
				'zip' => $customer->get_shipping_postcode(),
				'address_1' => $customer->get_shipping_address_1(),
				'address_2' => $customer->get_shipping_address_2(),
				'city' => $customer->get_shipping_city(),
				'state' => $customer->get_shipping_state(),
				'country' => $customer->get_shipping_country(),
			],
			'user' => self::get_user_data( $user ),
		];

		return self::sanitize_customer_info( $customer_data );
	}

	public static function get_customer_from_cookie(): array {
		if ( ! isset( $_COOKIE[ self::NON_USER_COOKIE_NAME ] ) ) {
			$user = \wp_get_current_user();
			return self::get_customer_data_by_user( $user );
		}

		// Decode the cookie value.
		$cookie_data = json_decode( stripslashes( $_COOKIE[ self::NON_USER_COOKIE_NAME ] ), true );

		if ( ! is_array( $cookie_data ) ) {
			$user = \wp_get_current_user();
			return self::get_customer_data_by_user( $user );
		}

		return $cookie_data;
	}

	public static function get_user_data( $user ): array {
		return [
			'user_id' => $user->ID,
			'email' => $user->user_email,
			'first_name' => $user->first_name,
			'last_name'  => $user->last_name,
			'registered' => $user->user_registered,
			'roles' => $user->roles,
		];
	}

	public static function set_non_user_cookie( $non_user_details ): string {
		$encoded_user_details = wp_json_encode( $non_user_details );
		if ( ! empty( $_COOKIE[ self::NON_USER_COOKIE_NAME ] ) ) {
			// Proper way to remove a cookie
			setcookie( self::NON_USER_COOKIE_NAME, $encoded_user_details, time() + ( 10 * 365 * 24 * 60 * 60 ), '/' );
			Logger::debug( 'Woo Commerce Logger: User Purchase Details Updated' );
		} else {
			Logger::debug( 'Woo Commerce Logger: New User Purchase Details Cookie Created' );
		}

		// Set the cookie with the JSON string
		setcookie( self::NON_USER_COOKIE_NAME, $encoded_user_details, time() + 3600, '/' );

		return $encoded_user_details;
	}

	private static function sanitize_customer_info( array $customer_info ): array {
		$customer_parts = [
			'user',
			'billing_info',
			'shipping_info',
		];

		foreach ( $customer_parts as $part ) {
			if ( empty( $customer_info[ $part ] ) || ! is_array( $customer_info[ $part ] ) ) {
				continue;
			}
			$set_to_null = true;
			foreach ( $customer_info[ $part ] as $key => $value ) {
				if ( ! empty( $value ) ) {
					$set_to_null = false;
					break;
				}
			}
			if ( $set_to_null ) {
				$customer_info[ $part ] = null;
			}
		}

		return $customer_info;
	}

	private static function sanitize_address_data( $data ): array {
		return [
			'email' => sanitize_email( $data['email'] ?? '' ),
			'phone' => sanitize_text_field( $data['phone'] ?? '' ),
			'first_name' => sanitize_text_field( $data['first_name'] ?? '' ),
			'last_name' => sanitize_text_field( $data['last_name'] ?? '' ),
			'zip' => sanitize_text_field( $data['zip'] ?? '' ),
			'address_1' => sanitize_text_field( $data['address_1'] ?? '' ),
			'address_2' => sanitize_text_field( $data['address_2'] ?? '' ),
			'city' => sanitize_text_field( $data['city'] ?? '' ),
			'state' => sanitize_text_field( $data['state'] ?? '' ),
			'country' => sanitize_text_field( $data['country'] ?? '' ),
		];
	}
}
