<?php

/**
 * A template for the variation field.
 *
 * @package PcfWooCommerce
 *
 * @var $c callable The function used to retrieve context values.
 */

?>
<div class="form-row form-row-first">

	<?php

	$label = $this->get_field_title_text();
	$label_second = $this->get_second_field_title_text();

	woocommerce_wp_text_input([
		'id' => sprintf('%s_%d', $field_name, $i),
		'label' => $label,
		'desc_tip' => true,
		'description' => sprintf(
			/* translators: 1 for label */
			__('%s refers to a company’s unique internal product identifier, needed for online product fulfillment.', 'product-code-for-woocommerce'),
			$label
		),
		'value' => $code,
	]);

	if ('yes' == $displaySecond) {
		woocommerce_wp_text_input([
			'id' => sprintf('%s_%d', $field_name_second, $i),
			'label' => $label_second,
			'desc_tip' => true,
			'description' => sprintf(
				/* translators: 1 for label */
				__('%s refers to a company’s unique internal product identifier, needed for online product fulfillment.', 'product-code-for-woocommerce'), 
				$label_second
			),
			'value' => $code_second,
		]);
	}

	?>
</div>
<div style="clear:both;"></div>