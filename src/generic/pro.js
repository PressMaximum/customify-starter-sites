/**
 * Pro-plugin gating helpers.
 *
 * A template is "Pro" when its `plugins[]` reference a Pro plugin slug
 * (e.g. `customify-pro`, `blocksify-pro`). A Pro template can only be
 * imported once every Pro plugin it needs is installed on the site — an
 * installed-but-inactive Pro plugin is fine (the importer activates it),
 * so only a *missing* Pro plugin blocks the import.
 *
 * The slug lists come from PHP via `window.customifyStarterSites`:
 *   - `proPlugins`          — all slugs that mark a template as Pro.
 *   - `installedProPlugins` — the subset already installed on the site.
 */

/** Pro slugs that gate templates (from PHP, with a safe fallback). */
export function proPluginSlugs() {
	const cfg =
		typeof window !== 'undefined' ? window.customifyStarterSites : null;
	const list = Array.isArray( cfg?.proPlugins ) ? cfg.proPlugins : [];
	return list.length ? list : [ 'customify-pro', 'blocksify-pro' ];
}

/** Pro slugs already installed on the site (may be inactive). */
export function installedProPluginSlugs() {
	const cfg =
		typeof window !== 'undefined' ? window.customifyStarterSites : null;
	return Array.isArray( cfg?.installedProPlugins )
		? cfg.installedProPlugins
		: [];
}

/** Flatten a template's `plugins[]` down to a list of directory slugs. */
export function templatePluginSlugs( template ) {
	const plugins = template?.plugins;
	if ( ! Array.isArray( plugins ) ) {
		return [];
	}
	return plugins
		.map( ( p ) => ( typeof p === 'string' ? p : p?.slug || p?.name || '' ) )
		.filter( Boolean );
}

/**
 * Pro plugin slugs this template requires (intersection of the
 * template's plugins and the gated Pro list).
 */
export function requiredProSlugs( template ) {
	const pro = proPluginSlugs();
	return templatePluginSlugs( template ).filter( ( slug ) =>
		pro.includes( slug )
	);
}

/** True when the template needs at least one Pro plugin. */
export function isProTemplate( template ) {
	// Honour an explicit Studio `is_pro`/`pro` flag too, but the primary
	// signal is the presence of a Pro plugin in `plugins[]`.
	return (
		Boolean( template?.is_pro || template?.pro ) ||
		requiredProSlugs( template ).length > 0
	);
}

/**
 * Pro plugin slugs the template needs that are NOT installed yet.
 * A non-empty result means Import must be blocked until they're installed.
 */
export function missingProSlugs( template ) {
	const installed = installedProPluginSlugs();
	return requiredProSlugs( template ).filter(
		( slug ) => ! installed.includes( slug )
	);
}

/** True when the template cannot be imported yet (a Pro plugin is missing). */
export function isProBlocked( template ) {
	return missingProSlugs( template ).length > 0;
}
