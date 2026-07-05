<?php
declare( strict_types=1 );

namespace CoreLabs\ProductOptions\Admin;

use CoreLabs\ProductOptions\Fields\FieldTypes;

defined( 'ABSPATH' ) || exit;

/**
 * Enqueue the hub SPA bundle on the Product Options hub page only.
 * (The v1 product-editor metabox builder is gone; the product screen gets a
 * slim server-rendered summary box instead — see ProductSummaryBox.)
 */
final class BuilderAssets {

	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function enqueue( string $hook ): void {
		if ( 'woocommerce_page_' . HubPage::SLUG !== $hook ) {
			return;
		}

		$asset_file = CLPO_PATH . 'build/hub.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;

		wp_enqueue_script( 'clpo-hub', CLPO_URL . 'build/hub.js', $asset['dependencies'], $asset['version'], true );
		wp_set_script_translations( 'clpo-hub', 'corelabs-product-options', CLPO_PATH . 'languages' );
		wp_enqueue_style( 'clpo-hub', CLPO_URL . 'build/hub.css', array(), $asset['version'] );
		wp_style_add_data( 'clpo-hub', 'rtl', 'replace' );
		wp_localize_script(
			'clpo-hub',
			'CLPO_HUB',
			array(
				'root'       => esc_url_raw( rest_url( '/' ) ),
				'nonce'      => wp_create_nonce( 'wp_rest' ),
				'fieldTypes' => FieldTypes::all(),
				'operators'  => (array) apply_filters( 'clpo_allowed_operators', array( 'is', 'is_not', 'contains', 'gt', 'lt' ) ),
			)
		);
	}
}
