<?php

defined( 'ABSPATH' ) || exit;

class Customify_Starter_Sites_Ajax {
	protected $mapping       = array();
	public $placeholder_id   = 0;
	public $placeholder_post = false;
	public $placeholder_url  = '';

	function __construct() {
		// Install Plugin
		add_action( 'wp_ajax_custstsi_install_plugin', array( Customify_Starter_Sites_Plugin::get_instance(), 'ajax_install_plugin' ) );
		// Active Plugin
		add_action( 'wp_ajax_custstsi_active_plugin', array( Customify_Starter_Sites_Plugin::get_instance(), 'ajax_activate_plugin' ) );

		// Import Content
		add_action( 'wp_ajax_custstsi_import__check', array( $this, 'ajax_import__check' ) );
		add_action( 'wp_ajax_custstsi_import_content', array( $this, 'ajax_import_content' ) );
		add_action( 'wp_ajax_custstsi_import_options', array( $this, 'ajax_import_options' ) );

		// Download files
		add_action( 'wp_ajax_custstsi_download_files', array( $this, 'ajax_download_files' ) );

		add_action( 'wp_ajax_custstsi_export', array( $this, 'ajax_export' ) );
	}

	protected function verify_ajax_referer() {
		check_ajax_referer( 'customify_starter_sites', 'nonce' );
	}

	/**
	 * Option name WordPress core uses to store a widget type's instances.
	 *
	 * The `widget_{$id_base}` option namespace is defined and owned by
	 * WordPress core (WP_Widget), not by this plugin. This helper simply
	 * reproduces that core naming scheme so widget instances can be read and
	 * written during import/export; it is not a plugin-owned option prefix.
	 *
	 * @param string $id_base Widget ID base, e.g. "text" or "nav_menu".
	 * @return string Core option name, e.g. "widget_text".
	 */
	protected function core_widget_option_name( $id_base ) {
		return sprintf( 'widget_%s', (string) $id_base );
	}

	function ajax_import__check() {
		$this->verify_ajax_referer();
		$this->user_can();
		wp_die( 'ajax_import__check' );
	}

	function get_export_file_name() {
		$sitename = sanitize_key( get_bloginfo( 'name' ) );
		if ( ! empty( $sitename ) ) {
			$sitename .= '-';
		}
		$date           = date_i18n( 'YmdHi' );
		$active_plugins = get_option( 'active_plugins' );
		$builders       = array();

		foreach ( $active_plugins as $slug ) {
			if ( strpos( $slug, 'elementor' ) !== false ) {
				$builders['elementor'] = 'elementor-';
			} elseif ( strpos( $slug, 'beaver-builder' ) !== false ) {
				$builders['beaver-builder'] = 'beaver-builder-';
			} elseif ( strpos( $slug, 'gutenberg' ) !== false ) {
				$builders['gutenberg'] = 'gutenberg-';
			}
		}

		$n = count( $builders );
		if ( $n > 1 ) {
			$builder = "{$n}-builders-";
		} elseif ( $n == 1 ) {
			$b       = current( $builders );
			$builder = "{$b}";
		} else {
			$builder = 'no-builders-';
		}

		$file_name = $sitename . $builder . $date;

		return $file_name;
	}

	function the_title_rss( $title ) {
		if ( function_exists( 'wxr_cdata' ) ) {
			return wxr_cdata( $title );
		}

		return $title;
	}

	/**
	 * Available widgets
	 *
	 * Gather site's widgets into array with ID base, name, etc.
	 * Used by export and import functions.
	 *
	 * @since 0.4
	 * @global array $wp_registered_widget_updates
	 * @return array Widget information
	 */
	function _get_available_widgets() {

		global $wp_registered_widget_controls;

		$widget_controls = $wp_registered_widget_controls;

		$available_widgets = array();

		foreach ( $widget_controls as $widget ) {

			if ( ! empty( $widget['id_base'] ) && ! isset( $available_widgets[ $widget['id_base'] ] ) ) { // no dupes

				$available_widgets[ $widget['id_base'] ]['id_base'] = $widget['id_base'];
				$available_widgets[ $widget['id_base'] ]['name']    = $widget['name'];

			}
		}

		return $available_widgets;

	}

	/**
	 * Generate Widgets export data
	 *
	 * @since 0.1
	 * @return string Export file contents
	 */
	function _get_widgets_export_data() {

		// Get all available widgets site supports
		$available_widgets = $this->_get_available_widgets();

		// Get all widget instances for each widget
		$widget_instances = array();
		foreach ( $available_widgets as $widget_data ) {

			// Get all instances for this ID base
			$instances = get_option( $this->core_widget_option_name( $widget_data['id_base'] ) );

			// Have instances
			if ( ! empty( $instances ) ) {

				// Loop instances
				foreach ( $instances as $instance_id => $instance_data ) {

					// Key is ID (not _multiwidget)
					if ( is_numeric( $instance_id ) ) {
						$unique_instance_id                      = $widget_data['id_base'] . '-' . $instance_id;
						$widget_instances[ $unique_instance_id ] = $instance_data;
					}
				}
			}
		}

		// Gather sidebars with their widget instances
		$sidebars_widgets          = get_option( 'sidebars_widgets' ); // get sidebars and their unique widgets IDs
		$sidebars_widget_instances = array();
		foreach ( $sidebars_widgets as $sidebar_id => $widget_ids ) {

			// Skip inactive widgets
			if ( 'wp_inactive_widgets' == $sidebar_id ) {
				continue;
			}

			// Skip if no data or not an array (array_version)
			if ( ! is_array( $widget_ids ) || empty( $widget_ids ) ) {
				continue;
			}

			// Loop widget IDs for this sidebar
			foreach ( $widget_ids as $widget_id ) {

				// Is there an instance for this widget ID?
				if ( isset( $widget_instances[ $widget_id ] ) ) {
					// Add to array
					$sidebars_widget_instances[ $sidebar_id ][ $widget_id ] = $widget_instances[ $widget_id ];
				}
			}
		}

		// Filter pre-encoded data
		$data = apply_filters( 'customify_starter_sites_export_widgets_data', $sidebars_widget_instances );

		// Encode the data for file contents
		return $data;

	}

	function _get_elementor_settings() {
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT * FROM `{$wpdb->options}` WHERE `option_name` LIKE 'elementor_scheme_%'", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$data = array();
		foreach ( (array) $rows as $row ) {
			$data[ $row['option_name'] ] = get_option( $row['option_name'] );
		}

		return $data;
	}

