# Import worker regression tests

Use a local WordPress Studio site with this plugin active and no import in progress:

```sh
studio wp --path /path/to/site eval 'require WP_PLUGIN_DIR . "/customify-starter-sites/tests/import-worker-regression.php";'
```

The test uses fixture-only jobs and an offline client, restores the prior latest-job pointer, and checks:

- completed/failed/cancelled callbacks cannot restart an import;
- duplicate callbacks cannot enter a second worker;
- one site-wide atomic claim serializes competing jobs;
- failure releases the claim and another job cannot release its owner's claim;
- the REST create handler rejects queued/running imports instead of cancelling a worker that may still be inserting content.

The worker also releases its claim on PHP shutdown. A process killed without PHP shutdown can leave a claim behind; it deliberately fails closed rather than guessing that an old worker has stopped. Recover such a claim only after confirming that the owning worker process is no longer running. Normal cancellation waits for the current phase to stop before releasing the claim.

## LILT local acceptance

Verified on `demo-import.wp.local` through Studio using public catalog template 67316. The real asset contains 11 pages and 3 posts with unique source IDs. Two complete imports produced 11 imported pages and 3 imported posts each, no duplicate `_ft_source_ref` values, and no warnings. Existing unrelated posts/pages were retained.

The pre-fix race was reproduced by interleaving two content imports immediately before inserting LILT's About page: two destination IDs acquired the same `post:122` source reference. The worker/controller fix prevents that overlap through the supported import entry point. A second create request during the fixed full import returned `custstsi_import_busy`. Replaying the completed real job left the post-table fingerprint unchanged.

Matching titles from unrelated starter templates are not automatically merged or deleted. Idempotency is based on importer source references, not titles.

## Additional CSS regression

Run `tests/custom-css-regression.php` through the same Studio command above. The test saves and restores the active theme's Additional CSS. It reproduces HTML KSES encoding child combinators for an anonymous worker, verifies exact CSS preservation on repeat imports (including backslashes and literal entities), rejects markup and incomplete tags, and checks hook cleanup without disabling HTML KSES.

## Customify icon library regression

Run `tests/customify-icons-regression.php` through Studio with Customify active. It restores the original icon option after checking explicit v4/v6/v456 settings, old array and URL-encoded icon descriptors, absent data, invalid values, and other source themes. Older bundles with v6 icons select v456 to retain legacy v4 icons. New PM Submitter bundles carry `theme.customify.font_awesome_version`.

## Worker process permissions

Run `tests/import-context-regression.php` through Studio. It verifies anonymous worker capabilities, actual SVG/HTML/block-CSS persistence, dynamically registered CPT capabilities, and cleanup of permissions, KSES filters and the worker claim after an exception. No user role, identity or capability record is changed.

The REST nonce and manage_options gate still authorize job creation. Both cron and synchronous execution enter the same process-local import context after claiming the job. It supplies content, media, theme settings and plugin installation capabilities; it does not grant user administration or network administration. The context is bound to the current site and closes in finally. Host file permissions, file-modification policy, missing plugins and malformed exports can still fail and must be reported separately.
