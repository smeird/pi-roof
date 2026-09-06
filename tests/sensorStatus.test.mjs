import test from 'node:test';
import assert from 'node:assert/strict';
import { classifySensor, observingStatus } from '../js/sensorStatus.mjs';
import { describeThreshold } from '../js/sensorThreshold.mjs';

test('green and amber boundaries are inclusive, in both directions including zero and negatives', () => {
  for (const [green, amber, direction, readings] of [
    [80, 90, 'below', [[79, 'green'], [80, 'green'], [80.01, 'amber'], [90, 'amber'], [90.01, 'red']]],
    [0, -10, 'above', [[1, 'green'], [0, 'green'], [-.01, 'amber'], [-10, 'amber'], [-10.01, 'red']]],
    [-20, -10, 'below', [[-20, 'green'], [-15, 'amber'], [-10, 'amber'], [-9, 'red']]]
  ]) for (const [value, expected] of readings) {
    assert.equal(classifySensor(value, { green, amber, greenDirection: direction }), expected);
    assert.equal(describeThreshold(value, green, direction, '', amber).status, expected);
  }
});

test('legacy thresholds stay green/red; invalid values or configuration are unknown', () => {
  assert.equal(classifySensor(11, { green: 10 }), 'red');
  assert.equal(classifySensor(10, { green: 10, amber: '' }), 'green');
  for (const value of ['', null, undefined, Infinity, '10oops', true]) assert.equal(classifySensor(value, { green: 10, amber: 20 }), 'unknown');
  for (const sensor of [{}, { green: '' }, { green: 10, amber: 5 }, { green: 10, amber: 10 }, { green: 10, amber: 'bad' }]) assert.equal(classifySensor(0, sensor), 'unknown');
});

test('overall status uses every sensor, prioritizes red, and never reports safe on missing data or disconnect', () => {
  const sensors = Array.from({ length: 12 }, (_, i) => ({ name: `Sensor ${i}`, status: 'green' }));
  assert.equal(observingStatus(sensors, true).status, 'green');
  assert.equal(observingStatus(sensors, false).status, 'unknown');
  assert.equal(observingStatus([], true).status, 'unknown');
  sensors[11].status = 'unknown';
  assert.equal(observingStatus(sensors, true).status, 'unknown');
  sensors[11].status = 'amber';
  assert.equal(observingStatus(sensors, true).status, 'amber');
  assert.match(observingStatus(sensors, true).detail, /Sensor 11: amber/);
  sensors[0].status = 'red';
  assert.equal(observingStatus(sensors, true).status, 'red');
});
