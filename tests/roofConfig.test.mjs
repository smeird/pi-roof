// Isolated CGI endpoint tests: no HTTP server, production database, or broker.
import test from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { spawnSync } from 'node:child_process';

test('travel settings round-trip, validate, and preserve legacy configuration', () => {
  const directory = mkdtempSync(join(tmpdir(), 'roof-config-test-'));
  function request(script, body) {
    const input = body ? JSON.stringify(body) : '';
    const result = spawnSync('php-cgi', [], {
      input, encoding: 'utf8',
      env: { ...process.env, ROOF_CONFIG_DB_PATH: join(directory, 'config.db'),
        REDIRECT_STATUS: '1', SCRIPT_FILENAME: resolve(script), REQUEST_METHOD: body ? 'POST' : 'GET',
        CONTENT_TYPE: 'application/json', CONTENT_LENGTH: String(Buffer.byteLength(input)) }
    });
    assert.equal(result.status, 0, result.stderr || result.error?.message);
    const [headers, ...response] = result.stdout.split(/\r?\n\r?\n/);
    return { headers, body: JSON.parse(response.join('\n\n')) };
  }
  try {
    const initial = request('get_config.php').body;
    assert.equal(initial.roofController.openSeconds, 30);
    assert.equal(initial.roofController.closeSeconds, 30);
    const controller = { ...initial.roofController, openSeconds: 42.5, closeSeconds: 37 };
    assert.equal(request('save_config.php', {
      roofController: controller, MQTT_BROKER_URL: 'ws://fixture.invalid', INFLUX_BUCKET: 'preserve-me',
      sensors: [{ path: 'test/temp', name: 'Temperature', unit: 'C', green: 10, greenDirection: 'below' }],
      switches: [{ name: 'Test light', commandPath: 'test/light/set', statusPath: 'test/light/state' }]
    }).body.status, 'ok');
    const saved = request('get_config.php').body;
    assert.deepEqual(saved.roofController, controller);
    const legacy = { ...controller };
    delete legacy.openSeconds;
    delete legacy.closeSeconds;
    assert.equal(request('save_config.php', { roofController: legacy }).body.status, 'ok');
    assert.deepEqual(request('get_config.php').body, saved);
    for (const value of [0, -1, 901, '', 'invalid', null, true, [], {}]) {
      const invalid = request('save_config.php', { roofController: { ...controller, openSeconds: value, closeSeconds: value } });
      assert.match(invalid.headers, /422/);
      assert.ok(invalid.body.fields['roofController.openSeconds']);
      assert.ok(invalid.body.fields['roofController.closeSeconds']);
    }
    assert.deepEqual(request('get_config.php').body, saved, 'invalid saves must not modify configuration');
    assert.equal(request('save_config.php', { roofController: { ...controller, openSeconds: 1, closeSeconds: 900 } }).body.status, 'ok');
    const boundaries = request('get_config.php').body.roofController;
    assert.equal(boundaries.openSeconds, 1);
    assert.equal(boundaries.closeSeconds, 900);
  } finally { rmSync(directory, { recursive: true, force: true }); }
});
