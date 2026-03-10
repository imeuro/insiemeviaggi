<?php 
// [ BACKEND ]
// export utenti coi campi che ci servono a noi

// Set default for CLI environment
if (!isset($_SERVER['SERVER_NAME'])) {
    $_SERVER['SERVER_NAME'] = 'default';
}

if ($_SERVER['SERVER_NAME'] == 'www.insiemeviaggi.com') :
    $ABSURL = 'https://www.insiemeviaggi.com/';
    $ABSPATH = '/home/customer/www/insiemeviaggi.com/public_html/';
elseif ($_SERVER['SERVER_NAME'] == 'meuro.dev') :
    $ABSURL = 'https://meuro.dev/insiemeviaggi/';
    $ABSPATH = '/home/meuro/www_root/insiemeviaggi/';
elseif ($_SERVER['SERVER_NAME'] == 'localhost') :
    $ABSURL = 'https://localhost/insiemeviaggi/';
    $ABSPATH = '/var/www/html/insiemeviaggi/';
else :
    // Default values based on current environment
    $ABSURL = 'https://www.insiemeviaggi.com/';
    $ABSPATH = '/var/www/html/insiemeviaggi/';
endif;
// * API prod (readonly)
$ck='ck_949470a85574c84b7a3cc662ca8f58cd7c7b3679';
$cs='cs_faf8293e8b36f6e0b41d49db552a5057a061d9f8';
$api_url = $ABSURL . 'wp-json/wc/v3/customers?consumer_key='.$ck.'&consumer_secret='.$cs.'&orderby=id&order=desc&per_page=100';
$json_filename = $ABSPATH . 'wp-content/uploads/customer-data.json';
$csv_filename = $ABSPATH . 'wp-content/uploads/customer-data.csv';
$csv_url =  $ABSURL . 'wp-content/uploads/customer-data.csv';

// Numero di clienti da mostrare nella preview (più recenti)
define('LTC_CSV_PREVIEW_LIMIT', 20);

// retrieves all clients, paginated (100 per page)

function retrieveAPIdata($endpoint,$force_regenerate) {
	global $json_filename, $csv_filename, $csv_url, $ABSPATH;
	if (( isset($_GET['regenerate_csv']) && $_GET['regenerate_csv'] == 'true' ) || $force_regenerate === true) {
		$responseBody='';
		$page = 1;
		while (true) {
			$request_url = $endpoint.'&page='.$page;
			$args = array();
			if (isset($_SERVER['SERVER_NAME']) && ($_SERVER['SERVER_NAME'] == 'localhost' || strpos($_SERVER['SERVER_NAME'], 'localhost') !== false)) {
				$args['sslverify'] = false;
			}
			$response = wp_remote_get($request_url, $args);
			if (is_wp_error($response)) {
				break;
			}
			$responseData = wp_remote_retrieve_body($response);
			if ($responseData == "[]" || $responseData === '') {
				break;
			}
			$responseBody .= $responseData;
			$responseBody = str_replace("][",",",$responseBody);
			$page++;
		}
		file_put_contents($json_filename, $responseBody);
	}
	//print_r($responseBody);
}

function ltc_get_longform_headers() {
	$HeadersList = [];
	$loop = new WP_Query( array(
		'post_type'         => 'shop_order',
		'post_status'       =>  array_keys( wc_get_order_statuses() ),
		'posts_per_page'    => -1,
		'meta_key' 			=> '_Order_Flag',
		'meta_value' 		=> 'longform',
	) );

	if ( $loop->have_posts() ): 
		while ( $loop->have_posts() ) : 
			$loop->the_post();
			$order = wc_get_order($loop->post->ID);
			foreach( $order->get_meta_data() as $meta_data_obj ) {
				$meta_data_array = $meta_data_obj->get_data();
				$meta_key = $meta_data_array['key'];
				if ( startsWith($meta_key,'vacanzestudio_') ) {
					$HeadersList[] = $meta_data_array['key'];
				}
			}
		endwhile;
		wp_reset_postdata();
	endif;

	return array_unique($HeadersList);
}

