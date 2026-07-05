<?php
declare( strict_types=1 );

namespace CoreLabs\ProductOptions\Admin;

use CoreLabs\ProductOptions\Groups\GroupRepository;

defined( 'ABSPATH' ) || exit;

/**
 * clpo/v1 hub API (spec §3.4): group CRUD, duplicate, assignment pickers and
 * the settings option. Every route is manage_woocommerce-gated; nonce comes
 * from standard REST cookie auth.
 */
final class GroupsRestController {

	private const NS = 'clpo/v1';

	private GroupRepository $repo;

	public function __construct( ?GroupRepository $repo = null ) {
		$this->repo = $repo ?? new GroupRepository();
	}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function can_manage(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	public function register_routes(): void {
		register_rest_route(
			self::NS,
			'/groups',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_groups' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_group' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/groups/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_group' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update_group' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete_group' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/groups/(?P<id>\d+)/duplicate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'duplicate_group' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
		register_rest_route(
			self::NS,
			'/products/search',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'search_products' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
		register_rest_route(
			self::NS,
			'/categories/search',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'search_categories' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
		register_rest_route(
			self::NS,
			'/settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);
	}

	/**
	 * Pure: list-row summary of a canonical group array.
	 *
	 * @param array<string,mixed> $group
	 */
	public static function summarize( array $group ): array {
		$fields = isset( $group['fields'] ) && is_array( $group['fields'] ) ? $group['fields'] : array();
		$priced = 0;
		foreach ( $fields as $f ) {
			if ( ( isset( $f['price'] ) && (float) $f['price'] > 0 ) || 'price' === ( $f['type'] ?? '' ) ) {
				++$priced;
			}
		}

		$a = $group['assignment'] ?? array( 'mode' => 'all', 'ids' => array() );
		$n = count( $a['ids'] ?? array() );
		if ( 'categories' === $a['mode'] ) {
			/* translators: %d: number of categories */
			$summary = sprintf( _n( '%d category', '%d categories', $n, 'corelabs-product-options' ), $n );
		} elseif ( 'products' === $a['mode'] ) {
			/* translators: %d: number of products */
			$summary = sprintf( _n( '%d product', '%d products', $n, 'corelabs-product-options' ), $n );
		} else {
			$summary = __( 'All products', 'corelabs-product-options' );
		}

		return array(
			'id'                 => (int) ( $group['id'] ?? 0 ),
			'title'              => (string) ( $group['title'] ?? '' ),
			'status'             => (string) ( $group['status'] ?? 'draft' ),
			'field_count'        => count( $fields ),
			'priced_count'       => $priced,
			'assignment_summary' => $summary,
			'priority'           => (int) ( $group['priority'] ?? 10 ),
		);
	}

	public function list_groups() {
		return rest_ensure_response( array_map( array( self::class, 'summarize' ), $this->repo->all() ) );
	}

	/** @param \WP_REST_Request $request */
	public function create_group( $request ) {
		$body = $request->get_json_params();
		return rest_ensure_response( $this->repo->save( 0, is_array( $body ) ? $body : array() ) );
	}

	/** @param \WP_REST_Request $request */
	public function get_group( $request ) {
		$group = $this->repo->get( (int) $request['id'] );
		if ( null === $group ) {
			return new \WP_Error( 'clpo_not_found', __( 'Group not found.', 'corelabs-product-options' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( $group );
	}

	/** @param \WP_REST_Request $request */
	public function update_group( $request ) {
		$id = (int) $request['id'];
		if ( null === $this->repo->get( $id ) ) {
			return new \WP_Error( 'clpo_not_found', __( 'Group not found.', 'corelabs-product-options' ), array( 'status' => 404 ) );
		}
		$body = $request->get_json_params();
		return rest_ensure_response( $this->repo->save( $id, is_array( $body ) ? $body : array() ) );
	}

	/** @param \WP_REST_Request $request */
	public function delete_group( $request ) {
		if ( ! $this->repo->delete( (int) $request['id'] ) ) {
			return new \WP_Error( 'clpo_not_found', __( 'Group not found.', 'corelabs-product-options' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( array( 'deleted' => true ) );
	}

	/** @param \WP_REST_Request $request */
	public function duplicate_group( $request ) {
		$copy = $this->repo->duplicate( (int) $request['id'] );
		if ( null === $copy ) {
			return new \WP_Error( 'clpo_not_found', __( 'Group not found.', 'corelabs-product-options' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( $copy );
	}

	/** @param \WP_REST_Request $request */
	public function search_products( $request ) {
		$q     = (string) $request->get_param( 'q' );
		$items = array();
		foreach ( wc_get_products( array( 's' => $q, 'limit' => 20, 'status' => 'publish' ) ) as $p ) {
			$items[] = array(
				'id'   => (int) $p->get_id(),
				'name' => (string) $p->get_name(),
			);
		}
		return rest_ensure_response( $items );
	}

	/** @param \WP_REST_Request $request */
	public function search_categories( $request ) {
		$q     = (string) $request->get_param( 'q' );
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'name__like' => $q,
				'number'     => 20,
				'hide_empty' => false,
			)
		);
		$items = array();
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			$path      = array();
			foreach ( array_reverse( get_ancestors( (int) $term->term_id, 'product_cat' ) ) as $aid ) {
				$anc = get_term( (int) $aid, 'product_cat' );
				if ( $anc && ! is_wp_error( $anc ) ) {
					$path[] = $anc->name;
				}
			}
			$items[] = array(
				'id'   => (int) $term->term_id,
				'name' => (string) $term->name,
				'path' => implode( ' › ', array_merge( $path, array( (string) $term->name ) ) ),
			);
		}
		return rest_ensure_response( $items );
	}

	public function get_settings() {
		return rest_ensure_response(
			array( 'removeDataOnUninstall' => (bool) get_option( 'clpo_remove_data_on_uninstall' ) )
		);
	}

	/** @param \WP_REST_Request $request */
	public function update_settings( $request ) {
		$body = $request->get_json_params();
		update_option( 'clpo_remove_data_on_uninstall', ! empty( $body['removeDataOnUninstall'] ) ? 1 : 0 );
		return $this->get_settings();
	}
}
