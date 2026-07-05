<?php
declare( strict_types=1 );

namespace CoreLabs\ProductOptions\Admin;

use CoreLabs\ProductOptions\Groups\GroupRepository;
use CoreLabs\ProductOptions\Groups\GroupResolver;

defined( 'ABSPATH' ) || exit;

/**
 * Slim product-editor metabox (spec §3.3): a server-rendered summary of the
 * groups that apply to this product, with deep links into the hub, plus a
 * one-click "create options for this product" flow.
 */
final class ProductSummaryBox {

	public const CREATE_ACTION = 'clpo_create_product_group';

	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'admin_post_' . self::CREATE_ACTION, array( $this, 'handle_create' ) );
	}

	public function add_meta_box(): void {
		add_meta_box(
			'clpo-summary-box',
			__( 'Product Options', 'corelabs-product-options' ),
			array( $this, 'render_box' ),
			'product',
			'side',
			'default'
		);
	}

	/**
	 * Pure: resolved groups -> display rows.
	 *
	 * @param array[] $groups canonical group arrays.
	 * @return array<int,array{id:int, title:string, field_count:int, edit_url:string}>
	 */
	public static function items( array $groups ): array {
		$out = array();
		foreach ( $groups as $g ) {
			$out[] = array(
				'id'          => (int) ( $g['id'] ?? 0 ),
				'title'       => (string) ( $g['title'] ?? '' ),
				'field_count' => count( $g['fields'] ?? array() ),
				'edit_url'    => admin_url( 'admin.php?page=' . HubPage::SLUG . '#/groups/' . (int) ( $g['id'] ?? 0 ) ),
			);
		}
		return $out;
	}

	/** @param \WP_Post $post */
	public function render_box( $post ): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			echo '<p>' . esc_html__( 'Managed by your store admin.', 'corelabs-product-options' ) . '</p>';
			return;
		}
		$items = self::items( GroupResolver::for_product( (int) $post->ID ) );

		if ( array() === $items ) {
			echo '<p>' . esc_html__( 'No option groups apply to this product yet.', 'corelabs-product-options' ) . '</p>';
		} else {
			echo '<ul class="clpo-sumlist">';
			foreach ( $items as $item ) {
				printf(
					'<li>&raquo; <strong>%s</strong> — %s · <a href="%s">%s ↗</a></li>',
					esc_html( $item['title'] ),
					esc_html(
						sprintf(
							/* translators: %d: number of fields */
							_n( '%d field', '%d fields', $item['field_count'], 'corelabs-product-options' ),
							$item['field_count']
						)
					),
					esc_url( $item['edit_url'] ),
					esc_html__( 'Edit', 'corelabs-product-options' )
				);
			}
			echo '</ul>';
		}

		$create = wp_nonce_url(
			admin_url( 'admin-post.php?action=' . self::CREATE_ACTION . '&product=' . (int) $post->ID ),
			self::CREATE_ACTION
		);
		printf(
			'<p><a class="button" href="%s">%s</a></p>',
			esc_url( $create ),
			esc_html__( 'Create options for this product', 'corelabs-product-options' )
		);
	}

	/** admin-post handler: draft group pre-assigned to the product -> builder. */
	public function handle_create(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'corelabs-product-options' ) );
		}
		check_admin_referer( self::CREATE_ACTION );

		$product_id = isset( $_GET['product'] ) ? (int) $_GET['product'] : 0;
		$product    = $product_id > 0 ? get_post( $product_id ) : null;
		if ( ! $product || 'product' !== $product->post_type ) {
			wp_die( esc_html__( 'Unknown product.', 'corelabs-product-options' ) );
		}

		$group = ( new GroupRepository() )->save(
			0,
			array(
				'title'      => $product->post_title . ' — Options',
				'status'     => 'draft',
				'fields'     => array(),
				'assignment' => array( 'mode' => 'products', 'ids' => array( $product_id ) ),
			)
		);

		wp_safe_redirect( admin_url( 'admin.php?page=' . HubPage::SLUG . '#/groups/' . $group['id'] ) );
		exit;
	}
}