function ltc_prepare_customer_row($row, $HeadersList) {
	// don't need this data:
	if (isset($row['username'])) unset($row['username']);
	if (isset($row['date_created'])) unset($row['date_created']);
	if (isset($row['date_modified'])) unset($row['date_modified']);
	if (isset($row['date_created_gmt'])) unset($row['date_created_gmt']);
	if (isset($row['date_modified_gmt'])) unset($row['date_modified_gmt']);
	if (isset($row['role'])) unset($row['role']);
	if (isset($row['shipping'])) unset($row['shipping']);
	if (isset($row['is_paying_customer'])) unset($row['is_paying_customer']);
	if (isset($row['avatar_url'])) unset($row['avatar_url']);
	if (isset($row['billing']['first_name'])) unset($row['billing']['first_name']);
	if (isset($row['billing']['last_name'])) unset($row['billing']['last_name']);
	if (isset($row['billing']['email'])) unset($row['billing']['email']);

	if (isset($row['meta_data'])) unset($row['meta_data']);
	if (isset($row['_links'])) unset($row['_links']);
	if (isset($row['collection'])) unset($row['collection']);

	if (isset($row['billing']) && is_array($row['billing'])) {
		foreach ($row['billing'] as $key => $billing) {
			$row['billing_'.$key] = $billing;
		}
		unset($row['billing']);
	}

    //rename headers:
	$row['ID'] = isset($row['id']) ? $row['id'] : '';
	if (isset($row['id'])) unset($row['id']);
	$row['MAIL'] = isset($row['email']) ? $row['email'] : '';
	if (isset($row['email'])) unset($row['email']);
	$row['COGNOME'] = isset($row['last_name']) ? $row['last_name'] : '';
	if (isset($row['last_name'])) unset($row['last_name']);
	$row['NOME'] = isset($row['first_name']) ? $row['first_name'] : '';
	if (isset($row['first_name'])) unset($row['first_name']);
	$row['SESSO'] = isset($row['sex']) ? $row['sex'] : '';
	if (isset($row['sex'])) {
		unset($row['sex']);
	}
	$billing_addr_1 = isset($row['billing_address_1']) ? $row['billing_address_1'] : '';
	$billing_addr_2 = isset($row['billing_address_2']) ? $row['billing_address_2'] : '';
	$row['INDIRIZZO'] = trim($billing_addr_1 . ' ' . $billing_addr_2);
	if (isset($row['billing_address_1'])) {
		unset($row['billing_address_1']);
	}
	if (isset($row['billing_address_2'])) {
		unset($row['billing_address_2']);
	}
	$row['CAP'] = isset($row['billing_postcode']) ? $row['billing_postcode'] : '';
	if (isset($row['billing_postcode'])) {
		unset($row['billing_postcode']);
	}
	$row['COMUNE'] = isset($row['billing_city']) ? $row['billing_city'] : '';
	if (isset($row['billing_city'])) {
		unset($row['billing_city']);
	}
	$row['PROVINCIA'] = isset($row['billing_state']) ? $row['billing_state'] : '';
	if (isset($row['billing_state'])) {
		unset($row['billing_state']);
	}
	$row['STATO'] = isset($row['billing_country']) ? $row['billing_country'] : '';
	if (isset($row['billing_country'])) {
		unset($row['billing_country']);
	}
	$row['TELEFONO'] = isset($row['billing_phone']) ? $row['billing_phone'] : '';
	if (isset($row['billing_phone'])) {
		unset($row['billing_phone']);
	}
	$row['CODICE FISCALE'] = isset($row['billing_company']) ? strtoupper($row['billing_company']) : '';
	if (isset($row['billing_company'])) {
		unset($row['billing_company']);
	}
	$row['DATA DI NASCITA'] = isset($row['birth_date']) ? $row['birth_date'] : '';
	if (isset($row['birth_date'])) {
		unset($row['birth_date']);
	}

	// Get all customer orders
	$customer_orders = get_posts(array(
		'numberposts' => -1,
		'meta_key' => '_customer_user',
		'meta_value' => $row['ID'],
		'post_type' => 'shop_order',
		'post_status'    => 'completed',
		'no_found_rows' => true,
		'update_post_meta_cache' => false,
		'update_post_term_cache' => false
    ));
    $orders_count = count($customer_orders);
    $coupons = [];
    $questionario = [];

	foreach($customer_orders as $order_post) :
		$order = wc_get_order( $order_post->ID );
		if (!$order) {
			continue;
		}

		// coupons
		if( $order->get_used_coupons() ) {
			foreach( $order->get_used_coupons() as $coupon) {
			    $coupons[] = $coupon;
			}
		}
		// additional data
		if ( sizeof( $order->get_items() ) > 0 ) {
			foreach( $order->get_items() as $item ) {						
				$fullmeta = get_post_meta( $order->get_id());
				$questionario = array_filter($fullmeta, function($key) {
					return strpos($key, 'vacanzestudio_') === 0;
				}, ARRAY_FILTER_USE_KEY);
			}
		}
		// Free memory
		unset($order);
		wp_cache_delete($order_post->ID, 'posts');
		wp_cache_delete($order_post->ID, 'post_meta');
	endforeach;
	
	// Free memory
	wp_reset_postdata();
	unset($customer_orders);

	$unique_coupons = array_unique($coupons);
	$used_coupons = ' — ';
	if(!empty($unique_coupons)) {
		$used_coupons = '';
		foreach($unique_coupons as $unique_coupon) :
			$used_coupons .= $unique_coupon.' ';
		endforeach;				
	}

	$row['CODICE SCONTO'] = $used_coupons;
	$row['ACQUISTI'] = $orders_count;

	// comunque creo le colonne per i dati del longform
	foreach ($HeadersList as $heading) {
		$humanheading = strtoupper(str_replace('_',' ',$heading));
		$row[$humanheading] = ' - ';
	}
	// poi se sono valorizzate, sovrascrivo il ' - ' con il valore
	if(!empty($questionario)) {
		foreach($questionario as $key => $value) :
			$humankey = strtoupper(str_replace('_',' ',$key));
			$qval = $value[0];
			$row[$humankey] = $qval;
		endforeach;	
	}

	$row['NOTE'] = isset($row['customer_notes']) ? $row['customer_notes'] : '';
	if (isset($row['customer_notes'])) {
		unset($row['customer_notes']);
	}

	return $row;
}

