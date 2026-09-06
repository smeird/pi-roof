#!/usr/bin/env python3
"""Run interactively on data; the secret is stored outside the web checkout."""
import getpass
import json
import os
from pathlib import Path
import subprocess

directory = Path.home() / '.config/roof-camera'
directory.mkdir(parents=True, mode=0o700, exist_ok=True)
directory.chmod(0o700)
username = input('TC70 local Camera Account username: ').strip()
password = getpass.getpass('TC70 local Camera Account password: ')
if not username or not password:
    raise SystemExit('Username and password are required; nothing saved.')
os.umask(0o077)
path = directory / 'account.json'
temporary = directory / 'account.json.new'
temporary.write_text(json.dumps({'username': username, 'password': password}))
temporary.chmod(0o600)
temporary.replace(path)
subprocess.run(['systemctl', '--user', 'restart', 'roof-camera.service'], check=True)
print('Saved privately and restarted camera bridge.')
