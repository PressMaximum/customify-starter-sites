<?php
/**
 * REST endpoints that drive the job lifecycle from the browser:
 *
 *   POST /customify-starter-sites/v1/theme/jobs              → create + enqueue
 *   GET  /customify-starter-sites/v1/theme/jobs/latest       → fetch latest (UI convenience)
 *   GET  /customify-starter-sites/v1/theme/jobs/{id}         → poll status + progress
 *   POST /customify-starter-sites/v1/theme/jobs/{id}/cancel  → flag for cancellation
 *
 * Auth shape mirrors {@see Studio_Proxy_Controller} — `manage_options`
 * + standard `X-WP-Nonce`. Job objects are returned as-is from
 * {@see Job_Store::get()}.
 */

namespace Customify_Starter_Sites\REST;

if ( ! defined( 'ABSPATH' ) ) { exit; }

use Customify_Starter_Sites\Jobs\Importer_Runner;
use Customify_Starter_Sites\Jobs\Job_Store;
use Customify_Starter_Sites\Studio\Remote_Client;

class Job_Controller {

	public const NAMESPACE = 'customify-starter-sites/v1';

	private Job_Store       $jobs;
	private Importer_Runner $runner;

	/**
	 * Studio client, used to look up a template's required plugins so a
	 * Pro-gated template can be rejected before a job is created.
	 *
	 * @var Remote_Client|null
	 */
	private ?Remote_Client $client;

	public function __construct( Job_Store $jobs, Importer_Runner $runner, ?Remote_Client $client = null ) {
		$this->jobs   = $jobs;
		$this->runner = $runner;
		$this->client = $client;
	}

	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		$perm = [ $this, 'check_permission' ];

