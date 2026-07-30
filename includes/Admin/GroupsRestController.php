<?php
declare( strict_types=1 );

namespace CoreLabs\ProductOptions\Admin;

use CoreLabs\ProductOptions\Fields\FieldOptions;
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
			'/export',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'export_groups' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
		register_rest_route(
			self::NS,
			'/import',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'import_groups' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
		register_rest_route(
			self::NS,
			'/templates',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list_templates' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
		register_rest_route(
			self::NS,
			'/templates/(?P<id>[a-z0-9-]+)/use',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'use_template' ),
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
			// A field charges if it has a price, is a surcharge, computes one
			// with a formula, or prices its options individually.
			if ( ( isset( $f['price'] ) && (float) $f['price'] > 0 )
				|| 'price' === ( $f['type'] ?? '' )
				|| 'formula' === ( $f['priceMode'] ?? '' )
				|| FieldOptions::has_priced_options( $f )
			) {
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
		// wc_get_products() has no free-text search arg — plain WP_Query 's' does.
		$posts = get_posts(
			array(
				'post_type'   => 'product',
				'post_status' => 'publish',
				's'           => $q,
				'numberposts' => 20,
			)
		);
		$items = array();
		foreach ( $posts as $p ) {
			$items[] = array(
				'id'   => (int) $p->ID,
				'name' => (string) $p->post_title,
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

	/**
	 * GET /export?ids=1,2 exports those groups; ids OMITTED exports ALL groups
	 * (the header "Export all" consumer).
	 *
	 * @param \WP_REST_Request $request
	 */
	public function export_groups( $request ) {
		$param  = (string) $request->get_param( 'ids' );
		$all    = $this->repo->all();
		$groups = $all;
		if ( '' !== $param ) {
			$ids    = array_map( 'intval', array_filter( explode( ',', $param ) ) );
			$groups = array_values(
				array_filter(
					$all,
					static function ( $g ) use ( $ids ) {
						return in_array( (int) $g['id'], $ids, true );
					}
				)
			);
		}
		return rest_ensure_response( ImportExport::package( $groups ) );
	}

	/**
	 * POST /import — unpacked groups become DRAFTS; response carries the new
	 * ids + normalization warnings.
	 *
	 * @param \WP_REST_Request $request
	 */
	public function import_groups( $request ) {
		$unpacked = ImportExport::unpack( $request->get_json_params() );
		$created  = array();
		foreach ( $unpacked['groups'] as $g ) {
			$g['status'] = 'draft';
			$saved       = $this->repo->save( 0, $g );
			$created[]   = (int) $saved['id'];
		}
		return rest_ensure_response(
			array(
				'created'  => $created,
				'warnings' => $unpacked['warnings'],
			)
		);
	}

	public function list_templates() {
		$items = array();
		foreach ( \CoreLabs\ProductOptions\Templates\Templates::all() as $tpl ) {
			$fields  = $tpl['package']['groups'][0]['fields'] ?? array();
			$items[] = array(
				'id'          => $tpl['id'],
				'name'        => $tpl['name'],
				'description' => $tpl['description'],
				'types'       => array_values( array_unique( array_column( $fields, 'type' ) ) ),
			);
		}
		return rest_ensure_response( $items );
	}

	/** @param \WP_REST_Request $request */
	public function use_template( $request ) {
		$tpl = \CoreLabs\ProductOptions\Templates\Templates::get( (string) $request['id'] );
		if ( null === $tpl ) {
			return new \WP_Error( 'clpo_not_found', __( 'Template not found.', 'corelabs-product-options' ), array( 'status' => 404 ) );
		}
		$unpacked = ImportExport::unpack( $tpl['package'] );
		$group    = $unpacked['groups'][0] ?? null;
		if ( null === $group ) {
			return new \WP_Error( 'clpo_bad_template', __( 'Template could not be loaded.', 'corelabs-product-options' ), array( 'status' => 500 ) );
		}
		$group['status'] = 'draft';
		$saved           = $this->repo->save( 0, $group );
		return rest_ensure_response( $saved );
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
