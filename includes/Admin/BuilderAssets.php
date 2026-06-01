<?php
declare( strict_types=1 );

namespace APO\Admin;

use APO\Fields\FieldTypes;

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
			'apo-builder-box',
			__( 'Product Options', 'advanced-product-options' ),
			array( $this, 'render_box' ),
			'product',
			'normal',
			'high'
		);
	}

	/** @param \WP_Post $post */
	public function render_box( $post ): void {
		printf( '<div id="apo-builder" data-product-id="%d"></div>', (int) $post->ID );
	}

	public function enqueue( string $hook ): void {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && 'product' !== $screen->post_type ) {
			return;
		}

		$asset_file = APO_PATH . 'build/index.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;

		wp_enqueue_script( 'apo-builder', APO_URL . 'build/index.js', $asset['dependencies'], $asset['version'], true );
		wp_enqueue_style( 'apo-builder', APO_URL . 'build/index.css', array(), $asset['version'] );
		wp_localize_script(
			'apo-builder',
			'APO_BUILDER',
			array(
				'root'       => esc_url_raw( rest_url( '/' ) ),
				'nonce'      => wp_create_nonce( 'wp_rest' ),
				'fieldTypes' => FieldTypes::all(),
			)
		);
	}
}
