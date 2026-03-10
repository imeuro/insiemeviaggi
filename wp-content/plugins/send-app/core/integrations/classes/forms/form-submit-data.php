<?php
namespace Send_App\Core\Integrations\Classes\Forms;

use Send_App\Core\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * @property string $name
 * @property string $form_name
 * @property string $post_id
 * @property string $form_id
 * @property array $fields
 * @property string $document_type
 * @property string $page_url
 * @property array | null $meta
 */
class Form_Submit_Data {
	const EVENT = 'submitted';
	private string $name;
	private string $form_id;
	private string $post_id;
	private array $fields;
	private string $form_name;
	private string $document_type;
	private string $page_url;
	private ?array $meta;

	public function get_data(): array {
		$data = [
			'source' => [
				'method' => 'form',
				'name' => $this->name,
			],
			'eventType' => self::EVENT,
			'formId' => $this->form_id,
			'page' => [
				'pageId'   => $this->post_id,
				'name'     => get_the_title( $this->post_id ),
				'pagePath' => $this->page_url,
			],
			'formName' => $this->form_name,
			'formFields' => $this->fields,
			'specialType' => $this->document_type,
		];

		if ( ! empty( $this->meta ) ) {
			$data['formMeta'] = $this->meta;
		}

		return $data;
	}

	public function __construct( string $name, string $form_id, string $post_id, array $fields, string $form_name = '', string $document_type = '', ?array $meta = null ) {
		$this->name = $name;
		$this->form_id = $form_id;
		$this->post_id = $post_id;
		$this->fields = $fields;
		$this->form_name = $form_name;
		$this->document_type = $document_type;
		$this->page_url = Utils::get_page_url( $post_id );
		$this->meta = $meta;
	}
}
