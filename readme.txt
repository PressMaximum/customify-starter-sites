=== Customify Starter Sites ===
Contributors: pressmaximum
Tags: importer, demo, starter sites, customify
Requires at least: 7.0
Tested up to: 7.1
Stable tag: 0.0.22
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Import starter sites demo content for the Customify theme.

== Description ==

The Customify Starter Sites plugin connects to the demo library and imports site content,
theme options, widgets, and related configuration.

== External services ==

This plugin connects to the Customify Starter Sites service at `https://customifysites.com`.

* When an administrator opens Customify > Starter Sites (or the top-level Customify Sites menu when the Customify theme is inactive), the browser requests the site catalog from `https://customifysites.com/wp-json/wp/v2.1/sites/`. The selected filters, search text, and page-builder choice are sent with the request, together with standard web-request information such as the visitor's IP address and user agent.
* When an administrator starts an import, the site downloads the selected XML, JSON, images, and other public demo assets identified by that catalog. A small set of legacy demos also requests Elementor metadata from `https://customifysites.com/wp-content/uploads/demo-meta/`.
* When an administrator opens a demo preview, the selected public demo URL returned by the service is opened in a new browser tab.

The service is provided by PressMaximum: [Terms of Service](https://pressmaximum.com/terms-and-conditions/) and [Privacy Policy](https://pressmaximum.com/privacy-policy/).

== Development ==

Human-readable source and build tooling are available at [github.com/PressMaximum/customify-starter-sites](https://github.com/PressMaximum/customify-starter-sites).

== Installation ==

1. Upload the `customify-starter-sites` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Open **Customify → Starter Sites** when the Customify theme is active. Otherwise, open the top-level **Customify Sites** menu.

== Changelog ==

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
