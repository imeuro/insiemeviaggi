<?php
/**
 * Intro HTML email "Ordine completato": default, override per prodotto/variazione, regole ordini misti.
 *
 * @package InsiemeViaggi_Ecommerce
 */

defined( 'ABSPATH' ) || exit;

/*****************************************
 * COSTANTI
 *****************************************/

const IV_COMPLETED_ORDER_INTRO_META_KEY = '_iv_completed_order_intro_html';

/*****************************************
 * TESTO DEFAULT (plain; wrappato via wpautop in output)
 *****************************************/

/**
 * Testo standard intro email ordine completato (senza tag HTML).
 *
 * @return string
 */
function iv_get_default_completed_order_intro_plain() {
	return 'Ti ringraziamo per averci scelto, rimaniamo a disposizione per ulteriori richieste e ti ricordiamo che oltre alla biglietteria parchi, siamo specializzati in soggiorni linguistici all’estero, siamo Tour Operator Irlanda e organizziamo pacchetti viaggio in tutto il mondo: www.insiemeviaggi.com';
}

/*****************************************
 * UTILITÀ
 *****************************************/

/**
 * True se il contenuto intro è considerato vuoto (dopo strip tag).
 *
 * @param string $raw Contenuto grezzo.
 * @return bool
 */
function iv_completed_order_intro_raw_is_empty( $raw ) {
	if ( ! is_string( $raw ) ) {
		return true;
	}

	return '' === trim( wp_strip_all_tags( $raw ) );
}

/**
 * Normalizza HTML per confronto tra voci ordine.
 *
 * @param string $html HTML.
 * @return string
 */
function iv_normalize_completed_order_intro_for_compare( $html ) {
	if ( ! is_string( $html ) ) {
		return '';
	}

	$normalized = trim( wp_kses_post( $html ) );
	$normalized = preg_replace( '/\s+/u', ' ', $normalized );

	return null === $normalized ? '' : $normalized;
}

/**
 * Formatta HTML intro per email (allineato a additional_content WooCommerce).
 *
 * @param string $raw Contenuto.
 * @return string
 */
function iv_format_completed_order_intro_html( $raw ) {
	if ( ! is_string( $raw ) ) {
		return '';
	}

	return wp_kses_post( wpautop( wptexturize( $raw ) ) );
}

/**
 * Intro effettiva per prodotto ordine: meta su variante o prodotto, fallback meta sul genitore per variazioni.
 *
 * @param WC_Product|null $product Prodotto riga ordine.
 * @return string HTML o testo grezzo salvato dall'editor.
 */
function iv_get_effective_product_completed_order_intro_raw( $product ) {
	if ( ! is_a( $product, 'WC_Product' ) ) {
		return '';
	}

	$raw = $product->get_meta( IV_COMPLETED_ORDER_INTRO_META_KEY, true );
	if ( ! is_string( $raw ) ) {
		$raw = '';
	}

	if ( ! iv_completed_order_intro_raw_is_empty( $raw ) ) {
		return $raw;
	}

	if ( $product->is_type( 'variation' ) ) {
		$parent = wc_get_product( $product->get_parent_id() );
		if ( $parent && is_a( $parent, 'WC_Product' ) ) {
			$parent_raw = $parent->get_meta( IV_COMPLETED_ORDER_INTRO_META_KEY, true );
			return is_string( $parent_raw ) ? $parent_raw : '';
		}
	}

	return '';
}

/**
 * Ritorna l'HTML intro per l'email ordine completato in base alle voci ordine e alle regole ordini misti.
 *
 * @param WC_Order|null $order Ordine.
 * @return string HTML sicuro.
 */
