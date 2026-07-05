<?php
declare( strict_types=1 );

namespace CoreLabs\ProductOptions\Admin;

use CoreLabs\ProductOptions\Fields\FieldRepository;

defined( 'ABSPATH' ) || exit;

/**
 * REST API for the builder: GET/POST a product's field group.
 * Capability-gated to users who can edit the product.
 */
final class RestController {

	private FieldRepository $repo;

	public function __construct( ?FieldRepository $repo = null ) {
		$this->repo = $repo ?? new FieldRepository();
	}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		// GET-only since 2.0: kept for back-compat consumers; group editing goes
		// through clpo/v1/groups. Keeps edit_product (called from the product screen).
		register_rest_route(
			'clpo/v1',
			'/product/(?P<id>\d+)/fields',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_fields' ),
					'permission_callback' => array( $this, 'can_edit' ),
				),
			)
		);
	}

	/** @param \WP_REST_Request $request */
	public function can_edit( $request ): bool {
		$id = (int) $request['id'];
		return 'product' === get_post_type( $id ) && current_user_can( 'edit_product', $id );
	}

	/** @param \WP_REST_Request $request */
	public function get_fields( $request ) {
		return rest_ensure_response( $this->repo->get( (int) $request['id'] ) );
	}

}
