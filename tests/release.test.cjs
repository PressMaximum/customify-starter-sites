const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { validate } = require('../scripts/validate-release.cjs');

test('release gates reject mismatched, malformed and prerelease tags', () => {
  const root = fs.mkdtempSync(path.join(os.tmpdir(), 'starter-release-test-'));
  try {
    fs.writeFileSync(path.join(root, 'package.json'), '{"version":"1.2.3"}');
    fs.writeFileSync(path.join(root, 'customify-starter-sites.php'), "Version: 1.2.3\ndefine( 'CUSTOMIFY_STARTER_SITES_VERSION', '1.2.3' );\n");
    fs.writeFileSync(path.join(root, 'readme.txt'), 'Stable tag: 1.2.3\n= 1.2.3 =\n');
    assert.equal(validate(root, 'release', 'v1.2.3'), '1.2.3');
    assert.equal(validate(root, 'release', '1.2.3'), '1.2.3');
    assert.equal(validate(root, 'workflow_dispatch'), '1.2.3');
    for (const tag of ['v1.2.4', 'main', 'v1.2.3-rc.1', 'v1.2.3\ninjected']) assert.throws(() => validate(root, 'release', tag));
    fs.writeFileSync(path.join(root, 'readme.txt'), 'Stable tag: 1.2.4\n= 1.2.4 =\n');
    assert.throws(() => validate(root));
    fs.writeFileSync(path.join(root, 'readme.txt'), 'Stable tag: 1.2.3\n');
    assert.throws(() => validate(root));
  } finally { fs.rmSync(root, { recursive: true, force: true }); }
});
