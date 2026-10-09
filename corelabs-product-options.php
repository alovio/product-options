<?php
/**
 * Plugin Name: Alovio Product Options for WooCommerce
 * Plugin URI: https://alovio.org/product-options
 * Update URI: https://licenses.alovio.org/corelabs-product-options
 * Description: Add custom product fields with a drag-and-drop builder, conditional logic, and add-on pricing.
 * Version: 2.6.1
 * Author: Alovio
 * Author URI: https://alovio.org
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Text Domain: corelabs-product-options
 * Domain Path: /languages
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */
defined( 'ABSPATH' ) || exit;

define( 'CLPO_VERSION', '2.6.1' );
define( 'CLPO_FILE', __FILE__ );
define( 'CLPO_PATH', plugin_dir_path( __FILE__ ) );
define( 'CLPO_URL', plugin_dir_url( __FILE__ ) );

require_once CLPO_PATH . 'vendor/autoload.php';

// Updates come from our own server rather than the plugin directory. Registered
// outside the WooCommerce gate below on purpose: a site with WooCommerce
// switched off still has to be able to update this plugin.
( new \CoreLabs\ProductOptions\Updater() )->register();

// Declare HPOS (custom order tables) compatibility.
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', CLPO_FILE, true );
	}
} );

add_action( 'plugins_loaded', function () {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}
	\CoreLabs\ProductOptions\Plugin::instance()->boot();
} );
