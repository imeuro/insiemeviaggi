<?php
/**
 * Email Downloads.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/email-downloads.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.7.0
 */

defined( 'ABSPATH' ) || exit;

?><h2 class="woocommerce-order-downloads__title">I tuoi acquisti</h2>
<p><strong>N.B.</strong> I biglietti acquistati sono disponibili in allegato a questa email</p>
<p>Per tutte le indicazioni sull'utilizzo dei biglietti, consigliamo vivamente di
consultare la nostra pagina dedicata, <a href="https://www.insiemeviaggi.com/gardaland/faq-gardaland/">cliccando qui</a>.</p>


<?php if ($order->get_payment_method() !== 'bacs') { ?>

	<table class="td" cellspacing="0" cellpadding="6" style="width: 100%; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; margin-bottom: 40px;" border="1">
		<thead>
			<tr>
				<?php foreach ( $columns as $column_id => $column_name ) : ?>
					<th class="td text-align-left" scope="col"><?php 
					if (esc_html( $column_name ) == 'Scarica') {
						echo 'Cod. Biglietto';
					} else { echo esc_html( $column_name ); } ?></th>
				<?php endforeach; ?>
			</tr>
		</thead>

		<?php
		// remove duplicates from $downloads
		$unique_downloads = unique_multidim_array($downloads,'download_id');
		?>
		<?php foreach ( $unique_downloads as $download ) : ?>
			<tr>
				<?php foreach ( $columns as $column_id => $column_name ) : ?>
					<td class="td text-align-left">
						<?php
						if ( has_action( 'woocommerce_email_downloads_column_' . $column_id ) ) {
							do_action( 'woocommerce_email_downloads_column_' . $column_id, $download, $plain_text );
						} else {
							switch ( $column_id ) {
								case 'download-product':
									?>
									<!-- <a href="<?php // echo esc_url( get_permalink( $download['product_id'] ) ); ?>"><?php // echo wp_kses_post( $download['product_name'] ); ?></a> -->
									<?php echo wp_kses_post( $download['product_name'] ); ?>
									<?php
									break;
								case 'download-file':
									?>

									<?php echo esc_html( $download['download_name'] ); ?>

									<?php
									break;
								case 'download-expires':
									if ( ! empty( $download['access_expires'] ) ) {
										?>
										<time datetime="<?php echo esc_attr( date( 'Y-m-d', strtotime( $download['access_expires'] ) ) ); ?>" title="<?php echo esc_attr( strtotime( $download['access_expires'] ) ); ?>"><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $download['access_expires'] ) ) ); ?></time>
										<?php
									} else {
										//esc_html_e( 'Never', 'woocommerce' );
									}
									break;
							}
						}
						?>
					</td>
				<?php endforeach; ?>
			</tr>
		<?php endforeach; ?>
	</table>

<?php } // if ($order->get_payment_method() !== 'bacs') ?>

<p>Consulenza immediata e assistenza post acquisto tramite WhatsApp: 375 561 6651 o tramite e-mail all’indirizzo: <a href="mailto:booking@insiemeviaggi.com">booking@insiemeviaggi.com</a></p>
