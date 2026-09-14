/**
 * REST client for the Generic track. Wraps `@wordpress/api-fetch` so the
 * nonce + namespace handling is one place — components just call
 * `studio.listTemplates({...})` / `jobs.create(...)` etc.
 *
 * All routes under `customify-starter-sites/v1/`. Auth: cookie + REST nonce
 * (apiFetch attaches automatically when `wp.apiFetch.createNonceMiddleware`
 * is registered — the Generic_Dashboard's enqueue path passes the nonce
 * via `customifyStarterSites.restNonce`).
 */

import apiFetch from '@wordpress/api-fetch';

const NS = '/customify-starter-sites/v1';

// Boot the nonce middleware once per page load. The localized
// `customifyStarterSites.restNonce` is fresh per request.
if ( window.customifyStarterSites && window.customifyStarterSites.restNonce ) {
	apiFetch.use( apiFetch.createNonceMiddleware( window.customifyStarterSites.restNonce ) );
}

// ---------------------------------------------------------------- Studio

export const studio = {
	me() {
		return apiFetch( { path: `${ NS }/studio/me` } );
	},

	/**
	 * @param {{ search?: string, category?: string, page?: number, per_page?: number }} params
	 */
	listTemplates( params = {} ) {
		const qs = new URLSearchParams();
		Object.entries( params ).forEach( ( [ k, v ] ) => {
			if ( v !== undefined && v !== null && v !== '' ) {
				qs.append( k, v );
			}
		} );
		const suffix = qs.toString() ? `?${ qs }` : '';
		return apiFetch( { path: `${ NS }/studio/templates${ suffix }` } );
	},

	getTemplate( id ) {
		return apiFetch( { path: `${ NS }/studio/templates/${ id }` } );
	},

	/**
	 * Server-side proxy for the template's bundled options.json.
	 * The Studio CDN doesn't allow cross-origin GETs, so the wizard
	 * goes through the plugin's REST namespace instead — `wp_safe_remote_get`
	 * fetches + parses + returns the JSON body. Caller receives the
	 * full parsed object (e.g. `theme.mods.customify_color_palettes`).
	 */
	getTemplateOptions( id ) {
		return apiFetch( { path: `${ NS }/studio/templates/${ id }/options` } );
	},

	listCategories( params = {} ) {
		const qs = new URLSearchParams();
		Object.entries( params ).forEach( ( [ k, v ] ) => {
			if ( v !== undefined && v !== null && v !== '' ) {
				qs.append( k, v );
			}
		} );
		const suffix = qs.toString() ? `?${ qs }` : '';
		return apiFetch( { path: `${ NS }/studio/categories${ suffix }` } );
	},
};

// ---------------------------------------------------------------- Jobs

export const jobs = {
	/**
	 * @param {{ template_id: number, import_content?: boolean, import_uploads?: boolean,
	 *           overwrite_existing?: boolean, replace_settings?: boolean,
	 *           plugins_skip?: string[] }} config
	 */
	create( config ) {
		return apiFetch( {
			path:   `${ NS }/theme/jobs`,
			method: 'POST',
			data:   config,
		} );
	},

	get( id ) {
		return apiFetch( { path: `${ NS }/theme/jobs/${ id }` } );
	},

	latest() {
		return apiFetch( { path: `${ NS }/theme/jobs/latest` } );
	},

	cancel( id ) {
		return apiFetch( {
			path:   `${ NS }/theme/jobs/${ id }/cancel`,
			method: 'POST',
		} );
	},

	/**
	 * Precheck a template's license as soon as its preview opens.
	 * Always resolves 200; the verdict is in the body.
	 *
	 * @param {number} templateId
	 * @param {string} license    Catalog license tier used as a UI fallback.
	 * @return {Promise<{required:boolean, ok:boolean, tier:string, tier_label:string, upsell_url:string, status:string, code:string, message:string}>}
	 */
	checkLicense( templateId, license = '' ) {
		return apiFetch( {
			path:   `${ NS }/theme/license/check`,
			method: 'POST',
			data:   { template_id: templateId, license },
		} );
	},
};
