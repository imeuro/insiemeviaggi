<?php
/**
 * Order Downloads.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/order/order-downloads.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 3.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="woocommerce-order-downloads">
	<?php if ( isset( $show_title ) ) : ?>
		<h2 class="woocommerce-order-downloads__title">I tuoi Acquisti</h2>
	<?php endif; ?>

	<p><b>Riceverai i biglietti acquistati via email all'indirizzo specificato in fase di registrazione.</b></p>
	<p>Consulenza immediata e assistenza post acquisto tramite WhatsApp: 375 561 6651 o tramite e-mail all’indirizzo: <a href="mailto:booking@insiemeviaggi.com">booking@insiemeviaggi.com</a></p>

</section>
