<?php
/**
 * Uninstall handler. Only purges stored data when the site owner explicitly
 * opted in (option `clpo_remove_data_on_uninstall`). Otherwise product field
 * definitions are left intact in case the plugin is reinstalled.
 */
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( get_option( 'clpo_remove_data_on_uninstall' ) ) {
	global $wpdb;
	$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_clpo_field_group' ) ); // phpcs:ignore WordPress.DB
	delete_option( 'clpo_remove_data_on_uninstall' );
}
