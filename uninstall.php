<?php
/**
 * Remove plugin-owned settings when the plugin is deleted.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'customify_import_placeholder_only' );
delete_option( 'customify_imported_site_slug' );
