=== Customify Starter Sites ===
Contributors: pressmaximum
Tags: importer, demo, starter sites, templates, customify
Requires at least: 7.0
Tested up to: 7.1
Stable tag: 1.0.2
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Browse Customify starter templates, preview them live, and import the one you love — pages, plugins, fonts, colors and options included.

== Description ==

Customify Starter Sites adds a **Starter Templates** library to the Customify theme dashboard. Browse the available designs, preview any of them live in your own admin, then import the one you want — all from a guided step-by-step wizard.

When you import a template, the plugin:

* Runs the import **in the background** with a live progress bar, so even large demos with big media libraries no longer hit PHP time limits.
* Installs and activates the **plugins** the template needs (including Blocksify) directly from the WordPress.org plugin directory. Templates that rely on premium plugins ask you to install those first.
* Imports **content** (pages, posts, menus, media) and applies the template's **theme options**, **color palette**, and **typography**.
* Installs the template's **fonts** through the built-in WordPress Font Library (available on WordPress 6.5 and newer).

Everything runs under the **Customify → Starter Templates** tab, integrated into the theme dashboard.

== External services ==

This plugin connects to the Blocksify Design Studio (PM Templates) service hosted at `https://pressmaximum.com/` to provide its template library. Only public, read-only catalog endpoints are used; no account or authentication is required.

What is sent, and when:

* **Browsing templates.** When an administrator opens **Customify → Starter Templates**, the site requests the public template catalog and its filter facets from `https://pressmaximum.com/wp-json/pm-templates/v1/public/*` (for example `.../templates` and `.../filters`). Search text and the selected filters are sent as query parameters, together with the standard web-request information every HTTP request carries, such as the site's IP address and user agent. No personal data from your WordPress site is sent.
* **Previewing a template.** The preview panel loads the public demo URL returned by the catalog inside an iframe so you can scroll through the design before importing. Opening a preview does not send any data from your site beyond the standard web-request information above.
* **Importing a template.** When an administrator starts an import, the site downloads the demo assets referenced by the selected template — the content XML, an uploads archive (media), and an options file — from the URLs the catalog provides (served from the same service). Any required plugins are downloaded from the WordPress.org plugin directory (`https://wordpress.org/`), and any template fonts are fetched through the WordPress Font Library from its configured provider (Google Fonts by default).

The template service is provided by PressMaximum: [Terms of Service](https://pressmaximum.com/terms-and-conditions/) and [Privacy Policy](https://pressmaximum.com/privacy-policy/). Advanced users can point the plugin at a different Studio host by defining the `CUSTOMIFY_STARTER_SITES_STUDIO_URL` constant.

== Development ==

Human-readable source and build tooling are available at [github.com/PressMaximum/customify-starter-sites](https://github.com/PressMaximum/customify-starter-sites).

== Installation ==

1. Install and activate the **Customify** theme.
2. Upload the `customify-starter-sites` folder to `/wp-content/plugins/`, or install it from the **Plugins → Add New** screen.
3. Activate the plugin through the **Plugins** screen in WordPress.
4. Open **Customify → Starter Templates** in the WordPress admin to browse, preview, and import templates.

== Frequently Asked Questions ==

= Do I need the Customify theme? =

Yes. Starter Templates appears inside the Customify theme dashboard, and imported templates apply Customify's theme options, color palette, and typography.

= Does importing overwrite my existing content? =

Importing adds the template's pages, posts, menus, and media to your site and applies its theme options. It is intended for new or staging sites; back up your site before importing into an established one.

= Which plugins get installed? =

Templates declare the plugins they need. Free plugins (including Blocksify) are installed and activated automatically from WordPress.org during the import. Templates that require premium plugins prompt you to install those plugins yourself first.

= Why does the fonts step need WordPress 6.5? =

Fonts are installed through the WordPress Font Library, which was added in WordPress 6.5. On older versions the import still runs; the template simply keeps its default fonts.

== Changelog ==

= 1.0.2 =
* NEW: After an import finishes, verify every plugin the template declares is active — a declared plugin that was installed but inactive (e.g. Blocksify Pro) is now switched on so the template renders faithfully.
* IMPROVED: The plugins step activates the required Pro plugins (Customify Pro / Blocksify Pro) before checking your license, so a licensed site is no longer wrongly told to enter a key.
* IMPROVED: Any Customify Pro / Blocksify Pro or bundle (Press Studio / Press Suites) license — including a child key from a bundle — now unlocks premium templates; accepted products are configured in one place.
* FIXED: Blocksify is activated before Blocksify Pro, which requires it, so activation no longer fails.

= 1.0.1 =
* Unlock premium templates with a Customify Pro or Blocksify Pro license, verified at import.

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
