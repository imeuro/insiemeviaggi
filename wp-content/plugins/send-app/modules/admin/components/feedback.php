<?php

namespace Send_App\Modules\Admin\Components;

use Send_App\Core\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Feedback {
	public function __construct() {
		add_action( 'current_screen', function () {
			if ( ! $this->is_plugins_screen() ) {
				return;
			}

			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_feedback_assets' ] );
			add_action( 'admin_footer', [ $this, 'print_deactivate_feedback_dialog' ] );
		} );

		add_action( 'wp_ajax_send_app_deactivate_feedback', [ $this, 'ajax_send_app_deactivate_feedback' ] );
	}

	private function is_plugins_screen(): bool {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}
		return in_array( $screen->id, [ 'plugins' ], true );
	}

	public function enqueue_feedback_assets() {
		$script_asset_path = SEND_ASSETS_PATH . 'js/send-app-feedback.asset.php';

		$script_asset = require $script_asset_path;

		wp_register_script(
			'send-app-feedback',
			SEND_ASSETS_URL . 'js/send-app-feedback.js',
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);
		wp_set_script_translations( 'send-app-feedback', 'send-app' );
		wp_enqueue_script( 'send-app-feedback' );

		$style_asset_path = SEND_ASSETS_PATH . 'css/send-app-feedback.asset.php';

		$style_asset = require $style_asset_path;

		wp_enqueue_style(
			'send-app-feedback',
			SEND_ASSETS_URL . 'css/send-app-feedback.css',
			$style_asset['dependencies'],
			$style_asset['version']
		);
	}

	public function print_deactivate_feedback_dialog() {
		?>
		<dialog id="send-app-deactivate-feedback-modal">
			<div id="send-app-deactivate-feedback-dialog-header">
				<img src="<?php echo esc_url( SEND_ASSETS_URL . 'images/send-icon.svg' ); ?>" alt="" aria-hidden="true" class="send-app-feedback-icon" />
				<span id="send-app-deactivate-feedback-dialog-header-title"><?php echo esc_html__( 'Quick Feedback', 'send-app' ); ?></span>
			</div>
			<form id="send-app-deactivate-feedback-dialog-form" method="post">
				<?php wp_nonce_field( 'send_app_deactivate_feedback' ); ?>
				<input type="hidden" name="action" value="send_app_deactivate_feedback" />

				<div id="send-app-deactivate-feedback-dialog-form-caption"><?php echo esc_html__( 'If you have a moment, please share why you are deactivating Send:', 'send-app' ); ?></div>
				<div id="send-app-deactivate-feedback-dialog-form-body">
					<div class="send-app-deactivate-feedback-dialog-input-wrapper">
						<input id="send-app-deactivate-feedback-no_longer_needed" class="send-app-deactivate-feedback-dialog-input" type="radio" name="reason_key" value="no_longer_needed" />
						<label for="send-app-deactivate-feedback-no_longer_needed" class="send-app-deactivate-feedback-dialog-label"><?php echo esc_html__( 'I no longer need the plugin', 'send-app' ); ?></label>
					</div>
					<div class="send-app-deactivate-feedback-dialog-input-wrapper">
						<input id="send-app-deactivate-feedback-found_a_better_plugin" class="send-app-deactivate-feedback-dialog-input" type="radio" name="reason_key" value="found_a_better_plugin" />
						<label for="send-app-deactivate-feedback-found_a_better_plugin" class="send-app-deactivate-feedback-dialog-label"><?php echo esc_html__( 'I found a better plugin', 'send-app' ); ?></label>
					</div>
					<div class="send-app-deactivate-feedback-dialog-input-wrapper">
						<input id="send-app-deactivate-feedback-couldnt_get_the_plugin_to_work" class="send-app-deactivate-feedback-dialog-input" type="radio" name="reason_key" value="couldnt_get_the_plugin_to_work" />
						<label for="send-app-deactivate-feedback-couldnt_get_the_plugin_to_work" class="send-app-deactivate-feedback-dialog-label"><?php echo esc_html__( 'I couldn\'t get the plugin to work', 'send-app' ); ?></label>
					</div>
					<div class="send-app-deactivate-feedback-dialog-input-wrapper">
						<input id="send-app-deactivate-feedback-temporary_deactivation" class="send-app-deactivate-feedback-dialog-input" type="radio" name="reason_key" value="temporary_deactivation" />
						<label for="send-app-deactivate-feedback-temporary_deactivation" class="send-app-deactivate-feedback-dialog-label"><?php echo esc_html__( 'It\'s a temporary deactivation', 'send-app' ); ?></label>
					</div>
					<div class="send-app-deactivate-feedback-dialog-input-wrapper">
						<input id="send-app-deactivate-feedback-other" class="send-app-deactivate-feedback-dialog-input" type="radio" name="reason_key" value="other" />
						<label for="send-app-deactivate-feedback-other" class="send-app-deactivate-feedback-dialog-label"><?php echo esc_html__( 'Other', 'send-app' ); ?></label>
						<div class="send-app-feedback-text-wrapper">
							<div class="send-app-feedback-hint"><?php echo esc_html__( 'Reason', 'send-app' ); ?></div>
							<input class="send-app-feedback-text" type="text" name="reason_other" placeholder="<?php echo esc_attr__( 'Type the reason for deactivation', 'send-app' ); ?>" />
						</div>
					</div>
				</div>

				<div class="send-app-dialog-buttons-wrapper">
					<button type="button" class="send-app-dialog-skip"><?php echo esc_html__( 'Skip & Deactivate', 'send-app' ); ?></button>
					<button type="button" class="send-app-dialog-submit"><?php echo esc_html__( 'Submit & Deactivate', 'send-app' ); ?></button>
				</div>
			</form>
		</dialog>
		<?php
	}

	public function ajax_send_app_deactivate_feedback() {
		$wpnonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $wpnonce, 'send_app_deactivate_feedback' ) ) {
			wp_send_json_error();
		}

		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$reason_key  = isset( $_POST['reason_key'] ) ? sanitize_text_field( wp_unslash( $_POST['reason_key'] ) ) : '';
		$reason_text = '';
		if ( $reason_key ) {
			$field_name = 'reason_' . $reason_key;
			$reason_text = isset( $_POST[ $field_name ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field_name ] ) ) : '';
		}

		API::make_request( 'POST', 'user-feedback/deactivation', [
			'type'  => $reason_key,
			'text' => $reason_text,
		] );

		wp_send_json_success();
	}
}
