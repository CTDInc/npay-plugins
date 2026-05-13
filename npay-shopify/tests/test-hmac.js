'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const crypto = require('node:crypto');

const { verifyShopifyHmac, verifyOAuthHmac } = require('../src/lib/shopify-hmac');
const { verifyWebhook } = require('../src/lib/npay-client');

test('verifyShopifyHmac: valid base64 HMAC passes', () => {
  const secret = 'shpss_secret_test';
  const body = Buffer.from(JSON.stringify({ id: 12345, total_price: '199000' }));
  const hmac = crypto.createHmac('sha256', secret).update(body).digest('base64');
  assert.equal(verifyShopifyHmac(body, hmac, secret), true);
});

test('verifyShopifyHmac: tampered body fails', () => {
  const secret = 'shpss_secret_test';
  const body = Buffer.from(JSON.stringify({ id: 1 }));
  const hmac = crypto.createHmac('sha256', secret).update(body).digest('base64');
  const tampered = Buffer.from(JSON.stringify({ id: 2 }));
  assert.equal(verifyShopifyHmac(tampered, hmac, secret), false);
});

test('verifyShopifyHmac: missing inputs fail', () => {
  assert.equal(verifyShopifyHmac(null, 'x', 'y'), false);
  assert.equal(verifyShopifyHmac(Buffer.from('x'), null, 'y'), false);
  assert.equal(verifyShopifyHmac(Buffer.from('x'), 'a', null), false);
});

test('verifyOAuthHmac: hex HMAC of sorted query params', () => {
  const secret = 'shpss_secret_oauth';
  const params = { code: 'abc', shop: 'test.myshopify.com', state: 'xyz', timestamp: '1700000000' };
  const message = Object.keys(params)
    .sort()
    .map((k) => `${k}=${params[k]}`)
    .join('&');
  const hmac = crypto.createHmac('sha256', secret).update(message).digest('hex');
  const query = Object.assign({ hmac }, params);
  assert.equal(verifyOAuthHmac(query, secret), true);
});

test('verifyOAuthHmac: rejects bad signature', () => {
  const query = { code: 'abc', shop: 's.myshopify.com', hmac: 'deadbeef' };
  assert.equal(verifyOAuthHmac(query, 'secret'), false);
});

test('npay verifyWebhook: matches Apikey header', () => {
  assert.equal(verifyWebhook('Apikey supersecret', 'supersecret'), true);
  assert.equal(verifyWebhook('apikey supersecret', 'supersecret'), true);
  assert.equal(verifyWebhook('Apikey wrong', 'supersecret'), false);
  assert.equal(verifyWebhook('Bearer supersecret', 'supersecret'), false);
  assert.equal(verifyWebhook('', 'supersecret'), false);
  assert.equal(verifyWebhook('Apikey supersecret', ''), false);
});
