<?php
/**
 * Plugin Name: Advanced Product Options for WooCommerce
 * Description: Add custom product fields with a drag-drop builder and conditional logic.
 * Version: 0.1.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Text Domain: advanced-product-options
 * License: GPL-2.0-or-later
 */
defined( 'ABSPATH' ) || exit;

define( 'APO_VERSION', '0.1.0' );
define( 'APO_FILE', __FILE__ );
define( 'APO_PATH', plugin_dir_path( __FILE__ ) );
define( 'APO_URL', plugin_dir_url( __FILE__ ) );

require_once APO_PATH . 'vendor/autoload.php';

// Declare HPOS (custom order tables) compatibility.
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', APO_FILE, true );
	}
} );

add_action( 'plugins_loaded', function () {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}
	\APO\Plugin::instance()->boot();
} );
