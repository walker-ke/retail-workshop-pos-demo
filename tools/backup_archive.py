#!/usr/bin/env python3
"""Validate and extract only authenticated POS backup content; no links/devices."""
import sys, tarfile, pathlib, shutil, json, hashlib
def checksum(path):
    digest = hashlib.sha256()
    with path.open('rb') as source:
        for chunk in iter(lambda: source.read(1048576), b''):
            digest.update(chunk)
    return digest.hexdigest()

archive, destination = sys.argv[1:3]
roots = ('public/images', 'public/uploads', 'public/public', 'storage/app/public')
with tarfile.open(archive, 'r:gz') as tar:
    members = tar.getmembers()
    names = set()
    for member in members:
        name = member.name.rstrip('/')
        path = pathlib.PurePosixPath(name)
        if name in names or path.is_absolute() or '..' in path.parts or not (member.isfile() or member.isdir()):
            raise ValueError('Unsafe backup archive member')
        if name not in ('database.sql', 'manifest.json') and not any(name == root or name.startswith(root + '/') for root in roots):
            raise ValueError('Unexpected backup archive path')
        names.add(name)
    if not {'database.sql', 'manifest.json'} <= names:
        raise ValueError('Missing database or manifest')
    manifest = json.load(tar.extractfile('manifest.json'))
    if manifest.get('version') != 1:
        raise ValueError('Unsupported manifest')
    for member in members:
        target = pathlib.Path(destination) / member.name
        if member.isdir():
            target.mkdir(parents=True, exist_ok=True, mode=0o700)
        else:
            target.parent.mkdir(parents=True, exist_ok=True, mode=0o700)
            with tar.extractfile(member) as src, target.open('xb') as dst:
                shutil.copyfileobj(src, dst)
            target.chmod(0o600)
    db = pathlib.Path(destination) / 'database.sql'
    if checksum(db) != manifest['database_sha256']:
        raise ValueError('Database checksum mismatch')
    for name, expected in manifest['files'].items():
        file = pathlib.Path(destination) / name
        if name not in names or checksum(file) != expected:
            raise ValueError('Uploaded file checksum mismatch')
    actual = {m.name for m in members if m.isfile()} - {'database.sql', 'manifest.json'}
    if actual != set(manifest['files']):
        raise ValueError('Uploaded file inventory mismatch')
