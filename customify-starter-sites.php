<?php
/*
Plugin Name: Starter Templates – Website Templates & Demo Import for Block Editor
Plugin URI: https://pressmaximum.com/website-templates/
Description: Browse website templates for the WordPress block editor, preview starter sites, choose colors and fonts, and import demo content with guided setup.
Author: pressmaximum
Author URI: https://pressmaximum.com/customify
Version: 1.0.6
Requires at least: 7.0
Requires PHP: 7.4
Text Domain: customify-starter-sites
Domain Path: /languages
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

defined( 'ABSPATH' ) || exit;

define( 'CUSTOMIFY_STARTER_SITES_VERSION', '1.0.6' );
define( 'CUSTOMIFY_STARTER_SITES_FILE', __FILE__ );
define( 'CUSTOMIFY_STARTER_SITES_URL', trailingslashit( plugins_url( '', CUSTOMIFY_STARTER_SITES_FILE ) ) );
define( 'CUSTOMIFY_STARTER_SITES_PATH', trailingslashit( plugin_dir_path( CUSTOMIFY_STARTER_SITES_FILE ) ) );

/**
 * Boot the importer.
 *
 * The importer is a background-job pipeline (cron-spawned runner +
 * transient-backed job state + REST progress polling) with a React admin
 * UI. It embeds into the Customify theme dashboard via the Customify
 * adapter in inc/generic/.
 *
 * Bootstrap fires for admin pageviews, AJAX, REST, and WP-Cron requests
 * only; front-end (public site) requests are skipped since the importer
 * has no front-end surface. The REST branch is required because a REST
 * request has is_admin() === false, so without it the Generic track's
 * routes would never register and the React UI would 404. The cron
 * branch is required so the queued import job's action handler exists
 * when wp-cron.php processes the queue.
 */
function customify_starter_sites_init() {
	// REST_REQUEST is defined inside parse_request (after plugins_loaded),
	// so it is not yet set when this hook fires. Sniff the URL the way WP
	// core itself does before the constant is available.
	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	$rest_prefix = trailingslashit( rest_get_url_prefix() );
	$is_rest     = isset( $_GET['rest_route'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only request-type sniff, no state change.
		|| ( '' !== $rest_prefix && false !== strpos( $request_uri, '/' . trim( $rest_prefix, '/' ) . '/' ) );

	$needs_boot = is_admin()
		|| ( defined( 'DOING_AJAX' ) && DOING_AJAX )
		|| ( defined( 'DOING_CRON' ) && DOING_CRON )
		|| $is_rest;
	if ( ! $needs_boot ) {
		return;
	}

	require_once CUSTOMIFY_STARTER_SITES_PATH . 'inc/generic/bootstrap.php';
}
add_action( 'plugins_loaded', 'customify_starter_sites_init' );