		register_rest_route( self::NAMESPACE, '/theme/jobs', [
			'methods'             => 'POST',
			'permission_callback' => $perm,
			'callback'            => [ $this, 'create_job' ],
		] );
		register_rest_route( self::NAMESPACE, '/theme/jobs/latest', [
			'methods'             => 'GET',
			'permission_callback' => $perm,
			'callback'            => [ $this, 'get_latest' ],
		] );
		register_rest_route( self::NAMESPACE, '/theme/jobs/(?P<id>[a-z0-9]+)', [
			'methods'             => 'GET',
			'permission_callback' => $perm,
			'callback'            => [ $this, 'get_job' ],
		] );
		register_rest_route( self::NAMESPACE, '/theme/jobs/(?P<id>[a-z0-9]+)/cancel', [
			'methods'             => 'POST',
			'permission_callback' => $perm,
			'callback'            => [ $this, 'cancel_job' ],
		] );
	}

	public function check_permission( \WP_REST_Request $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'custstsi_forbidden',
				__( 'You need `manage_options` to control imports.', 'customify-starter-sites' ),
				[ 'status' => 403 ]
			);
		}
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new \WP_Error(
				'custstsi_bad_nonce',
				__( 'Invalid nonce.', 'customify-starter-sites' ),
				[ 'status' => 403 ]
			);
		}
		return true;
	}

	// ----------------------------------------------------------------------

	public function create_job( \WP_REST_Request $request ) {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = [];
		}

		$template_id = (int) ( $body['template_id'] ?? 0 );
		if ( $template_id <= 0 ) {
			return new \WP_Error( 'custstsi_bad_template', 'template_id is required.', [ 'status' => 400 ] );
		}

		// Pro gate: a template that needs a Pro plugin (customify-pro /
		// blocksify-pro) can only be imported once that plugin is
		// installed. The UI already disables Import for these, but a
		// direct API call would bypass that — re-check server-side.
		$pro_error = $this->pro_gate_error( $template_id );
		if ( $pro_error instanceof \WP_Error ) {
			return $pro_error;
		}

		// Defaults match blocksify-design-importer's contract — import
		// everything + overwrite + replace settings on first run. UI
		// (B6 wizard) can flip individual flags via the body. `style`
		// is carried opaquely so the active theme adapter can consume
		// it from `after_phase('applying_options')` and translate
		// palette + font slugs into theme_mods + Font Library entries.
		$config = [
			'template_id'        => $template_id,
			'import_content'     => true,
			'import_uploads'     => true,
			'overwrite_existing' => true,
			// Master roll-up — legacy clients pass only this. New
			// clients pass `import_widgets` + `import_options`
			// alongside it for per-layer control; Options_Importer
			// falls back to `replace_settings` when granular flags
			// are absent.
			'replace_settings'   => true,
			'import_widgets'     => true,
			'import_options'     => true,
			'plugins_skip'       => [],
			'custom_logo_id'     => 0,
			'custom_logo_url'    => '',
			'style'              => [ 'palette' => null, 'font' => null ],
		];

		foreach ( [
			'import_content',
			'import_uploads',
			'overwrite_existing',
			'replace_settings',
			'import_widgets',
			'import_options',
		] as $key ) {
			if ( array_key_exists( $key, $body ) ) {
				$config[ $key ] = (bool) $body[ $key ];
			}
		}
		if ( isset( $body['plugins_skip'] ) && is_array( $body['plugins_skip'] ) ) {
			$config['plugins_skip'] = array_values( array_filter( array_map(
				static fn( $s ) => is_string( $s ) ? sanitize_key( $s ) : '',
				$body['plugins_skip']
			) ) );
		}
		if ( isset( $body['custom_logo_id'] ) ) {
			$config['custom_logo_id'] = max( 0, (int) $body['custom_logo_id'] );
		}
		if ( isset( $body['custom_logo_url'] ) && is_string( $body['custom_logo_url'] ) ) {
			$config['custom_logo_url'] = esc_url_raw( $body['custom_logo_url'] );
		}
		if ( isset( $body['style'] ) && is_array( $body['style'] ) ) {
			$style = $body['style'];
			// `font` accepts two shapes:
			//   - string id (legacy) → adapter resolves against its
			//     curated fallback list
			//   - full pair object {id, heading, body, weight} → wizard
			//     ships this when the user picks from the template's own
			//     `theme_options.typography` list (the studio-curated
			//     per-template set), so the adapter can apply without
			//     looking up an id that may not exist in the curated set
			$font_in = $style['font'] ?? null;
			if ( is_string( $font_in ) && '' !== $font_in ) {
				$font_out = sanitize_key( $font_in );
			} elseif ( is_array( $font_in ) ) {
				$heading = isset( $font_in['heading'] ) && is_string( $font_in['heading'] )
					? sanitize_text_field( $font_in['heading'] ) : '';
				$body_fam = isset( $font_in['body'] ) && is_string( $font_in['body'] )
					? sanitize_text_field( $font_in['body'] ) : '';
				if ( '' !== $heading && '' !== $body_fam ) {
					$font_out = [
						'id'      => isset( $font_in['id'] ) && is_string( $font_in['id'] )
							? sanitize_key( $font_in['id'] )
							: sanitize_key( $heading . '-' . $body_fam ),
						'heading' => $heading,
						'body'    => $body_fam,
						'weight'  => isset( $font_in['weight'] ) ? max( 100, min( 900, (int) $font_in['weight'] ) ) : 600,
					];
				} else {
					$font_out = null;
				}
			} else {
				$font_out = null;
			}
			$config['style'] = [
				'palette' => isset( $style['palette'] ) && is_string( $style['palette'] ) && '' !== $style['palette']
					? sanitize_key( $style['palette'] )
					: null,
				'font'    => $font_out,
			];
		}

		/**
		 * Filter the job config before it's persisted + enqueued.
		 *
		 * @param array<string,mixed> $config Final config — same keys as the request body.
		 * @param array<string,mixed> $body   Raw request body.
		 */
		$config = (array) apply_filters( 'custstsi_job_config', $config, $body );

		// Wipe leftover jobs (stuck `queued` from prior loopback failures
		// would otherwise sit alongside the new one and confuse polling).
		$discarded = $this->jobs->discard_pending();

		$job_id = $this->jobs->create( $config );
		if ( ! empty( $discarded ) ) {
			$this->jobs->log( $job_id, sprintf(
				/* translators: 1: number, 2: comma-separated IDs */
				__( 'Discarded %1$d pending job(s) before starting: %2$s', 'customify-starter-sites' ),
				count( $discarded ),
				implode( ', ', $discarded )
			) );
		}
		$this->runner->enqueue( $job_id );

		return new \WP_REST_Response( [ 'job_id' => $job_id ], 202 );
	}

	/**
	 * Reject the request when the template needs a Pro plugin that is not
	 * installed on this site.
	 *
	 * Mirrors the client-side gate in TemplateCard: a template is Pro when
	 * its `plugins[]` include a Pro slug; it can be imported only once each
	 * required Pro plugin is installed (inactive is fine — the importer
	 * activates it). Fails open if the template's plugin list cannot be
	 * fetched, so a Studio outage never blocks a legitimate import.
	 *
	 * @param int $template_id Studio template id.
	 * @return \WP_Error|null WP_Error when blocked, null when allowed.
	 */
	private function pro_gate_error( int $template_id ) {
		if ( ! $this->client instanceof Remote_Client ) {
			return null; // No client wired — cannot evaluate; fail open.
		}
		if ( ! class_exists( '\Customify_Starter_Sites\Adapters\Customify_Adapter' ) ) {
			return null;
		}

		$res = $this->client->get( "templates/{$template_id}" );
		if ( ! is_array( $res ) || (int) ( $res['status'] ?? 0 ) < 200 || (int) ( $res['status'] ?? 0 ) >= 300 ) {
			return null; // Detail unavailable — fail open.
		}
		$body    = $res['body'] ?? null;
		$plugins = is_array( $body ) && isset( $body['plugins'] ) && is_array( $body['plugins'] )
			? $body['plugins']
			: [];

		// Flatten to directory slugs.
		$slugs = [];
		foreach ( $plugins as $plugin ) {
			if ( is_string( $plugin ) ) {
				$slugs[] = sanitize_key( $plugin );
			} elseif ( is_array( $plugin ) && isset( $plugin['slug'] ) ) {
				$slugs[] = sanitize_key( (string) $plugin['slug'] );
			}
		}

		$pro       = \Customify_Starter_Sites\Adapters\Customify_Adapter::pro_plugin_slugs();
		$installed = \Customify_Starter_Sites\Adapters\Customify_Adapter::installed_pro_plugin_slugs();
		$required  = array_intersect( $pro, $slugs );
		$missing   = array_values( array_diff( $required, $installed ) );

		if ( empty( $missing ) ) {
			return null;
		}

		return new \WP_Error(
			'custstsi_pro_required',
			__( 'This template requires Pro plugins that are not installed yet. Please install Customify Pro and Blocksify Pro first.', 'customify-starter-sites' ),
			[
				'status'  => 403,
				'missing' => $missing,
			]
		);
	}

	public function get_job( \WP_REST_Request $request ) {
		$id  = (string) $request->get_param( 'id' );
		$job = $this->jobs->get( $id );
		if ( null === $job ) {
			return new \WP_Error( 'custstsi_job_not_found', 'Job not found.', [ 'status' => 404 ] );
		}
		return new \WP_REST_Response( $job, 200 );
	}

	public function get_latest( \WP_REST_Request $request ) {
		$job = $this->jobs->latest();
		return new \WP_REST_Response( $job ?? new \stdClass(), 200 );
	}

	public function cancel_job( \WP_REST_Request $request ) {
		$id  = (string) $request->get_param( 'id' );
		$job = $this->jobs->get( $id );
		if ( null === $job ) {
			return new \WP_Error( 'custstsi_job_not_found', 'Job not found.', [ 'status' => 404 ] );
		}
		$this->jobs->request_cancel( $id );
		return new \WP_REST_Response( [ 'ok' => true ], 200 );
	}
}
