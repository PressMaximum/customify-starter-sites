#!/usr/bin/env bash
#
# Package Customify Starter Sites as a .zip and create or update a GitHub Release (GitHub CLI: gh).
# Lives in the plugin root next to customify-starter-sites.php.
#
# Usage (bash; if you invoke `sh ./release.sh`, the script will re-exec under bash):
#   cd wp-content/plugins/customify-starter-sites
#   ./release.sh [--dry-run]
#
# Requirements:
#   - gh logged in: gh auth login
#   - Plugin directory is a git clone of the target GitHub repo (or GH_REPO=owner/repo)
#
# Zip output:
#   - dist/customify-starter-sites-<Version>.zip inside the plugin directory
#
# Version & file sync:
#   - Read only from package.json (requires Node).
#   - Before zipping (except --dry-run): write plugin header Version in customify-starter-sites.php
#     and Stable tag line in readme.txt.

set -euo pipefail

# Re-exec bash when run as `sh release.sh` (dash/ash) — script uses [[, pipefail, $BASH_SOURCE.
if [ -z "${BASH_VERSION-}" ]; then
	exec /usr/bin/env bash "$0" "$@"
fi

DRY_RUN=0
while [[ "${1:-}" == -* ]]; do
	case "$1" in
		--dry-run) DRY_RUN=1 ;;
		-h|--help)
			echo "Usage: $0 [--dry-run]"
			exit 0
			;;
		*)
			echo "Unknown option: $1" >&2
			exit 1
			;;
	esac
	shift || true
done

# Plugin directory follows the script path (POSIX: ${BASH_SOURCE[0]} empty under sh — use $0).
SCRIPT_PATH="${BASH_SOURCE[0]:-$0}"
PLUGIN_DIR="$(cd "$(dirname "$SCRIPT_PATH")" && pwd)"
PLUGIN_SLUG="customify-starter-sites"

MAIN_FILE="${PLUGIN_DIR}/${PLUGIN_SLUG}.php"
PACKAGE_JSON="${PLUGIN_DIR}/package.json"
DIST_DIR="${PLUGIN_DIR}/dist"

cd "${PLUGIN_DIR}"

if ! command -v gh >/dev/null 2>&1; then
	echo "ERROR: Missing gh (GitHub CLI)." >&2
	exit 1
fi
if ! command -v zip >/dev/null 2>&1; then
	echo "ERROR: Missing zip." >&2
	exit 1
fi
if ! command -v rsync >/dev/null 2>&1; then
	echo "ERROR: Missing rsync." >&2
	exit 1
fi

if [[ ! -f "${MAIN_FILE}" ]]; then
	echo "ERROR: Not found: ${MAIN_FILE}" >&2
	exit 1
fi

if [[ ! -f "${PACKAGE_JSON}" ]]; then
	echo "ERROR: Missing ${PACKAGE_JSON} — required for the version." >&2
	exit 1
fi

if ! command -v node >/dev/null 2>&1; then
	echo "ERROR: Node.js is required to read package.json and sync the version." >&2
	exit 1
fi

VERSION="$(node -p "require('./package.json').version" 2>/dev/null | tr -d $'\t\r\n' || true)"

if [[ -z "${VERSION}" ]]; then
	echo "ERROR: package.json has no valid version field." >&2
	exit 1
fi

if [[ ! "${VERSION}" =~ ^[0-9A-Za-z._+-]+$ ]]; then
	echo "ERROR: version in package.json contains disallowed characters: ${VERSION}" >&2
	exit 1
fi

