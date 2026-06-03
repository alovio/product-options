<?php
declare( strict_types=1 );

namespace CoreLabs\ProductOptions\Frontend;

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
		$asset_file = CLPO_PATH . 'build/frontend.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;

		wp_enqueue_script( 'clpo-frontend', CLPO_URL . 'build/frontend.js', $asset['dependencies'], $asset['version'], true );
		wp_localize_script(
			'clpo-frontend',
			'CLPO_FE',
			array(
				'symbol'      => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
				'decimals'    => wc_get_price_decimals(),
				'decimalSep'  => wc_get_price_decimal_separator(),
				'thousandSep' => wc_get_price_thousand_separator(),
				'position'    => get_option( 'woocommerce_currency_pos', 'left' ),
			)
		);
		wp_set_script_translations( 'clpo-frontend', 'corelabs-product-options', CLPO_PATH . 'languages' );
		if ( file_exists( CLPO_PATH . 'build/frontend.css' ) ) {
			wp_enqueue_style( 'clpo-frontend', CLPO_URL . 'build/frontend.css', array(), $asset['version'] );
			wp_style_add_data( 'clpo-frontend', 'rtl', 'replace' );
		}
	}
}
