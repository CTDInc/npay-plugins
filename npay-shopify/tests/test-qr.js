'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');

const { buildQrUrl, extractRefCode } = require('../src/lib/npay-client');

test('buildQrUrl: basic params', () => {
  const url = buildQrUrl({
    accountNumber: '0123456789',
    bankBin: '970422',
    amount: 150000,
    refCode: 'NPAY-1001',
    template: 'compact',
  });
  assert.ok(url.startsWith('https://qr.sepay.vn/img?'));
  assert.match(url, /acc=0123456789/);
  assert.match(url, /bank=970422/);
  assert.match(url, /amount=150000/);
  assert.match(url, /des=NPAY-1001/);
  assert.match(url, /template=compact/);
});

test('buildQrUrl: encodes special chars in accountHolder', () => {
  const url = buildQrUrl({
    accountNumber: '0123',
    bankBin: '970422',
    accountHolder: 'NGUYEN VAN A',
  });
  assert.match(url, /accountName=NGUYEN\+VAN\+A/);
});

test('buildQrUrl: omits empty amount', () => {
  const url = buildQrUrl({ accountNumber: '0123', bankBin: '970422' });
  assert.doesNotMatch(url, /amount=/);
});

test('buildQrUrl: requires account and bank', () => {
  assert.throws(() => buildQrUrl({ accountNumber: '', bankBin: '970422' }));
  assert.throws(() => buildQrUrl({ accountNumber: '123', bankBin: '' }));
});

test('extractRefCode: pulls NPAY-xxx from content', () => {
  assert.equal(
    extractRefCode({ content: 'CK FROM CUSTOMER NPAY-1234 thanks' }),
    'NPAY-1234'
  );
  assert.equal(extractRefCode({ transferContent: 'npay-abc99' }), 'NPAY-ABC99');
  assert.equal(extractRefCode({ content: 'no code here' }), null);
  assert.equal(extractRefCode(null), null);
});
