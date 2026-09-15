# WordPress.org listing artwork

The listing uses the Starter Templates name, a new page-layout logo, a neutral light-gray background, pale primary-blue highlights and six real catalog screenshots. The public copy lives in the root readme.txt; the plugin header uses the same title and short description.

## Directory assets

Publishing a stable GitHub Release runs the 10up workflow, which uploads these PNGs to the plugin's SVN assets directory alongside the runtime release. See [release instructions](../docs/RELEASING.md).

| File | Dimensions |
| --- | --- |
| banner-1544x500.png | 1544 × 500 |
| banner-772x250.png | 772 × 250 |
| icon-256x256.png | 256 × 256 |
| icon-128x128.png | 128 × 128 |

The icons have slightly rounded corners with transparency outside the shape. starter-templates-logo.svg is the editable logo source. The logo is shared by both icons and banners.

screenshot-provenance.json records the original public image URLs, source hashes, crop rectangles and placement for all six catalog screenshots. Image hashes refer to the original downloaded WebP files, not to the final PNG crops. All screenshot pixels are source-derived; the blank background was recolored using built-in Imagegen. The initial logo concept was generated and then reconstructed as editable vector geometry.

The plugin slug, text domain, runtime APIs and version are unchanged. This directory and local review artifacts are excluded from runtime packages. Manual workflow runs are dry runs and do not publish assets or create releases.

## Copy and compatibility

Tags: starter templates, website templates, demo import, block editor, gutenberg.

The license FAQ leads with Free templates requiring no key. The proposed premium wording follows the owner's Press Studio requirement. Version 1.0.2's historical changelog is retained; its runtime gate currently accepts a broader set of product IDs (Press Studio, Press Suites and Blocksify Pro). This listing change does not narrow runtime entitlements. Resolve that policy/runtime difference separately before publishing license claims.

## References

- [Public template catalog](https://pressmaximum.com/website-templates/)
- [WordPress.org asset requirements](https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/)
- [WordPress.org readme guidance](https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/)
