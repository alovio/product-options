<?php
/**
 * Plugin Name: Conditional Product Options — Pro
 * Description: Unlocks the Pro features of Conditional Product Options for WooCommerce (advanced conditional logic, per-unit & percentage pricing, colour swatches, date fields).
 * Version: 0.1.0
 * Requires PHP: 7.4
 * Requires Plugins: conditional-product-options
 * License: GPL-2.0-or-later
 *
 * Sold separately (e.g. via Gumroad / code-heaven). When active alongside the
 * free plugin it flips the `apo_is_pro` gate, which the free plugin's ProModule
 * uses to lift the free restrictions. A license-key check can be layered on the
 * same filter later without touching the free plugin.
 */
defined( 'ABSPATH' ) || exit;

add_filter( 'apo_is_pro', '__return_true' );
