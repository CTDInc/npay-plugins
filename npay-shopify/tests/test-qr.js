'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const crypto = require('crypto');

const { buildQrUrl, extractRefCode, verifyWebhook, verifySignature } = require('../src/lib/npay-client');

test('buildQrUrl: basic params', () => {
  const url = buildQrUrl({
    accountNumber: '0123456789',
    bankBin: '970422',
    amount: 150000,
    refCode: 'NPAY-1001',
    template: 'compact',
  });
  assert.ok(url.startsWith('https://qr.npay.vn/qrcard?'));
  assert.match(url, /ma_bin=970422/);
  assert.match(url, /tai_khoan=0123456789/);
  assert.match(url, /so_tien=150000/);
  assert.match(url, /noi_dung=NPAY-1001/);
});

test('buildQrUrl: bank code and bare QR', () => {
  const url = buildQrUrl({ accountNumber: '0123', bankBin: 'VCB', template: 'qr_only', accountHolder: 'A' });
  assert.ok(url.startsWith('https://qr.npay.vn/qrpay?'));
  assert.match(url, /ngan_hang=vietcombank/);
  assert.doesNotMatch(url, /chu_tai_khoan/);
});

test('buildQrUrl: encodes special chars in accountHolder', () => {
  const url = buildQrUrl({
    accountNumber: '0123',
    bankBin: '970422',
    accountHolder: 'NGUYEN VAN A',
  });
  assert.match(url, /chu_tai_khoan=NGUYEN\+VAN\+A/);
});

test('buildQrUrl: omits empty amount', () => {
  const url = buildQrUrl({ accountNumber: '0123', bankBin: '970422' });
  assert.doesNotMatch(url, /so_tien=/);
});

test('buildQrUrl: requires account and bank', () => {
  assert.throws(() => buildQrUrl({ accountNumber: '', bankBin: '970422' }));
  assert.throws(() => buildQrUrl({ accountNumber: '123', bankBin: '' }));
});

test('extractRefCode: pulls NPAY-xxx from content, with or without hyphen', () => {
  assert.equal(
    extractRefCode({ content: 'CK FROM CUSTOMER NPAY-1234 thanks' }),
    'NPAY-1234'
  );
  assert.equal(extractRefCode({ transferContent: 'npay-abc99' }), 'NPAY-ABC99');
  assert.equal(extractRefCode({ content: 'MBVCB.123 NPAY1234 chuyen tien' }), 'NPAY-1234');
  assert.equal(extractRefCode({ code: null, content: 'NPAY 77' }), 'NPAY-77');
  assert.equal(extractRefCode({ content: 'no code here' }), null);
  assert.equal(extractRefCode(null), null);
});

test('verifyWebhook: Apikey header', () => {
  assert.equal(verifyWebhook('Apikey tok', 'tok'), true);
  assert.equal(verifyWebhook('Apikey nope', 'tok'), false);
  assert.equal(verifyWebhook('Apikey tok', ''), false);
});

test('verifySignature: bare hex HMAC over raw body, timestamp window', () => {
  const body = Buffer.from('{"id":"tx_1","transferType":"in"}');
  const sig = crypto.createHmac('sha256', 'sec').update(body).digest('hex');
  const now = String(Math.floor(Date.now() / 1000));
  assert.equal(verifySignature(body, sig, 'sec'), true);
  assert.equal(verifySignature(body, sig, 'sec', now), true);
  assert.equal(verifySignature(body, sig, 'sec', String(Number(now) - 3600)), false);
  assert.equal(verifySignature(body, sig, 'other'), false);
  assert.equal(verifySignature(body, sig, ''), false);
});
