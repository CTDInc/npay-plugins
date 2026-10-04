'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const crypto = require('node:crypto');

process.env.DATABASE_PATH = ':memory:';
process.env.SHOPIFY_API_SECRET = 'shpss_admin_test';

const express = require('express');
const cookieParser = require('cookie-parser');

const db = require('../src/lib/db');
const session = require('../src/lib/admin-session');
const adminRoutes = require('../src/routes/admin');

const SECRET = process.env.SHOPIFY_API_SECRET;
const SHOP = 'demo.myshopify.com';

function launchQuery(shop, ts = Math.floor(Date.now() / 1000), secret = SECRET) {
  const params = { host: 'abc', shop, timestamp: String(ts) };
  const message = Object.keys(params).sort().map((k) => `${k}=${params[k]}`).join('&');
  const hmac = crypto.createHmac('sha256', secret).update(message).digest('hex');
  return new URLSearchParams({ ...params, hmac }).toString();
}

let base;
let server;

test.before(async () => {
  db.init();
  db.upsertShop(SHOP, 'shpat_x');
  db.updateShopSettings(SHOP, { account_number: '0123456789', bank_bin: '970422', account_holder: 'A', api_token: 'k', webhook_secret: 's', qr_template: 'compact' });
  db.upsertShop('other.myshopify.com', 'shpat_y');
  const app = express();
  app.use(express.urlencoded({ extended: true }));
  app.use(cookieParser());
  app.use('/admin', adminRoutes);
  await new Promise((resolve) => {
    server = app.listen(0, resolve);
  });
  base = `http://127.0.0.1:${server.address().port}`;
});

test.after(() => server.close());

function get(path, cookie) {
  return fetch(base + path, { redirect: 'manual', headers: cookie ? { cookie } : {} });
}

function cookieFrom(res) {
  const raw = res.headers.get('set-cookie') || '';
  const m = raw.match(new RegExp(`${session.COOKIE_NAME}=([^;]+)`));
  return m ? `${session.COOKIE_NAME}=${m[1]}` : null;
}

test('session: round trip, tamper, expiry', () => {
  const tok = session.issueSession(SHOP, SECRET);
  assert.equal(session.readSession(tok, SECRET).shop, SHOP);
  assert.equal(session.readSession(tok, 'wrong'), null);
  const [payload, mac] = tok.split('.');
  const forged = Buffer.from(JSON.stringify({ shop: 'other.myshopify.com', exp: 9e9 })).toString('base64url');
  assert.equal(session.readSession(`${forged}.${mac}`, SECRET), null);
  assert.equal(session.readSession(`${payload}.${mac}`, SECRET, Math.floor(Date.now() / 1000) + 9 * 3600), null);
});

test('GET /admin without session → 401, no secrets in body', async () => {
  const res = await get(`/admin?shop=${SHOP}`);
  assert.equal(res.status, 401);
  assert.doesNotMatch(await res.text(), /0123456789/);
});

test('GET /admin/shops.json is gone', async () => {
  const res = await get('/admin/shops.json');
  assert.equal(res.status, 404);
});

test('launch with bad or stale hmac → 401', async () => {
  assert.equal((await get(`/admin?${launchQuery(SHOP, undefined, 'nope')}`)).status, 401);
  const stale = Math.floor(Date.now() / 1000) - 3600;
  assert.equal((await get(`/admin?${launchQuery(SHOP, stale)}`)).status, 401);
});

test('valid launch → session for that shop only; settings need CSRF', async () => {
  const launch = await get(`/admin?${launchQuery(SHOP)}`);
  assert.equal(launch.status, 302);
  const cookie = cookieFrom(launch);
  assert.ok(cookie);

  const page = await get(`/admin?shop=${SHOP}`, cookie);
  assert.equal(page.status, 200);
  const html = await page.text();
  assert.match(html, /0123456789/);
  const csrf = html.match(/name="csrf_token" value="([^"]+)"/)[1];

  assert.equal((await get('/admin?shop=other.myshopify.com', cookie)).status, 401);

  const post = (body) =>
    fetch(`${base}/admin/settings`, {
      method: 'POST',
      redirect: 'manual',
      headers: { cookie, 'content-type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams(body).toString(),
    });

  assert.equal((await post({ shop: SHOP, account_number: '999' })).status, 403);
  assert.equal((await post({ shop: 'other.myshopify.com', account_number: '999', csrf_token: csrf })).status, 403);
  assert.equal(db.getShop('other.myshopify.com').account_number, null);

  const ok = await post({ shop: SHOP, account_number: '111', bank_bin: '970422', csrf_token: csrf });
  assert.equal(ok.status, 302);
  assert.equal(db.getShop(SHOP).account_number, '111');
});

test('no SHOPIFY_API_SECRET → refuse', async () => {
  const saved = process.env.SHOPIFY_API_SECRET;
  delete process.env.SHOPIFY_API_SECRET;
  try {
    const cookie = `${session.COOKIE_NAME}=${session.issueSession(SHOP, saved)}`;
    assert.equal((await get(`/admin?shop=${SHOP}`, cookie)).status, 503);
  } finally {
    process.env.SHOPIFY_API_SECRET = saved;
  }
});
