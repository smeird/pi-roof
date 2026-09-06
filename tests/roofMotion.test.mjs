import test from 'node:test';
import assert from 'node:assert/strict';
import { RoofMotion } from '../js/roofMotion.mjs';

const closed = { available: true, openLimit: '0', closeLimit: '1', openMotor: '0', closeMotor: '0', moving: '0', fault: '0' };
const opening = { ...closed, closeLimit: '0', openMotor: '1', moving: '1' };
const closing = { ...opening, openMotor: '0', closeMotor: '1' };
const started = options => { const model = new RoofMotion(options); model.update(closed, 0); model.update(opening, 0); return model; };

test('unknown position is never represented as confirmed closed', () => {
  const model = new RoofMotion();
  assert.equal(model.update({ available: true }, 0).position, null);
  assert.match(model.update(opening, 0).label, /position unknown/);
  assert.equal(model.tick(30000).position, null);
});
test('configured opening time, repeated telemetry and elapsed time', () => {
  const model = started({ openSeconds: 20 });
  assert.equal(model.tick(5000).position, .25);
  model.update(opening, 10000);
  assert.equal(model.tick(15000).position, .75);
  const end = model.tick(30000);
  assert.equal(end.position, 1);
  assert.match(end.label, /awaiting limit/);
  assert.equal(end.openLimit, '0');
});
test('limit confirmation overrides the timer, lights retain real inputs', () => {
  const model = started();
  const end = model.update({ ...opening, openLimit: '1' }, 2000);
  assert.equal(end.position, 1);
  assert.equal(end.direction, 0);
  assert.match(end.label, /limit confirmed/);
});
test('departing switch does not reset the estimate on repeated messages', () => {
  const model = new RoofMotion({ openSeconds: 20 });
  model.update(closed, 0);
  model.update({ ...opening, closeLimit: '1' }, 0);
  model.update({ ...opening, closeLimit: '1' }, 2000);
  assert.equal(model.tick(4000).position, .2);
});
test('stop freezes and reverse uses closing duration for remaining distance', () => {
  const model = started({ openSeconds: 20, closeSeconds: 10 });
  model.update({ ...opening, openMotor: '0', moving: '0' }, 10000);
  assert.equal(model.tick(20000).position, .5);
  model.update(closing, 20000);
  assert.equal(model.tick(22500).position, .25);
  assert.equal(model.tick(25000).position, 0);
  assert.match(model.snapshot().label, /awaiting limit/);
  assert.match(model.update(closed, 26000).label, /Closed · limit confirmed/);
});
for (const [name, signals] of [
  ['disconnect or stale telemetry', { ...opening, available: false }],
  ['fault', { ...opening, fault: '1' }],
  ['conflicting limits', { ...opening, closeLimit: '1', openLimit: '1' }],
  ['conflicting motors', { ...opening, closeMotor: '1' }],
  ['unknown motor direction', { ...opening, openMotor: '0' }]
]) test(`${name} pauses the estimate`, () => {
  const model = started({ openSeconds: 20 });
  model.update(signals, 10000);
  assert.equal(model.tick(20000).position, .5);
  assert.equal(model.snapshot().direction, 0);
});
test('invalid durations fall back safely', () => {
  const model = started({ openSeconds: 0, closeSeconds: Infinity });
  assert.equal(model.openSeconds, 30);
  assert.equal(model.closeSeconds, 30);
});
