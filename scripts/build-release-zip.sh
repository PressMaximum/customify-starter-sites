#!/usr/bin/env bash
# Build a local archive without releasing or modifying version metadata.
set -euo pipefail
plugin_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$plugin_root"
node scripts/validate-release.cjs
plugin_version="$(node -p "require('./package.json').version")"
package_stage="$(mktemp -d)"
trap 'rm -rf "$package_stage"' EXIT
mkdir -p "$package_stage/customify-starter-sites" dist
rsync -a --exclude-from=.distignore ./ "$package_stage/customify-starter-sites/"
package_archive="$package_stage/customify-starter-sites-$plugin_version.zip"
(cd "$package_stage" && zip -qr "$package_archive" customify-starter-sites)
python3 scripts/validate-release-zip.py "$package_archive" "$plugin_version"
cp "$package_archive" "dist/customify-starter-sites-$plugin_version.zip"