sync_plugin_version_files() {
	if [[ "${DRY_RUN}" -eq 1 ]]; then
		echo "[dry-run] Would update Version (${VERSION}) in ${PLUGIN_SLUG}.php and Stable tag in readme.txt"
		return 0
	fi

	export SYNC_VER="${VERSION}"
	export SYNC_PLUGIN_DIR="${PLUGIN_DIR}"
	export SYNC_PLUGIN_SLUG="${PLUGIN_SLUG}"

	node <<'NODESYNC'
const fs = require('fs');
const path = require('path');
const dir = process.env.SYNC_PLUGIN_DIR;
const slug = process.env.SYNC_PLUGIN_SLUG;
const v = process.env.SYNC_VER;
if (!dir || !slug || !v) {
	process.stderr.write('ERROR: Missing sync environment variables.\n');
	process.exit(1);
}

const mainPath = path.join(dir, `${slug}.php`);
let php = fs.readFileSync(mainPath, 'utf8');
if (php.charCodeAt(0) === 0xfeff) {
	php = php.slice(1);
}

// Normalize line endings (Classic Mac CR-only → \n)
php = php.replace(/\r\n/g, '\n').replace(/\r/g, '\n');

// Line-by-line avoids fragile ^/$ over the whole buffer
let hitHeaderVersion = false;
const linesPhp = php.split('\n');
const maxScan = Math.min(linesPhp.length, 120);
for (let i = 0; i < maxScan; i++) {
	const line = linesPhp[i];
	if (/^\s*\*\s*Version\s*:/.test(line) || /^Version\s*:/.test(line.trimStart())) {
		// Same version as package.json → replace leaves string unchanged; only error when no semver token
		if (!/Version\s*:\s*\S+/.test(line)) {
			process.stderr.write(
				`ERROR: Found a Version header in ${slug}.php but could not parse the value (line ${i + 1}).\n`
			);
			process.exit(1);
		}
		const nextLine = line.replace(/Version\s*:\s*\S+/, `Version: ${v}`);
		linesPhp[i] = nextLine;
		hitHeaderVersion = true;
		break;
	}
}
if (!hitHeaderVersion) {
	process.stderr.write(
		`ERROR: No "Version:" line found in ${slug}.php within the first ~120 lines.\n`
	);
	process.exit(1);
}
php = linesPhp.join('\n');

fs.writeFileSync(mainPath, php);

const readmePath = path.join(dir, 'readme.txt');
if (fs.existsSync(readmePath)) {
	let rd = fs.readFileSync(readmePath, 'utf8');
	if (rd.charCodeAt(0) === 0xfeff) {
		rd = rd.slice(1);
	}
	rd = rd.replace(/\r\n/g, '\n').replace(/\r/g, '\n');
	if (/Stable tag:[ \t]*\S+/m.test(rd)) {
		rd = rd.replace(/Stable tag:[ \t]*\S+/, `Stable tag: ${v}`);
		fs.writeFileSync(readmePath, rd);
	}
}
NODESYNC
	echo "→ Synced Version / Stable tag from package.json → plugin + readme.txt"
	unset SYNC_VER SYNC_PLUGIN_DIR SYNC_PLUGIN_SLUG
}

sync_plugin_version_files

TAG="v${VERSION}"
ZIP_NAME="${PLUGIN_SLUG}-${VERSION}.zip"
ZIP_PATH="${DIST_DIR}/${ZIP_NAME}"

echo "→ Plugin dir: ${PLUGIN_DIR}"
if [[ "${DRY_RUN}" -eq 1 ]]; then
	echo "→ Version:    ${VERSION} (package.json — dry-run: files not modified, gh not required)"
else
	echo "→ Version:    ${VERSION} (package.json — written to ${PLUGIN_SLUG}.php and readme Stable tag)"
fi
echo "→ Tag/release: ${TAG}"
echo "→ Output zip: ${ZIP_PATH}"

run() {
	if [[ "${DRY_RUN}" -eq 1 ]]; then
		printf '[dry-run] '
		printf '%q ' "$@"
		echo
	else
		"$@"
	fi
}

if [[ "${DRY_RUN}" -ne 1 ]]; then
	gh auth status >/dev/null 2>&1 || {
		echo "ERROR: gh is not logged in. Run: gh auth login" >&2
		exit 1
	}
fi

mkdir -p "${DIST_DIR}"

STAGE="$(mktemp -d)"
cleanup() {
	rm -rf "${STAGE}"
}
trap cleanup EXIT

DEST="${STAGE}/${PLUGIN_SLUG}"
mkdir -p "${DEST}"

