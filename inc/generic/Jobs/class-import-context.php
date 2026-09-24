<?php
/** Process-local permissions for an authorized, claimed import worker. */
namespace Customify_Starter_Sites\Jobs;
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Import_Context {
	private bool $active = false;
	private int $blog_id = 0;
	private array $saved = [];
	private array $caps = [];

	/** No roles, users, sessions or persistent capabilities are modified. */
	public function enter(): void {
		if ( $this->active ) { return; }
		$this->blog_id = get_current_blog_id();
		$this->caps = array_fill_keys( [
			'import', 'manage_options', 'manage_woocommerce', 'edit_theme_options', 'edit_css',
			'upload_files', 'unfiltered_upload', 'unfiltered_html',
			'install_plugins', 'activate_plugins', 'activate_plugin', 'update_plugins',
			'install_themes', 'update_themes', 'switch_themes',
			'edit_posts', 'edit_others_posts', 'publish_posts', 'read_private_posts',
			'delete_posts', 'delete_others_posts', 'edit_post', 'delete_post', 'read_post',
			'edit_pages', 'edit_others_pages', 'publish_pages', 'read_private_pages',
			'delete_pages', 'delete_others_pages', 'manage_categories',
		], true );
		foreach ( $this->filters() as [ $hook, $callback ] ) {
			$this->saved[] = [ $hook, $callback, has_filter( $hook, $callback ) ];
		}
		$this->active = true;
		add_filter( 'map_meta_cap', [ $this, 'map_caps' ], PHP_INT_MAX, 4 );
		// KSES was registered during anonymous cron bootstrap. Refresh it with
		// the worker's import capability; subsequent init calls do the same.
		$this->refresh_filters();
		foreach ( [ 'init', 'set_current_user', 'switch_blog' ] as $hook ) {
			add_action( $hook, [ $this, 'refresh_filters' ], PHP_INT_MAX, 0 );
		}
	}

	public function refresh_filters(): void {
		kses_init();
		foreach ( $this->saved as [ $hook, $callback, $priority ] ) {
			if ( 'wp_strip_custom_css_from_blocks' !== $callback ) { continue; }
			$current = has_filter( $hook, $callback );
			if ( false !== $current ) { remove_filter( $hook, $callback, $current ); }
			if ( get_current_blog_id() !== $this->blog_id && false !== $priority ) { add_filter( $hook, $callback, $priority ); }
		}
	}

	public function map_caps( array $caps, string $cap, int $user_id, array $args ): array {
		if ( ! $this->active || get_current_blog_id() !== $this->blog_id ) { return $caps; }
		$allowed = isset( $this->caps[ $cap ] );
		// Read registered capabilities dynamically: plugins may register CPTs
		// and taxonomies during this import (products, variations, templates).
		foreach ( get_post_types( [], 'objects' ) as $type ) {
			if ( in_array( $cap, (array) $type->cap, true ) ) { $allowed = true; break; }
		}
		foreach ( get_taxonomies( [], 'objects' ) as $taxonomy ) {
			if ( in_array( $cap, (array) $taxonomy->cap, true ) ) { $allowed = true; break; }
		}
		// Host-level file modification policy is not a WordPress role.
		if ( in_array( $cap, [ 'install_plugins', 'update_plugins', 'install_themes', 'update_themes' ], true ) && ! wp_is_file_mod_allowed( 'custstsi_import' ) ) { return $caps; }
		return $allowed ? [] : $caps;
	}

	public function close(): void {
		if ( ! $this->active ) { return; }
		$this->active = false;
		foreach ( [ 'init', 'set_current_user', 'switch_blog' ] as $hook ) {
			remove_action( $hook, [ $this, 'refresh_filters' ], PHP_INT_MAX );
		}
		remove_filter( 'map_meta_cap', [ $this, 'map_caps' ], PHP_INT_MAX );
		foreach ( $this->saved as [ $hook, $callback, $priority ] ) {
			$current = has_filter( $hook, $callback );
			if ( false !== $current ) { remove_filter( $hook, $callback, $current ); }
			if ( false !== $priority ) { add_filter( $hook, $callback, $priority ); }
		}
		$this->saved = [];
	}

	private function filters(): array {
		return [
			[ 'content_save_pre', 'wp_strip_custom_css_from_blocks' ],
			[ 'title_save_pre', 'wp_filter_kses' ],
			[ 'pre_comment_content', 'wp_filter_post_kses' ],
			[ 'pre_comment_content', 'wp_filter_kses' ],
			[ 'content_save_pre', 'wp_filter_global_styles_post' ],
			[ 'content_filtered_save_pre', 'wp_filter_global_styles_post' ],
			[ 'content_save_pre', 'wp_filter_post_kses' ],
			[ 'excerpt_save_pre', 'wp_filter_post_kses' ],
			[ 'content_filtered_save_pre', 'wp_filter_post_kses' ],
		];
	}
}
