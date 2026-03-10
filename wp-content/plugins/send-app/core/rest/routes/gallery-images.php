<?php
namespace Send_App\Core\Rest\Routes;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use Send_App\Core\Base\Route_Base;
use Send_App\Core\Logger;

class Gallery_Images extends Route_Base {
	public string $path = 'gallery-images';

	public const NONCE_NAME = 'wp_rest';
	private const DEFAULT_PER_PAGE = 20;
	private const MAX_PER_PAGE = 50;

	public function get_methods(): array {
		return [ 'GET' ];
	}

	public function get_name(): string {
		return 'gallery-images';
	}

	public function get_args(): array {
		return [
			'search' => [
				'description' => 'Search term to filter gallery images by name',
				'type' => 'string',
				'required' => false,
				'sanitize_callback' => function ( $input ) {
					return sanitize_text_field( $input );
				},
				'validate_callback' => function ( $param ) {
					return is_string( $param );
				},
			],
			'per_page' => [
				'description' => 'Number of images per page (default: 20, max: 50)',
				'type' => 'integer',
				'required' => false,
				'default' => self::DEFAULT_PER_PAGE,
				'sanitize_callback' => function ( $input ) {
					return min( (int) $input, self::MAX_PER_PAGE );
				},
				'validate_callback' => function ( $param ) {
					return is_numeric( $param ) && $param > 0;
				},
			],
			'page' => [
				'description' => 'Page number for pagination',
				'type' => 'integer',
				'required' => false,
				'default' => 1,
				'sanitize_callback' => function ( $input ) {
					return max( 1, (int) $input );
				},
				'validate_callback' => function ( $param ) {
					return is_numeric( $param ) && $param > 0;
				},
			],
		];
	}

	public function GET( \WP_REST_Request $request ) {
		try {
			$search_term = $request->get_param( 'search' );
			$per_page = $request->get_param( 'per_page' ) ?? self::DEFAULT_PER_PAGE;
			$page = $request->get_param( 'page' ) ?? 1;

			$gallery_data = $this->retrieveWordPressGalleryImages( $search_term, $per_page, $page );
			return $this->respond_success_json( $gallery_data );
		} catch ( \Throwable $t ) {
			Logger::error( '[' . $t->getCode() . '] ' . $t->getMessage() );

			return $this->respond_error_json( new \WP_Error(
				$t->getCode(),
				$t->getMessage(),
				[ 'status' => 500 ]
			) );
		}
	}

	private function retrieveWordPressGalleryImages( ?string $search_term = null, int $per_page = self::DEFAULT_PER_PAGE, int $page = 1 ): array {
		$query_args = [
			'post_type' => 'attachment',
			'post_mime_type' => 'image',
			'post_status' => 'inherit',
			'posts_per_page' => $per_page,
			'paged' => $page,
			'orderby' => 'date',
			'order' => 'DESC',
		];

		if ( ! empty( $search_term ) ) {
			$query_args['s'] = sanitize_text_field( $search_term );
		}

		$image_query = new \WP_Query( $query_args );
		$gallery_images = [];

		foreach ( $image_query->posts as $image_post ) {
			$image_title = get_the_title( $image_post->ID );

			// Additional filtering by title if search term is provided and WP_Query search didn't catch it
			if ( ! empty( $search_term ) && ! $this->doesImageTitleMatchSearchTerm( $image_title, $search_term ) ) {
				continue;
			}

			$thumbnail_data = wp_get_attachment_image_src( $image_post->ID, 'thumbnail' );
			$thumbnail_url = $thumbnail_data ? $thumbnail_data[0] : '';

			$gallery_images[] = [
				'id' => $image_post->ID,
				'url' => wp_get_attachment_url( $image_post->ID ),
				'thumbnail' => $thumbnail_url,
				'title' => $image_title,
			];
		}

		return [
			'items' => $gallery_images,
			'pagination' => [
				'total' => $image_query->found_posts,
				'total_pages' => $image_query->max_num_pages,
				'current_page' => $page,
				'per_page' => $per_page,
			],
		];
	}

	private function doesImageTitleMatchSearchTerm( string $image_title, string $search_term ): bool {
		return stripos( $image_title, $search_term ) !== false;
	}
}