	function ajax_export() {

		$this->verify_ajax_referer();
		$this->user_can();

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		ob_start();
		ob_end_clean();
		ob_flush();

		$filename = $this->get_export_file_name() . '.json';

		header( 'Content-Description: File Transfer' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		header( 'Content-Type: application/xml; charset=' . get_option( 'blog_charset' ), true );

		$nav_menu_locations = get_theme_mod( 'nav_menu_locations' );

		$options                  = $this->_get_elementor_settings();
		$options['show_on_front'] = get_option( 'show_on_front' );

		$active_plugins = get_option( 'active_plugins' );
		$all_plugins    = get_plugins();
		if ( ! is_array( $all_plugins ) ) {
			$all_plugins = array();
		}

		$include_plugins = apply_filters(
			'customify-sites/export_plugins/exclude',
			array(
				'customify-sites-library'         => 1,
				'customify-sites-api'     => 1,
				'customify-sites-listing' => 1,
			)
		);

		// List Plugins
		$plugins = array();
		foreach ( $active_plugins as $file ) {
			if ( isset( $all_plugins[ $file ] ) ) {
				$info = $all_plugins[ $file ];
				$slug = dirname( $file );
				if ( ! isset( $include_plugins[ $slug ] ) ) {
					$plugins[ $slug ] = $info['Name'];
				}
			}
		}

		$config = array(
			'_recommend_plugins' => $this->filter_skipped_recommend_plugins( $plugins ),
			'home_url'           => home_url( '/' ),
			'menus'              => $nav_menu_locations,
			'pages'              => array(
				'page_on_front'  => get_option( 'page_on_front' ),
				'page_for_posts' => get_option( 'page_for_posts' ),
			),
			'options'            => $options,
			'theme_mods'         => get_theme_mods(),
			'widgets'            => $this->_get_widgets_export_data(),
		);
		// myaccount, edit_address, shop, cart, checkout, pay, view_order, terms
		/**
		 * @see wc_get_page_id
		 */
		if ( function_exists( 'wc_get_page_id' ) ) {
			foreach ( array( 'myaccount', 'shop', 'cart', 'checkout', 'view_order', 'terms' ) as $page_name ) {
				$id = wc_get_page_id( $page_name );
				if ( $id > 0 ) {
					$config['pages'][ 'woocommerce_' . $page_name . '_page_id' ] = $id;
				}
			}
		}

		$config = apply_filters( 'customify-sites/export/json', $config );

		echo wp_json_encode( $config, JSON_PRETTY_PRINT );
		exit;
	}

	function install_theme() {
		/**
		 * @see app/public/wp-admin/update.php L215
		 *
		 * https://beacon.dev/wp-admin/admin-ajax.ph
		 * slug: switty
		 * action: install-theme
		 * _ajax_nonce: 647d5a7e33
		 */
	}

	function user_can() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'customify-starter-sites' ), '', array( 'response' => 403 ) );
		}
	}

	/**
	 * Plugin directory slugs the starter wizard never installs (recommended list is stripped).
	 *
	 * @return string[]
	 */
	public function get_skipped_recommend_plugin_slugs() {
		$skipped = array(
			'custom-sidebars',
			'gutenberg',
		);

		return apply_filters( 'customify_starter_sites/skipped_recommend_plugin_slugs', $skipped );
	}

	/**
	 * Drop skipped plugin slugs from _recommend_plugins (slug => name).
	 *
	 * @param mixed $plugins List from starter JSON export.
	 * @return array
	 */
	public function filter_skipped_recommend_plugins( $plugins ) {
		if ( ! is_array( $plugins ) ) {
			return array();
		}

		$skipped = array_map( 'sanitize_key', $this->get_skipped_recommend_plugin_slugs() );
		$skip    = array_flip( $skipped );

		$filtered = array();
		foreach ( $plugins as $slug => $label ) {
			$slug = sanitize_key( $slug );
			if ( '' === $slug || isset( $skip[ $slug ] ) ) {
				continue;
			}
			$filtered[ $slug ] = sanitize_text_field( (string) $label );
		}

		return $filtered;
	}

	function ajax_import_content() {

		$this->verify_ajax_referer();
		$this->user_can();

		$import_ui = new Customify_Starter_Sites_WXR_Import_UI();
		$import_ui->import();

		wp_die( 'content_imported' );
	}


	function is_url( $url ) {
		$result = ( false !== filter_var( $url, FILTER_VALIDATE_URL ) );

		return $result;
	}

	function ajax_download_files() {
		$this->verify_ajax_referer();
		$this->user_can();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Nonce verified in verify_ajax_referer() above.
		// try to get files exists
		$slug             = isset( $_REQUEST['site_slug'] ) ? sanitize_key( wp_unslash( $_REQUEST['site_slug'] ) ) : '';
		$builder          = isset( $_REQUEST['builder'] ) ? sanitize_key( wp_unslash( $_REQUEST['builder'] ) ) : '';
		$resources        = isset( $_REQUEST['resources'] ) && is_array( $_REQUEST['resources'] ) ? map_deep( wp_unslash( $_REQUEST['resources'] ), 'sanitize_text_field' ) : array();
		$placeholder_only = isset( $_REQUEST['placeholder_only'] ) ? wp_validate_boolean( sanitize_text_field( wp_unslash( $_REQUEST['placeholder_only'] ) ) ) : true;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$placeholder_only = (bool) apply_filters( 'customify_import_placeholder_only', $placeholder_only );

		update_option( 'customify_import_placeholder_only', $placeholder_only );


		$resource_defaults = array(
				'xml_url'                            => '',
				'xml_placeholder_url'                => '',
				'json_url'                           => '',

				'elementor_xml_url'                  => '',
				'elementor_xml_placeholder_url'      => '',
				'elementor_json_url'                 => '',

				'beaver_builder_xml_url'             => '',
				'beaver_builder_xml_placeholder_url' => '',
				'beaver_builder_json_url'             => '',
		);
		$resources         = array_intersect_key( $resources, $resource_defaults );
		$resources         = wp_parse_args( $resources, $resource_defaults );

		foreach ( $resources as $k => $v ) {
			if ( 'false' === $v ) {
				$resources[ $k ] = false;
			} elseif ( is_string( $v ) ) {
				$resources[ $k ] = esc_url_raw( $v, array( 'https' ) );
			} else {
				$resources[ $k ] = false;
			}
		}

		$xml_url     = false;
		$json_url    = false;
		$suffix_name = '-no-builder';
		switch ( $builder ) {
			case 'beaver-builder':
			case 'beaver-builder-lite-version':
				if ( $placeholder_only && $resources['beaver_builder_xml_placeholder_url'] ) {
					$xml_url     = $resources['beaver_builder_xml_placeholder_url'];
					$suffix_name = '-beaver-builder-placeholder';
				} else {
					$xml_url     = $resources['beaver_builder_xml_url'];
					$suffix_name = '-beaver-builder';
				}

				$json_url = $resources['beaver_builder_json_url'];
				break;
			case 'elementor':
			case 'all':
				if ( $placeholder_only && $resources['elementor_xml_placeholder_url'] ) {
					$suffix_name = '-elementor-placeholder';
					$xml_url     = $resources['elementor_xml_placeholder_url'];
				} else {
					$suffix_name = '-elementor';
					$xml_url     = $resources['elementor_xml_url'];
				}

				$json_url = $resources['elementor_json_url'];
				break;
		}

		if ( ! $xml_url && $placeholder_only && $resources['xml_placeholder_url'] ) {
			$xml_url     = $resources['xml_placeholder_url'];
			$suffix_name = '-no-builder-placeholder';
		}

		if ( ! $xml_url ) {
			$xml_url = $resources['xml_url'];
		}
		if ( ! $json_url ) {
			$json_url = $resources['json_url'];
		}

		$return = array(
			'xml_id'             => 0,
			'json_id'            => 0,
			'summary'            => array(),
			'texts'              => array(),
			'_recommend_plugins' => array(),
		);

		if ( ! $slug ) {
			wp_send_json_error( array( 'message' => __( 'Invalid starter site.', 'customify-starter-sites' ) ), 400 );
		}
		$xml_file_name  = sanitize_file_name( basename( (string) wp_parse_url( $xml_url, PHP_URL_PATH ) ) );
		$json_file_name = sanitize_file_name( basename( (string) wp_parse_url( $json_url, PHP_URL_PATH ) ) );

		/*
		$xml_file_name = str_replace( '.xml', '.xml', basename( $xml_url ) );
		$json_file_name = str_replace( '.json', '.xml', basename( $json_url ) );
		$xml_file_name = sanitize_title( $xml_file_name );
		$json_file_name = sanitize_title( $json_file_name );
		*/

		// $xml_file_name = $slug.'-content'.$suffix_name;
		// $json_file_name = $slug.'-config'.$suffix_name;
		$xml_file_exists  = get_page_by_path( str_replace( '.', '-', $xml_file_name ), OBJECT, 'attachment' );
		$json_file_exists = get_page_by_path( str_replace( '.', '-', $json_file_name ), OBJECT, 'attachment' );
		if ( $xml_file_exists && $xml_url === get_post_meta( $xml_file_exists->ID, '_customify_starter_source_url', true ) ) {
			$return['xml_id'] = $xml_file_exists->ID;
		} else {
			$return['xml_id'] = self::download_file( $xml_url, $xml_file_name );
		}
		if ( is_wp_error( $return['xml_id'] ) || ! $return['xml_id'] ) {
			$message = is_wp_error( $return['xml_id'] ) ? $return['xml_id']->get_error_message() : __( 'The starter content could not be downloaded.', 'customify-starter-sites' );
			wp_send_json_error( array( 'message' => $message ), 400 );
		}

		if ( $json_file_exists && $json_url === get_post_meta( $json_file_exists->ID, '_customify_starter_source_url', true ) ) {
			$return['json_id'] = $json_file_exists->ID;
		} else {
			$return['json_id'] = self::download_file( $json_url, $json_file_name );
		}
		if ( is_wp_error( $return['json_id'] ) || ! $return['json_id'] ) {
			$message = is_wp_error( $return['json_id'] ) ? $return['json_id']->get_error_message() : __( 'The starter configuration could not be downloaded.', 'customify-starter-sites' );
			wp_send_json_error( array( 'message' => $message ), 400 );
		}

		$import_ui         = new Customify_Starter_Sites_WXR_Import_UI();
		$return['summary'] = $import_ui->get_data_for_attachment( $return['xml_id'] );
		if ( is_wp_error( $return['summary'] ) ) {
			wp_send_json_error( array( 'message' => $return['summary']->get_error_message() ), 400 );
		}

		$return['summary'] = (array) $return['summary'];
		if ( ! is_array( $return['summary'] ) ) {
			$return['summary'] = array();
		}

		$return['summary'] = wp_parse_args(
			$return['summary'],
			array(
				'post_count'    => 0,
				'media_count'   => 0,
				'user_count'    => 0,
				'term_count'    => 0,
				'comment_count' => 0,
				'users'         => 0,
			)
		);

		if ( isset( $return['summary']['users'] ) ) {
			$return['summary']['user_count'] = count( $return['summary']['users'] );
		}

		$return['texts']['post_count']    = sprintf( _n( '%d post (including CPT)', '%d posts (including CPTs)', $return['summary']['post_count'], 'customify-starter-sites' ), $return['summary']['post_count'] ); // phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment
		$return['texts']['media_count']   = sprintf( _n( '%d media item', '%d media items', $return['summary']['media_count'], 'customify-starter-sites' ), $return['summary']['media_count'] ); // phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment
		$return['texts']['user_count']    = sprintf( _n( '%d user', '%d users', $return['summary']['user_count'], 'customify-starter-sites' ), $return['summary']['user_count'] ); // phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment
		$return['texts']['term_count']    = sprintf( _n( '%d term', '%d terms', $return['summary']['term_count'], 'customify-starter-sites' ), $return['summary']['term_count'] ); // phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment
		$return['texts']['comment_count'] = sprintf( _n( '%d comment', '%d comments', $return['summary']['comment_count'], 'customify-starter-sites' ), $return['summary']['comment_count'] ); // phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment

		if ( $return['json_id'] ) {
			$options = $this->get_config_options( $return['json_id'] );
			if ( isset( $options['_recommend_plugins'] ) ) {
				$return['_recommend_plugins'] = $this->filter_skipped_recommend_plugins( $options['_recommend_plugins'] );
			}
		}

		update_option( 'customify_imported_site_slug', $slug );

		wp_send_json( $return );
	}

	/**
	 * Handles a side-loaded file in the same way as an uploaded file is handled by media_handle_upload().
	 *
	 * @since 2.6.0
	 *
	 * @param array  $file_array Array similar to a `$_FILES` upload array.
	 * @param int    $post_id    The post ID the media is associated with.
	 * @param string $desc       Optional. Description of the side-loaded file. Default null.
	 * @param array  $post_data  Optional. Post data to override. Default empty array.
	 *
	 * @return int|object The ID of the attachment or a WP_Error on failure.
	 */
	static function media_handle_sideload( $file_array, $post_id, $desc = null, $post_data = array(), $save_attachment = true ) {
		$overrides = array(
			'test_form' => false,
			'test_type' => true,
			'mimes'     => array(
				'xml'  => 'application/xml',
				'json' => 'application/json',
			),
		);

		$time = current_time( 'mysql' );
		if ( $post = get_post( $post_id ) ) {
			if ( substr( $post->post_date, 0, 4 ) > 0 ) {
				$time = $post->post_date;
			}
		}

		$file = wp_handle_sideload( $file_array, $overrides, $time );
		if ( isset( $file['error'] ) ) {
			return new WP_Error( 'upload_error', $file['error'] );
		}

		$url     = $file['url'];
		$type    = $file['type'];
		$file    = $file['file'];
		$title   = sanitize_text_field( $file_array['name'] );
		$content = '';

		if ( $save_attachment ) {
			if ( isset( $desc ) ) {
				$title = $desc;
			}

			// Construct the attachment array.
			$attachment = array_merge(
				array(
					'post_mime_type' => $type,
					'guid'           => $url,
					'post_parent'    => $post_id,
					'post_title'     => $title,
					'post_content'   => $content,
				),
				$post_data
			);

			// This should never be set as it would then overwrite an existing attachment.
			unset( $attachment['ID'] );

			// Save the attachment metadata
			$id = wp_insert_attachment( $attachment, $file, $post_id );

			return $id;
		} else {
			return $file;
		}
	}


	/**
	 * Get config from json file
	 *
	 * @param $file_id
	 *
	 * @return array|mixed|null|object
	 */
	function get_config_options( $file_id ) {
		if ( is_numeric( $file_id ) ) {
			$file = get_attached_file( $file_id );
		} else {
			$file = $file_id;
		}

		global $wp_filesystem;
		if ( ! $file || ! WP_Filesystem() || ! $wp_filesystem || ! $wp_filesystem->exists( $file ) ) {
			return array();
		}

		if ( $wp_filesystem->is_readable( $file ) ) {
			$file_contents  = $wp_filesystem->get_contents( $file );
			$customize_data = json_decode( $file_contents, true );
		} else {
			return array();
		}

		return is_array( $customize_data ) && JSON_ERROR_NONE === json_last_error() ? $customize_data : array();
	}

	function ajax_import_options() {
		$this->verify_ajax_referer();
		$this->user_can();
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Nonce verified in verify_ajax_referer() above.
		$id     = isset( $_REQUEST['id'] ) ? absint( wp_unslash( $_REQUEST['id'] ) ) : 0;
		$xml_id = isset( $_REQUEST['xml_id'] ) ? absint( wp_unslash( $_REQUEST['xml_id'] ) ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$file       = $id ? get_attached_file( $id ) : false;
		$source_url = $id ? get_post_meta( $id, '_customify_starter_source_url', true ) : '';

		$xml_file       = $xml_id ? get_attached_file( $xml_id ) : false;
		$xml_source_url = $xml_id ? get_post_meta( $xml_id, '_customify_starter_source_url', true ) : '';

		if (
			$file
			&& 'json' === strtolower( pathinfo( $file, PATHINFO_EXTENSION ) )
			&& self::is_safe_remote_url( $source_url )
			&& $xml_file
			&& 'xml' === strtolower( pathinfo( $xml_file, PATHINFO_EXTENSION ) )
			&& self::is_safe_remote_url( $xml_source_url )
		) {
			$this->mapping = get_post_meta( $xml_id, '_wxr_importer_mapping', true );
			if ( ! is_array( $this->mapping ) ) {
				$this->mapping = array();
			}

			$customize_data = $this->get_config_options( $id );

			$customize_data = Customify_Starter_Sites_Placeholder::get_instance()->progress_config( $customize_data );

			if ( isset( $customize_data['options'] ) ) {
				$this->_import_options( $customize_data['options'] );
			}

			if ( isset( $customize_data['pages'] ) ) {
				$this->_import_options( $customize_data['pages'], true );
			}

			if ( isset( $customize_data['theme_mods'] ) ) {
				$this->_import_theme_mod( $customize_data['theme_mods'] );
			}

			if ( isset( $customize_data['widgets'] ) ) {
				$this->_import_widgets( $customize_data['widgets'] );
			}
		} else {
			wp_send_json_error( array( 'message' => __( 'Invalid starter configuration attachment.', 'customify-starter-sites' ) ), 400 );
		}

		$imported_slug = get_option( 'customify_imported_site_slug', '' );
		if ( ! empty( $imported_slug ) ) {
			$this->import_elementor_page_setting( $imported_slug );
		}

		wp_die( 'ajax_import_options' );
	}

	function _import_options( $options, $re_mapping_posts = false ) {
		if ( empty( $options ) || ! is_array( $options ) ) {
			return;
		}

		$page_options = array(
			'page_on_front',
			'page_for_posts',
			'woocommerce_myaccount_page_id',
			'woocommerce_shop_page_id',
			'woocommerce_cart_page_id',
			'woocommerce_checkout_page_id',
			'woocommerce_view_order_page_id',
			'woocommerce_terms_page_id',
		);
		$allowed      = $re_mapping_posts ? $page_options : array( 'show_on_front' );
		foreach ( array_keys( $options ) as $option_name ) {
			if ( is_string( $option_name ) && preg_match( '/^elementor_scheme_[a-z0-9_-]+$/', $option_name ) ) {
				$allowed[] = $option_name;
			}
		}
		$allowed = (array) apply_filters( 'customify_starter_sites_allowed_import_options', $allowed, $re_mapping_posts );
		$allowed = array_flip( array_map( 'sanitize_key', $allowed ) );

		$processed_posts = isset( $this->mapping['post'] ) ? $this->mapping['post'] : array();
		foreach ( $options as $option_name => $ops ) {
			$option_name = sanitize_key( $option_name );
			if ( '' === $option_name || ! isset( $allowed[ $option_name ] ) ) {
				continue;
			}

			if ( $re_mapping_posts ) {
				if ( isset( $processed_posts[ $ops ] ) ) {
					$ops = $processed_posts[ $ops ];
				}
				$ops = absint( $ops );
			} elseif ( 'show_on_front' === $option_name ) {
				$ops = in_array( $ops, array( 'posts', 'page' ), true ) ? $ops : 'posts';
			} else {
				$ops = $this->sanitize_import_value( $ops );
			}

			update_option( $option_name, $ops );
		}
	}

	/**
	 * Sanitize recursively imported scalar data while preserving its structure.
	 *
	 * @param mixed $value Imported value.
	 * @param bool  $allow_html Whether to allow post HTML in strings.
	 * @return mixed
	 */
	private function sanitize_import_value( $value, $allow_html = false ) {
		if ( is_array( $value ) ) {
			$sanitized = array();
			foreach ( $value as $key => $item ) {
				$sanitized[ is_string( $key ) ? sanitize_key( $key ) : $key ] = $this->sanitize_import_value( $item, $allow_html );
			}
			return $sanitized;
		}
		if ( is_object( $value ) ) {
			return $this->sanitize_import_value( get_object_vars( $value ), $allow_html );
		}
		if ( is_string( $value ) ) {
			return $allow_html ? wp_kses_post( $value ) : sanitize_text_field( $value );
		}

		return is_scalar( $value ) || null === $value ? $value : null;
	}

	/**
	 * Import widgets
	 */
	function _import_widgets( $data, $widgets_config = array() ) {
		global $wp_filesystem, $wp_registered_widget_controls, $wp_registered_sidebars;

		// add_filter( 'sidebars_widgets', array( $this, '_unset_sidebar_widget' ) );
		if ( empty( $data ) || ! is_array( $data ) ) {
			return;
		}
		if ( ! is_array( $widgets_config ) ) {
			$widgets_config = array();
		}

		$valid_sidebar    = false;
		$widget_instances = array();
		$imported_terms   = isset( $this->mapping['term_id'] ) ? $this->mapping['term_id'] : array();
		$imported_posts   = isset( $this->mapping['post'] ) ? $this->mapping['post'] : array();

		if ( ! is_array( $imported_terms ) ) {
			$imported_terms = array();
		}

		foreach ( $wp_registered_widget_controls as $widget_id => $widget ) {
			$base_id = isset( $widget['id_base'] ) ? $widget['id_base'] : null;
			if ( ! empty( $base_id ) && ! isset( $widget_instances[ $base_id ] ) ) {
				$widget_option_name           = $this->core_widget_option_name( $base_id );
				$widget_instances[ $base_id ] = get_option( $widget_option_name );
			}
		}

		// Delete old widgets
		update_option( 'sidebars_widgets', array() );

		foreach ( $data as $sidebar_id => $widgets ) {
			if ( 'wp_inactive_widgets' === $sidebar_id ) {
				continue;
			}
			if ( isset( $wp_registered_sidebars[ $sidebar_id ] ) ) {
				$valid_sidebar = true;
				$_sidebar_id   = $sidebar_id;
			} else {
				$_sidebar_id = 'wp_inactive_widgets';
			}
			if ( ! is_array( $widgets ) ) {
				continue;
			}
			foreach ( $widgets as $widget_instance_id => $widget ) {
				if ( ! is_array( $widget ) ) {
					continue;
				}
				$widget = $this->sanitize_import_value( $widget, true );
				if ( false !== strpos( $widget_instance_id, 'nav_menu' ) && ! empty( $widget['nav_menu'] ) ) {
					$widget['nav_menu'] = isset( $imported_terms[ $widget['nav_menu'] ] ) ? $imported_terms[ $widget['nav_menu'] ] : 0;
				}

				// Media gallery widget
				if ( false !== strpos( $widget_instance_id, 'media_gallery' ) && ! empty( $widget['media_gallery'] ) ) {
					foreach ( (array) $widget['ids'] as $k => $v ) {
						$widget['ids'][ $k ] = isset( $imported_posts[ $v ] ) ? $imported_posts[ $v ] : 0;
					}
				}

				// Replace all images with placeholder
				$widget = Customify_Starter_Sites_Placeholder::get_instance()->replace_placeholder( $widget );

				$base_id = preg_replace( '/-[0-9]+$/', '', $widget_instance_id );
				if ( isset( $widget_instances[ $base_id ] ) ) {
					$widget_option_name      = $this->core_widget_option_name( $base_id );
					$single_widget_instances = get_option( $widget_option_name );
					$single_widget_instances = ! empty( $single_widget_instances ) ? $single_widget_instances : array( '_multiwidget' => 1 );

					$single_widget_instances[] = apply_filters( 'customify_starter_sites_import_widget_data', $widget, $widget_instances[ $base_id ], $base_id );
					end( $single_widget_instances );
					$new_instance_id_number = key( $single_widget_instances );
					if ( '0' === strval( $new_instance_id_number ) ) {
						$new_instance_id_number                             = 1;
						$single_widget_instances[ $new_instance_id_number ] = $single_widget_instances[0];
						unset( $single_widget_instances[0] );
					}
					if ( isset( $single_widget_instances['_multiwidget'] ) ) {
						$multiwidget = $single_widget_instances['_multiwidget'];
						unset( $single_widget_instances['_multiwidget'] );
						$single_widget_instances['_multiwidget'] = $multiwidget;
					}
					$updated                            = update_option( $widget_option_name, $single_widget_instances );
					$sidebars_widgets                   = get_option( 'sidebars_widgets' );
					$sidebars_widgets[ $_sidebar_id ][] = $base_id . '-' . $new_instance_id_number;
					update_option( 'sidebars_widgets', $sidebars_widgets );
				}
			}
		}

	}


	function down_load_image( $file ) {
		$data = new \stdClass();

		if ( ! function_exists( 'media_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		if ( ! empty( $file ) ) {
			if ( ! self::is_safe_remote_url( $file ) ) {
				return new WP_Error( 'invalid_url', __( 'Invalid image URL.', 'customify-starter-sites' ) );
			}
			preg_match( '/[^\?]+\.(jpe?g|jpe|gif|png)\b/i', $file, $matches );
			if ( empty( $matches[0] ) ) {
				return new WP_Error( 'invalid_file', __( 'Invalid image file.', 'customify-starter-sites' ) );
			}
			$file_array             = array();
			$file_array['name']     = basename( $matches[0] );
			$file_array['tmp_name'] = download_url( $file );
			if ( is_wp_error( $file_array['tmp_name'] ) ) {
				return $file_array['tmp_name'];
			}
			$id = media_handle_sideload( $file_array, 0 );
			if ( is_wp_error( $id ) ) {
				wp_delete_file( $file_array['tmp_name'] );

				return $id;
			}
			$meta                = wp_get_attachment_metadata( $id );
			$data->attachment_id = $id;
			$data->url           = wp_get_attachment_url( $id );
			$data->thumbnail_url = wp_get_attachment_thumb_url( $id );
			$data->height        = isset( $meta['height'] ) ? absint( $meta['height'] ) : 0;
			$data->width         = isset( $meta['width'] ) ? absint( $meta['width'] ) : 0;
		}

		return $data;
	}

	function _import_theme_mod( $customize_data = array() ) {
		global $wp_customize;

		if ( ! empty( $customize_data ) ) {
			set_theme_mod( 'header_builder_version', 'v2' );
			set_theme_mod( 'hide_header_builder_switcher', 'yes' );
			$imported_terms  = isset( $this->mapping['term_id'] ) ? $this->mapping['term_id'] : array();
			$processed_posts = isset( $this->mapping['post'] ) ? $this->mapping['post'] : array();

			foreach ( $customize_data as $mod_key => $mod_value ) {
				if ( ! is_numeric( $mod_key ) && sanitize_key( $mod_key ) === $mod_key ) {

					if ( is_string( $mod_value ) && preg_match( '/\.(jpg|jpeg|png|gif)/i', $mod_value ) ) {
						$attachment = $this->down_load_image( $mod_value );
						if ( ! is_wp_error( $attachment ) ) {
							$mod_value = $attachment->url;
							$index_key = $mod_key . '_data';
							if ( isset( $customize_data['mods'][ $index_key ] ) ) {
								$customize_data['mods'][ $index_key ] = $attachment;
								update_post_meta( $attachment->attachment_id, '_wp_attachment_is_custom_header', get_option( 'stylesheet' ) );
							}
						}
					}

					if ( 'nav_menu_locations' === $mod_key && is_array( $mod_value ) ) {

						if ( ! is_array( $imported_terms ) ) {
							$imported_terms = array();
						}
						foreach ( $mod_value as $menu_location => $menu_term_id ) {
							if ( empty( $imported_terms ) ) {
								$t = false;
								if ( $menu_location == 'menu-1' ) {
									$t = get_term_by( 'name', 'Primary', 'nav_menu' );
								} elseif ( $menu_location == 'menu-2' ) {
									$t = get_term_by( 'name', 'Secondary', 'nav_menu' );
								}

								if ( $t ) {
									$mod_value[ $menu_location ] = $t->term_id;
								}
							} else {
								$mod_value[ $menu_location ] = isset( $imported_terms[ $menu_term_id ] ) ? $imported_terms[ $menu_term_id ] : $menu_term_id;
							}
						}
					}
					if ( 'custom_logo' == $mod_key ) {
						if ( ! is_array( $processed_posts ) ) {
							$processed_posts = array();
						}
						$mod_value = isset( $processed_posts[ $mod_value ] ) ? $processed_posts[ $mod_value ] : $mod_value;
					}

					$mod_value = $this->sanitize_import_value( $mod_value, true );
					set_theme_mod( $mod_key, $mod_value );
				}
			}
		}

	}


	/**
	 * Validate a starter-site resource URL against a strict HTTPS host allowlist.
	 *
	 * @param string $url Remote URL.
	 * @return bool
	 */
	public static function is_safe_remote_url( $url ) {
		if ( ! is_string( $url ) || '' === trim( $url ) ) {
			return false;
		}
		$url = trim( $url );
		if ( ! wp_http_validate_url( $url ) ) {
			return false;
		}
		$parsed = wp_parse_url( $url );
		if ( empty( $parsed['scheme'] ) || empty( $parsed['host'] ) ) {
			return false;
		}
		$scheme = strtolower( $parsed['scheme'] );
		if ( 'https' !== $scheme ) {
			return false;
		}
		$host = strtolower( $parsed['host'] );
		/**
		 * Allowlist of hostnames for demo downloads. Subdomains of each entry are allowed.
		 *
		 * @param string[] $hosts Hostnames.
		 */
		$allowed = (array) apply_filters(
			'customify_starter_sites_allowed_download_hosts',
			array(
				'customifysites.com',
			)
		);
		foreach ( $allowed as $allowed_host ) {
			$allowed_host = strtolower( trim( (string) $allowed_host ) );
			if ( '' === $allowed_host ) {
				continue;
			}
			$suffix = '.' . $allowed_host;
			if ( $host === $allowed_host || ( strlen( $host ) > strlen( $suffix ) && substr( $host, -strlen( $suffix ) ) === $suffix ) ) {
				return true;
			}
		}

		return false;
	}

	static function download_file( $url, $name = '', $save_attachment = true ) {
		if ( ! $url || empty( $url ) ) {
			return new WP_Error( 'missing_url', __( 'The download URL is missing.', 'customify-starter-sites' ) );
		}
		if ( ! self::is_safe_remote_url( $url ) ) {
			return new WP_Error( 'invalid_url', __( 'The download URL is not allowed.', 'customify-starter-sites' ) );
		}

		$name      = $name ? sanitize_file_name( $name ) : sanitize_file_name( basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ) );
		$extension = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		if ( ! in_array( $extension, array( 'xml', 'json' ), true ) ) {
			return new WP_Error( 'invalid_file_type', __( 'Only XML and JSON starter files are allowed.', 'customify-starter-sites' ) );
		}

		// These files need to be included as dependencies when on the front end.
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		$file_array = array();
		// Download file to temp location.
		$file_array['tmp_name'] = download_url( $url, 30 );

		// If error storing temporarily, return the error.
		if ( empty( $file_array['tmp_name'] ) || is_wp_error( $file_array['tmp_name'] ) ) {
			return is_wp_error( $file_array['tmp_name'] ) ? $file_array['tmp_name'] : new WP_Error( 'download_failed', __( 'The starter file could not be downloaded.', 'customify-starter-sites' ) );
		}

		$file_array['name'] = $name;
		// Do the validation and storage stuff.
		$file_path_or_id = self::media_handle_sideload( $file_array, 0, null, array(), $save_attachment );

		// If error storing permanently, unlink.
		if ( is_wp_error( $file_path_or_id ) ) {
			wp_delete_file( $file_array['tmp_name'] );

			return $file_path_or_id;
		}
		if ( $save_attachment && is_numeric( $file_path_or_id ) ) {
			update_post_meta( (int) $file_path_or_id, '_customify_starter_source_url', esc_url_raw( $url, array( 'https' ) ) );
		}

		return $file_path_or_id;
	}

	function import_elementor_page_setting( $slug ) {
		if ( isset( $slug ) && ! empty( $slug ) ) {
			$valid_slugs = array( 'outfit', 'charity', 'consulting', 'customify-2018', 'studio' );
			if ( ! in_array( $slug, $valid_slugs, true ) ) {
				return;
			}
			$header_items = array(
				'outfit'         => 'a:2:{s:7:"desktop";a:3:{s:3:"top";a:3:{s:4:"left";a:1:{i:0;a:1:{s:2:"id";s:4:"html";}}s:6:"center";a:0:{}s:5:"right";a:1:{i:0;a:1:{s:2:"id";s:12:"social-icons";}}}s:4:"main";a:3:{s:4:"left";a:1:{i:0;a:1:{s:2:"id";s:12:"primary-menu";}}s:6:"center";a:1:{i:0;a:1:{s:2:"id";s:4:"logo";}}s:5:"right";a:2:{i:0;a:1:{s:2:"id";s:7:"wc_cart";}i:1;a:1:{s:2:"id";s:11:"search_icon";}}}s:6:"bottom";a:3:{s:4:"left";a:0:{}s:6:"center";a:0:{}s:5:"right";a:0:{}}}s:6:"mobile";a:4:{s:3:"top";a:3:{s:4:"left";a:0:{}s:6:"center";a:0:{}s:5:"right";a:0:{}}s:4:"main";a:3:{s:4:"left";a:1:{i:0;a:1:{s:2:"id";s:4:"logo";}}s:6:"center";a:0:{}s:5:"right";a:2:{i:0;a:1:{s:2:"id";s:11:"search_icon";}i:1;a:1:{s:2:"id";s:8:"nav-icon";}}}s:6:"bottom";a:3:{s:4:"left";a:0:{}s:6:"center";a:0:{}s:5:"right";a:0:{}}s:7:"sidebar";a:1:{s:7:"sidebar";a:5:{i:0;a:1:{s:2:"id";s:4:"html";}i:1;a:1:{s:2:"id";s:10:"search_box";}i:2;a:1:{s:2:"id";s:12:"primary-menu";}i:3;a:1:{s:2:"id";s:12:"social-icons";}i:4;a:1:{s:2:"id";s:6:"button";}}}}}',
				'charity'        => 'a:2:{s:7:"desktop";a:3:{s:3:"top";a:3:{s:4:"left";a:0:{}s:6:"center";a:0:{}s:5:"right";a:0:{}}s:4:"main";a:3:{s:4:"left";a:1:{i:0;a:1:{s:2:"id";s:4:"logo";}}s:6:"center";a:0:{}s:5:"right";a:2:{i:0;a:1:{s:2:"id";s:12:"social-icons";}i:1;a:1:{s:2:"id";s:6:"button";}}}s:6:"bottom";a:3:{s:4:"left";a:1:{i:0;a:1:{s:2:"id";s:12:"primary-menu";}}s:6:"center";a:0:{}s:5:"right";a:1:{i:0;a:1:{s:2:"id";s:11:"search_icon";}}}}s:6:"mobile";a:4:{s:3:"top";a:3:{s:4:"left";a:0:{}s:6:"center";a:0:{}s:5:"right";a:0:{}}s:4:"main";a:3:{s:4:"left";a:1:{i:0;a:1:{s:2:"id";s:4:"logo";}}s:6:"center";a:0:{}s:5:"right";a:2:{i:0;a:1:{s:2:"id";s:11:"search_icon";}i:1;a:1:{s:2:"id";s:8:"nav-icon";}}}s:6:"bottom";a:3:{s:4:"left";a:0:{}s:6:"center";a:0:{}s:5:"right";a:0:{}}s:7:"sidebar";a:1:{s:7:"sidebar";a:5:{i:0;a:1:{s:2:"id";s:4:"html";}i:1;a:1:{s:2:"id";s:10:"search_box";}i:2;a:1:{s:2:"id";s:12:"primary-menu";}i:3;a:1:{s:2:"id";s:12:"social-icons";}i:4;a:1:{s:2:"id";s:6:"button";}}}}}',
				'consulting'     => 'a:2:{s:7:"desktop";a:3:{s:3:"top";a:3:{s:4:"left";a:1:{i:0;a:1:{s:2:"id";s:4:"html";}}s:6:"center";a:0:{}s:5:"right";a:2:{i:0;a:1:{s:2:"id";s:10:"search_box";}i:1;a:1:{s:2:"id";s:12:"social-icons";}}}s:4:"main";a:3:{s:4:"left";a:1:{i:0;a:1:{s:2:"id";s:4:"logo";}}s:6:"center";a:0:{}s:5:"right";a:2:{i:0;a:1:{s:2:"id";s:12:"primary-menu";}i:1;a:1:{s:2:"id";s:6:"button";}}}s:6:"bottom";a:3:{s:4:"left";a:0:{}s:6:"center";a:0:{}s:5:"right";a:0:{}}}s:6:"mobile";a:4:{s:3:"top";a:3:{s:4:"left";a:0:{}s:6:"center";a:0:{}s:5:"right";a:0:{}}s:4:"main";a:3:{s:4:"left";a:1:{i:0;a:1:{s:2:"id";s:4:"logo";}}s:6:"center";a:0:{}s:5:"right";a:2:{i:0;a:1:{s:2:"id";s:11:"search_icon";}i:1;a:1:{s:2:"id";s:8:"nav-icon";}}}s:6:"bottom";a:3:{s:4:"left";a:0:{}s:6:"center";a:0:{}s:5:"right";a:0:{}}s:7:"sidebar";a:1:{s:7:"sidebar";a:5:{i:0;a:1:{s:2:"id";s:4:"html";}i:1;a:1:{s:2:"id";s:10:"search_box";}i:2;a:1:{s:2:"id";s:12:"primary-menu";}i:3;a:1:{s:2:"id";s:12:"social-icons";}i:4;a:1:{s:2:"id";s:6:"button";}}}}}',
				'customify-2018' => 'a:2:{s:7:"desktop";a:3:{s:3:"top";a:3:{s:4:"left";a:1:{i:0;a:1:{s:2:"id";s:4:"html";}}s:6:"center";a:0:{}s:5:"right";a:1:{i:0;a:1:{s:2:"id";s:12:"social-icons";}}}s:4:"main";a:3:{s:4:"left";a:1:{i:0;a:1:{s:2:"id";s:4:"logo";}}s:6:"center";a:1:{i:0;a:1:{s:2:"id";s:12:"primary-menu";}}s:5:"right";a:3:{i:0;a:1:{s:2:"id";s:11:"search_icon";}i:1;a:1:{s:2:"id";s:8:"nav-icon";}i:2;a:1:{s:2:"id";s:6:"button";}}}s:6:"bottom";a:3:{s:4:"left";a:0:{}s:6:"center";a:0:{}s:5:"right";a:0:{}}}s:6:"mobile";a:4:{s:3:"top";a:3:{s:4:"left";a:0:{}s:6:"center";a:0:{}s:5:"right";a:0:{}}s:4:"main";a:3:{s:4:"left";a:1:{i:0;a:1:{s:2:"id";s:4:"logo";}}s:6:"center";a:0:{}s:5:"right";a:2:{i:0;a:1:{s:2:"id";s:11:"search_icon";}i:1;a:1:{s:2:"id";s:8:"nav-icon";}}}s:6:"bottom";a:3:{s:4:"left";a:0:{}s:6:"center";a:0:{}s:5:"right";a:0:{}}s:7:"sidebar";a:1:{s:7:"sidebar";a:5:{i:0;a:1:{s:2:"id";s:4:"html";}i:1;a:1:{s:2:"id";s:10:"search_box";}i:2;a:1:{s:2:"id";s:12:"primary-menu";}i:3;a:1:{s:2:"id";s:12:"social-icons";}i:4;a:1:{s:2:"id";s:6:"button";}}}}}',
				'studio'         => 'a:2:{s:7:"desktop";a:3:{s:3:"top";a:3:{s:4:"left";a:0:{}s:6:"center";a:0:{}s:5:"right";a:0:{}}s:4:"main";a:3:{s:4:"left";a:1:{i:0;a:1:{s:2:"id";s:4:"logo";}}s:6:"center";a:0:{}s:5:"right";a:1:{i:0;a:1:{s:2:"id";s:8:"nav-icon";}}}s:6:"bottom";a:3:{s:4:"left";a:0:{}s:6:"center";a:0:{}s:5:"right";a:0:{}}}s:6:"mobile";a:4:{s:3:"top";a:3:{s:4:"left";a:0:{}s:6:"center";a:0:{}s:5:"right";a:0:{}}s:4:"main";a:3:{s:4:"left";a:1:{i:0;a:1:{s:2:"id";s:4:"logo";}}s:6:"center";a:0:{}s:5:"right";a:2:{i:0;a:1:{s:2:"id";s:11:"search_icon";}i:1;a:1:{s:2:"id";s:8:"nav-icon";}}}s:6:"bottom";a:3:{s:4:"left";a:0:{}s:6:"center";a:0:{}s:5:"right";a:0:{}}s:7:"sidebar";a:1:{s:7:"sidebar";a:5:{i:0;a:1:{s:2:"id";s:4:"html";}i:1;a:1:{s:2:"id";s:10:"search_box";}i:2;a:1:{s:2:"id";s:12:"primary-menu";}i:3;a:1:{s:2:"id";s:12:"social-icons";}i:4;a:1:{s:2:"id";s:6:"button";}}}}}',
			);
			if ( isset( $header_items[ $slug ] ) && ! empty( $header_items[ $slug ] ) ) {
				$header_data = customify_starter_sites_maybe_unserialize( $header_items[ $slug ], false );
				if ( is_array( $header_data ) ) {
					set_theme_mod( 'header_builder_panel_v2', $header_data );
				}
			}

			$config_url = sprintf( 'https://customifysites.com/wp-content/uploads/demo-meta/%s.txt', $slug );
			$response   = wp_safe_remote_get(
				$config_url,
				array(
					'timeout'             => 15,
					'redirection'         => 3,
					'limit_response_size' => 1024 * 1024,
				)
			);
			if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
				return;
			}
			$response_body = wp_remote_retrieve_body( $response );

			if ( ! empty( $response_body ) ) {
				try {
					$result = customify_starter_sites_maybe_unserialize( $this->decode_settings( $response_body ), false );
					if ( is_array( $result ) && ! is_wp_error( $result ) ) {
						$page_front = get_option( 'page_on_front', 0 );
						if ( is_numeric( $page_front ) && $page_front > 0 && get_post_status( $page_front ) ) {
							$allowed_meta_keys = array(
								'_elementor_data',
								'_elementor_css',
								'_elementor_edit_mode',
								'_elementor_template_type',
								'_elementor_version',
								'_elementor_page_settings',
							);
							foreach ( $result as $key => $res ) {
								if ( in_array( $key, $allowed_meta_keys, true ) && is_array( $res ) && isset( $res[0] ) && ! empty( $res[0] ) ) {
									$value = $res[0];
									if ( '_elementor_data' === $key ) {
										$value = json_decode( $res[0], true );
										if ( ! is_array( $value ) ) {
											continue;
										}
										array_walk_recursive( $value, array( $this, 'replace_by_placeholder' ) );
										$value = wp_json_encode( $value );
									} elseif ( '_elementor_css' === $key || '_elementor_page_settings' === $key ) {
										$value = customify_starter_sites_maybe_unserialize( $res[0], false );
										$value = $this->sanitize_import_value( $value, true );
									} else {
										$value = sanitize_text_field( (string) $value );
									}
									update_post_meta( $page_front, $key, $value );
								}
							}
							if ( defined( 'ELEMENTOR_VERSION' ) && class_exists( 'Elementor\\Plugin' ) ) {
								Elementor\Plugin::$instance->files_manager->clear_cache();
							}
						}
					}
				} catch ( Exception $e ) {
					// catch $e.
				}
			}
		}
	}

	/**
	 * Local placeholder image URL.
	 *
	 * Prefers the placeholder copied into the media library on import; falls
	 * back to the image bundled with this plugin. Never points at a remote
	 * host so imports do not depend on an external server.
	 *
	 * @return string
	 */
	protected function get_placeholder_url() {
		if ( class_exists( 'Customify_Starter_Sites_Placeholder' ) ) {
			$placeholder = Customify_Starter_Sites_Placeholder::get_instance();
			if ( ! empty( $placeholder->placeholder_url ) ) {
				return $placeholder->placeholder_url;
			}
		}

		return CUSTOMIFY_STARTER_SITES_URL . '/assets/placeholder/placeholder.jpg';
	}

	function replace_by_placeholder( &$value, $key ) {
		if ( ! is_string( $value ) ) {
			return;
		}
		if ( false !== strpos( $value, 'https://customifysites.com/' ) && ( false !== strpos( $value, '.jpeg' ) || false !== strpos( $value, '.jpg' ) || false !== strpos( $value, '.png' ) ) ) {
			$value = $this->get_placeholder_url();
		}
	}

	function decode_settings( $input ) {
		$keyStr = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=';
		$chr1   = $chr2 = $chr3 = '';
		$enc1   = $enc2 = $enc3 = $enc4 = '';
		$i      = 0;
		$output = '';

		$input = preg_replace( '[^A-Za-z0-9\+\/\=]', '', $input );
		do {
			$enc1   = strpos( $keyStr, substr( $input, $i++, 1 ) );
			$enc2   = strpos( $keyStr, substr( $input, $i++, 1 ) );
			$enc3   = strpos( $keyStr, substr( $input, $i++, 1 ) );
			$enc4   = strpos( $keyStr, substr( $input, $i++, 1 ) );
			$chr1   = ( $enc1 << 2 ) | ( $enc2 >> 4 );
			$chr2   = ( ( $enc2 & 15 ) << 4 ) | ( $enc3 >> 2 );
			$chr3   = ( ( $enc3 & 3 ) << 6 ) | $enc4;
			$output = $output . chr( (int) $chr1 );
			if ( $enc3 != 64 ) {
				$output = $output . chr( (int) $chr2 );
			}
			if ( $enc4 != 64 ) {
				$output = $output . chr( (int) $chr3 );
			}
			$chr1 = $chr2 = $chr3 = '';
			$enc1 = $enc2 = $enc3 = $enc4 = '';
		} while ( $i < strlen( $input ) );
		return urldecode( $output );
	}

}
