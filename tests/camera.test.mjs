import test from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, writeFileSync, utimesSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { spawnSync } from 'node:child_process';

test('camera endpoint serves only fresh local HLS and rejects arbitrary paths', () => {
  const directory = mkdtempSync(join(tmpdir(), 'roof-camera-test-'));
  const request = query => {
    const result = spawnSync('php-cgi', [], { encoding: 'utf8', env: {
      ...process.env, ROOF_CAMERA_CACHE: directory, REDIRECT_STATUS: '1',
      SCRIPT_FILENAME: resolve('camera.php'), REQUEST_METHOD: 'GET', QUERY_STRING: query
    }});
    assert.equal(result.status, 0, result.stderr);
    return result.stdout;
  };
  try {
    assert.match(request('file=stream.m3u8'), /503/);
    const manifest = join(directory, 'stream.m3u8');
    writeFileSync(manifest, '#EXTM3U\n#EXTINF:2,\nsegment123.ts\n');
    writeFileSync(join(directory, 'segment123.ts'), 'video fixture');
    assert.match(request('file=stream.m3u8'), /camera.php\?file=segment123.ts/);
    assert.match(request('file=segment123.ts'), /video fixture/);
    assert.match(request('file=segment999.ts'), /404/);
    for (const path of ['../config.php', 'https://example.com', '/etc/passwd', 'segment1.ts/extra']) {
      assert.match(request('file=' + encodeURIComponent(path)), /400/);
    }
    const old = new Date(Date.now() - 30000);
    utimesSync(manifest, old, old);
    assert.match(request('file=stream.m3u8'), /503/);
    assert.match(request('file=segment123.ts'), /503/);
  } finally { rmSync(directory, { recursive: true, force: true }); }
});
