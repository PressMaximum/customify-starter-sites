<?php
/**
 * Thin getter/setter facade over the two wp_options rows the generic
 * track persists:
 *
 *   - `custstsi_studio_url` — base URL of the PM Templates server
 *     that supplies templates (e.g. `https://design-library.example.com/`).
 *     The plugin consumes its **public catalog** (`pm-templates/v1/public`),
 *     which needs no authentication.
 *   - `custstsi_studio_key` — legacy `read`-scope API key. Optional
 *     and ignored by the public catalog; kept for backward compatibility.
 *     Never sent to the browser — only the masked prefix is exposed.
 *
 * Option names are deliberately prefixed `custstsi_` to avoid
 * collisions with the OnePress track (which stores nothing in wp_options
 * apart from transients) and with the `pmbd_importer_*` options used by
 * blocksify-design-importer itself — both plugins can be active in the
 * same install.
 */

namespace Customify_Starter_Sites\Settings;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Options_Store {

	public const OPT_STUDIO_URL = 'custstsi_studio_url';
	public const OPT_STUDIO_KEY = 'custstsi_studio_key';

	/**
	 * Built-in Studio that ships with the plugin — used when neither the
	 * wp-config constant nor the saved option is set. Defining this in
	 * code (instead of seeding a wp_option on activation) means the
	 * fallback always reflects the current plugin version, even after
	 * the user clears the option.
	 */
	public const DEFAULT_STUDIO_URL = 'https://pressmaximum.com/';

	/**
	 * wp-config constants that override the wp_options values entirely.
	 * Set them in wp-config.php to lock credentials across environments
	 * (and to avoid storing the key in the database where staging dumps
	 * might leak it):
	 *
	 *   define( 'CUSTOMIFY_STARTER_SITES_STUDIO_URL', 'https://studio.example.com/' );
	 *   define( 'CUSTOMIFY_STARTER_SITES_STUDIO_KEY', 'pmbd_live_xxxxxxxx' );
	 *
	 * When defined and non-empty, the corresponding Settings page input
	 * becomes read-only and `set_*()` is a no-op.
	 */
	public const CONST_STUDIO_URL = 'CUSTOMIFY_STARTER_SITES_STUDIO_URL';
	public const CONST_STUDIO_KEY = 'CUSTOMIFY_STARTER_SITES_STUDIO_KEY';

	/**
	 * Resolve the Studio URL with this priority order:
	 *
	 *   1. wp-config constant `CUSTOMIFY_STARTER_SITES_STUDIO_URL` (highest)
	 *   2. Saved `custstsi_studio_url` option
	 *   3. Plugin default `https://pressmaximum.com/`
	 *
	 * The `custstsi_default_studio_url` filter runs last so
	 * programmatic overrides still win — useful for tests + multisite
	 * setups that pre-fill per site.
	 */
	public function studio_url(): string {
		if ( $this->is_studio_url_locked() ) {
			$url = (string) constant( self::CONST_STUDIO_URL );
		} else {
			$url = (string) get_option( self::OPT_STUDIO_URL, '' );
			if ( '' === trim( $url ) ) {
				$url = self::DEFAULT_STUDIO_URL;
			}
		}
		$url = (string) apply_filters( 'custstsi_default_studio_url', $url );
		return trim( $url );
	}

	/**
	 * True when the wp-config constant is defined + non-empty. Settings
	 * UI uses this to disable the URL input and show a "locked by
	 * wp-config" note so the user understands why their saved value
	 * isn't being used.
	 */
	public function is_studio_url_locked(): bool {
		if ( ! defined( self::CONST_STUDIO_URL ) ) {
			return false;
		}
		return '' !== trim( (string) constant( self::CONST_STUDIO_URL ) );
	}

	/**
	 * Resolve the API key with this priority order (mirrors {@see studio_url()}):
	 *
	 *   1. wp-config constant `CUSTOMIFY_STARTER_SITES_STUDIO_KEY` (highest)
	 *   2. Saved `custstsi_studio_key` option
	 *   3. Empty string (= unauthenticated; public read endpoints still work)
	 *
	 * The `custstsi_studio_key` filter runs last so a theme
	 * adapter (or any other consumer) can inject its own bundled key
	 * without forcing the user to copy/paste one into Settings. The
	 * second arg `$source` says where the base value came from so
	 * adapter callbacks can decide to only override when nothing
	 * was configured locally:
	 *
	 *   add_filter( 'custstsi_studio_key', function ( $key, $source ) {
	 *       return ( 'empty' === $source ) ? 'pmbd_live_xxx' : $key;
	 *   }, 10, 2 );
	 */
	public function studio_key(): string {
		if ( $this->is_studio_key_locked() ) {
			$key    = (string) constant( self::CONST_STUDIO_KEY );
			$source = 'constant';
		} else {
			$key    = (string) get_option( self::OPT_STUDIO_KEY, '' );
			$source = '' !== trim( $key ) ? 'saved' : 'empty';
		}
		$key = (string) apply_filters( 'custstsi_studio_key', $key, $source );
		return trim( $key );
	}