function ltc_generate_csv_from_api($endpoint, $cfilename, $force_regenerate) {
	// Verifica che l'utente sia un amministratore prima di generare il CSV
	// Permetti l'esecuzione da cron job (quando non c'è utente loggato ma force_regenerate è true)
	$is_cron_job = ( $force_regenerate === true && ! is_user_logged_in() );
	
	if ( ! $is_cron_job && ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Non hai i permessi per eseguire questa operazione.', 'woocommerce' ) );
	}
	
	if ( ! ( ( isset($_GET['regenerate_csv']) && $_GET['regenerate_csv'] == 'true' ) || $force_regenerate === true ) ) {
		return;
	}

	// Increase memory limit for large exports
	@ini_set('memory_limit', '512M');

	$HeadersList = ltc_get_longform_headers();
	$fp = fopen($cfilename, 'w');
	if ($fp === false) {
		return;
	}
	$header = false;
	$page = 1;

	while ( true ) {
		$request_url = $endpoint.'&page='.$page;
		$args = array();
		if (isset($_SERVER['SERVER_NAME']) && ($_SERVER['SERVER_NAME'] == 'localhost' || strpos($_SERVER['SERVER_NAME'], 'localhost') !== false)) {
			$args['sslverify'] = false;
		}
		$response = wp_remote_get($request_url, $args);
		if (is_wp_error($response)) {
			break;
		}
		$responseData = wp_remote_retrieve_body($response);
		if ($responseData == "[]" || $responseData === '') {
			break;
		}

		$data = json_decode($responseData, true);
		if ( empty($data) ) {
			break;
		}

		foreach ($data as $key => $row) {
			$row = ltc_prepare_customer_row($row, $HeadersList);

			if (empty($header)) {
			    $header = array_keys($row);
			    fputcsv($fp, $header);
			    $header = array_flip($header);
			}

		    fputcsv($fp, array_merge($header, $row));
		    
		    // Free memory after each customer
		    unset($row);
		}
		
		// Free memory after each page
		unset($data);
		unset($responseData);
		unset($response);
		$page++;
	}

	fclose($fp);
	return;
}

