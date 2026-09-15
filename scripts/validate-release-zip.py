"""Validate the actual archive before a WordPress.org deploy."""
import re
import sys
import zipfile
from pathlib import PurePosixPath

SLUG = 'customify-starter-sites'
REQUIRED = {
    'customify-starter-sites.php', 'readme.txt', 'uninstall.php', 'LICENSE',
    'style.css', 'inc/generic/bootstrap.php', 'inc/generic/build/admin.js',
    'inc/generic/build/admin.css', 'inc/generic/build/admin-rtl.css',
    'inc/generic/build/admin.asset.php',
}
ROOT_FILES = {'customify-starter-sites.php', 'readme.txt', 'uninstall.php', 'LICENSE', 'style.css'}


def validate(filename, version):
    with zipfile.ZipFile(filename) as archive:
        files = set()
        for item in archive.infolist():
            name = item.filename
            parts = PurePosixPath(name).parts
            assert not name.startswith('/') and '..' not in parts and '\\' not in name, f'Unsafe archive path: {name}'
            assert parts and parts[0] == SLUG, f'Incorrect ZIP root: {name}'
            assert (item.external_attr >> 16) & 0o170000 != 0o120000, f'Symlink in archive: {name}'
            if item.is_dir():
                continue
            relative = '/'.join(parts[1:])
            assert relative not in files, f'Duplicate archive entry: {name}'
            files.add(relative)
            assert relative in ROOT_FILES or (len(parts) > 2 and parts[1] in {'inc', 'languages'}), f'Non-runtime file in archive: {name}'
            assert not any(part.startswith('.') or part in {'node_modules', 'tests', 'src'} for part in parts[1:]), f'Hidden/dev file: {name}'
            assert not name.endswith(('.map', '.zip', '.log')), f'Unexpected build artifact: {name}'
            assert item.file_size > 0, f'Empty runtime file: {name}'
        assert REQUIRED <= files, f'Missing runtime files: {REQUIRED - files}'
        plugin = archive.read(f'{SLUG}/customify-starter-sites.php').decode()
        readme = archive.read(f'{SLUG}/readme.txt').decode()
        assert re.search(r'^Version:\s*(\S+)', plugin, re.M)[1] == version, 'ZIP plugin version mismatch'
        assert re.search(r'^Stable tag:\s*(\S+)', readme, re.M)[1] == version, 'ZIP stable tag mismatch'
        assert f"'CUSTOMIFY_STARTER_SITES_VERSION', '{version}'" in plugin, 'ZIP constant version mismatch'
        asset = archive.read(f'{SLUG}/inc/generic/build/admin.asset.php').decode()
        assert 'ABSPATH' in asset and 'dependencies' in asset and 'version' in asset, 'Missing protected asset manifest'
    return len(files)


if __name__ == '__main__':
    count = validate(sys.argv[1], sys.argv[2])
    print(f'Validated {count} runtime files in {sys.argv[1]} (version {sys.argv[2]}).')