# Ship WordPress runtime only — exclude build tools, deps, dev sources.
RSYNC_EXCLUDES=(
	# Repo / CI
	'--exclude=.git/'
	'--exclude=.gitignore'
	'--exclude=.github/'
	'--exclude=.wordpress-org/'
	'--exclude=review/'
	'--exclude=.gitlab-ci.yml'
	'--exclude=.travis.yml'
	'--exclude=bitbucket-pipelines.yml'

	# Node / package managers
	'--exclude=node_modules/'
	'--exclude=.npm/'
	'--exclude=.yarn/'
	'--exclude=yarn.lock'
	'--exclude=pnpm-lock.yaml'
	'--exclude=.npmrc'
	'--exclude=.yarnrc'
	'--exclude=.yarnrc.yml'
	'--exclude=.pnp.*'
	'--exclude=package.json'
	'--exclude=package-lock.json'

	# Grunt, Gulp, task caches
	'--exclude=Gruntfile.js'
	'--exclude=Gruntfile.coffee'
	'--exclude=gruntfile.js'
	'--exclude=gulpfile.js'
	'--exclude=Gulpfile.js'
	'--exclude=.grunt/'

	# Sass sources (built CSS stays in assets/css/)
	'--exclude=assets/sass/'

	# Bundler / other tooling (if added later)
	'--exclude=vite.config.js'
	'--exclude=vite.config.ts'
	'--exclude=webpack.config.js'
	'--exclude=webpack.config.*'
	'--exclude=rollup.config.js'
	'--exclude=rollup.config.*'

	# Lint / format (not for production bundles)
	'--exclude=.eslintrc'
	'--exclude=.eslintrc.*'
	'--exclude=.eslintignore'
	'--exclude=.prettierrc'
	'--exclude=.prettierrc.*'
	'--exclude=.prettierignore'
	'--exclude=.stylelintrc'
	'--exclude=.stylelintrc.*'
	'--exclude=.stylelintignore'
	'--exclude=.browserslistrc'
	'--exclude=.nvmrc'
	'--exclude=.node-version'

	# Composer / PHP dev (safe even when vendor absent)
	'--exclude=composer.json'
	'--exclude=composer.lock'
	'--exclude=vendor/'
	'--exclude=phpcs.xml'
	'--exclude=phpcs.xml.dist'
	'--exclude=.phpcs.xml'
	'--exclude=.phpcs.xml.dist'
	'--exclude=phpunit.xml'
	'--exclude=phpunit.xml.dist'
	'--exclude=tests/'

	# Legacy front-end deps
	'--exclude=bower_components/'
	'--exclude=bower.json'

	# Artefacts / bundles / IDE
	'--exclude=releases/'
	'--exclude=dist/'
	'--exclude=scripts/'
	'--exclude=release.sh'
	'--exclude=.idea/'
	'--exclude=.vscode/'
	'--exclude=.sass-cache/'
	'--exclude=.DS_Store'
	'--exclude=Thumbs.db'
	'--exclude=*.zip'
	'--exclude=*.css.map'
	'--exclude=*.log'
	'--exclude=npm-debug.log*'
	'--exclude=yarn-debug.log*'
	'--exclude=yarn-error.log*'
	'--exclude=.env'
	'--exclude=.env.*'
	'--exclude=.editorconfig'
	'--exclude=.gitattributes'

	# Docker / Makefile (dev)
	'--exclude=Dockerfile'
	'--exclude=docker-compose.yml'
	'--exclude=docker-compose.*.yml'
	'--exclude=.dockerignore'
	'--exclude=Makefile'
)

rsync -a "${RSYNC_EXCLUDES[@]}" "${PLUGIN_DIR}/" "${DEST}/"

# WordPress “Upload Plugin” expects exactly one root folder in the zip: ${PLUGIN_SLUG}/...
rm -f "${ZIP_PATH}"
(
	cd "${STAGE}" || exit 1
	if [[ ! -d "${PLUGIN_SLUG}" ]]; then
		echo "ERROR: Stage directory ${PLUGIN_SLUG} does not exist." >&2
		exit 1
	fi
	# Argument must be a directory (with trailing /) so entries are always ${PLUGIN_SLUG}/...
	zip -r -q "${ZIP_PATH}" "${PLUGIN_SLUG}/"
)

# Verify: every archive entry sits under ${PLUGIN_SLUG}/
if command -v unzip >/dev/null 2>&1; then
	ENTRIES="$(unzip -Z1 "${ZIP_PATH}" 2>/dev/null || true)"
	if [[ -z "${ENTRIES}" ]]; then
		echo "ERROR: Zip is empty or unreadable: ${ZIP_PATH}" >&2
		exit 1
	fi
	BAD_LINES="$(printf '%s\n' "${ENTRIES}" | grep -Ev "^${PLUGIN_SLUG}(/|$)" | sed '/^$/d' || true)"
	if [[ -n "${BAD_LINES}" ]]; then
		echo "ERROR: Invalid zip structure — paths outside ${PLUGIN_SLUG}/:" >&2
		printf '%s\n' "${BAD_LINES}" >&2
		exit 1
	fi
else
	echo "WARNING: unzip not found — skipping zip layout check." >&2
fi

echo "→ Archive size: $(du -h "${ZIP_PATH}" | cut -f1)"

TITLE="Customify Starter Sites ${VERSION}"
NOTES_FILE="${STAGE}/release-notes.md"
cat >"${NOTES_FILE}" <<EOF
Plugin **${PLUGIN_SLUG}** version **${VERSION}**.

In WordPress go to **Plugins → Add New → Upload Plugin** and choose \`${ZIP_NAME}\`.
EOF

if gh release view "${TAG}" >/dev/null 2>&1; then
	echo "→ Release ${TAG} already exists — uploading / overwriting the zip asset."
	run gh release upload "${TAG}" "${ZIP_PATH}" --clobber
	exit 0
fi

run gh release create "${TAG}" "${ZIP_PATH}" \
	--title "${TITLE}" \
	--notes-file "${NOTES_FILE}"

echo "→ Done."
RELEASE_URL="$(gh release view "${TAG}" --json url -q '.url' 2>/dev/null || true)"
if [[ -n "${RELEASE_URL}" ]]; then
	echo "→ ${RELEASE_URL}"
fi
