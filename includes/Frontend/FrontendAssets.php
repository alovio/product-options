<?php
declare( strict_types=1 );

namespace APO\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Enqueue the product-page runtime bundle (conditional logic + price display).
 */
final class FrontendAssets {

	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function enqueue(): void {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}
		$asset_file = APO_PATH . 'build/frontend.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;

		wp_enqueue_script( 'apo-frontend', APO_URL . 'build/frontend.js', $asset['dependencies'], $asset['version'], true );
		if ( file_exists( APO_PATH . 'build/frontend.css' ) ) {
			wp_enqueue_style( 'apo-frontend', APO_URL . 'build/frontend.css', array(), $asset['version'] );
		}
	}
}
