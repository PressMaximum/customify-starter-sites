/**
 * One template tile in the grid — 4:5 thumb, then a content column with
 * title + description and a footer pinned to the card bottom. The footer
 * holds an Import button (primary) followed by an icon-only Preview that
 * opens the demo site in a new tab. Clicking the thumb (or Import) opens
 * the import wizard. Layout mirrors the public template library card.
 */

import { Button, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { external } from '@wordpress/icons';

/**
 * License tag — same markup/classes as the public template library
 * (`pmtpl-license`): a plain "Press" prefix + a dark tier pill ("Studio", …).
 * `free` shows NO badge. The catalog's license casing is inconsistent
 * ("free"/"Free", "pressstudio"/"PressStudio"), so normalize to lower-case
 * before matching. The card stylesheet ports the library's rules verbatim.
 */
function LicenseBadge( { license } ) {
	const slug = String( license || '' ).trim().toLowerCase();
	if ( '' === slug || 'free' === slug ) {
		return null; // free (or unset) has no badge
	}
	if ( 0 === slug.indexOf( 'press' ) ) {
		const tier = slug.slice( 'press'.length );
		const tierLabel = tier ? tier.charAt( 0 ).toUpperCase() + tier.slice( 1 ) : 'Studio';
		return (
			<span className="pmtpl-license">
				<span className="pmtpl-license__prefix">Press</span>{ ' ' }
				<span className="pmtpl-license__tier">{ tierLabel }</span>
			</span>
		);
	}
	return <span className="pmtpl-license">{ license }</span>;
}

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
	// Prose description under the title (mirrors the public library card).
	// The catalog now ships it as `excerpt` (a string); `keywords` is the
	// separate tag array. Guard against the legacy array-shaped excerpt.
	const description =
		typeof template.excerpt === 'string'
			? template.excerpt
			: template.description || '';
	// License tag — replicates the public library's `pmtpl-license` markup
	// exactly: "free" → a single grey "Free"; any "press*" tier → a plain
	// "Press" prefix + a dark pill for the tier ("Studio", "Suites", …).
	const license = template.license || '';
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
					<Button variant="primary" onClick={ openWizard }>
						{ __( 'Import', 'customify-starter-sites' ) }
					</Button>
					<Button
						variant="secondary"
						className="custstsi-card__preview"
						onClick={ openDemo }
						label={ __( 'Preview site', 'customify-starter-sites' ) }
						showTooltip
						icon={ external }
					/>
					<LicenseBadge license={ license } />
				</footer>
			</div>
		</article>
	);
}
