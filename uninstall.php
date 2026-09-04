<?php
/**
 * Remove plugin-owned options and transients when the plugin is deleted.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Studio connection settings.
delete_option( 'custstsi_studio_url' );
delete_option( 'custstsi_studio_key' );
delete_option( 'custstsi_test_result' );

// Job pipeline state.
delete_option( 'custstsi_job_latest' );
delete_option( 'custstsi_imported_templates' );

// Job + proxy transients (job state, latest pointer, Studio proxy cache).
global $wpdb;
$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off cleanup on uninstall.
	"DELETE FROM {$wpdb->options}
	 WHERE option_name LIKE '\_transient\_custstsi\_%'
	    OR option_name LIKE '\_transient\_timeout\_custstsi\_%'"
);
