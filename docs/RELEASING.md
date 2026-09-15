# Releasing Starter Templates

This repository follows Blocksify's 10up release pattern. Publishing a stable GitHub Release triggers `.github/workflows/deploy.yml`. A push, merge or draft release does not publish to WordPress.org. Prereleases are skipped.

## One-time setup

In the repository's Actions secrets, configure `SVN_USERNAME` and `SVN_PASSWORD` for a WordPress.org account with commit access to `customify-starter-sites`. Secrets in the Blocksify repository are not inherited and cannot be retrieved from GitHub to copy here. The workflow uses GitHub-hosted Ubuntu runners, so no self-hosted runner is required.

## Release

1. Update `package.json`, the plugin header Version, `CUSTOMIFY_STARTER_SITES_VERSION`, and the `readme.txt` Stable tag to the same new X.Y.Z version. Add that version's changelog entry. Do not reuse a version already in WordPress.org SVN.
2. Merge the release commit into `master` and wait for CI.
3. Create and publish a stable GitHub Release using either `vX.Y.Z` (compatible with `release.sh`) or `X.Y.Z`, pointing to that commit. The release tag must match all version metadata and the commit must be reachable from `master`.
4. Check the Deploy to WordPress.org run. It builds with the frozen pnpm lockfile, runs 10up in dry-run mode, validates the resulting ZIP, and passes that exact runtime package and listing images to a separate deploy job. Only that job receives SVN secrets and can commit.

The deploy syncs runtime and readme to SVN trunk and the new version tag, and the four banners/icons to SVN assets. CDN caching may delay visible image updates. `.wordpress-org` holds the canonical artwork; its documentation, logo source and provenance JSON are not uploaded. If screenshots are added to the listing later, add them to the workflow's asset staging step too, because 10up treats the staged assets directory as the complete listing asset set.

The production ZIP is attached to the GitHub Release as `customify-starter-sites.zip`. WordPress.org artwork, local previews, source/build tooling and development files are excluded from the installable package using `.distignore`.

`release.sh` still creates a GitHub Release through the caller's GitHub CLI credentials, which triggers this workflow. It is not an alternative SVN deployment path. Do not publish a test release to test the workflow.

## Safe dry run

Run **Deploy to WordPress.org → Run workflow** from the Actions tab. Manual execution builds and exercises 10up's real SVN copy/diff/ZIP path but never commits to SVN or attaches files to a release. It needs no SVN secrets. The pinned 10up action checks that its credential environment variables are nonempty even in dry-run mode; harmless placeholder values satisfy that check for anonymous public SVN reads.

Preflight and deployment run on separate ephemeral runners, so their SVN workspaces cannot collide and no HOME overrides are needed. The dry-run uses a unique unpublished SVN version to prevent 10up's existing-tag short circuit. The publish job rejects existing SVN versions explicitly.

For a local package check, run `pnpm install --frozen-lockfile`, `pnpm run build`, `node --test tests/release.test.cjs`, then `bash scripts/build-release-zip.sh`.

References: [10up plugin deploy action](https://github.com/10up/action-wordpress-plugin-deploy), [WordPress.org assets](https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/).
