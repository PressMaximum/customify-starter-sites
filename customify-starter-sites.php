<?php
/*
Plugin Name: Customify Starter Sites
Plugin URI: https://wpcustomify.com
Description: Import free sites build with Customify theme.
Author: pressmaximum
Author URI: https://pressmaximum.com/customify
Version: 0.0.17
Text Domain: customify-starter-sites
Domain Path: /languages
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

define( 'CUSTOMIFY_STARTER_SITES_FILE',__FILE__ );
define( 'CUSTOMIFY_STARTER_SITES_URL', untrailingslashit( plugins_url( '', CUSTOMIFY_STARTER_SITES_FILE ) ) );
define( 'CUSTOMIFY_STARTER_SITES_PATH', dirname( __FILE__ ) );

if ( ! class_exists( 'WP_Importer' ) ) {
	defined( 'WP_LOAD_IMPORTERS' ) || define( 'WP_LOAD_IMPORTERS', true );
	require ABSPATH . '/wp-admin/includes/class-wp-importer.php';
}

require dirname( __FILE__ ) . '/classess/class-placeholder.php';

require dirname( __FILE__ ) . '/importer/class-logger.php';
require dirname( __FILE__ ) . '/importer/class-logger-serversentevents.php';
require dirname( __FILE__ ) . '/importer/class-wxr-importer.php';
require dirname( __FILE__ ) . '/importer/class-wxr-import-info.php';
require dirname( __FILE__ ) . '/importer/class-wxr-import-ui.php';

require dirname( __FILE__ ) . '/classess/class-plugin.php';
require dirname( __FILE__ ) . '/classess/class-sites.php';
require dirname( __FILE__ ) . '/classess/class-export.php';
require dirname( __FILE__ ) . '/classess/class-ajax.php';


Customify_Starter_Sites::get_instance();
new Customify_Starter_Sites_Ajax();

/**
 * Redirect to import page
 *
 * @param $plugin
 * @param bool|false $network_wide
 */
function customify_starter_sites_plugin_activate( $plugin, $network_wide = false ) {
	if ( ! $network_wide && $plugin == plugin_basename( __FILE__ ) ) {

		$url = add_query_arg(
			array(
				'page' => "customify-starter-sites",
			),
			admin_url( 'themes.php' )
		);

		wp_safe_redirect( $url );
		die();

	}
}
add_action( 'activated_plugin', 'customify_starter_sites_plugin_activate', 90, 2 );

/**
 * Preview Bridge — frontend iframe listener.
 *
 * Enqueues `assets/js/preview-bridge.js` on the frontend when the
 * active theme is Customify (or a Customify child). The script bails
 * unless it's running inside an iframe, so the cost on normal page
 * views is one extra script tag with no runtime work.
 *
 * Contract: receives `{ type: 'fdi-preview-style', css }` postMessage
 * from the FameThemes Demo Importer wizard and writes the supplied CSS
 * into a single managed `<style id="cpb-overrides">` re-appended to the
 * bottom of `<head>` on every message (so the override always beats
 * the theme's later inline rules). Sends `{ type: 'fdi-preview-ready' }`
 * upstream on load so the wizard can replay its last payload.
 *
 * Kept inline here (rather than its own file) because the surface is
 * tiny — one enqueue gated on the active stylesheet.
 */
function customify_starter_sites_preview_bridge_enqueue() {
	$template = (string) get_template();
	if ( 'customify' !== $template ) {
		return;
	}
	wp_enqueue_script(
		'customify-preview-bridge',
		CUSTOMIFY_STARTER_SITES_URL . '/assets/js/preview-bridge.js',
		array(),
		'0.2.0',
		true
	);
}
add_action( 'wp_enqueue_scripts', 'customify_starter_sites_preview_bridge_enqueue' );

if ( is_admin() ) {
	function customify_starter_sites_admin_footer( $html ) {
		if ( isset( $_REQUEST['dev'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$sc = get_current_screen();
			if ( $sc->id == 'appearance_page_customify-starter-sites' ) {
				$html = '<a class="page-title-action" href="' . esc_url( admin_url( 'export.php?content=all&download=true&from_customify=placeholder' ) ) . '">Export XML Placeholder</a> - <a class="page-title-action" href="' . esc_url( admin_url( 'export.php?content=all&download=true&from_customify' ) ) . '">Export XML</a> - <a class="page-title-action" href="' . esc_url( wp_nonce_url( admin_url( 'admin-ajax.php?action=cs_export' ), 'customify_starter_sites', 'nonce' ) ) . '">Export Config</a>';
			}
		}
		return $html;
	}
	add_filter( 'update_footer', 'customify_starter_sites_admin_footer', 199 );
}
