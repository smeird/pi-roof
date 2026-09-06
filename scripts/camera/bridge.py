#!/usr/bin/env python3
"""One shared RTSP connection feeds a rolling HLS buffer for dashboard viewers."""
import json
import os
from pathlib import Path
import stat
import subprocess
import sys
from urllib.parse import quote

config_path = Path.home() / '.config/roof-camera/account.json'
try:
    if stat.S_IMODE(config_path.stat().st_mode) & 0o077:
        raise ValueError('Camera account file must have mode 600')
    config = json.loads(config_path.read_text())
    username, password = config['username'], config['password']
    if not username or not password:
        raise ValueError('Camera account is empty')
except (OSError, ValueError, KeyError):
    sys.exit('Camera account unavailable. Run scripts/camera/configure.py on data.')

cache = Path('/var/tmp/roof-camera')
cache.mkdir(mode=0o755, exist_ok=True)
if cache.is_symlink() or cache.stat().st_uid != os.getuid():
    sys.exit('Camera cache must be owned by the service user and not a symlink')
os.umask(0o022)
url = f'rtsp://{quote(username, safe="")}:{quote(password, safe="")}@10.0.179.35:554/stream2'
args = [
    '/usr/bin/ffmpeg', '-nostdin', '-hide_banner', '-loglevel', 'fatal',
    '-rtsp_transport', 'tcp', '-timeout', '10000000', '-i', url,
    '-map', '0:v:0', '-an', '-c:v', 'copy', '-f', 'hls', '-hls_time', '2',
    '-hls_list_size', '6', '-hls_delete_threshold', '2', '-hls_start_number_source', 'epoch',
    '-hls_flags', 'delete_segments+temp_file+omit_endlist',
    '-hls_segment_filename', str(cache / 'segment%d.ts'), str(cache / 'stream.m3u8')
]
# On restart, clear only generated files in this validated, dedicated directory.
for file in cache.iterdir():
    if file.name == 'stream.m3u8' or (file.name.startswith('segment') and file.suffix in ('.ts', '.tmp')):
        if file.is_file() and not file.is_symlink():
            file.unlink()
# ffmpeg diagnostics may contain the authenticated URL: never send them to logs.
with open(os.devnull, 'wb') as quiet:
    result = subprocess.run(args, stdout=quiet, stderr=quiet)
sys.exit(result.returncode)
