<?php
/**
 * Silent AJAX plugin installer/activator for starter site demos.
 */

defined( 'ABSPATH' ) || exit;

class Customify_Starter_Sites_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	static $instance;

	/**
	 * Singleton.
	 *
	 * @return self
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Find plugin main file relative path (basename) from a slug (plugin directory slug).
	 *
	 * @param string $slug Directory slug like "elementor".
	 * @return string|false Relative path such as "elementor/elementor.php" or false if not installed.
	 */
	public static function get_plugin_basename_from_slug( $slug ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$slug = sanitize_key( $slug );
		if ( '' === $slug ) {
			return false;
		}

		// Plugins that live directly in wp-content/plugins/file.php use "." as dirname in export.
		if ( '.' === $slug ) {
			return false;
		}

		$all_plugins = get_plugins();
		if ( ! is_array( $all_plugins ) ) {
			return false;
		}

		foreach ( array_keys( $all_plugins ) as $rel_file ) {
			if ( dirname( $rel_file ) === $slug ) {
				return $rel_file;
			}

			if ( '.' === dirname( $rel_file ) ) {
				$base_slug = strtolower( basename( $rel_file, '.php' ) );
				if ( $base_slug === $slug ) {
					return $rel_file;
				}
			}
		}

		return false;
	}

	/**
	 * AJAX: install plugin from wordpress.org silently.
	 */
	public function ajax_install_plugin() {
		check_ajax_referer( 'customify_starter_sites', 'nonce' );

		if ( ! current_user_can( 'install_plugins' ) ) {
			wp_send_json_error( array( 'message' => 'access_denied' ), 403 );
		}

		$slug = isset( $_REQUEST['plugin'] ) ? sanitize_key( wp_unslash( $_REQUEST['plugin'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce checked above.

		if ( '' === $slug ) {
			wp_send_json_error( array( 'message' => 'invalid_plugin' ), 400 );
		}

		$basename = self::get_plugin_basename_from_slug( $slug );
		if ( $basename && is_readable( WP_PLUGIN_DIR . '/' . $basename ) ) {
			wp_send_json_success(
				array(
					'basename' => $basename,
					'status'   => 'already_installed',
				)
			);
		}

		include_once ABSPATH . 'wp-admin/includes/file.php';
		include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		if ( ! class_exists( 'Customify_Starter_Sites_Silent_Upgrader_Skin', false ) ) {
			require_once __DIR__ . '/class-silent-upgrader-skin.php';
		}

		$api = plugins_api(
			'plugin_information',
			array(
				'slug'   => $slug,
				'fields' => array( 'sections' => false ),
			)
		);

		if ( is_wp_error( $api ) || empty( $api->download_link ) ) {
			wp_send_json_error(
				array(
					/* translators: plugin slug */
					'message' => sprintf( __( 'Could not locate plugin "%s" on wordpress.org.', 'customify-starter-sites' ), $slug ),
					'slug'    => $slug,
				),
				404
			);
		}

		$skin     = new Customify_Starter_Sites_Silent_Upgrader_Skin(
			array(
				'title' => '',
			)
		);
		$upgrader = new Plugin_Upgrader( $skin );

		wp_raise_memory_limit( 'admin' );

		if ( false === WP_Filesystem() ) {
			wp_send_json_error( array( 'message' => 'filesystem_unavailable' ), 500 );
		}

		$result = $upgrader->install( $api->download_link );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
					'slug'    => $slug,
				),
				500
			);
		}

		if ( true !== $result ) {
			wp_send_json_error(
				array(
					'message' => __( 'Plugin installation failed.', 'customify-starter-sites' ),
					'slug'    => $slug,
				),
				500
			);
		}

		wp_clean_plugins_cache( true );

		$basename = self::get_plugin_basename_from_slug( $slug );
		if ( ! $basename ) {
			wp_send_json_error(
				array(
					'message' => __( 'Plugin installed but the main plugin file could not be detected.', 'customify-starter-sites' ),
					'slug'    => $slug,
				),
				500
			);
		}

		wp_send_json_success(
			array(
				'basename' => $basename,
				'status'   => 'installed',
			)
		);
	}

	/**
	 * AJAX: activate an installed plugin silently.
	 */
	public function ajax_activate_plugin() {
		check_ajax_referer( 'customify_starter_sites', 'nonce' );

		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_send_json_error( array( 'message' => 'access_denied' ), 403 );
		}

		$slug = isset( $_REQUEST['plugin'] ) ? sanitize_key( wp_unslash( $_REQUEST['plugin'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce checked above.

		if ( '' === $slug ) {
			wp_send_json_error( array( 'message' => 'invalid_plugin' ), 400 );
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( ! function_exists( 'activate_plugin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$basename = self::get_plugin_basename_from_slug( $slug );
		if ( ! $basename ) {
			wp_send_json_error(
				array(
					'message' => __( 'Plugin is not installed.', 'customify-starter-sites' ),
					'slug'    => $slug,
				),
				404
			);
		}

		if ( ! is_readable( WP_PLUGIN_DIR . '/' . $basename ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Plugin file is missing.', 'customify-starter-sites' ),
					'basename'=> $basename,
				),
				404
			);
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( is_plugin_active( $basename ) ) {
			wp_send_json_success(
				array(
					'basename' => $basename,
					'status'   => 'already_active',
				)
			);
		}

		$activated = activate_plugin( $basename, '', false, true );

		if ( is_wp_error( $activated ) ) {
			wp_send_json_error(
				array(
					'message' => $activated->get_error_message(),
					'basename'=> $basename,
				),
				500
			);
		}

		wp_send_json_success(
			array(
				'basename' => $basename,
				'status'   => 'activated',
			)
		);
	}
}
