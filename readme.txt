=== Customify Starter Sites ===
Contributors: pressmaximum
Tags: importer, demo, starter sites, customify
Requires at least: 5.0
Tested up to: 7.0
Stable tag: 0.0.19
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
* When an administrator opens a demo preview, the selected public demo is embedded in an iframe, so the browser connects to the demo URL returned by the service.

The service is provided by PressMaximum: [Terms of Service](https://pressmaximum.com/terms-and-conditions/) and [Privacy Policy](https://pressmaximum.com/privacy-policy/).

Some starter XML or JSON files may be served from `https://raw.githubusercontent.com`. In that case, the site requests only the selected public starter file and sends standard web-request information such as the site's server IP address. GitHub provides that service: [Terms of Service](https://docs.github.com/en/site-policy/github-terms/github-terms-of-service) and [Privacy Statement](https://docs.github.com/en/site-policy/privacy-policies/github-general-privacy-statement).

== Development ==

Human-readable source and build tooling are available at [github.com/PressMaximum/customify-starter-sites](https://github.com/PressMaximum/customify-starter-sites).

== Installation ==

1. Upload the `customify-starter-sites` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Open **Customify → Starter Sites** when the Customify theme is active. Otherwise, open the top-level **Customify Sites** menu.

== Changelog ==

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
