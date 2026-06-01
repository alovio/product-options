<?php
/**
 * Plugin Name: Conditional Product Options for WooCommerce
 * Plugin URI: https://addons.itahir.com/conditional-product-options
 * Description: Add custom product fields with a drag-and-drop builder, conditional logic, and add-on pricing.
 * Version: 1.0.0
 * Author: CoreLabs
 * Author URI: https://addons.itahir.com
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Text Domain: conditional-product-options
 * Domain Path: /languages
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */
defined( 'ABSPATH' ) || exit;

define( 'APO_VERSION', '1.0.0' );
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
