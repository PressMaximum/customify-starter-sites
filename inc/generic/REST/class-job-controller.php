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
		// License precheck for the wizard's plugins step — lets the UI show a
		// warning + block "Next" before the user reaches Start, mirroring the
		// server gate applied at job creation.
		register_rest_route( self::NAMESPACE, '/theme/license/check', [
			'methods'             => 'POST',
			'permission_callback' => $perm,
			'callback'            => [ $this, 'check_license' ],
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
	 * Store item ids that unlock each premium template tier. A template whose
	 * tier maps here imports only when a configured license key validates for
	 * ANY one of the listed products; `free` (and any unmapped tier) needs no
	 * license.
	 *
	 *   pressstudio → 66895 (Press Studio) or 66894 (Blocksify Pro)
	 *   presssuites → 66896 (Press Suites) or 66894 (Blocksify Pro)
	 *
	 * Blocksify Pro (66894) is accepted for both tiers so a Blocksify Pro
	 * licensee can import premium templates. The `855` id in customify-pro is
	 * the *plugin's own* license and is unrelated — a Press Studio key returns
	 * `invalid_item_id` for it.
	 *
	 * @var array<string, int[]>
	 */
	private const LICENSE_TIER_ITEM_IDS = [
		'pressstudio' => [ 66895, 66894 ],
		'presssuite'  => [ 66896, 66894 ],
		'presssuites' => [ 66896, 66894 ],
	];

	/**
	 * Product metadata shown by the wizard when a premium template is locked.
	 * The API returns the matching label and checkout URL with the license
	 * verdict so the React client never needs to hard-code product links.
	 *
	 * @var array<string, array{label:string, url:string}>
	 */
	private const LICENSE_TIER_UPSELLS = [
		'pressstudio' => [
			'label' => 'Press Studio',
			'url'   => 'https://pressmaximum.com/pricing/',
		],
		'presssuite'  => [
			'label' => 'Press Suite',
			'url'   => 'https://pressmaximum.com/pricing/',
		],
		'presssuites' => [
			'label' => 'Press Suite',
			'url'   => 'https://pressmaximum.com/pricing/',
		],
	];

	/**
	 * Store statuses that grant import. A Press Studio key is accepted as long
	 * as it belongs to the product and is still live: `valid` (activated here),
	 * or `inactive` / `site_inactive` (a real key not yet activated for this
	 * site — the user only needs to have entered it, per product requirement).
	 * Anything else (`expired`, `disabled`, `revoked`, `invalid`,
	 * `invalid_item_id`, `item_name_mismatch`, …) blocks.
	 */
	private const LICENSE_OK_STATUSES = [ 'valid', 'inactive', 'site_inactive' ];

	/**
	 * REST: precheck a template's license when its preview opens.
	 *
	 * Lets the UI show a warning + disable "Next"/"Start" before the user
	 * reaches the end, mirroring the server gate at job creation. Always 200 —
	 * the verdict is in the body, not the HTTP status, so the UI can render it
	 * inline. Shape:
	 *
	 *   { required: bool, ok: bool, tier: string, tier_label: string,
	 *     upsell_url: string, status: string, code: string, message: string }
	 *
	 * `required=false` → free/unmapped tier, nothing to check (ok=true).
	 *
	 * @return \WP_REST_Response
	 */
	public function check_license( \WP_REST_Request $request ) {
		$body        = $request->get_json_params();
		$template_id = (int) ( is_array( $body ) ? ( $body['template_id'] ?? 0 ) : 0 );
		$tier_hint   = sanitize_key( (string) ( is_array( $body ) ? ( $body['license'] ?? '' ) : '' ) );
		if ( $template_id <= 0 ) {
			return new \WP_Error( 'custstsi_bad_template', 'template_id is required.', [ 'status' => 400 ] );
		}
		return new \WP_REST_Response( $this->evaluate_license( $template_id, $tier_hint ), 200 );
	}

	/**
	 * Convert the license evaluation into a WP_Error for the job-create gate,
	 * or null when the import is allowed. Thin wrapper over
	 * {@see evaluate_license()} so the create + precheck paths share one rule.
	 *
	 * @param int $template_id Studio template id.
	 * @return \WP_Error|null WP_Error when blocked, null when allowed.
	 */
	private function pro_gate_error( int $template_id ) {
		$verdict = $this->evaluate_license( $template_id );
		if ( ! empty( $verdict['ok'] ) ) {
			return null;
		}
		return new \WP_Error(
			(string) $verdict['code'],
			(string) $verdict['message'],
			[
				'status'         => 403,
				'license'        => (string) $verdict['tier'],
				'license_status' => (string) $verdict['status'],
			]
		);
	}

	/**
	 * Evaluate whether a template may be imported under the current license,
	 * for both the job-create gate and the wizard precheck.
	 *
	 * Free templates import with no license. For a premium tier
	 * (Press Studio / Press Suites), the caller must have a license key
	 * configured (Settings → Customify Pro) AND that key must validate against
	 * the store for that tier's product id — checked live via EDD
	 * `check_license`, so an expired/wrong-product key is caught immediately.
	 * A Press Studio license covers the whole template (both Customify Pro and
	 * Blocksify Pro), so a single key per tier is all that's checked.
	 *
	 * Missing *plugins* never affect this — they only warn during the run.
	 * Fails open (ok=true) when the tier can't be determined (Studio outage)
	 * or the store is unreachable, never when a known-premium tier has a bad
	 * key.
	 *
	 * @param int    $template_id Studio template id.
	 * @param string $tier_hint   Catalog tier used only when remote detail is unavailable.
	 * @return array{required:bool, ok:bool, tier:string, tier_label:string, upsell_url:string, status:string, code:string, message:string}
	 */
	private function evaluate_license( int $template_id, string $tier_hint = '' ): array {
		$allow = function ( string $tier = '', string $status = 'ok', bool $required = false ): array {
			return array_merge( [
				'required' => $required,
				'ok'       => true,
				'tier'     => $tier,
				'status'   => $status,
				'code'     => '',
				'message'  => '',
			], $this->license_tier_upsell( $tier ) );
		};

		$tier = sanitize_key( $tier_hint );
		if ( $this->client instanceof Remote_Client ) {
			$res = $this->client->get( "templates/{$template_id}" );
			if ( is_array( $res ) && (int) ( $res['status'] ?? 0 ) >= 200 && (int) ( $res['status'] ?? 0 ) < 300 ) {
				$body        = $res['body'] ?? null;
				$remote_tier = is_array( $body ) ? sanitize_key( (string) ( $body['license'] ?? '' ) ) : '';
				if ( '' !== $remote_tier ) {
					$tier = $remote_tier;
				}
			}
		}

		/**
		 * Filter the tier → store item-id map, so new premium tiers (or a
		 * staging store's ids) can be wired without a code change.
		 *
		 * @param array<string, int[]> $map  tier slug => list of accepting EDD item ids.
		 */
		$tier_map = (array) apply_filters( 'custstsi_license_tier_item_map', self::LICENSE_TIER_ITEM_IDS );

		// Free / unmapped tiers need no license.
		if ( '' === $tier || 'free' === $tier || empty( $tier_map[ $tier ] ) ) {
			return $allow( $tier, 'not_required', false );
		}
		// A tier accepts any of one or more products; tolerate a bare int too.
		$item_ids = array_values( array_filter( array_map( 'intval', (array) $tier_map[ $tier ] ) ) );
		if ( empty( $item_ids ) ) {
			return $allow( $tier, 'not_required', false );
		}

		// Premium tier — at least one PressMaximum license key must be
		// configured (Customify Pro and/or Blocksify Pro).
		$keys = \Customify_Starter_Sites\Settings\Options_Store::license_keys();
		if ( empty( $keys ) ) {
			return array_merge( [
				'required' => true,
				'ok'       => false,
				'tier'     => $tier,
				'status'   => 'missing',
				'code'     => 'custstsi_license_required',
				'message'  => __( 'This is a premium template. Enter your Customify Pro or Blocksify Pro license key under Settings → Customify Pro to import it.', 'customify-starter-sites' ),
			], $this->license_tier_upsell( $tier ) );
		}

		// Try every configured key against every product the tier accepts; the
		// first key+product pair that validates (or a transient store error)
		// unlocks the import. Keep the last real status for the error message.
		$last_status = 'invalid';
		foreach ( $keys as $key ) {
			foreach ( $item_ids as $item_id ) {
				$status = $this->check_license_status( $key, $item_id );

				// Accepted, or a transient store error → fail open.
				if ( in_array( $status, self::LICENSE_OK_STATUSES, true ) || 'unknown' === $status ) {
					return $allow( $tier, $status, true );
				}
				$last_status = $status;
			}
		}

		return array_merge( [
			'required' => true,
			'ok'       => false,
			'tier'     => $tier,
			'status'   => $last_status,
			'code'     => 'custstsi_license_invalid',
			'message'  => sprintf(
				/* translators: %s: license status from the store, e.g. "expired" or "invalid_item_id" */
				__( 'Your license can’t import this template (status: %s). Check that your Customify Pro or Blocksify Pro license key is valid and covers this template under Settings → Customify Pro.', 'customify-starter-sites' ),
				$last_status
			),
		], $this->license_tier_upsell( $tier ) );
	}

	/**
	 * Resolve product metadata for a catalog license tier.
	 *
	 * @param string $tier Normalized catalog license slug.
	 * @return array{tier_label:string, upsell_url:string}
	 */
	private function license_tier_upsell( string $tier ): array {
		/**
		 * Filter the product label and checkout URL used for each premium tier.
		 *
		 * @param array<string, array{label:string, url:string}> $map Tier metadata.
		 */
		$map  = (array) apply_filters( 'custstsi_license_tier_upsell_map', self::LICENSE_TIER_UPSELLS );
		$item = isset( $map[ $tier ] ) && is_array( $map[ $tier ] ) ? $map[ $tier ] : [];

		return [
			'tier_label' => sanitize_text_field( (string) ( $item['label'] ?? '' ) ),
			'upsell_url' => esc_url_raw( (string) ( $item['url'] ?? '' ) ),
		];
	}

	/**
	 * Validate a license key against the store for a specific product via EDD
	 * Software Licensing `check_license`, mirroring customify-pro's updater
	 * transport (same store URL + parameters). Returns the store's `license`
	 * status string lower-cased — `valid`, `inactive`, `site_inactive`,
	 * `expired`, `disabled`, `invalid`, `invalid_item_id`, … — or `unknown`
	 * ONLY when the store can't be reached, returns non-2xx, or an unparseable
	 * body (so callers can fail open on outages). A parseable response is
	 * trusted even when `success:false` — the store still reports the real
	 * status there (e.g. a garbage key returns `success:false, license:invalid`),
	 * and treating that as `unknown` would wrongly let a bad key through.
	 *
	 * @param string $key     License key.
	 * @param int    $item_id EDD download id to validate the key against.
	 * @return string Store status, or 'unknown' on transport/parse failure.
	 */
	private function check_license_status( string $key, int $item_id ): string {
		// Store URL from customify-pro when available (single source of truth),
		// else the known default. Overridable by constant for staging/tests.
		$store = defined( 'CUSTOMIFY_PRO_STORE_URL' ) ? (string) CUSTOMIFY_PRO_STORE_URL : 'https://pressmaximum.com/';
		if ( class_exists( '\Customify_Pro' ) && isset( \Customify_Pro::$api_url ) && '' !== (string) \Customify_Pro::$api_url ) {
			$store = (string) \Customify_Pro::$api_url;
		}

		/**
		 * Filter the EDD check_license request parameters.
		 *
		 * @param array<string,mixed> $params  EDD request body.
		 * @param string              $key     License key being checked.
		 * @param int                 $item_id Product id being checked against.
		 */
		$params = (array) apply_filters( 'custstsi_license_check_params', [
			'edd_action' => 'check_license',
			'license'    => $key,
			'item_id'    => $item_id,
			'url'        => home_url(),
		], $key, $item_id );

		$response = wp_remote_post( $store, [
			'timeout'   => 15,
			'sslverify' => false, // matches customify-pro's updater
			'body'      => $params,
		] );

		if ( is_wp_error( $response ) ) {
			return 'unknown';
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return 'unknown';
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		// Trust any parseable `license` field, even under `success:false` — the
		// store reports the real status there (garbage key → invalid, etc.).
		// Only a missing/non-string license is treated as an outage.
		if ( ! is_array( $data ) || ! isset( $data['license'] ) || ! is_string( $data['license'] ) ) {
			return 'unknown';
		}
		return strtolower( trim( $data['license'] ) );
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
