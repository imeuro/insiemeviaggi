<?php
/*
* Plugin Name: 				LTC Ecommerce insiemeviaggi
* Description: 				Funzioni specifiche per l'ecommerce di insiemeviaggi (in precedenza agenziaviaggiLTC). 
* Author: 					Meuro
* Version: 					26.02
* Author URI: 				https://meuro.dev
* License: 					GPLv3 or later
* License URI:         		http://www.gnu.org/licenses/gpl-3.0.html
* Requires PHP: 	    	7.2
* Requires Plugins: 		woocommerce, product-code-for-woocommerce, viva-com-smart-for-woocommerce
* Text Domain: 				ecommerce-lts
* Domain Path: 				/languages
*/


// SECTIONS:
// [ OUTILS ]
// [ BOOKING ]
// [ EMAIL ]
// [ FRONTEND ]
// [ BACKEND ]



/*****************************************
 * OUTILS *
 *****************************************/

define( 'PLUGIN_DIR', dirname(__FILE__).'/' );

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.


// check for plugin using plugin name
if(
	in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))) && 
	in_array('product-code-for-woocommerce/product-code-for-woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))) &&
	in_array('viva-com-smart-for-woocommerce/wc-vivacom-smart.php', apply_filters('active_plugins', get_option('active_plugins')))

) { 
		//... you did good!
} else {
	function check_necessary_plugin_notice() {
	?>
	<div class="error">
		<p>
			<?php
			printf(
				esc_html__( 'ATTENZIONE: Il plugin "Ecommerce insiemeviaggi" necessita "Woocommerce", "Product code for Woocommerce", "Checkout Field Editor for WooCommerce", "Viva Wallet Standard Checkout" siano installati e attivati', 'ecommerce-ltc' )
			);
			?>
		</p>
	</div>
	<?php
	}
	add_action( 'admin_notices', 'check_necessary_plugin_notice' );
}

/**
 * Lingua italiana: gestita dal must-use plugin wp-content/mu-plugins/insiemeviaggi-locale-it.php
 * (si carica prima di tutti i plugin e forza it_IT per sito e WooCommerce).
 */

if ( version_compare( PHP_VERSION, '5.6.0', '<' ) ) {
	function check_plugin_compatibility_notice() {
		?>
		<div class="error">
			<p>
				<?php
				printf(
					esc_html__( 'Ecommerce insiemeviaggi requires at least PHP 5.6 to function properly. Please upgrade PHP.', 'ecommerce-lts' )
				);
				?>
			</p>
		</div>
		<?php
	}

	add_action( 'admin_notices', 'check_plugin_compatibility_notice' );
}



// Create multidimensional array unique for any single key index.
// stolen at: https://www.php.net/manual/en/function.array-unique.php#116302
function unique_multidim_array($array, $key) {
    $temp_array = array();
    $i = 0;
    $key_array = array();
   
    foreach($array as $val) {
        if (!in_array($val[$key], $key_array)) {
            $key_array[$i] = $val[$key];
            $temp_array[$i] = $val;
        }
        $i++;
    }
    return $temp_array;
}

// i try to give self explanatory function names :P
function has_product_category_in_cart( $product_category ) {
	//print_r('has_product_category_in_cart');
    foreach ( WC()->cart->get_cart() as $cart_item ) {
        // If any product category is found in cart items
        if ( has_term( $product_category, 'product_cat', $cart_item['product_id'] ) ) {
        	//print_r('true');
            return true;
        }
    }
    //print_r('false');
    return false;
}
// if string starts with
function startsWith ($string, $startString) {
    $len = strlen($startString);
    return (substr($string, 0, $len) === $startString);
}


/*****************************************
 * BOOKING RELATED *
 *****************************************/

include 'inc/ltc-booking.php';


/*****************************************
 * EMAIL RELATED *
 *****************************************/

include 'inc/ltc-email.php';


/*****************************************
 * FRONTEND ENHANCEMENTS *
 *****************************************/

