import test from 'node:test';
import assert from 'node:assert/strict';
import { describeCloseRecovery } from '../js/roofRecovery.mjs';

const baseline = { closePending: '0', closeResult: 'idle', closeRetryCount: '0', closeLimit: '1', fault: '0', faultCode: '0' };

test('a close remains unconfirmed through a closed-limit blip and sequential completion messages', () => {
  const states = [
    { ...baseline, closePending: '1', closeResult: 'pending', closeLimit: '0' },
    { ...baseline, closePending: '1', closeResult: 'pending' },
    { ...baseline, closePending: '0', closeResult: 'pending' },
    { ...baseline, closePending: '1', closeResult: 'closed' }
  ];
  states.forEach(state => assert.equal(describeCloseRecovery(state).confirmed, false));
  assert.equal(describeCloseRecovery({ ...baseline, closeResult: 'closed' }).confirmed, true);
});

test('transient limit faults communicate recovery and retained close intent', () => {
  const result = describeCloseRecovery({ ...baseline, closePending: '1', closeResult: 'recovering', fault: '1', faultCode: 'BOTH_LIMITS', closeRetryCount: '1' });
  assert.equal(result.recovering, true);
  assert.equal(result.retries, 1);
  assert.match(result.label, /Recovering.*close pending/);
  assert.equal(result.confirmed, false);
});

test('permanent faults never become recovery even with older pending telemetry', () => {
  for (const fault of ['0', '1']) {
    assert.equal(describeCloseRecovery({ ...baseline, closePending: '1', closeResult: 'recovering', fault, faultCode: 'CLOSE_RECOVERY_EXHAUSTED' }).recovering, false);
  }
  assert.equal(describeCloseRecovery({ ...baseline, closePending: '0', closeResult: 'recovering', fault: '1', faultCode: 'BOTH_LIMITS' }).recovering, false);
});

test('stopped at the endpoint communicates confirmation rather than completion', () => {
  assert.equal(describeCloseRecovery({ ...baseline, closePending: '1', closeResult: 'pending' }).label, 'Confirming closed limit');
});

test('cancelled, failed and unfamiliar results cannot confirm closure', () => {
  for (const closeResult of ['cancelled', 'failed', 'recovering', 'unexpected']) {
    assert.equal(describeCloseRecovery({ ...baseline, closeResult }).confirmed, false);
  }
});

test('older firmware keeps raw-limit completion when recovery topics are absent', () => {
  assert.equal(describeCloseRecovery({ closeLimit: '1' }).confirmed, true);
  assert.equal(describeCloseRecovery({ closeLimit: '0' }).confirmed, false);
  for (const value of [undefined, '', '-1', 'NaN']) assert.equal(describeCloseRecovery({ closeRetryCount: value }).retries, null);
});