	/**
	 * True when the wp-config constant is defined + non-empty. Same
	 * semantics as {@see is_studio_url_locked()} — UI disables the
	 * input + `set_studio_key()` becomes a no-op.
	 */
	public function is_studio_key_locked(): bool {
		if ( ! defined( self::CONST_STUDIO_KEY ) ) {
			return false;
		}
		return '' !== trim( (string) constant( self::CONST_STUDIO_KEY ) );
	}

	public function has_credentials(): bool {
		return '' !== $this->studio_url() && '' !== $this->studio_key();
	}

	/**
	 * wp_options rows that the PressMaximum plugins' EDD Software Licensing
	 * writes their license data into, each an array shaped
	 * `[ 'license' => '<key>', 'data' => [...], 'error' => false ]`:
	 *
	 *   - customify_pro_license_data  — Customify Pro's updater
	 *   - blocksify_pro_license_data  — Blocksify Pro's updater
	 *
	 * A premium ("Press Studio") template can be unlocked by whichever of these
	 * keys validates for the template's product, so the gate tries them all.
	 */
	public const LICENSE_OPTION_KEYS = [
		'customify_pro_license_data',
		'blocksify_pro_license_data',
	];

	/**
	 * The Customify Pro license key (from `customify_pro_license_data`), or ''.
	 * Kept for callers that specifically want Customify Pro's key; the gate
	 * itself uses {@see license_keys()} to also consider Blocksify Pro.
	 */
	public static function customify_pro_license_key(): string {
		return self::read_license_key( 'customify_pro_license_data' );
	}

	/**
	 * The Blocksify Pro license key (from `blocksify_pro_license_data`), or ''.
	 */
	public static function blocksify_pro_license_key(): string {
		return self::read_license_key( 'blocksify_pro_license_data' );
	}

	/**
	 * Read one license option row's `license` key, trimmed, or '' when absent.
	 *
	 * @param string $option_name wp_option name.
	 */
	private static function read_license_key( string $option_name ): string {
		$data = get_option( $option_name, [] );
		return is_array( $data ) && ! empty( $data['license'] ) ? trim( (string) $data['license'] ) : '';
	}

	/**
	 * Every configured PressMaximum license key, de-duplicated, in priority
	 * order (Customify Pro first, then Blocksify Pro). Empty when none is set.
	 * The gate verifies each against the template's product and accepts the
	 * first that validates — so a key entered under either plugin unlocks a
	 * premium template it covers.
	 *
	 * @return string[]
	 */
	public static function license_keys(): array {
		$keys = [];
		foreach ( self::LICENSE_OPTION_KEYS as $option_name ) {
			$key = self::read_license_key( $option_name );
			if ( '' !== $key && ! in_array( $key, $keys, true ) ) {
				$keys[] = $key;
			}
		}
		return $keys;
	}

	public function set_studio_url( string $url ): bool {
		// No-op when locked by wp-config — the value would be ignored by
		// `studio_url()` anyway, so persisting it would just confuse
		// future admins looking at the wp_options row.
		if ( $this->is_studio_url_locked() ) {
			return false;
		}
		$url = trim( $url );
		// Empty input clears the option — same convention WP Settings API uses.
		if ( '' === $url ) {
			return delete_option( self::OPT_STUDIO_URL );
		}
		return update_option( self::OPT_STUDIO_URL, esc_url_raw( $url ) );
	}

	public function set_studio_key( string $key ): bool {
		// No-op when locked by wp-config — see set_studio_url() for the
		// "don't persist a value studio_key() will ignore" rationale.
		if ( $this->is_studio_key_locked() ) {
			return false;
		}
		$key = trim( $key );
		if ( '' === $key ) {
			return delete_option( self::OPT_STUDIO_KEY );
		}
		return update_option( self::OPT_STUDIO_KEY, sanitize_text_field( $key ) );
	}

	/**
	 * Return the key shaped for safe display in the admin UI — first 12
	 * chars + asterisks. Lets the user verify they pasted the right key
	 * without leaking it on screen.
	 */
	public function masked_key(): string {
		$key = $this->studio_key();
		if ( strlen( $key ) <= 12 ) {
			return $key ? str_repeat( '•', strlen( $key ) ) : '';
		}
		return substr( $key, 0, 12 ) . str_repeat( '•', 8 );
	}
}