function iv_get_completed_order_email_intro_html( $order ) {
	$default_plain = iv_get_default_completed_order_intro_plain();

	if ( ! is_a( $order, 'WC_Order' ) ) {
		return iv_format_completed_order_intro_html(
			apply_filters( 'iv_completed_order_email_intro_default_plain', $default_plain, null )
		);
	}

	$rows = array();

	foreach ( $order->get_items( 'line_item' ) as $item ) {
		if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
			continue;
		}

		$product = $item->get_product();
		if ( ! $product ) {
			continue;
		}

		$raw            = iv_get_effective_product_completed_order_intro_raw( $product );
		$has_intro      = ! iv_completed_order_intro_raw_is_empty( $raw );
		$rows[]         = array(
			'has_intro' => $has_intro,
			'raw'       => $raw,
		);
	}

	$n = count( $rows );

	if ( 0 === $n ) {
		$chosen_raw = apply_filters( 'iv_completed_order_email_intro_default_plain', $default_plain, $order );
		$html       = iv_format_completed_order_intro_html( $chosen_raw );

		return apply_filters(
			'iv_completed_order_email_intro_html',
			$html,
			$order,
			array(
				'source' => 'no_line_items',
			)
		);
	}

	if ( 1 === $n ) {
		$chosen_raw = $rows[0]['has_intro'] ? $rows[0]['raw'] : apply_filters( 'iv_completed_order_email_intro_default_plain', $default_plain, $order );
		$html       = iv_format_completed_order_intro_html( $chosen_raw );

		return apply_filters(
			'iv_completed_order_email_intro_html',
			$html,
			$order,
			array(
				'source' => 'single_line_item',
			)
		);
	}

	$with_intro = array();
	foreach ( $rows as $row ) {
		if ( $row['has_intro'] ) {
			$with_intro[] = $row;
		}
	}

	$count_with = count( $with_intro );

	if ( 0 === $count_with ) {
		$chosen_raw = apply_filters( 'iv_completed_order_email_intro_default_plain', $default_plain, $order );
		$html       = iv_format_completed_order_intro_html( $chosen_raw );

		return apply_filters(
			'iv_completed_order_email_intro_html',
			$html,
			$order,
			array(
				'source' => 'multi_all_empty',
			)
		);
	}

	if ( $count_with < $n ) {
		$chosen_raw = apply_filters( 'iv_completed_order_email_intro_default_plain', $default_plain, $order );
		$html       = iv_format_completed_order_intro_html( $chosen_raw );

		return apply_filters(
			'iv_completed_order_email_intro_html',
			$html,
			$order,
			array(
				'source' => 'multi_mixed_filled_empty',
			)
		);
	}

	$first_norm = iv_normalize_completed_order_intro_for_compare( $with_intro[0]['raw'] );
	$all_same   = true;

	foreach ( $with_intro as $row ) {
		if ( iv_normalize_completed_order_intro_for_compare( $row['raw'] ) !== $first_norm ) {
			$all_same = false;
			break;
		}
	}

	if ( $all_same ) {
		$chosen_raw = $with_intro[0]['raw'];
		$html       = iv_format_completed_order_intro_html( $chosen_raw );

		return apply_filters(
			'iv_completed_order_email_intro_html',
			$html,
			$order,
			array(
				'source' => 'multi_all_same_override',
			)
		);
	}

	$chosen_raw = apply_filters( 'iv_completed_order_email_intro_default_plain', $default_plain, $order );
	$html       = iv_format_completed_order_intro_html( $chosen_raw );

	return apply_filters(
		'iv_completed_order_email_intro_html',
		$html,
		$order,
		array(
			'source' => 'multi_different_overrides',
		)
	);
}

/*****************************************
 * ADMIN: SCRIPT EDITOR
 *****************************************/

add_action( 'admin_enqueue_scripts', 'iv_completed_order_intro_admin_enqueue', 20 );

/**
 * Carica dipendenze editor su schermata prodotto.
 *
 * @param string $hook_suffix Hook corrente.
 */
function iv_completed_order_intro_admin_enqueue( $hook_suffix ) {
	if ( 'post.php' !== $hook_suffix && 'post-new.php' !== $hook_suffix ) {
		return;
	}

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'product' !== $screen->id ) {
		return;
	}

	wp_enqueue_editor();

	$iv_email_tab_css = IV_PLUGIN_DIR . 'assets/iv-wc-product-email-tab.css';
	if ( file_exists( $iv_email_tab_css ) ) {
		wp_enqueue_style(
			'iv-wc-product-email-tab',
			IV_PLUGIN_URL . 'assets/iv-wc-product-email-tab.css',
			array( 'dashicons' ),
			(string) filemtime( $iv_email_tab_css )
		);
	}
}

/*****************************************
 * ADMIN: TAB E PANNELLO PRODOTTO
 *****************************************/

add_filter( 'woocommerce_product_data_tabs', 'iv_completed_order_intro_product_data_tabs', 70 );

/**
 * Aggiunge tab dati prodotto per intro email.
 *
 * @param array<string, array<string, mixed>> $tabs Tab esistenti.
 * @return array<string, array<string, mixed>>
 */
function iv_completed_order_intro_product_data_tabs( $tabs ) {
	$tabs['iv_completed_email'] = array(
		'label'    => __( 'Email', 'insiemeviaggi-ecommerce' ),
		'target'   => 'iv_completed_email_product_data',
		'class'    => array(
			'show_if_simple',
			'show_if_variable',
			'show_if_grouped',
			'show_if_external',
		),
		'priority' => 80,
	);

	return $tabs;
}

add_action( 'woocommerce_product_data_panels', 'iv_completed_order_intro_product_data_panel' );

/**
 * Pannello tab con editor WYSIWYG (prodotto semplice o genitore variabile).
 */
