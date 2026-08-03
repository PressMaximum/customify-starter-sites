/**
 * Customify Preview Bridge — iframe listener.
 *
 * Receives a pre-assembled CSS payload from the FameThemes Demo
 * Importer wizard (`PreviewPanel.jsx::sendStyleToIframe`) and pastes
 * it into a single managed `<style>` block at the END of `<head>` so
 * the cascade beats every earlier theme rule.
 *
 * The bridge accepts messages only from a same-origin parent window.
 *
 * Intentionally dumb — every piece of styling logic (palette → CSS
 * vars, font @import lines, font-family overrides) lives in the
 * parent. The bridge here only needs:
 *
 *   1. Detect it's running inside an iframe (skip on direct visits).
 *   2. Receive `css` strings and write them into a single managed
 *      `<style id="cpb-overrides">` tag, moved to the end of `<head>`
 *      on every write so it always wins the cascade.
 *   3. Announce ready upstream so the parent replays its last payload
 *      after slow iframe loads / hard refreshes.
 *
 * No build pipeline — plain ES5 / vanilla JS so the file can run on
 * any frontend without transpilation.
 */
( function () {
	'use strict';

	// Debug — flip to true while wiring up new themes / origins.
	// Off in production so the iframe console stays clean.
	var DEBUG = false;
	function log() {
		if ( ! DEBUG || ! window.console ) {
			return;
		}
		try {
			var args = Array.prototype.slice.call( arguments );
			args.unshift( '[cpb]' );
			window.console.info.apply( window.console, args );
		} catch ( e ) {}
	}

	log( 'script loaded, top===self?', window.top === window.self );

	// Bail when not inside an iframe. Regular site visits skip every
	// listener / network cost below.
	if ( window.top === window.self ) {
		log( 'not in iframe — bail' );
		return;
	}

	// Single managed <style> block — re-appended to <head> on each
	// write so the override always sits last in document order, beating
	// any rule the theme emitted earlier. Idempotent: same id is
	// reused across messages.
	var overrideEl = null;
	function applyCss( css ) {
		if ( ! overrideEl ) {
			overrideEl = document.createElement( 'style' );
			overrideEl.id = 'cpb-overrides';
		}
		overrideEl.textContent = typeof css === 'string' ? css : '';
		// Re-append moves the node to the end of <head>; cheap, and
		// guarantees cascade order even if the theme injects more
		// styles after our first attach.
		document.head.appendChild( overrideEl );
	}

	window.addEventListener( 'message', function ( event ) {
		var data = event && event.data;
		if ( ! data || event.source !== window.parent || event.origin !== window.location.origin ) {
			return;
		}
		log( 'message received', data.type, 'from', event.origin );
		if ( data.type !== 'fdi-preview-style' ) {
			return;
		}
		applyCss( data.css );
		log( 'style applied,', String( data.css || '' ).length, 'chars' );
	} );
	log( 'listener registered' );

	// Tell the parent we're ready. Parent replays the last payload —
	// covers slow loads, hard refreshes, and the parent's first push
	// landing before this listener attached.
	function announceReady() {
		try {
			window.parent.postMessage( { type: 'fdi-preview-ready' }, window.location.origin );
			log( 'announced ready to parent' );
		} catch ( e ) {
			log( 'announce failed', e && e.message );
		}
	}
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', announceReady );
	} else {
		announceReady();
	}
} )();