function jsonAPIToCSV($jfilename, $cfilename, $force_regenerate) {
    if (($json = file_get_contents($jfilename)) == false)
        die('Error reading json file from '.$jfilename.'...');

    if (( isset($_GET['regenerate_csv']) && $_GET['regenerate_csv'] == 'true' ) || $force_regenerate === true) {
		$data = json_decode($json, true);
		$fp = fopen($cfilename, 'w');
		$header = false;

		// qui devo ciclare, in tutti gli ordini del sito, i metadata e vedere quelli che cominciano con "vacanzestudio", in modo da poter predisporre una colonna per ogni campo del form
		// !! : forse posso fare query per ordini con uno specifico metadata che mi indicano presenza di "form lungo"

		$HeadersList = [];
		$loop = new WP_Query( array(
			'post_type'         => 'shop_order',
			'post_status'       =>  array_keys( wc_get_order_statuses() ),
			'posts_per_page'    => -1,
			'meta_key' 			=> '_Order_Flag',
			'meta_value' 		=> 'longform',
		) );

		// The Wordpress post loop
		if ( $loop->have_posts() ): 
			while ( $loop->have_posts() ) : 
				$loop->the_post();

				// The order ID
				$order_id = $loop->post->ID;

				// Get an instance of the WC_Order Object
				$order = wc_get_order($loop->post->ID);

				foreach( $order->get_meta_data() as $meta_data_obj ) {
					$meta_data_array = $meta_data_obj->get_data();

					$meta_key   = $meta_data_array['key']; // The meta key
					$meta_value = $meta_data_array['value']; // The meta value
					if ( startsWith($meta_key,'vacanzestudio_') ) {
						$HeadersList[] = $meta_data_array['key'];
					}
				}

			endwhile;

		wp_reset_postdata();

		endif;


		// ...
		// mi tiro fuori un array ($HeadersList) , e poi passo a creare le $row (da riga ~115) così ottengo le intestazioni della colonna
		
		// echo "<pre>ooo";
		// print_r($HeadersList);
		// echo "</pre>";
		// die();


		foreach ($data as $key => $row) {


			// don't need this data:
			unset($row['username']);
			unset($row['date_created']);
			unset($row['date_modified']);
			unset($row['date_created_gmt']);
			unset($row['date_modified_gmt']);
			unset($row['role']);
			unset($row['shipping']);
			unset($row['is_paying_customer']);
			unset($row['avatar_url']);
			unset($row['billing']['first_name']);
			unset($row['billing']['last_name']);
			unset($row['billing']['email']);

			unset($row['meta_data']);
			unset($row['role']);
			unset($row['_links']);
			unset($row['collection']);

			foreach ($row['billing'] as $key => $billing) {
				$row['billing_'.$key] = $billing;
			}
			unset($row['billing']);

		    //rename headers:
			$row['ID'] = $row['id'];
			unset($row['id']);
			$row['MAIL'] = $row['email'];
			unset($row['email']);
			$row['COGNOME'] = $row['last_name'];
			unset($row['last_name']);
			$row['NOME'] = $row['first_name'];
			unset($row['first_name']);
			$row['SESSO'] = $row['sex'];
			unset($row['sex']);
			$row['INDIRIZZO'] = $row['billing_address_1'].' '.$row['billing_address_2'];
			unset($row['billing_address_1']);
			unset($row['billing_address_2']);
			$row['CAP'] = $row['billing_postcode'];
			unset($row['billing_postcode']);
			$row['COMUNE'] = $row['billing_city'];
			unset($row['billing_city']);
			$row['PROVINCIA'] = $row['billing_state'];
			unset($row['billing_state']);
			$row['STATO'] = $row['billing_country'];
			unset($row['billing_country']);
			$row['TELEFONO'] = $row['billing_phone'];
			unset($row['billing_phone']);
			$row['CODICE FISCALE'] = strtoupper($row['billing_company']);
			unset($row['billing_company']);
			$row['DATA DI NASCITA'] = $row['birth_date'];
			unset($row['birth_date']);

			// Get all customer orders
			$customer_orders = get_posts(array(
				'numberposts' => -1,
				'meta_key' => '_customer_user',
				'meta_value' => $row['ID'],
				'post_type' => 'shop_order',
				'post_status'    => 'completed'
		    ));
		    $orders_count = count($customer_orders);
		    $coupons = [];
		    $questionario = [];

			foreach($customer_orders as $order) :
				$orderID = $order->ID;
				$order = wc_get_order( $orderID );

				// coupons
				if( $order->get_used_coupons() ) {
					$coupons_count = count( $order->get_used_coupons() );
					$i = 1;
					foreach( $order->get_used_coupons() as $coupon) {
					    $coupons[] = $coupon;
					}
				}
				// additional data
				if ( sizeof( $order->get_items() ) > 0 ) {
					foreach( $order->get_items() as $item ) {						

						$fullmeta = get_post_meta( $order->get_id());
						$questionario = array_filter($fullmeta, function($key) {
							return strpos($key, 'vacanzestudio_') === 0;
						}, ARRAY_FILTER_USE_KEY);

					}
				}
			endforeach;

			$unique_coupons = array_unique($coupons);
			$used_coupons = ' — ';
			if(!empty($unique_coupons)) {
				$used_coupons = '';
				foreach($unique_coupons as $unique_coupon) :
					$used_coupons .= $unique_coupon.' ';
				endforeach;				
			}

			$row['CODICE SCONTO'] = $used_coupons;

			$row['ACQUISTI'] = $orders_count;

			// comunque creo le colonne per i dati del longform
			foreach ($HeadersList as $heading) {
				$humanheading = strtoupper(str_replace('_',' ',$heading));
				$row[$humanheading] = ' - ';
			}
			$qval = ' - ';
			// poi se sono valorizzate, sovrascrivo il ' - ' con il valore
			if(!empty($questionario)) {
				$questionarii = '';
				foreach($questionario as $key => $value) :
					$humankey = strtoupper(str_replace('_',' ',$key));
					$qval = $value[0];
					$row[$humankey] = $qval;
				endforeach;	
			}
			// unset($row[$key]);



			// ste stronze non ne vogliono sapere proprio... 
			$row['NOTE'] = $row['customer_notes'];
			unset($row['customer_notes']);


			// echo '$header: '.$header;
			if (empty($header)) {
			    $header = array_keys($row);
			    fputcsv($fp, $header);
			    $header = array_flip($header);
			    // echo '<pre>qqq';
			    // print_r($header);
			    // echo '</pre>';
			}
		    
		    fputcsv($fp, array_merge($header, $row));
		}

		fclose($fp);
	}
    return;
} 

