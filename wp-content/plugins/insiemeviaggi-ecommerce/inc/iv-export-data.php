<?php
// [ BACKEND ]
// export utenti coi campi che ci servono a noi

defined( 'ABSPATH' ) || exit;

if ( ! isset( $_SERVER['SERVER_NAME'] ) ) {
	$_SERVER['SERVER_NAME'] = 'default';
}

if ( 'www.insiemeviaggi.com' === $_SERVER['SERVER_NAME'] ) :
	$ABSURL  = 'https://www.insiemeviaggi.com/';
	$ABSPATH = '/home/customer/www/insiemeviaggi.com/public_html/';
elseif ( 'meuro.dev' === $_SERVER['SERVER_NAME'] ) :
	$ABSURL  = 'https://meuro.dev/insiemeviaggi/';
	$ABSPATH = '/home/meuro/www_root/insiemeviaggi/';
elseif ( 'localhost' === $_SERVER['SERVER_NAME'] ) :
	$ABSURL  = 'https://localhost/insiemeviaggi/';
	$ABSPATH = '/var/www/html/insiemeviaggi/';
else :
	$ABSURL  = 'https://www.insiemeviaggi.com/';
	$ABSPATH = '/var/www/html/insiemeviaggi/';
endif;

$ck           = 'ck_949470a85574c84b7a3cc662ca8f58cd7c7b3679';
$cs           = 'cs_faf8293e8b36f6e0b41d49db552a5057a061d9f8';
$api_url      = $ABSURL . 'wp-json/wc/v3/customers?consumer_key=' . $ck . '&consumer_secret=' . $cs . '&orderby=id&order=desc&per_page=100';
$json_filename = $ABSPATH . 'wp-content/uploads/customer-data.json';
$csv_filename  = $ABSPATH . 'wp-content/uploads/customer-data.csv';
$csv_url       = $ABSURL . 'wp-content/uploads/customer-data.csv';

define( 'IV_CSV_PREVIEW_LIMIT', 20 );


function iv_get_longform_headers() {
	$headers_list = array();
	$loop         = new WP_Query( array(
		'post_type'      => 'shop_order',
		'post_status'    => array_keys( wc_get_order_statuses() ),
		'posts_per_page' => -1,
		'meta_key'       => '_Order_Flag',
		'meta_value'     => 'longform',
	) );

	if ( $loop->have_posts() ) :
		while ( $loop->have_posts() ) :
			$loop->the_post();
			$order = wc_get_order( $loop->post->ID );
			foreach ( $order->get_meta_data() as $meta_data_obj ) {
				$meta_data_array = $meta_data_obj->get_data();
				$meta_key        = $meta_data_array['key'];
				if ( iv_starts_with( $meta_key, 'vacanzestudio_' ) ) {
					$headers_list[] = $meta_data_array['key'];
				}
			}
		endwhile;
		wp_reset_postdata();
	endif;

	return array_unique( $headers_list );
}

