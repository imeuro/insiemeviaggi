<?php

namespace Send_App\Modules\Admin\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Notification_Template {
	private array $notifications;

	private function get_fallback_data(): array {
		$fallback = [
			'id' => 'placeholder' . SEND_VERSION,
			'title' => __( 'Stay Tuned!', 'send-app' ),
			'description' => __( 'Coming soon...', 'send-app' ),
			'topic' => __( 'Send App', 'send-app' ),
			'link' => 'https://send2.co/?utm_source=wp-plugins&utm_campaign=whatsnew-fallback&utm_medium=wp-dash',
		];

		/**
		 * Filter the fallback notification item.
		 * @param array $fallback The fallback notification item.
		 */
		return apply_filters( 'send_app/notifications/fallback', $fallback );
	}

	private function print_inner_list() {
		?>
		<h3><?php esc_html_e( 'What\'s new in Send:', 'send-app' ); ?></h3>
		<ul>
			<?php
			foreach ( $this->notifications as $item ) {
				$item->print();
			}
			?>
		</ul>
		<?php
	}

	public function print_notice() {
		?>
		<div class="notice notice-info is-dismissible">
			<?php
				$this->print_inner_list();
			?>
		</div>
		<div class="notice notice-info is-dismissible">
			<button class="send-app-notifications-dialog-open">Open Notification</button>
		</div>
		<?php
	}
	public function print_dialog() {
		?>
		<dialog id="send-app-notifications-dialog">
			<?php $this->print_inner_list(); ?>
			<button class="close"><?php echo esc_html__( 'Close', 'send-app' ); ?></button>
		</dialog>
		<script>
			document.addEventListener( 'DOMContentLoaded', function() {
				const openDialogBtn = document.getElementById( 'send-app-notifications-dialog-open' );
				const closeDialogBtn = document.querySelector( '#send-app-notifications-dialog button.close' );
				const dialog = document.getElementById( 'send-app-notifications-dialog' );

				openDialogBtn?.addEventListener( 'click', function( e ) {
					e.preventDefault();
					dialog.showModal();
				} );

				closeDialogBtn.addEventListener( 'click', function() {
					dialog.close();
				} );
			} );
		</script>
		<?php
	}

	public function __construct( array $notifications ) {
		if ( empty( $notifications ) ) {
			$notifications = [ $this->get_fallback_data() ];
		}

		foreach ( $notifications as $notification ) {
			$this->notifications[] = new Notification_Item( $notification );
		}
	}
}
