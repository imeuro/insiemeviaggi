<?php
/*
 * Plugin Name:        Ecommerce insiemeviaggi
 * Description:        Funzioni specifiche per l'ecommerce di insiemeviaggi: generazione biglietti, email con allegati, personalizzazioni frontend e backend.
 * Author:             Meuro
 * Version:            1.0.0
 * Author URI:         https://meuro.dev
 * License:            GPLv3 or later
 * License URI:        http://www.gnu.org/licenses/gpl-3.0.html
 * Requires PHP:       7.4
 * Requires Plugins:   woocommerce, product-code-for-woocommerce, viva-com-smart-for-woocommerce
 * Text Domain:        insiemeviaggi-ecommerce
 * Domain Path:        /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'IV_PLUGIN_DIR', dirname( __FILE__ ) . '/' );
define( 'IV_PLUGIN_URL', plugin_dir_url( __FILE__ ) );


/*****************************************
 * CONTROLLO DIPENDENZE
 *****************************************/

$iv_required_plugins = array(
	'woocommerce/woocommerce.php',
	'product-code-for-woocommerce/product-code-for-woocommerce.php',
	'viva-com-smart-for-woocommerce/wc-vivacom-smart.php',
);

$iv_active_plugins = apply_filters( 'active_plugins', get_option( 'active_plugins' ) );
$iv_missing        = array();

foreach ( $iv_required_plugins as $iv_plugin ) {
	if ( ! in_array( $iv_plugin, $iv_active_plugins, true ) ) {
		$iv_missing[] = $iv_plugin;
	}
}

if ( ! empty( $iv_missing ) ) {
	add_action( 'admin_notices', function () {
		?>
		<div class="error">
			<p>
				<?php
				echo esc_html__(
					'ATTENZIONE: Il plugin "Ecommerce insiemeviaggi" necessita che "WooCommerce", "Product Code for WooCommerce" e "Viva.com Smart Checkout" siano installati e attivati.',
					'insiemeviaggi-ecommerce'
				);
				?>
			</p>
		</div>
		<?php
	} );
}

if ( version_compare( PHP_VERSION, '7.4.0', '<' ) ) {
	add_action( 'admin_notices', function () {
		?>
		<div class="error">
			<p>
				<?php
				echo esc_html__(
					'Ecommerce insiemeviaggi richiede almeno PHP 7.4. Aggiorna la versione di PHP.',
					'insiemeviaggi-ecommerce'
				);
				?>
			</p>
		</div>
		<?php
	} );
}


/*****************************************
 * UTILITY
 *****************************************/

/**
 * Lingua italiana: gestita dal must-use plugin wp-content/mu-plugins/insiemeviaggi-locale-it.php
 * (si carica prima di tutti i plugin e forza it_IT per sito e WooCommerce).
 */

const iv_unique_multidim_array = 'iv_unique_multidim_array';
function iv_unique_multidim_array( $array, $key ) {
	$temp_array = array();
	$key_array  = array();
	$i          = 0;

	foreach ( $array as $val ) {
		if ( ! in_array( $val[ $key ], $key_array, true ) ) {
			$key_array[ $i ]  = $val[ $key ];
			$temp_array[ $i ] = $val;
		}
		$i++;
	}

	return $temp_array;
}

const iv_has_product_category_in_cart = 'iv_has_product_category_in_cart';
function iv_has_product_category_in_cart( $product_category ) {
	if ( ! WC()->cart ) {
		return false;
	}

	foreach ( WC()->cart->get_cart() as $cart_item ) {
		if ( has_term( $product_category, 'product_cat', $cart_item['product_id'] ) ) {
			return true;
		}
	}

	return false;
}

const iv_starts_with = 'iv_starts_with';
function iv_starts_with( $string, $start_string ) {
	return ( substr( $string, 0, strlen( $start_string ) ) === $start_string );
}


/*****************************************
 * RETROCOMPATIBILITA' CON PLUGIN LEGACY (ltc-custom)
 * Alias delle vecchie funzioni globali usate nei template WooCommerce del tema.
 * Rimuovere quando tutti i template saranno aggiornati al prefisso iv_.
 *****************************************/

if ( ! function_exists( 'unique_multidim_array' ) ) {
	function unique_multidim_array( $array, $key ) {
		return iv_unique_multidim_array( $array, $key );
	}
}

if ( ! function_exists( 'has_product_category_in_cart' ) ) {
	function has_product_category_in_cart( $product_category ) {
		return iv_has_product_category_in_cart( $product_category );
	}
}

if ( ! function_exists( 'startsWith' ) ) {
	function startsWith( $string, $start_string ) {
		return iv_starts_with( $string, $start_string );
	}
}


/*****************************************
 * TICKETS (generazione biglietti)
 *****************************************/

