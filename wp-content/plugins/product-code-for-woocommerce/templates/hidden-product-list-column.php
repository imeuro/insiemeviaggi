<?php

/**
 * This is a template for the hidden column in the product list that contains the Product Code fields.
 *
 * @package PcfWooCommerce
 * 
 * @var $c callable The function used to retrieve context values.
 */

?>
<div class="hidden product_code" id="product_code_inline_<?php echo esc_attr( $c( 'post_id' ) ); ?>">
	<div id="product_codeddd"><?php echo esc_html( $c( 'product_code' ) ); ?></div>
</div>
