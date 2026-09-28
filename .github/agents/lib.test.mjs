import assert from 'node:assert/strict';
import test from 'node:test';
import { normalizeTriage, normalizeVerdict, triageLabels } from './lib.mjs';

const confirmed = {
  classification: 'confirmed_bug', severity: 'high', fix_ready: true,
  summary: 'Publishing fails', evidence: 'The save handler returns an error',
  missing_info: [], duplicate_of: null
};

test('confirmed bug receives a single category and severity label', () => {
  assert.deepEqual(triageLabels(normalizeTriage(confirmed)), ['confirmed bug', 'severity: high']);
});

test('uncertain reports cannot enter the fixing stage', () => {
  assert.throws(() => normalizeTriage({ ...confirmed, classification: 'needs_info' }));
  assert.deepEqual(triageLabels(normalizeTriage({
    ...confirmed, classification: 'needs_info', severity: 'unknown', fix_ready: false
  })), ['needs info']);
});

test('a duplicate requires an issue number', () => {
  assert.throws(() => normalizeTriage({
    ...confirmed, classification: 'duplicate', severity: 'unknown', fix_ready: false
  }));
});

const verified = {
  verdict: 'verified', summary: 'Regression test passes', evidence_before: 'Fails on development',
  evidence_after: 'Passes on PR', regression_test: 'calendar regression', remaining_risks: []
};

test('test verdict cannot be verified with failed checks or missing baseline', () => {
  assert.equal(normalizeVerdict(verified, { status: 'failed' }).verdict, 'failed');
  assert.equal(normalizeVerdict({ ...verified, evidence_before: '' }, { status: 'passed' }).verdict, 'inconclusive');
  assert.equal(normalizeVerdict(verified, { status: 'passed' }).verdict, 'verified');
});
