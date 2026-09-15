<?php
/**
 * Step 1 — fetch template assets (content.json / options.json / uploads.zip)
 * from the Studio into a per-job tmp dir.
 *
 * Ported from `blocksify-design-importer/includes/Theme/AssetFetcher.php`.
 *
 * Output layout:
 *   wp-content/uploads/.customify-starter-sites-tmp/{job_id}/
 *       content.json
 *       options.json
 *       uploads.zip
 *
 * Streams via wp_remote_get( stream:true ) so 200 MB uploads.zip doesn't
 * blow up memory; same-origin URLs short-circuit through Remote_Client::download
 * to copy directly from disk.
 */

namespace Customify_Starter_Sites\Steps;

if ( ! defined( 'ABSPATH' ) ) { exit; }

use Customify_Starter_Sites\Studio\Remote_Client;

class Asset_Fetcher {

	/** Required assets — both must be present or the import fatals. */
	public const REQUIRED_KINDS = [ 'content', 'options' ];

	/** Optional assets — manifest may have `null` for these (pattern-only
	 *  templates ship no `uploads.zip`; many ship no `customizer`). The
	 *  downstream phases short-circuit on an empty path. */
	public const OPTIONAL_KINDS = [ 'uploads' ];

	public const ASSET_KINDS = [ 'content', 'options', 'uploads' ];

	private Remote_Client $client;

	public function __construct( Remote_Client $client ) {
		$this->client = $client;
	}

	/**
	 * Download the template's assets into a tmp folder named after $job_id.
	 *
	 * Returns absolute paths per kind. Optional kinds (`uploads`) return
	 * `''` when the manifest didn't ship them — callers MUST check before
	 * touching the path (`Uploads_Extractor::extract('')` will fatal).
	 *
	 * @return array{content:string, options:string, uploads:string, dir:string, declared_plugin_slugs:string[]}
	 *
	 * @throws \RuntimeException When a required asset is missing or a download fails.
	 */
	public function download( int $template_id, string $job_id ): array {
		$res = $this->client->get( "templates/{$template_id}" );
		if ( $res['status'] < 200 || $res['status'] >= 300 || ! is_array( $res['body'] ) ) {
			throw new \RuntimeException( esc_html( sprintf(
				'Failed to fetch template %d: %s',
				$template_id,
				(string) ( $res['error'] ?? 'unknown' )
			)  ));
		}
		$assets = $res['body']['assets'] ?? null;
		if ( ! is_array( $assets ) ) {
			throw new \RuntimeException( esc_html( sprintf( 'Template %d has no assets field.', $template_id )  ));
		}

		$tmp_dir = $this->tmp_dir( $job_id );
		if ( ! wp_mkdir_p( $tmp_dir ) ) {
			throw new \RuntimeException( esc_html( 'Could not create tmp dir: ' . $tmp_dir  ));
		}

		$paths = [
			'content' => '',
			'options' => '',
			'uploads' => '',
		];

		foreach ( self::ASSET_KINDS as $kind ) {
			$entry      = $assets[ $kind ] ?? null;
			$is_present = is_array( $entry ) && ! empty( $entry['url'] );

			if ( ! $is_present ) {
				if ( in_array( $kind, self::REQUIRED_KINDS, true ) ) {
					throw new \RuntimeException( esc_html( sprintf(
						'Template %d missing required asset: %s',
						$template_id,
						$kind
					)  ));
				}
				// Optional kind — leave the path empty and move on.
				continue;
			}

			$ext    = 'uploads' === $kind ? 'zip' : 'json';
			$target = trailingslashit( $tmp_dir ) . "{$kind}.{$ext}";
			if ( ! $this->client->download( (string) $entry['url'], $target ) ) {
				throw new \RuntimeException( esc_html( sprintf(
					'Failed to download asset %s (URL: %s).',
					$kind,
					(string) $entry['url']
				)  ));
			}
			$paths[ $kind ] = $target;
		}

		$paths['dir'] = $tmp_dir;

		// Every plugin the template declares, so the runner can verify at the
		// end that each one that's present on disk ended up active (a template
		// needs its declared plugins to render faithfully). Two catalog fields
		// carry them: the top-level `plugins[]` list and `requirements.plugins[]`.
		// Union them by slug; the runner still honours the wizard's skip flags.
		$paths['declared_plugin_slugs'] = $this->declared_plugin_slugs( $res['body'] );

		return $paths;
	}

	/**
	 * Collect the directory slugs of every plugin a template declares, from
	 * both the top-level `plugins[]` list and `requirements.plugins[]`.
	 *
	 * @param array<string, mixed> $body Decoded `templates/{id}` response body.
	 * @return string[] Unique, sanitised plugin directory slugs.
	 */
	private function declared_plugin_slugs( array $body ): array {
		$slugs = [];

		$lists = [
			$body['plugins'] ?? [],
			$body['requirements']['plugins'] ?? [],
		];
		foreach ( $lists as $list ) {
			if ( ! is_array( $list ) ) {
				continue;
			}
			foreach ( $list as $entry ) {
				$slug = is_array( $entry ) ? ( $entry['slug'] ?? '' ) : '';
				$slug = sanitize_key( (string) $slug );
				if ( '' !== $slug ) {
					$slugs[ $slug ] = true;
				}
			}
		}

		return array_keys( $slugs );
	}

	public function cleanup( string $job_id ): void {
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		$dir = $this->tmp_dir( $job_id );
		if ( ! is_dir( $dir ) ) {
			return;
		}
		foreach ( (array) glob( trailingslashit( $dir ) . '*' ) as $file ) {
			if ( is_file( $file ) ) {
				wp_delete_file( $file );
			}
		}
		if ( $wp_filesystem ) {
			$wp_filesystem->rmdir( $dir );
		}
	}

	private function tmp_dir( string $job_id ): string {
		$upload  = wp_get_upload_dir();
		$basedir = trailingslashit( (string) ( $upload['basedir'] ?? sys_get_temp_dir() ) );
		$safe_id = preg_replace( '/[^a-z0-9]/i', '', $job_id ) ?: 'job';
		return $basedir . '.customify-starter-sites-tmp/' . $safe_id;
	}
}
