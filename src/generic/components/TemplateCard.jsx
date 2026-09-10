/**
 * One template tile in the grid — 4:5 thumb, then a content column with
 * title + description and a footer pinned to the card bottom. The footer
 * holds an Import button (primary) followed by an icon-only Preview that
 * opens the demo site in a new tab. Clicking the thumb (or Import) opens
 * the import wizard. Layout mirrors the public template library card.
 */

import { Button, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export function TemplateCard( { template, onSelect, loading = false } ) {
	// Studio's preview_image is a nested attachment-shape (see
	// `docs/studio/rest-api.md` §preview_image): full / medium / thumb
	// crops, plus a flat top-level `url`. Prefer the largest crop the
	// browser will actually render at 3:4 card width — `full` typically
	// matches the source upload size; `medium` is the 300x200 thumbnail
	// fallback for templates with smaller source images.
	//
	// `template.preview_url` is the iframe demo route (e.g. `?pmbd_preview=N`),
	// NOT an image URL — used by PreviewPanel.jsx, never by the card.
	const preview = template.preview_image;
	const rawThumb = preview?.full?.url
		|| preview?.medium?.url
		|| preview?.url
		|| template.thumb_url
		|| '';

	// Cache-bust the thumbnail URL with the template's `version`
	// counter so admins see fresh screenshots immediately after a
	// re-submit (each re-submit increments the version), but the
	// browser still caches between updates — much better than
	// `Date.now()` which would defeat caching entirely.
	const thumb = rawThumb && template.version != null
		? `${ rawThumb }${ rawThumb.includes( '?' ) ? '&' : '?' }v=${ encodeURIComponent( template.version ) }`
		: rawThumb;
	const name    = template.title || template.name || `#${ template.id }`;
	// Prose description shown under the title when the catalog provides one.
	// (`keywords`/`excerpt` are tag arrays, never a description.)
	const description = template.description || template.content || template.subtitle || '';
	// License badge (matches the public library's "Press Studio" tag).
	const license = template.license || '';
	const licenseLabel =
		license === 'pressstudio'
			? __( 'Studio', 'customify-starter-sites' )
			: license === 'free'
				? __( 'Free', 'customify-starter-sites' )
				: '';
	// Studio's canonical demo URL lives at `preview_url` (verified from
	// `GET /studio/templates`). `demo_url` / `frame_url` /
	// `preview_route` are kept as fallbacks for legacy Studio schemas
	// and for tests that stub the shape.
	const demoUrl =
		template.preview_url
		|| template.demo_url
		|| template.frame_url
		|| template.preview_route
		|| '';

	const openWizard = () => {
		if ( loading ) return; // a prefetch is already running for this card
		onSelect( template );
	};

	const handleThumbClick = ( e ) => {
		// Buttons inside the body row handle their own clicks via
		// `<Button onClick>`; only the thumb surface itself opens the
		// wizard, so stopPropagation on the buttons isn't needed.
		e.preventDefault();
		openWizard();
	};

	const openDemo = ( e ) => {
		e.preventDefault();
		if ( demoUrl ) {
			window.open( demoUrl, '_blank', 'noopener,noreferrer' );
		} else {
			openWizard();
		}
	};

	return (
		<article
			className={ 'custstsi-card' + ( loading ? ' is-loading' : '' ) }
			data-template-id={ template.id }
			aria-busy={ loading || undefined }
		>
			{ loading && (
				<div className="custstsi-card__loading" role="status" aria-label={ __( 'Loading template…', 'customify-starter-sites' ) }>
					<Spinner />
				</div>
			) }
			<div
				className="custstsi-card__thumb"
				onClick={ handleThumbClick }
				role="button"
				tabIndex={ 0 }
				onKeyDown={ ( e ) => { if ( e.key === 'Enter' ) handleThumbClick( e ); } }
				aria-label={ name }
			>
				{ thumb && (
					<img src={ thumb } alt="" loading="lazy" />
				) }
			</div>

			<div className="custstsi-card__content">
				<div className="custstsi-card__body">
					<h3 className="custstsi-card__title" title={ name }>{ name }</h3>
					{ description && (
						<p className="custstsi-card__desc">{ description }</p>
					) }
				</div>
				<footer className="custstsi-card__footer">
					<Button
						variant="primary"
						className="custstsi-card__import"
						onClick={ openWizard }
					>
						{ __( 'Import', 'customify-starter-sites' ) }
					</Button>
					{ licenseLabel && (
						<span className={ `custstsi-card__license is-${ license }` }>
							{ licenseLabel }
						</span>
					) }
					<button
						type="button"
						className="custstsi-card__preview"
						onClick={ openDemo }
						aria-label={ __( 'Preview site', 'customify-starter-sites' ) }
						title={ __( 'Preview site', 'customify-starter-sites' ) }
					>
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" aria-hidden="true" focusable="false">
							<path d="M7 7h10v10" />
							<path d="M7 17 17 7" />
						</svg>
					</button>
				</footer>
			</div>
		</article>
	);
}
