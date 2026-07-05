<?php
/**
 * Uninstall handler. Only purges stored data when the site owner explicitly
 * opted in (option `clpo_remove_data_on_uninstall`). Otherwise option groups
 * and legacy field definitions are left intact in case the plugin is
 * reinstalled.
 */
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( get_option( 'clpo_remove_data_on_uninstall' ) ) {
	global $wpdb;

	// Option-group posts + their meta (2.0 storage).
	$clpo_group_ids = get_posts(
		array(
			'post_type'   => 'alovio_option_group',
			'post_status' => 'any',
			'numberposts' => -1,
			'fields'      => 'ids',
		)
	);
	foreach ( $clpo_group_ids as $clpo_gid ) {
		wp_delete_post( (int) $clpo_gid, true );
	}

	// Legacy 1.x product meta + migration markers.
	$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_clpo_field_group' ) ); // phpcs:ignore WordPress.DB
	delete_post_meta_by_key( '_clpo_migrated_to' );

	delete_option( 'clpo_version' );
	delete_option( 'clpo_remove_data_on_uninstall' );
}
