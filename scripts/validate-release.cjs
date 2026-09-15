const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');

function validate(root, event = '', tag = '') {
  const pkg = JSON.parse(fs.readFileSync(path.join(root, 'package.json'), 'utf8'));
  const plugin = fs.readFileSync(path.join(root, 'customify-starter-sites.php'), 'utf8');
  const readme = fs.readFileSync(path.join(root, 'readme.txt'), 'utf8');
  const versions = [
    pkg.version,
    plugin.match(/^Version:\s*(\S+)/m)?.[1],
    plugin.match(/define\(\s*'CUSTOMIFY_STARTER_SITES_VERSION',\s*'([^']+)'/)?.[1],
    readme.match(/^Stable tag:\s*(\S+)/m)?.[1],
  ];
  assert(versions.every(Boolean), 'Missing release version metadata');
  assert.equal(new Set(versions).size, 1, 'Release version metadata does not match');
  assert(/^\d+\.\d+\.\d+$/.test(pkg.version), 'Version must be X.Y.Z');
  assert(readme.includes(`= ${pkg.version} =`), 'Missing version changelog');
  if (event === 'release') {
    assert(/^v?\d+\.\d+\.\d+$/.test(tag), 'Release tag must be X.Y.Z or vX.Y.Z');
    assert.equal(tag.replace(/^v/, ''), pkg.version, 'Release tag does not match plugin version');
  }
  return pkg.version;
}

function validateAssets(root) {
  for (const [name, width, height, limit] of [
    ['banner-1544x500.png', 1544, 500, 4000000],
    ['banner-772x250.png', 772, 250, 4000000],
    ['icon-256x256.png', 256, 256, 1000000],
    ['icon-128x128.png', 128, 128, 1000000],
  ]) {
    const image = fs.readFileSync(path.join(root, '.wordpress-org', name));
    assert(image.subarray(0, 8).equals(Buffer.from([137, 80, 78, 71, 13, 10, 26, 10])), `${name}: invalid PNG`);
    assert.equal(image.toString('ascii', 12, 16), 'IHDR', `${name}: missing PNG header`);
    assert.equal(image.readUInt32BE(16), width, `${name}: wrong width`);
    assert.equal(image.readUInt32BE(20), height, `${name}: wrong height`);
    assert(image.length < limit, `${name}: exceeds WordPress.org size limit`);
  }
}

if (require.main === module) {
  const root = path.resolve(__dirname, '..');
  const version = validate(root, process.env.EVENT_NAME, process.env.RELEASE_TAG);
  validateAssets(root);
  if (process.env.GITHUB_OUTPUT) fs.appendFileSync(process.env.GITHUB_OUTPUT, `version=${version}\n`);
  console.log(`Validated version ${version} and four WordPress.org images.`);
}
module.exports = { validate, validateAssets };
