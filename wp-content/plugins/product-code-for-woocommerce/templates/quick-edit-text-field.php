<?php

/**
 * A template for the quick edit text field.
 *
 * @package PcfWooCommerce
 *
 * @var $c callable The function used to retrieve context values.
 */

?>
<div style="clear:both;"></div>
<label class="product_code">
	<span class="title"><?php esc_html_e('Product Code', 'product-code-for-woocommerce'); ?></span>
	<span class="input-text-wrap">
		<input type="text" 
			name="<?php echo esc_attr($field_name); ?>" class="text" 
			placeholder="<?php esc_attr_e('Product Code', 'product-code-for-woocommerce'); ?>" 
			value="<?php echo esc_attr($code); ?>" 
		/>
	</span>
</label>