<?php
class Customify_Starter_Sites_Plugin{

    static $instance;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

    function ajax(){
		check_ajax_referer( 'customify_starter_sites', 'nonce' );
        $plugin = isset( $_REQUEST['plugin'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['plugin'] ) ) : '';
        $_GET['plugin'] = $plugin;
        if ( ! current_user_can( 'install_plugins' ) ) {
			status_header( 403 );
			die( 'access_denied' );
        }
        ob_start();
    

        $msg = ob_get_clean();
        ob_end_clean();
        die();

    }

}