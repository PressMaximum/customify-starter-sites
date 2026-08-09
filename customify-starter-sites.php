<?php
defined( 'ABSPATH' ) || exit;

/*
Plugin Name: Customify Starter Sites
Plugin URI: https://wpcustomify.com
Description: Import free sites built with the Customify theme.
Author: pressmaximum
Author URI: https://pressmaximum.com/customify
Version: 0.0.20
Requires at least: 5.0
Requires PHP: 7.4
Text Domain: customify-starter-sites
Domain Path: /languages
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

define( 'CUSTOMIFY_STARTER_SITES_VERSION', '0.0.20' );
define( 'CUSTOMIFY_STARTER_SITES_FILE', __FILE__ );
define( 'CUSTOMIFY_STARTER_SITES_URL', untrailingslashit( plugins_url( '', CUSTOMIFY_STARTER_SITES_FILE ) ) );
define( 'CUSTOMIFY_STARTER_SITES_PATH', plugin_dir_path( CUSTOMIFY_STARTER_SITES_FILE ) );

/**
 * Safely decode legacy serialized arrays without instantiating arbitrary classes.
 *
 * New starter-site configuration must use JSON. This helper only exists for
 * legacy WXR/meta values that WordPress historically serialized.
 *
 * @param mixed             $value           Value that may be serialized.
 * @param bool|string[]     $allowed_classes Classes allowed during decoding.
 * @return mixed
 */
function customify_starter_sites_maybe_unserialize( $value, $allowed_classes = false ) {
	if ( ! is_string( $value ) || ! is_serialized( $value ) ) {
		return $value;
	}

	return unserialize( trim( $value ), array( 'allowed_classes' => $allowed_classes ) );
}

if ( is_admin() ) {
	if ( ! class_exists( 'WP_Importer' ) ) {
		defined( 'WP_LOAD_IMPORTERS' ) || define( 'WP_LOAD_IMPORTERS', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- WordPress core importer bootstrap constant.
		require_once ABSPATH . 'wp-admin/includes/class-wp-importer.php';
	}

	require_once CUSTOMIFY_STARTER_SITES_PATH . 'classess/class-placeholder.php';

	require_once CUSTOMIFY_STARTER_SITES_PATH . 'importer/class-logger.php';
	require_once CUSTOMIFY_STARTER_SITES_PATH . 'importer/class-logger-serversentevents.php';
	require_once CUSTOMIFY_STARTER_SITES_PATH . 'importer/class-wxr-importer.php';
	require_once CUSTOMIFY_STARTER_SITES_PATH . 'importer/class-wxr-import-info.php';
	require_once CUSTOMIFY_STARTER_SITES_PATH . 'importer/class-wxr-import-ui.php';

	require_once CUSTOMIFY_STARTER_SITES_PATH . 'classess/class-plugin.php';
	require_once CUSTOMIFY_STARTER_SITES_PATH . 'classess/class-sites.php';
	require_once CUSTOMIFY_STARTER_SITES_PATH . 'classess/class-export.php';
	require_once CUSTOMIFY_STARTER_SITES_PATH . 'classess/class-ajax.php';

	Customify_Starter_Sites::get_instance();
	new Customify_Starter_Sites_Ajax();
}

if ( is_admin() ) {
	function customify_starter_sites_admin_footer( $html ) {
		if ( isset( $_GET['dev'] ) && current_user_can( 'export' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only developer links.
			$sc              = get_current_screen();
			$starter_screens = array( 'customify_page_customify-starter-sites', 'toplevel_page_customify-starter-sites' );
			if ( $sc && in_array( $sc->id, $starter_screens, true ) ) {
				$html = '<a class="page-title-action" href="' . esc_url( admin_url( 'export.php?content=all&download=true&from_customify=placeholder' ) ) . '">Export XML Placeholder</a> - <a class="page-title-action" href="' . esc_url( admin_url( 'export.php?content=all&download=true&from_customify' ) ) . '">Export XML</a> - <a class="page-title-action" href="' . esc_url( wp_nonce_url( admin_url( 'admin-ajax.php?action=cs_export' ), 'customify_starter_sites', 'nonce' ) ) . '">Export Config</a>';
			}
		}
		return $html;
	}
	add_filter( 'update_footer', 'customify_starter_sites_admin_footer', 199 );
}
