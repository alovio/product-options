<?php
declare( strict_types=1 );

namespace CoreLabs\ProductOptions\Admin;

use CoreLabs\ProductOptions\Fields\FieldTypes;

defined( 'ABSPATH' ) || exit;

/**
 * Mount the React builder in the product editor and enqueue its bundle.
 */
final class BuilderAssets {

	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function add_meta_box(): void {
		add_meta_box(
			'clpo-builder-box',
			__( 'Product Options', 'corelabs-product-options' ),
			array( $this, 'render_box' ),
			'product',
			'normal',
			'high'
		);
	}

	/** @param \WP_Post $post */
	public function render_box( $post ): void {
		printf( '<div id="clpo-builder" data-product-id="%d"></div>', (int) $post->ID );
	}

	public function enqueue( string $hook ): void {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && 'product' !== $screen->post_type ) {
			return;
		}

		$asset_file = CLPO_PATH . 'build/index.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;

		wp_enqueue_script( 'clpo-builder', CLPO_URL . 'build/index.js', $asset['dependencies'], $asset['version'], true );
		wp_set_script_translations( 'clpo-builder', 'corelabs-product-options', CLPO_PATH . 'languages' );
		wp_enqueue_style( 'clpo-builder', CLPO_URL . 'build/index.css', array(), $asset['version'] );
		wp_style_add_data( 'clpo-builder', 'rtl', 'replace' );
		wp_localize_script(
			'clpo-builder',
			'CLPO_BUILDER',
			array(
				'root'       => esc_url_raw( rest_url( '/' ) ),
				'nonce'      => wp_create_nonce( 'wp_rest' ),
				'fieldTypes' => FieldTypes::all(),
				'operators'  => (array) apply_filters( 'clpo_allowed_operators', array( 'is', 'is_not', 'contains', 'gt', 'lt' ) ),
			)
		);
	}
}
