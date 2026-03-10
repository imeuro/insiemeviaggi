<?php
/**
 * The template for displaying product content in the single-product.php template
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/content-single-product.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 3.6.0
 */

defined( 'ABSPATH' ) || exit;

global $product;

/**
 * Hook: woocommerce_before_single_product.
 *
 * @hooked woocommerce_output_all_notices - 10
 */
do_action( 'woocommerce_before_single_product' );

if ( post_password_required() ) {
	echo get_the_password_form(); // WPCS: XSS ok.
	return;
}
?>

<div class="e-con-inner">
	<?php 
	$pcats = $product->category_ids;
	// if ( in_array( 85, $pcats ) ) :
	// se è biglietti-gardaland...
	?>
		<!-- div class="ltc-product-intro">

			<b>ACCEDI AL LUOGO PIÙ MAGICO D’ITALIA IN POCHI SEMPLICI PASSAGGI:</b>
			<ul>
				<li>Inserisci nel carrello il n° di biglietti che desideri</li>
				<li>Se hai una convenzione applica il codice sconto a te dedicato</li>
				<li>Inserisci i dati necessari per la registrazione (i biglietti non sono nominativi).<br>
				Se usufruisci di una convenzione, i dati da inserire devono necessariamente essere del convenzionato.</li>
				<li>Prosegui con il pagamento tramite carta o bonifico (se il pagamento viene effettuato con bonifico, è necessario inviare copia a <a href="mailto:booking@insiemeviaggi.com">booking@insiemeviaggi.com</a>)</li>
				<li>I biglietti saranno inviati all'indirizzo mail inserito in fase di registrazione entro 1 ora dal ricevimento del pagamento con carta o entro 24 ore dal ricevimento del bonifico.</li>
			</ul>
			<p>Per qualsiasi informazione contattaci al numero: 02 3300 2117, su WhatsApp: 375 561 6651 o tramite e-mail: <a href="mailto:booking@insiemeviaggi.com">booking@insiemeviaggi.com</a></p>
		</div-->
	<?php // endif; ?>


	<div id="product-<?php the_ID(); ?>" <?php wc_product_class( '', $product ); ?>>

		<?php
		/**
		 * Hook: woocommerce_before_single_product_summary.
		 *
		 * @hooked woocommerce_show_product_sale_flash - 10
		 * @hooked woocommerce_show_product_images - 20
		 */
		do_action( 'woocommerce_before_single_product_summary' );
		?>

		<div class="summary entry-summary<?php if ( in_array( 85, $pcats ) ) : echo' gardaland'; endif;?>">
			<?php
			/**
			 * Hook: woocommerce_single_product_summary.
			 *
			 * @hooked woocommerce_template_single_title - 5
			 * @hooked woocommerce_template_single_rating - 10
			 * @hooked woocommerce_template_single_price - 10
			 * @hooked woocommerce_template_single_excerpt - 20
			 * @hooked woocommerce_template_single_add_to_cart - 30
			 * @hooked woocommerce_template_single_meta - 40
			 * @hooked woocommerce_template_single_sharing - 50
			 * @hooked WC_Structured_Data::generate_product_data() - 60
			 */
			do_action( 'woocommerce_single_product_summary' );
			?>
		</div>

	</div>

	<div class="ltc-product-related-description">

		<div class="ltc-product-description">
			<?php the_content(); ?>
		</div>

		<?php
		/**
		 * Hook: woocommerce_after_single_product_summary.
		 *
		 * @hooked woocommerce_output_product_data_tabs - 10
		 * @hooked woocommerce_upsell_display - 15
		 * @hooked woocommerce_output_related_products - 20
		 */
		do_action( 'woocommerce_after_single_product_summary' );
		?>


	</div>
</div>



<?php do_action( 'woocommerce_after_single_product' ); ?>