function csvPreview($cfilename, $csv_url = '', $preview_limit = null) {
	if ($preview_limit === null) {
		$preview_limit = defined('LTC_CSV_PREVIEW_LIMIT') ? LTC_CSV_PREVIEW_LIMIT : 20;
	}
	if (($handle = fopen($cfilename, "r")) !== FALSE) {
		// Leggi tutte le righe
		$all_rows = array();
		while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
			$all_rows[] = $data;
		}
		fclose($handle);
		
		$total_rows = count($all_rows);
		
		// Il CSV è ordinato per ID decrescente (più recenti prima)
		// La prima riga è l'header, le successive sono i clienti più recenti
		// Mostra header + primi N clienti (più recenti)
		$rows_to_show = array();
		if ($total_rows > 0) {
			// Aggiungi header (prima riga)
			$rows_to_show[] = $all_rows[0];
			// Aggiungi i primi N clienti (più recenti)
			$data_rows = array_slice($all_rows, 1, $preview_limit);
			$rows_to_show = array_merge($rows_to_show, $data_rows);
		}
		
		echo '<div id="ltc_table_container"><table id="ltc_table" cellspacing="0" cellpadding="0">';
		echo '<tbody>';
		
		$row = 0;
		foreach ($rows_to_show as $data) {
			$num = count($data);
			echo "<tr>\n";
			for ($c=0; $c < $num; $c++) {
			    echo ($row==0) ? "<th>" : "<td>";
			    echo htmlspecialchars($data[$c], ENT_QUOTES, 'UTF-8');
			    echo ($c==0) ? "</th>\n" : "</td>\n";
			}
			echo "</tr>\n";
			$row++;
		}
		
		echo "</tbody></table></div>\n\n";
		
		// Messaggio se ci sono più clienti del limite
		$total_customers = $total_rows - 1; // Escludi l'header
		if ($total_customers > $preview_limit) {
			$csv_link = '<button type="button" onclick="GETcsv()" class="button button-large button-primary dashicons-before dashicons-download">&nbsp;&nbsp;Download Lista Clienti (CSV)</button>';
			echo '<p class="ltc_csv_preview_notice"><strong>Mostro gli ultimi ' . $preview_limit . ' clienti (totale: ' . $total_customers . ').</strong><br /><br /> ' . $csv_link . '</p>';
		}
	}
}
?>