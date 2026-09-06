import test from 'node:test';
import assert from 'node:assert/strict';
import { describeThreshold } from '../js/sensorThreshold.mjs';

test('below thresholds show the required decrease and green headroom', () => {
  const outside = describeThreshold(83, 80, 'below', '%');
  assert.equal(outside.distance, '↓ 3 pp to green');
  assert.equal(outside.green, false);
  assert.ok(outside.position < 50);
  assert.equal(describeThreshold(75, 80, 'below', '%').distance, '5 pp inside green');
});
test('above, negative and zero thresholds retain the right direction', () => {
  assert.equal(describeThreshold(-23, -20, 'above', '°C').distance, '↑ 3 °C to green');
  assert.equal(describeThreshold(-23, -20, 'below', '°C').green, true);
  assert.equal(describeThreshold(.4, 0, 'below', 'mm').distance, '↓ 0.4 mm to green');
  const boundary = describeThreshold(0, 0, 'below', 'mm');
  assert.equal(boundary.green, true);
  assert.equal(boundary.position, 50);
  assert.equal(boundary.distance, 'At green boundary');
});
test('invalid readings and absent thresholds never produce a marker', () => {
  for (const value of [undefined, '', 'offline', Infinity]) assert.equal(describeThreshold(value, 10).available, false);
  for (const threshold of [undefined, '', 'invalid', Infinity]) assert.equal(describeThreshold(12, threshold).available, false);
});
test('small nonzero gaps are not mislabeled as the boundary; marker caps do not cap the distance', () => {
  assert.equal(describeThreshold(10.01, 10).distance, '↓ <0.1 to green');
  const outside = describeThreshold(100, 10);
  assert.equal(outside.position, 0);
  assert.equal(outside.distance, '↓ 90 to green');
  assert.equal(describeThreshold(-100, 10).position, 100);
});
