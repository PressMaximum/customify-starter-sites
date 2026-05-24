#!/usr/bin/env bash
#
# Đóng gói Customify Starter Sites thành .zip và tạo / cập nhật GitHub Release (GitHub CLI: gh).
# Đặt ở thư mục gốc plugin cùng cấp với customify-starter-sites.php.
#
# Cách chạy:
#   cd wp-content/plugins/customify-starter-sites
#   ./release.sh [--dry-run]
#
# Yêu cầu:
#   - gh đã đăng nhập: gh auth login
#   - Thư mục plugin là clone git trùng GitHub đích (hoặc GH_REPO=owner/repo)
#
# Zip output:
#   - releases/customify-starter-sites-<Version>.zip trong thư mục plugin
#
# Version (tag & tên file):
#   - Ưu tiên package.json → "version"; fallback header PHP customify-starter-sites.php
#

set -euo pipefail

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

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_SLUG="customify-starter-sites"

MAIN_FILE="${PLUGIN_DIR}/${PLUGIN_SLUG}.php"
PACKAGE_JSON="${PLUGIN_DIR}/package.json"
RELEASES_DIR="${PLUGIN_DIR}/releases"

cd "${PLUGIN_DIR}"

if ! command -v gh >/dev/null 2>&1; then
	echo "ERROR: Thiếu gh (GitHub CLI)." >&2
	exit 1
fi
if ! command -v zip >/dev/null 2>&1; then
	echo "ERROR: Thiếu zip." >&2
	exit 1
fi
if ! command -v rsync >/dev/null 2>&1; then
	echo "ERROR: Thiếu rsync." >&2
	exit 1
fi

if [[ ! -f "${MAIN_FILE}" ]]; then
	echo "ERROR: Không tìm thấy ${MAIN_FILE}" >&2
	exit 1
fi

VERSION=""
VERSION_SOURCE="package.json"

if [[ -f "${PACKAGE_JSON}" ]] && command -v node >/dev/null 2>&1; then
	VERSION="$(node -p "require('./package.json').version" 2>/dev/null | tr -d $'\t\r\n' || true)"
fi

if [[ -z "${VERSION}" ]]; then
	VERSION="$(grep -m1 -E '^[[:space:]]*Version:' "${MAIN_FILE}" | sed -e 's/^[[:space:]]*Version:[[:space:]]*//' -e 's/[[:space:]]*$//')"
	VERSION_SOURCE="customify-starter-sites.php (header)"
fi

if [[ -z "${VERSION}" ]]; then
	echo "ERROR: Không có version — kiểm tra package.json hoặc ${MAIN_FILE}" >&2
	exit 1
fi

PHP_HDR_VER="$(grep -m1 -E '^[[:space:]]*Version:' "${MAIN_FILE}" | sed -e 's/^[[:space:]]*Version:[[:space:]]*//' -e 's/[[:space:]]*$//')"
if [[ "${VERSION_SOURCE}" == "package.json" && -n "${PHP_HDR_VER}" && "${VERSION}" != "${PHP_HDR_VER}" ]]; then
	echo "WARNING: package.json (${VERSION}) khác Plugin header (${PHP_HDR_VER}). Tag/release dùng theo package.json." >&2
fi

TAG="v${VERSION}"
ZIP_NAME="${PLUGIN_SLUG}-${VERSION}.zip"
ZIP_PATH="${RELEASES_DIR}/${ZIP_NAME}"

echo "→ Plugin dir: ${PLUGIN_DIR}"
echo "→ Version:    ${VERSION}  (${VERSION_SOURCE}) — tag ${TAG}"
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
		echo "ERROR: gh chưa đăng nhập. Chạy: gh auth login" >&2
		exit 1
	}
fi

mkdir -p "${RELEASES_DIR}"

STAGE="$(mktemp -d)"
cleanup() {
	rm -rf "${STAGE}"
}
trap cleanup EXIT

DEST="${STAGE}/${PLUGIN_SLUG}"
mkdir -p "${DEST}"

# Chỉ đóng gói file runtime cho WordPress — loại công cụ build, dependencies, nguồn dev.
RSYNC_EXCLUDES=(
	# Repo / CI
	'--exclude=.git/'
	'--exclude=.github/'
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

	# Grunt, Gulp, cache task
	'--exclude=Gruntfile.js'
	'--exclude=Gruntfile.coffee'
	'--exclude=gruntfile.js'
	'--exclude=gulpfile.js'
	'--exclude=Gulpfile.js'
	'--exclude=.grunt/'

	# Sass nguồn (đã có CSS trong assets/css/)
	'--exclude=assets/sass/'

	# Bundler / tooling khác (nếu thêm sau này)
	'--exclude=vite.config.js'
	'--exclude=vite.config.ts'
	'--exclude=webpack.config.js'
	'--exclude=webpack.config.*'
	'--exclude=rollup.config.js'
	'--exclude=rollup.config.*'

	# Lint / format (không cần production)
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

	# Composer / PHP dev (vendor thường không có trong plugin này; vẫn exclude an toàn)
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

	# Artefact / bundle / IDE
	'--exclude=releases/'
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

(cd "${STAGE}" && zip -r -q "${ZIP_PATH}" "${PLUGIN_SLUG}")

echo "→ Kích thước archive: $(du -h "${ZIP_PATH}" | cut -f1)"

TITLE="Customify Starter Sites ${VERSION}"
NOTES_FILE="${STAGE}/release-notes.md"
cat >"${NOTES_FILE}" <<EOF
Plugin **${PLUGIN_SLUG}** phiên bản **${VERSION}**.

Upload trong WP: **Plugins → Add New → Upload Plugin** và chọn \`${ZIP_NAME}\`.
EOF

if gh release view "${TAG}" >/dev/null 2>&1; then
	echo "→ Release ${TAG} đã tồn tại — upload / ghi đè file zip."
	run gh release upload "${TAG}" "${ZIP_PATH}" --clobber
	exit 0
fi

run gh release create "${TAG}" "${ZIP_PATH}" \
	--title "${TITLE}" \
	--notes-file "${NOTES_FILE}"

echo "→ Xong."
RELEASE_URL="$(gh release view "${TAG}" --json url -q '.url' 2>/dev/null || true)"
if [[ -n "${RELEASE_URL}" ]]; then
	echo "→ ${RELEASE_URL}"
fi
