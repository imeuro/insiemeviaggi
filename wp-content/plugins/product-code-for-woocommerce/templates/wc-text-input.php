<?php

/**
 * A template for a WooCommerce text input
 *
 * @package PcfWooCommerce
 *
 * @var $c callable The function used to retrieve context values.
 */

woocommerce_wp_text_input(['id' => $c('id'),'label' => $c('label'),'desc_tip' => $c('desc_tip'),'description' => $c('description'),'value' => $c('value'),]);
