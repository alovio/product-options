<?php
declare( strict_types=1 );

namespace CoreLabs\ProductOptions\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the "Product Options" hub page (WooCommerce submenu) that mounts
 * the full-screen React app: groups list, builder, templates and settings.
 */
final class HubPage {

	public const SLUG = 'alovio-product-options';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_filter( 'admin_body_class', array( $this, 'body_class' ) );
	}

	/** Full-bleed layout flag for the hub page only. */
	public function body_class( string $classes ): string {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && false !== strpos( (string) $screen->id, self::SLUG ) ) {
			$classes .= ' clpo-hub-page';
		}
		return $classes;
	}

	public function add_page(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Product Options', 'corelabs-product-options' ),
			__( 'Product Options', 'corelabs-product-options' ),
			'manage_woocommerce',
			self::SLUG,
			array( $this, 'render' )
		);
	}

	public function render(): void {
		// The hub app ships its own header — no wp-admin wrap/h1.
		echo '<div id="clpo-hub-root"></div>';
	}
}
