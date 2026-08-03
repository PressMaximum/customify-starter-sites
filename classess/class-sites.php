<?php
defined( 'ABSPATH' ) || exit;

class Customify_Starter_Sites {
    static $_instance = null;
    const THEME_NAME = 'customify';
	private $menu_hook = '';


	function admin_scripts( $id ){
		if ( $id === $this->menu_hook ) {
			wp_localize_script('jquery', 'Customify_Starter_Sites',  $this->get_localize_script() );
			wp_enqueue_style('owl.carousel', CUSTOMIFY_STARTER_SITES_URL.'/assets/css/owl.carousel.css', array(), CUSTOMIFY_STARTER_SITES_VERSION );
			wp_enqueue_style('owl.theme.default', CUSTOMIFY_STARTER_SITES_URL.'/assets/css/owl.theme.default.css', array(), CUSTOMIFY_STARTER_SITES_VERSION );
			wp_enqueue_style("customify-starter-sites", CUSTOMIFY_STARTER_SITES_URL.'/assets/css/customify-sites.css', array(), CUSTOMIFY_STARTER_SITES_VERSION );

			wp_enqueue_script('owl.carousel', CUSTOMIFY_STARTER_SITES_URL.'/assets/js/owl.carousel.js',  array( 'jquery' ), CUSTOMIFY_STARTER_SITES_VERSION, true );
			wp_enqueue_script("customify-starter-sites", CUSTOMIFY_STARTER_SITES_URL.'/assets/js/backend.js',  array( 'jquery', 'underscore' ), CUSTOMIFY_STARTER_SITES_VERSION, true );
        }
    }