function iv_prepare_customer_row( $row, $headers_list ) {
	$unset_keys = array(
		'username', 'date_created', 'date_modified',
		'date_created_gmt', 'date_modified_gmt',
		'role', 'shipping', 'is_paying_customer',
		'avatar_url', 'meta_data', '_links', 'collection',
	);
	foreach ( $unset_keys as $uk ) {
		if ( isset( $row[ $uk ] ) ) {
			unset( $row[ $uk ] );
		}
	}

	if ( isset( $row['billing']['first_name'] ) ) unset( $row['billing']['first_name'] );
	if ( isset( $row['billing']['last_name'] ) ) unset( $row['billing']['last_name'] );
	if ( isset( $row['billing']['email'] ) ) unset( $row['billing']['email'] );

	if ( isset( $row['billing'] ) && is_array( $row['billing'] ) ) {
		foreach ( $row['billing'] as $key => $billing ) {
			$row[ 'billing_' . $key ] = $billing;
		}
		unset( $row['billing'] );
	}

	$row['ID']   = isset( $row['id'] ) ? $row['id'] : '';
	if ( isset( $row['id'] ) ) unset( $row['id'] );
	$row['MAIL'] = isset( $row['email'] ) ? $row['email'] : '';
	if ( isset( $row['email'] ) ) unset( $row['email'] );
	$row['COGNOME'] = isset( $row['last_name'] ) ? $row['last_name'] : '';
	if ( isset( $row['last_name'] ) ) unset( $row['last_name'] );
	$row['NOME'] = isset( $row['first_name'] ) ? $row['first_name'] : '';
	if ( isset( $row['first_name'] ) ) unset( $row['first_name'] );
	$row['SESSO'] = isset( $row['sex'] ) ? $row['sex'] : '';
	if ( isset( $row['sex'] ) ) unset( $row['sex'] );

	$billing_addr_1    = isset( $row['billing_address_1'] ) ? $row['billing_address_1'] : '';
	$billing_addr_2    = isset( $row['billing_address_2'] ) ? $row['billing_address_2'] : '';
	$row['INDIRIZZO']  = trim( $billing_addr_1 . ' ' . $billing_addr_2 );
	if ( isset( $row['billing_address_1'] ) ) unset( $row['billing_address_1'] );
	if ( isset( $row['billing_address_2'] ) ) unset( $row['billing_address_2'] );

	$row['CAP']       = isset( $row['billing_postcode'] ) ? $row['billing_postcode'] : '';
	if ( isset( $row['billing_postcode'] ) ) unset( $row['billing_postcode'] );
	$row['COMUNE']    = isset( $row['billing_city'] ) ? $row['billing_city'] : '';
	if ( isset( $row['billing_city'] ) ) unset( $row['billing_city'] );
	$row['PROVINCIA'] = isset( $row['billing_state'] ) ? $row['billing_state'] : '';
	if ( isset( $row['billing_state'] ) ) unset( $row['billing_state'] );
	$row['STATO']     = isset( $row['billing_country'] ) ? $row['billing_country'] : '';
	if ( isset( $row['billing_country'] ) ) unset( $row['billing_country'] );
	$row['TELEFONO']  = isset( $row['billing_phone'] ) ? $row['billing_phone'] : '';
	if ( isset( $row['billing_phone'] ) ) unset( $row['billing_phone'] );

	$row['CODICE FISCALE']  = isset( $row['billing_company'] ) ? strtoupper( $row['billing_company'] ) : '';
	if ( isset( $row['billing_company'] ) ) unset( $row['billing_company'] );
	$row['DATA DI NASCITA'] = isset( $row['birth_date'] ) ? $row['birth_date'] : '';
	if ( isset( $row['birth_date'] ) ) unset( $row['birth_date'] );

	$customer_orders = get_posts( array(
		'numberposts'            => -1,
		'meta_key'               => '_customer_user',
		'meta_value'             => $row['ID'],
		'post_type'              => 'shop_order',
		'post_status'            => 'completed',
		'no_found_rows'          => true,
		'update_post_meta_cache' => false,
		'update_post_term_cache' => false,
	) );
	$orders_count  = count( $customer_orders );
	$coupons       = array();
	$questionario  = array();

	foreach ( $customer_orders as $order_post ) :
		$order = wc_get_order( $order_post->ID );
		if ( ! $order ) {
			continue;
		}

		if ( $order->get_used_coupons() ) {
			foreach ( $order->get_used_coupons() as $coupon ) {
				$coupons[] = $coupon;
			}
		}

		if ( sizeof( $order->get_items() ) > 0 ) {
			foreach ( $order->get_items() as $item ) {
				$fullmeta     = get_post_meta( $order->get_id() );
				$questionario = array_filter( $fullmeta, function ( $key ) {
					return strpos( $key, 'vacanzestudio_' ) === 0;
				}, ARRAY_FILTER_USE_KEY );
			}
		}

		unset( $order );
		wp_cache_delete( $order_post->ID, 'posts' );
		wp_cache_delete( $order_post->ID, 'post_meta' );
	endforeach;

	wp_reset_postdata();
	unset( $customer_orders );

	$unique_coupons = array_unique( $coupons );
	$used_coupons   = ' — ';
	if ( ! empty( $unique_coupons ) ) {
		$used_coupons = '';
		foreach ( $unique_coupons as $unique_coupon ) :
			$used_coupons .= $unique_coupon . ' ';
		endforeach;
	}

	$row['CODICE SCONTO'] = $used_coupons;
	$row['ACQUISTI']      = $orders_count;

	foreach ( $headers_list as $heading ) {
		$humanheading          = strtoupper( str_replace( '_', ' ', $heading ) );
		$row[ $humanheading ] = ' - ';
	}

	if ( ! empty( $questionario ) ) {
		foreach ( $questionario as $key => $value ) :
			$humankey          = strtoupper( str_replace( '_', ' ', $key ) );
			$row[ $humankey ] = $value[0];
		endforeach;
	}

	$row['NOTE'] = isset( $row['customer_notes'] ) ? $row['customer_notes'] : '';
	if ( isset( $row['customer_notes'] ) ) unset( $row['customer_notes'] );

	return $row;
}

