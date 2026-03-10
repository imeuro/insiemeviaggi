<?php
namespace Send_App\Modules\Admin\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * @property string $id
 * @property string $title
 * @property string $description
 * @property ?string $topic
 * @property ?string $link
 * @property ?string $read_more_text
 * @property ?string $image_src
 * @property ?string $chip_plan
 * @property ?array $chip_tags
 */
class Notification_Item {
	private string $id;
	private string $title;
	private string $description;
	private ?string $topic = null;
	private ?string $link = null;
	private ?string $read_more_text = null;
	private ?string $image_src = null;
	private ?string $chip_plan = null;
	private ?array $chip_tags = null;

	public function print( $wrapper_selector = 'li' ) {
		$open_selector  = '<' . esc_html( $wrapper_selector ) . ' class="send-app-notification-item" id="' . esc_attr( 'send-app-notification-item-' . $this->id ) . '\">';
		$close_selector = '</' . esc_html( $wrapper_selector ) . '>';
		echo $open_selector; //@phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( $this->topic ) {
			echo '<div class="send-app-notification-item-topic">' . esc_html( $this->topic ) . '</div>';
		}
		echo '<div class="send-app-notification-item-title">' . esc_html( $this->title ) . '</div>';
		if ( $this->image_src ) {
			echo '<img class="send-app-notification-item-image" src="' . esc_attr( $this->image_src ) . '" alt="' . esc_attr( $this->title ) . '">';
		}
		echo '<div class="send-app-notification-item-description">' . esc_html( $this->description );
		if ( $this->link ) {
			echo '<a href="' . esc_url( $this->link ) . '" target="_blank">' . esc_html( $this->read_more_text ?? __( 'Learn more', 'send-app' ) ) . '</a>';
		}
		echo '</div>';
		echo $close_selector; //@phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	public function __construct( array $data ) {
		$this->id = $data['id'];
		$this->title = $data['title'];
		$this->description = $data['description'] . '  ';
		$this->link = $data['link'] ?? null;
		$this->read_more_text = $data['readMoreText'] ?? null;
		$this->topic = $data['topic'] ?? null;
		$this->image_src = $data['imageSrc'] ?? null;
		$this->chip_plan = $data['chipPlan'] ?? null;
		$this->chip_tags = $data['chipTags'] ?? null;
	}
}