    static function get_instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
            add_action( 'admin_menu', array( self::$_instance, 'add_menu' ), 50 );
            add_action( 'admin_enqueue_scripts', array( self::$_instance, 'admin_scripts' ) );
            add_action( 'admin_notices', array( self::$_instance, 'admin_notice' ) );
        }
        return self::$_instance;
    }

	function admin_notice() {
		$screen = get_current_screen();
		if ( ! $screen || ( $screen->id !== $this->menu_hook && $screen->id !== 'themes' ) ) {
            return '';
        }

        if( get_template() == self::THEME_NAME  ) {
            return '';
        }

        $themes = wp_get_themes();
        if ( isset( $themes[ self::THEME_NAME ] ) ) {
            $url = esc_url( 'themes.php?theme='.self::THEME_NAME );
        } else {
            $url = esc_url( 'theme-install.php?search='.self::THEME_NAME );
        }

        ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <?php
                printf(
                    '<strong>%1$s</strong> %2$s <strong>%3$s</strong> %4$s <a href="%5$s">%6$s</a>',
                    esc_html__( 'Customify Site Library', 'customify-starter-sites' ),
                    esc_html__( 'requires', 'customify-starter-sites' ),
                    esc_html__( 'Customify', 'customify-starter-sites' ),
                    esc_html__( 'theme to be activated to work.', 'customify-starter-sites' ),
                    esc_url( $url ),
                    esc_html__( 'Install & Activate Now', 'customify-starter-sites' )
                );
                ?>
            </p>
        </div>
        <?php
    }

	static function get_api_url(){
		$default = 'https://customifysites.com/wp-json/wp/v2.1/sites/';
		$url     = esc_url_raw( apply_filters( 'customify_sites/api_url', $default ), array( 'https' ) );

		if ( ! $url || ! wp_http_validate_url( $url ) ) {
			return $default;
		}

		return $url;
	}

	function add_menu() {
		$page_title = __( 'Customify Sites', 'customify-starter-sites' );

		if ( get_template() === self::THEME_NAME ) {
			$this->menu_hook = add_submenu_page(
				'customify',
				$page_title,
				__( 'Starter Sites', 'customify-starter-sites' ),
				'manage_options',
				'customify-starter-sites',
				array( $this, 'page' )
			);
			return;
		}

		$this->menu_hook = add_menu_page(
			$page_title,
			$page_title,
			'manage_options',
			'customify-starter-sites',
			array( $this, 'page' ),
			'dashicons-layout',
			59
		);
	}

    function page(){
        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">' . esc_html__( 'Customify Site Library', 'customify-starter-sites' ) . '</h1><hr class="wp-header-end">';
        require_once CUSTOMIFY_STARTER_SITES_PATH.'/templates/dashboard.php';
        require_once CUSTOMIFY_STARTER_SITES_PATH.'/templates/modal.php';
        echo '</div>';
        require_once CUSTOMIFY_STARTER_SITES_PATH.'/templates/preview.php';
    }

    function get_installed_plugins(){
        // Check if get_plugins() function exists. This is required on the front end of the
        // site, since it is in a file that is normally only loaded in the admin.
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $all_plugins = get_plugins();
        if ( ! is_array( $all_plugins ) ) {
            $all_plugins = array();
        }

        $plugins = array();
        foreach ( $all_plugins as $file => $info ) {
            $slug = dirname( $file );
            $plugins[ $slug ] = $info['Name'];
        }

        return $plugins;
    }

    function get_activated_plugins(){
        $activated_plugins = array();
        foreach( ( array ) get_option('active_plugins') as $plugin_file ) {
            $plugin_file = dirname( $plugin_file );
            $activated_plugins[ $plugin_file ] = $plugin_file;
        }
        return $activated_plugins;
    }

    function get_support_plugins(){
        $plugins = array(
            'customify-pro' => _x( 'Customify Pro', 'plugin-name', 'customify-starter-sites'),

            'elementor' => _x( 'Elementor', 'plugin-name', 'customify-starter-sites' ),
            'elementor-pro' => _x( 'Elementor Pro', 'plugin-name', 'customify-starter-sites' ),
            'beaver-builder-lite-version' => _x( 'Beaver Builder', 'plugin-name', 'customify-starter-sites' ),
            'contact-form-7' => _x( 'Contact Form 7', 'plugin-name', 'customify-starter-sites' ),

            'breadcrumb-navxt' => _x( 'Breadcrumb NavXT', 'plugin-name', 'customify-starter-sites' ),
            'jetpack' => _x( 'JetPack', 'plugin-name', 'customify-starter-sites' ),
            'easymega' => _x( 'Mega menu', 'plugin-name', 'customify-starter-sites' ),
            'polylang' => _x( 'Polylang', 'plugin-name', 'customify-starter-sites'),
            'woocommerce' => _x( 'WooCommerce', 'plugin-name', 'customify-starter-sites' ),
            'give' => _x( 'Give – Donation Plugin and Fundraising Platform', 'plugin-name', 'customify-starter-sites' ),
        );

        return $plugins;
    }

    function is_license_valid(){

        if ( ! class_exists('Customify_Pro' ) ) {
            return false;
        }
	    $pro_data = get_option('customify_pro_license_data');
	    if ( ! is_array( $pro_data ) ) {
	        return false;
        }
	    if ( ! isset( $pro_data['license'] ) ) {
		    return false;
	    }

	    if ( ! isset( $pro_data['data'] ) || ! is_array( $pro_data['data'] ) ) {
		    return false;
	    }

	    if ( isset( $pro_data['data']['license'] ) && $pro_data['data']['license'] == 'valid' &&  $pro_data['data']['success'] ) {
            return true;
        }

        return false;
    }

    function get_localize_script(){

        $args = array(
            'api_url' => self::get_api_url(),
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'is_admin' => is_admin(),
            'try_again' => __( 'Try Again', 'customify-starter-sites' ),
            'pro_text' => __( 'Pro only', 'customify-starter-sites' ),
            'activated_plugins' => $this->get_activated_plugins(),
            'installed_plugins' => $this->get_installed_plugins(),
            'support_plugins' => $this->get_support_plugins(),
            'license_valid' =>   $this->is_license_valid(),
        );

        $args['elementor_clear_cache_nonce'] = wp_create_nonce( 'elementor_clear_cache' );
        $args['elementor_reset_library_nonce'] = wp_create_nonce( 'elementor_reset_library' );
		$args['ajax_nonce']                  = wp_create_nonce( 'customify_starter_sites' );

        return $args;
    }

}
