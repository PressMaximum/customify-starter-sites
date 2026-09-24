=== Starter Templates – Website Templates & Demo Import for Block Editor ===
Contributors: pressmaximum
Tags: starter templates, website templates, demo import, block editor, gutenberg
Requires at least: 7.0
Tested up to: 7.1
Stable tag: 1.0.7
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Browse website templates for the WordPress block editor, preview starter sites, choose colors and fonts, and import demo content with guided setup.

== Description ==

Starter Templates is a WordPress website template library for the WordPress block editor (Gutenberg), with a guided demo importer. Browse starter sites, preview their pages, choose colors and fonts, then import the content and settings you need.

The current templates use the Customify theme and Blocksify. Review each design's plugin requirements before importing.

[Browse Website Templates](https://pressmaximum.com/website-templates/) to explore the available designs and open their live demos. To import a starter site, open Customify → Starter Templates in your WordPress dashboard.

= Find and preview a starter template =

* Search by template name or keywords, and filter by category and license.
* Open a live demo inside your admin dashboard.
* Switch between desktop, tablet and mobile preview widths, or expand the preview area.
* Preview premium designs before choosing a package.

= Choose colors and typography =

Select a color palette and a heading/body font pair before importing. The available choices include template palettes and typography where provided, plus choices supplied by Customify.

Supported demos show your style choices in the live preview. The importer applies those choices to Customify and installs the selected fonts through the WordPress Font Library when available.

= Review and import =

The setup panel shows required and recommended plugins, including their installed and active status. You can select recommended plugins and choose whether to import demo content, widgets and Customizer settings.

During import, the plugin:

* Attempts to install and activate required free plugins from WordPress.org, including Blocksify.
* Imports the template's pages, posts, media and menus when demo content is selected.
* Applies the selected widgets and Customify settings, including the template's header, footer and layout options where supplied.
* Runs a background import job and displays progress, completion or an error message in the setup panel.
* Checks declared plugins after import and attempts to activate installed plugins that are still inactive, respecting your skip choices.

Install any required premium plugins from your PressMaximum account before importing. The importer cannot download those plugins from WordPress.org. If a dependency is unavailable, the import can continue with a warning; features may be missing, and content for an unavailable plugin can be skipped.

Use a new or staging site for your first import. Importing can replace settings and menu items, and re-importing can replace previously imported content. Back up an existing site before proceeding.

= Free starter templates and premium designs =

The plugin is free. Templates marked Free do not require a premium template license. Check each template's plugin requirements as well: a template's license and the licenses for its required plugins are separate.

A Press Studio license key is required to import premium templates. A standalone Customify Pro or Blocksify Pro license does not include premium template access. Use the license filter to browse Free or Press Studio templates.

You can still view a premium demo without access to import it. When a package is required, the setup sidebar names that package and links to [PressMaximum pricing](https://pressmaximum.com/pricing/). A configured license is checked when you open a premium preview and again when you start an import.

= Customify compatibility =

These starter sites are intended for the [Customify theme](https://wordpress.org/themes/customify/). The plugin integrates with its Starter Templates dashboard and applies Customify's settings, palettes and typography. Activate Customify, or a child theme based on Customify, to use this integration.

Each design may require additional plugins for its blocks, shop or other content. Keep those plugins active after importing so the features used by your site remain available.

== External services ==

= PressMaximum / PM Templates =

The plugin uses the public PM Templates catalog at https://pressmaximum.com/wp-json/pm-templates/v1/public/ to list templates, retrieve details and download their files. Public catalog browsing does not require an account or API key.

Requests happen at these points:

* Library loading: your WordPress server requests the template list and category filters. Search, category selection and license filtering in the current library run in your browser on the downloaded list; typing in the search field does not send each search to PressMaximum. Requests may be served from the site's cache.
* Preview setup: your server requests the selected template's details and may download its options.json file to load style choices. These requests identify the template or asset being requested.
* Live preview: your browser loads the public demo URL supplied by the catalog in an iframe, including a timestamp query parameter used to refresh the preview. The demo receives your browser's normal request information, such as IP address and user agent, and may receive a referrer subject to browser policy. Palette and typography selections are sent to the embedded demo as CSS so compatible demos can display them.
* Import: your server retrieves template details and downloads content data in JSON format, options.json, and uploads.zip when supplied. The archive contains the template's media files. These are inbound downloads; the importer does not upload your site's content to the catalog.
* Premium access: when a premium preview opens, when you retry a license check, or when you start a premium import, your server may send configured license keys, read from the Customify Pro or Blocksify Pro settings, to the PressMaximum license service. Each check includes the license key, product ID, site home URL and the check_license action. No license request is sent if there is no configured key to check.

Server requests expose the server's IP address. WordPress's default HTTP user agent includes the WordPress version and site URL. If an advanced configuration supplies a Studio API key, the remote client sends it in the X-PMBD-Api-Key header with catalog requests and import asset downloads. This optional key is separate from premium license verification.

PressMaximum provides the catalog and license service. See its [Terms and Conditions](https://pressmaximum.com/terms-and-conditions/) and [Privacy Policy](https://pressmaximum.com/privacy-policy/). The CUSTOMIFY_STARTER_SITES_STUDIO_URL constant can override the catalog host; license-service configuration is separate.

= WordPress.org and Google Fonts =

During import, WordPress requests plugin information and downloads selected free plugin packages from WordPress.org. These requests identify the plugins being installed and include normal HTTP request information. See the [WordPress.org Privacy Policy](https://wordpress.org/about/privacy/).

When the style panel opens, your browser requests font CSS from fonts.googleapis.com for the displayed font pairs, plus their font files, to render the typography samples. Applying a font pair in the live preview can trigger the same requests from the embedded demo. These requests identify font families and variants and include the browser's normal HTTP information.

During font installation, your server may fetch WordPress's Google Fonts collection from s.w.org, request font CSS from fonts.googleapis.com as a fallback, and download the font files referenced by the collection or CSS, typically from fonts.gstatic.com. Requests identify the collection or requested font resources and include normal HTTP information. Successfully installed fonts are stored locally through the WordPress Font Library. See [Google's Terms of Service](https://policies.google.com/terms) and [Privacy Policy](https://policies.google.com/privacy).

== Installation ==

1. Install and activate the Customify theme, or a Customify child theme.
2. Install this plugin from WordPress.org, or upload the customify-starter-sites folder to /wp-content/plugins/.
3. Activate the plugin and open Customify → Starter Templates.
4. Browse the library and open a template to preview it.
5. Choose a palette and typography, review plugin requirements, and select the content and settings to import. For premium templates, configure your Press Studio license key and install any required premium plugins first.
6. Start the import and follow its progress. When it finishes, review the imported site and replace the demo content with your own.

== Frequently Asked Questions ==

= Do I need Customify? =

Use Customify or a Customify child theme for the template designs and dashboard integration described here. The imported theme settings are specific to Customify.

= Which editor do the templates use? =

The current starter sites use the WordPress block editor (Gutenberg) with Blocksify. A template can also require plugins such as WooCommerce for its shop or other features. After importing, edit the pages and replace the demo text and images with your own.

= Are all templates free? =

The library includes free and premium templates. Use the license filter to find Free or Press Studio templates. A Free label refers to template access; review the required plugins for any separate premium dependencies.

= Can I preview a premium template without a license? =

Yes. The public demo remains visible. If you do not have access to import it, the setup sidebar identifies the required package and links to PressMaximum pricing.

= Do I need a license to import templates? =

You can import templates marked Free without a license key. Only premium templates require a Press Studio license key. Check each template’s required plugins for any separate licenses.

= What changes on an existing site? =

The import adds demo content and can replace imported content, menu items, widgets and theme settings. Re-importing a template can replace edits to its previously imported content. Use a backup and test on a staging site first.

= Can I choose what to import? =

Yes. The setup panel has separate choices for demo content, widgets and Customizer settings, plus a selection of recommended plugins. Media follows the demo content choice. If you skip content, widgets or menus may refer to pages that have not been imported.

= Which plugins are installed automatically? =

The importer attempts to install and activate required free plugins from WordPress.org, including Blocksify, and the recommended plugins you select. Install required premium plugins separately before starting. If a dependency cannot be installed or activated, the import can continue with a warning and some content or features may be unavailable.

= What should I check if an import stops? =

Read the error shown in the setup panel. Check that your server can reach PressMaximum, the template file hosts and WordPress.org, and that required plugins can be installed and activated. Background imports also depend on WordPress cron and loopback requests. Hosting limits and download failures can still interrupt an import.

== Development ==

Source code and build tools are available on [GitHub](https://github.com/PressMaximum/customify-starter-sites).

== Changelog ==

= 1.0.7 =
* FIXED: Background imports now use process-local import permissions without changing user roles, preserving HTML, SVG and block CSS and restoring permissions and filters when the worker finishes or fails.
* FIXED: Additional CSS child selectors could be HTML-encoded during import, breaking header alignment and other layouts. CSS now retains its original syntax.
* FIXED: Customify Font Awesome settings are restored from new exports or inferred from typed icons in older bundles, so TikTok and other v6 icons display alongside legacy icons.
* FIXED: Overlapping import workers could create duplicate pages and posts. Imports now acquire a site-wide worker lock, and a second import request is rejected while another import is queued or running.
* FIXED: Repeated callbacks could restart completed, failed or cancelled imports. These callbacks now leave the existing content unchanged, and sites with disabled WP-Cron use only the synchronous import path.
* FIXED: Draft content with a zero GMT date could fail to import with an invalid-date error.
* FIXED: Re-importing non-public Customify mega contents could create duplicates because the existing-content lookup excluded non-searchable post types. Source-reference lookups now include all registered post types.
* TESTED: Added worker regression checks and verified two complete LILT imports retain 11 imported pages and 3 imported posts without duplicate source references.

= 1.0.6 =
* FIXED: Some templates lost their layout after import — the front page showed the blog instead of the home page — when the template has no separate blog page. The posts-page setting is now cleared so the home page shows correctly.
* FIXED: The header logo (including retina and transparent-header variants) and the site favicon could be wrong or missing after import. Logo image IDs are now remapped to the imported images, and a leftover favicon from a previous import is cleared when the template ships none.
* FIXED: An import that included a video could stop partway with an error. Video and audio files now import reliably.
* FIXED: Rows that filter products by category (for example a "just arrived" row) could show nothing after import; the category filter is now remapped to the imported categories.
* FIXED: Some blocks lost their custom layout CSS after import — a centred block could shift left, or an image could stop filling its column and leave a gap below it. A block's saved custom CSS is now preserved during import.
* IMPROVED: The Choose a style step always starts with a color palette selected, even when the template has no saved palette.

= 1.0.5 =
* FIXED: A "shop by category" or similar row that lists specific product categories could show the wrong items (or none) after import. Category and product IDs pinned in a block are now remapped separately to the newly imported ones, so both product rows and category rows display their intended items.

= 1.0.4 =
* UPDATED: Rename the plugin to Starter Templates – Website Templates & Demo Import for Block Editor.
* UPDATED: Refresh the WordPress.org description, tags, installation instructions and FAQs, with a direct link to browse website templates.
* CLARIFIED: Templates marked Free can be imported without a license key; premium template access is described separately.
* UPDATED: Add a new Starter Templates logo, rounded icons and banners featuring six real website designs on a light gray background.

= 1.0.3 =
* FIXED: Sections that show hand-picked products or posts (for example a "bestsellers" or featured row) rendered "No posts found" after import. The importer now remaps the pinned item IDs to the newly imported ones, so these sections display their intended products and posts.

= 1.0.2 =
* NEW: After an import finishes, verify every plugin the template declares is active — a declared plugin that was installed but inactive (e.g. Blocksify Pro) is now switched on so the template renders faithfully.
* IMPROVED: The plugins step activates the required Pro plugins (Customify Pro / Blocksify Pro) before checking your license, so a licensed site is no longer wrongly told to enter a key.
* IMPROVED: Any Customify Pro / Blocksify Pro or bundle (Press Studio / Press Suites) license — including a child key from a bundle — now unlocks premium templates; accepted products are configured in one place.
* FIXED: Blocksify is activated before Blocksify Pro, which requires it, so activation no longer fails.

= 1.0.1 =
* Import premium templates with a Press Studio license key, checked before import.

= 1.0.0 =
* NEW: Rebuilt starter-site importer — templates now import in the background with a live progress UI, so large sites no longer time out.
* NEW: Live template preview and a guided wizard for choosing plugins, fonts, and options before importing.
* NEW: Bundled plugins (including Blocksify) are installed and activated as part of the import; Pro templates prompt for the required Pro plugins.
* NEW: Fonts install through the WordPress Font Library (WordPress 6.5+).
* IMPROVED: Fully integrated into the Customify dashboard under the Starter Templates tab.

= 0.0.22 =
* Verify escaping and nonce checks directly in code instead of suppressing them, and remove the file-level PHPCS disable from the exporter.
* Add capability- and nonce-checked export links to the Starter Sites screen and drop the old developer query-string links.

= 0.0.21 =
* Escape all WXR export output with the core esc_xml() function instead of a custom helper.
* Remove raw.githubusercontent.com from the download allowlist; starter files are served only by the Customify Starter Sites service.
* Read and write core widget instances through a documented helper so the core widget_{id_base} option namespace is not mistaken for a plugin prefix.
* Raise the minimum WordPress version to 7.0.

= 0.0.20 =
* Replace the admin starter-site preview iframe with a link that opens the public demo in a new browser tab, so no external site is embedded in the dashboard.
* Serve the import placeholder image from the plugin instead of a remote URL.
* Use require_once when conditionally loading core admin files for media side-loading.
* Escape all values echoed into the WXR export document.
* Add unique prefixes to AJAX actions, importer hooks, and helper functions to avoid collisions with other plugins and themes.
* Use wp_is_valid_utf8() with a fallback when preparing export data, avoiding the deprecated seems_utf8() on newer WordPress.

= 0.0.19 =
* Show the Customify requirement notice directly on the Starter Sites page when the theme is inactive.
* Replace deprecated legacy text encoding with a WordPress 5.0 and PHP 7.4 compatible implementation.
* Write release archives to the Git-ignored `dist` directory.

= 0.0.18 =
* Address WordPress.org review feedback for output escaping, plugin headers, core widget option documentation, and bundled Owl Carousel assets.
* Fix Starter Sites button alignment with current WordPress admin styles.
* Place Starter Sites under the Customify menu when the theme is active, with a standalone menu otherwise.
* Harden remote downloads, imported configuration, WXR parsing, and iframe messaging.
* Escape and validate starter-site API data before rendering it in the admin.
* Add external-service disclosure and synchronize release metadata.

= 0.0.17 =
* Maintenance release.

= 0.0.16 =
* Maintenance and tooling updates.
