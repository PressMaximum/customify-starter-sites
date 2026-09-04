/**
 * Generic-track admin app entry.
 *
 * Two modes, decided by PHP via `window.customifyStarterSites.embedded`:
 *
 *   - Standalone (default): auto-mounts `<App/>` into
 *     `#customify-starter-sites-app` rendered by `Generic_Dashboard::dashboard()`.
 *
 *   - Embedded: host page (e.g. Customify dashboard) imports the importer
 *     bundle via the adapter's `embed_host_hook()` and calls
 *     `window.customifyStarterSites.mount(el)` from its own React lifecycle.
 *     Auto-mount is skipped — the host owns the mount slot's DOM node.
 *
 * The mount/unmount API is intentionally generic — any future theme
 * adapter that opts into embedding gets the same contract for free.
 */

import './admin.scss';

import { createRoot } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';

import { App } from './components/App';

const roots = new WeakMap();

function mount( el ) {
	if ( ! el || roots.has( el ) ) {
		return;
	}
	const root = createRoot( el );
	root.render( <App /> );
	roots.set( el, root );
}

function unmount( el ) {
	const root = roots.get( el );
	if ( root ) {
		root.unmount();
		roots.delete( el );
	}
}

// PHP `wp_localize_script` has already populated `window.customifyStarterSites`
// with REST root, nonce, etc. Merge the public mount API on top —
// `Object.assign` preserves the boot data instead of clobbering it.
window.customifyStarterSites = Object.assign( window.customifyStarterSites || {}, {
	mount,
	unmount,
} );

// Back-compat alias. Older Customify theme builds embed this importer by
// calling `window.ftDemoImporter.mount(el)` and rendering a slot with the
// legacy id `ft-demo-importer-app`. Expose the same object under the old
// name so those themes keep working without a theme update.
window.ftDemoImporter = window.customifyStarterSites;

// Mount targets, current + legacy id. A host theme (embedded mode) owns
// one of these and calls mount() itself, so we only auto-mount in
// standalone mode.
const MOUNT_IDS = [ 'customify-starter-sites-app', 'ft-demo-importer-app' ];

domReady( () => {
	if ( window.customifyStarterSites?.embedded ) {
		return;
	}
	for ( const id of MOUNT_IDS ) {
		const el = document.getElementById( id );
		if ( el ) {
			mount( el );
		}
	}
} );
