#!/usr/bin/env python3
import hashlib, io, json, pathlib, subprocess, tarfile, tempfile, unittest

TOOL = str(pathlib.Path(__file__).with_name('backup_archive.py'))
class ArchiveTest(unittest.TestCase):
    def run_archive(self, extra=None, corrupt=False):
        with tempfile.TemporaryDirectory() as work:
            archive = pathlib.Path(work) / 'test.tar.gz'
            destination = pathlib.Path(work) / 'out'; destination.mkdir()
            sql = b'-- synthetic database'
            manifest = {'version': 1, 'database_sha256': hashlib.sha256(sql).hexdigest(), 'files': {}}
            if corrupt: manifest['database_sha256'] = '0' * 64
            entries = [('database.sql', sql), ('manifest.json', json.dumps(manifest).encode())]
            with tarfile.open(archive, 'w:gz') as tar:
                for name, content in entries:
                    info = tarfile.TarInfo(name); info.size = len(content); tar.addfile(info, io.BytesIO(content))
                if extra:
                    name, symlink = extra
                    info = tarfile.TarInfo(name)
                    if symlink: info.type = tarfile.SYMTYPE; info.linkname = '/tmp/escape'
                    tar.addfile(info, io.BytesIO(b''))
            result = subprocess.run(['python3', TOOL, str(archive), str(destination)], capture_output=True)
            return result.returncode
    def test_valid_archive(self): self.assertEqual(self.run_archive(), 0)
    def test_path_traversal_absolute_paths_links_and_unexpected_files(self):
        for extra in [('../escape', False), ('/tmp/escape', False), ('public/images/link', True), ('.env', False)]:
            self.assertNotEqual(self.run_archive(extra), 0)
    def test_database_checksum_mismatch(self): self.assertNotEqual(self.run_archive(corrupt=True), 0)

if __name__ == '__main__': unittest.main()
