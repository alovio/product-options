<?php
/**
 * Uninstall handler. Only purges stored data when the site owner explicitly
 * opted in (option `apo_remove_data_on_uninstall`). Otherwise product field
 * definitions are left intact in case the plugin is reinstalled.
 */
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( get_option( 'apo_remove_data_on_uninstall' ) ) {
	global $wpdb;
	$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_apo_field_group' ) ); // phpcs:ignore WordPress.DB
	delete_option( 'apo_remove_data_on_uninstall' );
}
