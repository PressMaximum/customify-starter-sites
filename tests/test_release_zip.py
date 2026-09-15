import importlib.util
import tempfile
import unittest
import zipfile
from pathlib import Path

spec = importlib.util.spec_from_file_location('release_zip', Path(__file__).parents[1] / 'scripts/validate-release-zip.py')
release_zip = importlib.util.module_from_spec(spec)
spec.loader.exec_module(release_zip)


class ReleaseArchiveTest(unittest.TestCase):
    def make_archive(self, path, extra=None, missing=None, version='1.2.3'):
        files = {name: 'runtime fixture' for name in release_zip.REQUIRED}
        files['customify-starter-sites.php'] = f"Version: {version}\ndefine( 'CUSTOMIFY_STARTER_SITES_VERSION', '{version}' );\n"
        files['readme.txt'] = f'Stable tag: {version}\n'
        files['inc/generic/build/admin.asset.php'] = "<?php if (!defined('ABSPATH')) exit; return ['dependencies' => [], 'version' => 'hash'];"
        if missing:
            files.pop(missing)
        with zipfile.ZipFile(path, 'w') as archive:
            for name, body in files.items():
                archive.writestr(f'customify-starter-sites/{name}', body)
            if extra:
                archive.writestr(extra, 'unexpected')

    def test_valid_runtime_archive(self):
        with tempfile.TemporaryDirectory() as tmp:
            archive = Path(tmp) / 'plugin.zip'
            self.make_archive(archive)
            self.assertEqual(release_zip.validate(archive, '1.2.3'), len(release_zip.REQUIRED))

    def test_reject_development_files_and_unsafe_paths(self):
        with tempfile.TemporaryDirectory() as tmp:
            archive = Path(tmp) / 'plugin.zip'
            for extra in ['customify-starter-sites/.env', 'customify-starter-sites/review/index.html', 'customify-starter-sites/.wordpress-org/icon.png', 'customify-starter-sites/inc/.env', '../escaped.php', '/absolute.php', 'wrong-root/readme.txt']:
                with self.subTest(extra=extra):
                    self.make_archive(archive, extra=extra)
                    with self.assertRaises(AssertionError):
                        release_zip.validate(archive, '1.2.3')

    def test_reject_missing_build_and_wrong_version(self):
        with tempfile.TemporaryDirectory() as tmp:
            archive = Path(tmp) / 'plugin.zip'
            self.make_archive(archive, missing='inc/generic/build/admin.js')
            with self.assertRaises(AssertionError):
                release_zip.validate(archive, '1.2.3')
            self.make_archive(archive, version='1.2.2')
            with self.assertRaises(AssertionError):
                release_zip.validate(archive, '1.2.3')