function iv_completed_order_intro_product_data_panel() {
	global $post;

	if ( ! $post || 'product' !== $post->post_type ) {
		return;
	}

	$product_object = wc_get_product( $post->ID );
	$value          = '';
	if ( $product_object && is_a( $product_object, 'WC_Product' ) ) {
		$meta = $product_object->get_meta( IV_COMPLETED_ORDER_INTRO_META_KEY, true );
		$value = is_string( $meta ) ? $meta : '';
	}

	wp_nonce_field( 'iv_save_completed_order_intro', 'iv_completed_order_intro_nonce' );
	?>
	<div id="iv_completed_email_product_data" class="panel woocommerce_options_panel hidden">
		<div class="options_group iv-completed-email-intro-panel">
			<p class="form-field form-field-wide iv-completed-email-intro-description">
				<?php
				echo esc_html__(
					"Inserisci un eventuale testo specifico per questo prodotto per l'email 'Ordine Completato'. Se lasci vuoto, verrà usato il testo standard.",
					'insiemeviaggi-ecommerce'
				);
				?>
			</p>
			<p class="form-field form-field-wide">
				<label class="screen-reader-text" for="iv_completed_order_intro_editor">
					<?php echo esc_html__( 'Testo email Ordine completato', 'insiemeviaggi-ecommerce' ); ?>
				</label>
				<?php
				wp_editor(
					wp_kses_post( $value ),
					'iv_completed_order_intro_editor',
					array(
						'textarea_name' => 'iv_completed_order_intro_html',
						'textarea_rows' => 6,
						'media_buttons' => false,
						'teeny'         => true,
						'quicktags'     => true,
					)
				);
				?>
			</p>
		</div>
	</div>
	<?php
}

add_action( 'woocommerce_product_after_variable_attributes', 'iv_completed_order_intro_variation_field', 10, 3 );

/**
 * Campo editor per singola variazione.
 *
 * @param int                $loop           Indice loop.
 * @param array<string,mixed> $variation_data Dati (legacy).
 * @param WC_Product         $variation      Variazione.
 */
function iv_completed_order_intro_variation_field( $loop, $variation_data, $variation ) {
	if ( ! is_a( $variation, 'WC_Product' ) || ! $variation->is_type( 'variation' ) ) {
		return;
	}

	$value = $variation->get_meta( IV_COMPLETED_ORDER_INTRO_META_KEY, true );
	$value = is_string( $value ) ? $value : '';
	$editor_id = 'iv_v_co_intro_' . (int) $variation->get_id();
	?>
	<div class="form-row form-field iv-variation-completed-email-intro">
		<p class="form-field form-field-full">
			<label>
				<?php echo esc_html__( 'Intro email ordine completato (variazione)', 'insiemeviaggi-ecommerce' ); ?>
			</label>
			<?php
			wp_editor(
				wp_kses_post( $value ),
				$editor_id,
				array(
					'textarea_name' => 'iv_variation_completed_intro[' . (int) $loop . ']',
					'textarea_rows' => 6,
					'media_buttons' => false,
					'teeny'         => true,
					'quicktags'     => true,
				)
			);
			?>
		</p>
	</div>
	<?php
}

/*****************************************
 * ADMIN: SALVATAGGIO
 *****************************************/

add_action( 'woocommerce_admin_process_product_object', 'iv_completed_order_intro_save_product', 25, 1 );

/**
 * Salva meta intro su prodotto (non variazioni: gestite da hook dedicato).
 *
 * @param WC_Product $product Prodotto.
 */
function iv_completed_order_intro_save_product( $product ) {
	if ( ! is_a( $product, 'WC_Product' ) ) {
		return;
	}

	if ( ! isset( $_POST['iv_completed_order_intro_nonce'] ) ) {
		return;
	}

	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['iv_completed_order_intro_nonce'] ) ), 'iv_save_completed_order_intro' ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $product->get_id() ) ) {
		return;
	}

	if ( ! isset( $_POST['iv_completed_order_intro_html'] ) ) {
		return;
	}

	$html = wp_unslash( $_POST['iv_completed_order_intro_html'] );
	$html = is_string( $html ) ? wp_kses_post( $html ) : '';

	$product->update_meta_data( IV_COMPLETED_ORDER_INTRO_META_KEY, $html );
}

add_action( 'woocommerce_save_product_variation', 'iv_completed_order_intro_save_variation', 10, 2 );

/**
 * Salva meta intro su variazione.
 *
 * @param int $variation_id ID variazione.
 * @param int $i            Indice loop variazioni.
 */
function iv_completed_order_intro_save_variation( $variation_id, $i ) {
	$variation_id = (int) $variation_id;
	$i            = (int) $i;

	if ( $variation_id < 1 ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $variation_id ) ) {
		return;
	}

	$variation = wc_get_product( $variation_id );
	if ( ! $variation || ! $variation->is_type( 'variation' ) ) {
		return;
	}

	if ( ! isset( $_POST['iv_variation_completed_intro'] ) || ! is_array( $_POST['iv_variation_completed_intro'] ) ) {
		return;
	}

	if ( ! array_key_exists( $i, $_POST['iv_variation_completed_intro'] ) ) {
		return;
	}

	$posted = wp_unslash( $_POST['iv_variation_completed_intro'][ $i ] );
	$html   = is_string( $posted ) ? wp_kses_post( $posted ) : '';

	$variation->update_meta_data( IV_COMPLETED_ORDER_INTRO_META_KEY, $html );
	$variation->save();
}