function iv_generate_csv_from_api( $endpoint, $cfilename, $force_regenerate ) {
	$is_cron_job = ( true === $force_regenerate && ! is_user_logged_in() );

	if ( ! $is_cron_job && ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Non hai i permessi per eseguire questa operazione.', 'woocommerce' ) );
	}

	if ( ! ( ( isset( $_GET['regenerate_csv'] ) && 'true' === $_GET['regenerate_csv'] ) || true === $force_regenerate ) ) {
		return;
	}

	@ini_set( 'memory_limit', '512M' );

	$headers_list = iv_get_longform_headers();
	$fp           = fopen( $cfilename, 'w' );
	if ( false === $fp ) {
		return;
	}
	$header = false;
	$page   = 1;

	while ( true ) {
		$request_url = $endpoint . '&page=' . $page;
		$args        = array();
		if ( isset( $_SERVER['SERVER_NAME'] ) && ( 'localhost' === $_SERVER['SERVER_NAME'] || false !== strpos( $_SERVER['SERVER_NAME'], 'localhost' ) ) ) {
			$args['sslverify'] = false;
		}
		$response = wp_remote_get( $request_url, $args );
		if ( is_wp_error( $response ) ) {
			break;
		}
		$response_data = wp_remote_retrieve_body( $response );
		if ( '[]' === $response_data || '' === $response_data ) {
			break;
		}

		$data = json_decode( $response_data, true );
		if ( empty( $data ) ) {
			break;
		}

		foreach ( $data as $key => $row ) {
			$row = iv_prepare_customer_row( $row, $headers_list );

			if ( empty( $header ) ) {
				$header = array_keys( $row );
				fputcsv( $fp, $header );
				$header = array_flip( $header );
			}

			fputcsv( $fp, array_merge( $header, $row ) );
			unset( $row );
		}

		unset( $data, $response_data, $response );
		$page++;
	}

	fclose( $fp );
}

function iv_csv_preview( $cfilename, $csv_url_param = '', $preview_limit = null ) {
	if ( null === $preview_limit ) {
		$preview_limit = defined( 'IV_CSV_PREVIEW_LIMIT' ) ? IV_CSV_PREVIEW_LIMIT : 20;
	}
	if ( ( $handle = fopen( $cfilename, 'r' ) ) !== false ) {
		$all_rows = array();
		while ( ( $data = fgetcsv( $handle, 1000, ',' ) ) !== false ) {
			$all_rows[] = $data;
		}
		fclose( $handle );

		$total_rows   = count( $all_rows );
		$rows_to_show = array();
		if ( $total_rows > 0 ) {
			$rows_to_show[] = $all_rows[0];
			$data_rows      = array_slice( $all_rows, 1, $preview_limit );
			$rows_to_show   = array_merge( $rows_to_show, $data_rows );
		}

		echo '<div id="iv_table_container"><table id="iv_table" cellspacing="0" cellpadding="0">';
		echo '<tbody>';

		$row = 0;
		foreach ( $rows_to_show as $data ) {
			$num = count( $data );
			echo "<tr>\n";
			for ( $c = 0; $c < $num; $c++ ) {
				echo ( 0 === $row ) ? '<th>' : '<td>';
				echo htmlspecialchars( $data[ $c ], ENT_QUOTES, 'UTF-8' );
				echo ( 0 === $c ) ? "</th>\n" : "</td>\n";
			}
			echo "</tr>\n";
			$row++;
		}

		echo '</tbody></table></div>' . "\n\n";

		$total_customers = $total_rows - 1;
		if ( $total_customers > $preview_limit ) {
			$csv_link = '<button type="button" onclick="GETcsv()" class="button button-large button-primary dashicons-before dashicons-download">&nbsp;&nbsp;Download Lista Clienti (CSV)</button>';
			echo '<p class="iv_csv_preview_notice"><strong>Mostro gli ultimi ' . $preview_limit . ' clienti (totale: ' . $total_customers . ').</strong><br /><br /> ' . $csv_link . '</p>';
		}
	}
}
