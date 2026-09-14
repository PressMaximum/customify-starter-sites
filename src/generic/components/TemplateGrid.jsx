/**
 * Starter Templates page — page header + category pills + search +
 * card grid. Matches mockup3.html (UX research). All colors / buttons
 * use WP admin's `--wp-admin-theme-color` so the dashboard reflects
 * the user's admin color scheme.
 *
 * Data flow (single round-trip):
 *   - Templates  — ONE request on mount with `per_page=-1` (Studio
 *                  returns every template that matches the active
 *                  theme, capped server-side at 500). Search + category
 *                  filtering both happen client-side over the cached
 *                  `items[]` so toggling pills / typing in the box
 *                  never re-hits the network.
 *   - Categories — ONE request on mount alongside the templates fetch.
 *
 * The previous flow paged 24-at-a-time and re-fetched on every search
 * keystroke; that made each character feel laggy and forced a "Load
 * more" affordance for libraries with more than 24 items. The single
 * fetch trades one larger response (typically 50-150 KB JSON for a
 * theme's full catalog) for instant filter UX, which lines up with
 * how WP's own block-pattern picker behaves.
 */

import { useEffect, useMemo, useState } from '@wordpress/element';
import {
	Button,
	Spinner,
		Notice,
		SearchControl,
		SelectControl,
		DropdownMenu,
	MenuGroup,
	MenuItemsChoice,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { category as categoryIcon, chevronDownSmall } from '@wordpress/icons';

import { studio } from '../api';
import { TemplateCard } from './TemplateCard';

function categorySlug( category ) {
	return String( category?.slug || category?.id || category?.name || '' );
}

function categoryLabel( category ) {
	return String( category?.name || category?.label || categorySlug( category ) );
}

/** Build top-level category buttons with their visible descendants. */
function buildCategoryGroups( categories ) {
	const visible = categories.filter( ( category ) => Number( category?.count || 0 ) > 0 );
	const ids = new Set( visible.map( ( category ) => String( category?.id || categorySlug( category ) ) ) );
	const childrenByParent = new Map();

	visible.forEach( ( category ) => {
		const parent = String( category?.parent || 0 );
		const children = childrenByParent.get( parent ) || [];
		children.push( category );
		childrenByParent.set( parent, children );
	} );

	const descendantsFor = ( root ) => {
		const descendants = [];
		const seen = new Set();
		const walk = ( parentId, depth ) => {
			( childrenByParent.get( String( parentId ) ) || [] ).forEach( ( child ) => {
				const childId = String( child?.id || categorySlug( child ) );
				if ( seen.has( childId ) ) {
					return;
				}
				seen.add( childId );
				descendants.push( { category: child, depth } );
				walk( childId, depth + 1 );
			} );
		};
		walk( root?.id || categorySlug( root ), 1 );
		return descendants;
	};

	return visible
		.filter( ( category ) => {
			const parent = String( category?.parent || 0 );
			return '0' === parent || ! ids.has( parent );
		} )
		.map( ( category ) => ( {
			category,
			descendants: descendantsFor( category ),
		} ) );
}

export function TemplateGrid({ onSelect, loadingId = null }) {
	const [items, setItems] = useState([]);
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState(null);
	const [categories, setCategories] = useState([]);
	const [activeCat, setActiveCat] = useState('all');
	const [licenseFilter, setLicenseFilter] = useState('all');
	const [search, setSearch] = useState('');

	// `?no_cache=1` on the admin page URL forwards to the proxy, which clears
	// the ENTIRE templates cache server-side before returning fresh data.
	const noCache = useMemo(() => {
		try {
			return new URLSearchParams(window.location.search).get('no_cache') === '1'
				? { no_cache: 1 }
				: {};
		} catch (e) {
			return {};
		}
	}, []);

	const categoryGroups = useMemo( () => buildCategoryGroups( categories ), [ categories ] );

	// Categories — one-shot fetch on mount. Failure leaves the strip
	// empty (just the "All" pill) rather than blocking the grid.
	//
	// Studio response shape: `{ type, categories: [...], total, uncategorized }`.
	// Accept a bare array or `{items:[...]}` too for resilience against
	// future Studio versions that might normalize the envelope.
	useEffect(() => {
		let cancelled = false;
		studio.listCategories(noCache)
			.then((res) => {
				if (cancelled) {
					return;
				}
				const list = Array.isArray(res)
					? res
					: (res?.categories || res?.items || []);
				setCategories(list);
			})
			.catch(() => { /* silent — strip just shows "All" */ });
		return () => { cancelled = true; };
	}, []);

	// Templates — ONE fetch on mount, strictly scoped to the active
	// theme. `view_context=site` tells the studio to drop the universal-
	// OR fallback so the response contains only templates explicitly
	// bound to this theme's stylesheet. `per_page=-1` asks for the
	// whole list in a single response (capped at 500 server-side).
	useEffect(() => {
		let cancelled = false;
		setLoading(true);
		studio.listTemplates({ per_page: -1, view_context: 'site', ...noCache })
			.then((res) => {
				if (cancelled) {
					return;
				}
				const incoming = Array.isArray(res?.items)
					? res.items
					: ( Array.isArray( res ) ? res : [] );
				setItems(incoming);
				setError(null);
			})
			.catch((e) => {
				if (!cancelled) {
					setError(e.message || __('Failed to load templates.', 'customify-starter-sites'));
				}
			})
			.finally(() => {
				if (!cancelled) {
					setLoading(false);
				}
			});
		return () => {
			cancelled = true;
		};
	}, []);

	// Client-side search + category filter — both run over the same
	// in-memory `items[]`. Search matches title and `keywords[]` so the
	// behaviour matches the studio's `?search=` server-side filter
	// (which searches title + _pmbd_keywords meta). Lower-cased on both
	// sides; substring match.
	const filtered = useMemo(() => {
		const q = search.trim().toLowerCase();
		return items.filter((t) => {
			const license = String( t.license || 'free' ).trim().toLowerCase();
			if ( 'all' !== licenseFilter && licenseFilter !== license ) {
				return false;
			}
			if (activeCat !== 'all') {
				const slugs = new Set( Array.isArray( t.category_slugs ) ? t.category_slugs : [] );
				if ( Array.isArray( t.categories ) ) {
					t.categories.forEach( ( category ) => {
						if ( category?.slug ) {
							slugs.add( category.slug );
						}
						if ( Array.isArray( category?.path ) ) {
							category.path.forEach( ( slug ) => slugs.add( slug ) );
						}
					} );
				}
				if ( ! slugs.has( activeCat ) ) {
					return false;
				}
			}
			if (q === '') {
				return true;
			}
			const title = String(t.title || '').toLowerCase();
			if (title.includes(q)) {
				return true;
			}
			const kws = Array.isArray(t.keywords) ? t.keywords : [];
			return kws.some((k) => String(k).toLowerCase().includes(q));
		});
	}, [items, activeCat, licenseFilter, search]);

	const handleSearch = (value) => {
		setSearch(value);
	};

	const isEmbedded = !! ( typeof window !== 'undefined' && window.customifyStarterSites?.embedded );
	const allLabel = __( 'All', 'customify-starter-sites' );

	return (
		<div className={ 'custstsi-grid-page' + ( isEmbedded ? ' is-embedded' : '' ) }>
			{ ! isEmbedded && (
				<header className="custstsi-page-header">
					<h1 className="wp-heading-inline">
						{__('Starter Templates', 'customify-starter-sites')}
					</h1>
				</header>
			) }

			{error && (
				<Notice status="error" isDismissible={false}>
					{error}
				</Notice>
			)}

			<div className="custstsi-topbar">
				<div className="custstsi-categories">
					<Button
						icon={ categoryIcon }
						className={ 'custstsi-categories__item' + ( 'all' === activeCat ? ' is-active' : '' ) }
						aria-pressed={ 'all' === activeCat }
						onClick={ () => setActiveCat( 'all' ) }
					>
						{ allLabel }
					</Button>

					{ categoryGroups.map( ( group ) => {
						const slug = categorySlug( group.category );
						const label = categoryLabel( group.category );
						if ( 0 === group.descendants.length ) {
							return (
								<Button
									key={ slug }
									className={ 'custstsi-categories__item' + ( slug === activeCat ? ' is-active' : '' ) }
									aria-pressed={ slug === activeCat }
									onClick={ () => setActiveCat( slug ) }
								>
									{ label }
								</Button>
							);
						}

						const choices = [
							{ label, value: slug },
							...group.descendants.map( ( descendant ) => ( {
								label: `${ '— '.repeat( descendant.depth ) }${ categoryLabel( descendant.category ) }`,
								value: categorySlug( descendant.category ),
							} ) ),
						];
						const isActive = choices.some( ( choice ) => choice.value === activeCat );

						return (
							<DropdownMenu
								key={ slug }
								icon={ chevronDownSmall }
								text={ label }
								label={ __( 'Filter by category', 'customify-starter-sites' ) }
								toggleProps={ {
									className: 'custstsi-categories__toggle' + ( isActive ? ' is-active' : '' ),
									'aria-pressed': isActive,
									showTooltip: false,
								} }
								popoverProps={ {
									placement: 'bottom-start',
									className: 'custstsi-category-popover',
								} }
							>
								{ ( { onClose } ) => (
									<MenuGroup>
										<MenuItemsChoice
											choices={ choices }
											value={ String( activeCat ) }
											onSelect={ ( selectedSlug ) => {
												setActiveCat( selectedSlug );
												onClose();
											} }
										/>
									</MenuGroup>
								) }
							</DropdownMenu>
						);
					} ) }
				</div>

				<div className="custstsi-filters">
					<div className="custstsi-license-filter">
						<SelectControl
							__nextHasNoMarginBottom
							value={ licenseFilter }
							onChange={ setLicenseFilter }
							label={ __( 'Filter by license', 'customify-starter-sites' ) }
							hideLabelFromVision
							options={ [
								{ label: __( 'All licenses', 'customify-starter-sites' ), value: 'all' },
								{ label: __( 'Free', 'customify-starter-sites' ), value: 'free' },
								{ label: __( 'Press Studio', 'customify-starter-sites' ), value: 'pressstudio' },
							] }
						/>
					</div>
					<div className="custstsi-search">
						<SearchControl
							__nextHasNoMarginBottom
							value={search}
							onChange={handleSearch}
							placeholder={__('Search templates…', 'customify-starter-sites')}
							label={__('Search templates', 'customify-starter-sites')}
							hideLabelFromVision
						/>
					</div>
				</div>
			</div>

			{loading && items.length === 0 ? (
				<div className="custstsi-loading">
					<Spinner />
					<p>{__('Loading templates from Studio…', 'customify-starter-sites')}</p>
				</div>
			) : filtered.length === 0 ? (
				<div className="custstsi-empty">
					{search || activeCat !== 'all'
						? __('No templates match your filter.', 'customify-starter-sites')
						: __('No templates available yet.', 'customify-starter-sites')
					}
				</div>
			) : (
				<div className="custstsi-grid">
					{filtered.map((t) => (
						<TemplateCard
							key={t.id}
							template={t}
							onSelect={onSelect}
							loading={ loadingId === t.id }
						/>
					))}
				</div>
			)}

		</div>
	);
}