include 'inc/ltc-frontend.php';


/*****************************************
 * BACKEND ENHANCEMENTS *
 *****************************************/

include 'inc/ltc-export-data.php';
include 'inc/ltc-backend.php';


function LTC_load_scripts($hook) {
	$LTC_js_ver  = date("ymd-Gis", filemtime( plugin_dir_path( __FILE__ ) . 'assets/ltc-custom.js' ));
	wp_enqueue_script( 'custom_js', plugins_url( 'assets/ltc-custom.js', __FILE__ ), array(), $LTC_js_ver );
	wp_enqueue_style('ltc-custom-css', plugins_url('assets/ltc-custom.css',__FILE__));
}
add_action('wp_enqueue_scripts', 'LTC_load_scripts', 10);

/**
 * Riattiva gli stili dei blocchi WooCommerce solo sulle pagine WooCommerce.
 */
function ltc_reenqueue_woocommerce_block_styles() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	if ( ! is_woocommerce() && ! is_cart() && ! is_checkout() && ! is_account_page() ) {
		return;
	}

	wp_enqueue_style( 'wc-block-style' );
	wp_enqueue_style( 'wc-blocks-style' );
}
add_action( 'wp_enqueue_scripts', 'ltc_reenqueue_woocommerce_block_styles', 1000 );

/**
 * Evita l'override del template Elementor Pro sui 404 e sui singoli prodotti.
 */
function ltc_disable_elementor_single_override_on_404_and_products( $need_override_location, $location ) {
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
add_filter( 'elementor/theme/need_override_location', 'ltc_disable_elementor_single_override_on_404_and_products', 999, 2 );

/**
 * Normalizza i path dei font locali Elementor per evitare CORS.
 */
function ltc_normalize_elementor_local_fonts_paths() {
	$upload_dir = wp_upload_dir();

	if ( empty( $upload_dir['basedir'] ) || empty( $upload_dir['baseurl'] ) ) {
		return;
	}

	$fonts_baseurl = trailingslashit( $upload_dir['baseurl'] ) . 'elementor/google-fonts/fonts/';
	$css_baseurl = trailingslashit( $upload_dir['baseurl'] ) . 'elementor/google-fonts/css/';
	$css_dir = trailingslashit( $upload_dir['basedir'] ) . 'elementor/google-fonts/css/';

	if ( ! is_dir( $css_dir ) ) {
		return;
	}

	$last_baseurl = get_option( 'ltc_elementor_fonts_baseurl', '' );
	if ( $last_baseurl === $upload_dir['baseurl'] ) {
		return;
	}

	$css_files = glob( $css_dir . '*.css' );
	if ( empty( $css_files ) ) {
		update_option( 'ltc_elementor_fonts_baseurl', $upload_dir['baseurl'] );
		return;
	}

	foreach ( $css_files as $css_file ) {
		$css_content = file_get_contents( $css_file );
		if ( $css_content === false ) {
			continue;
		}

		$updated_css = preg_replace(
			'~https?://[^/]+/wp-content/uploads/elementor/google-fonts/fonts/~',
			$fonts_baseurl,
			$css_content
		);

		if ( $updated_css !== null && $updated_css !== $css_content ) {
			file_put_contents( $css_file, $updated_css );
		}
	}

	$local_fonts = (array) get_option( '_elementor_local_google_fonts', [] );
	if ( ! empty( $local_fonts ) ) {
		foreach ( $local_fonts as $font_name => $font_data ) {
			$local_fonts[ $font_name ] = array_merge(
				(array) $font_data,
				[ 'url' => $css_baseurl . sanitize_key( $font_name ) . '.css' ]
			);
		}

		update_option( '_elementor_local_google_fonts', $local_fonts );
	}

	update_option( 'ltc_elementor_fonts_baseurl', $upload_dir['baseurl'] );
}
add_action( 'init', 'ltc_normalize_elementor_local_fonts_paths', 5 );