include IV_PLUGIN_DIR . 'inc/iv-tickets.php';


/*****************************************
 * EMAIL (allegati, BCC, gate)
 *****************************************/

include IV_PLUGIN_DIR . 'inc/iv-email.php';


/*****************************************
 * FRONTEND
 *****************************************/

include IV_PLUGIN_DIR . 'inc/iv-frontend.php';


/*****************************************
 * BACKEND
 *****************************************/

include IV_PLUGIN_DIR . 'inc/iv-export-data.php';
include IV_PLUGIN_DIR . 'inc/iv-backend.php';


/*****************************************
 * ASSETS
 *****************************************/

add_action( 'wp_enqueue_scripts', 'iv_load_scripts', 10 );
function iv_load_scripts() {
	$js_file  = IV_PLUGIN_DIR . 'assets/iv-custom.js';
	$css_file = IV_PLUGIN_DIR . 'assets/iv-custom.css';

	if ( file_exists( $js_file ) ) {
		$js_ver = date( 'ymd-Gis', filemtime( $js_file ) );
		wp_enqueue_script( 'iv-custom-js', IV_PLUGIN_URL . 'assets/iv-custom.js', array(), $js_ver );
	}

	if ( file_exists( $css_file ) ) {
		wp_enqueue_style( 'iv-custom-css', IV_PLUGIN_URL . 'assets/iv-custom.css' );
	}
}


/*****************************************
 * WOOCOMMERCE BLOCK STYLES
 *****************************************/

add_action( 'wp_enqueue_scripts', 'iv_reenqueue_woocommerce_block_styles', 1000 );
function iv_reenqueue_woocommerce_block_styles() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	if ( ! is_woocommerce() && ! is_cart() && ! is_checkout() && ! is_account_page() ) {
		return;
	}

	wp_enqueue_style( 'wc-block-style' );
	wp_enqueue_style( 'wc-blocks-style' );
}


/*****************************************
 * ELEMENTOR OVERRIDES
 *****************************************/

add_filter( 'elementor/theme/need_override_location', 'iv_disable_elementor_single_override_on_404_and_products', 999, 2 );
function iv_disable_elementor_single_override_on_404_and_products( $need_override_location, $location ) {
	if ( 'single' !== $location ) {
		return $need_override_location;
	}

	if (
		is_404()
		|| (
			function_exists( 'is_woocommerce' )
			&& (
				is_woocommerce()
				|| is_shop()
				|| is_product_taxonomy()
				|| is_cart()
				|| is_checkout()
				|| is_account_page()
			)
		)
	) {
		return false;
	}

	return $need_override_location;
}

add_action( 'init', 'iv_normalize_elementor_local_fonts_paths', 5 );
function iv_normalize_elementor_local_fonts_paths() {
	$upload_dir = wp_upload_dir();

	if ( empty( $upload_dir['basedir'] ) || empty( $upload_dir['baseurl'] ) ) {
		return;
	}

	$fonts_baseurl = trailingslashit( $upload_dir['baseurl'] ) . 'elementor/google-fonts/fonts/';
	$css_baseurl   = trailingslashit( $upload_dir['baseurl'] ) . 'elementor/google-fonts/css/';
	$css_dir       = trailingslashit( $upload_dir['basedir'] ) . 'elementor/google-fonts/css/';

	if ( ! is_dir( $css_dir ) ) {
		return;
	}

	$last_baseurl = get_option( 'iv_elementor_fonts_baseurl', '' );
	if ( $last_baseurl === $upload_dir['baseurl'] ) {
		return;
	}

	$css_files = glob( $css_dir . '*.css' );
	if ( empty( $css_files ) ) {
		update_option( 'iv_elementor_fonts_baseurl', $upload_dir['baseurl'] );
		return;
	}

	foreach ( $css_files as $css_file ) {
		$css_content = file_get_contents( $css_file );
		if ( false === $css_content ) {
			continue;
		}

		$updated_css = preg_replace(
			'~https?://[^/]+/wp-content/uploads/elementor/google-fonts/fonts/~',
			$fonts_baseurl,
			$css_content
		);

		if ( null !== $updated_css && $updated_css !== $css_content ) {
			file_put_contents( $css_file, $updated_css );
		}
	}

	$local_fonts = (array) get_option( '_elementor_local_google_fonts', array() );
	if ( ! empty( $local_fonts ) ) {
		foreach ( $local_fonts as $font_name => $font_data ) {
			$local_fonts[ $font_name ] = array_merge(
				(array) $font_data,
				array( 'url' => $css_baseurl . sanitize_key( $font_name ) . '.css' )
			);
		}
		update_option( '_elementor_local_google_fonts', $local_fonts );
	}

	update_option( 'iv_elementor_fonts_baseurl', $upload_dir['baseurl'] );
}
