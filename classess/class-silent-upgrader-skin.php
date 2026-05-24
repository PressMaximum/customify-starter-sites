<?php
/**
 * Silent upgrader skin — load only after wp-admin/includes/class-wp-upgrader.php.
 *
 * @package Customify_Starter_Sites
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'Customify_Starter_Sites_Silent_Upgrader_Skin', false ) ) {
	return;
}

/**
 * Upgrader skin that emits no markup (silent install via AJAX).
 */
class Customify_Starter_Sites_Silent_Upgrader_Skin extends WP_Upgrader_Skin {

	/**
	 * Suppress installer feedback output.
	 *
	 * @param string $string Feedback message text.
	 * @param mixed  ...$args Optional feedback arguments for compatibility with core.
	 */
	public function feedback( $string, ...$args ) { // phpcs:ignore Squiz.Commenting.FunctionComment.MissingParam
	}

	public function header() {}

	public function footer() {}

	public function increment_header() {}

	public function increment_footer() {}
}
