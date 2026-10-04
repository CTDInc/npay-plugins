'use strict';

const express = require('express');
const fs = require('fs');
const path = require('path');

const db = require('../lib/db');
const { render } = require('./payment');
const { isValidShopDomain } = require('./auth');
const session = require('../lib/admin-session');

const router = express.Router();

const templatePath = path.join(__dirname, '..', 'templates', 'admin.html');
function loadTemplate() {
  return fs.readFileSync(templatePath, 'utf8');
}

function escapeHtml(s) {
  return String(s == null ? '' : s)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function apiSecret() {
  return process.env.SHOPIFY_API_SECRET || '';
}

function denied(res, status, shopDomain) {
  const reinstall = isValidShopDomain(shopDomain)
    ? ` Mở app từ Shopify admin, hoặc đăng nhập lại qua <a href="/auth?shop=${encodeURIComponent(shopDomain)}">/auth?shop=${escapeHtml(shopDomain)}</a>.`
    : ' Mở app từ Shopify admin (Apps → NPay).';
  return res
    .status(status)
    .set('Content-Type', 'text/html; charset=utf-8')
    .send(`<p>Không có quyền truy cập trang quản trị.${reinstall}</p>`);
}

function sessionShop(req) {
  const data = session.readSession(req.cookies?.[session.COOKIE_NAME], apiSecret());
  return data ? data.shop : null;
}

// GET /admin?shop=<shop> — Shopify launch (hmac) or an existing session for that shop
router.get('/', (req, res) => {
  const secret = apiSecret();
  const shopDomain = req.query.shop;
  if (!secret) {
    return res.status(503).send('Server misconfigured: SHOPIFY_API_SECRET is required');
  }
  if (!isValidShopDomain(shopDomain)) {
    return res.status(400).send('Missing or invalid ?shop=<store>.myshopify.com');
  }

  if (req.query.hmac) {
    if (!session.verifyLaunch(req.query, secret)) {
      return denied(res, 401, shopDomain);
    }
    if (!db.getShop(shopDomain)) {
      return res.redirect('/auth?shop=' + encodeURIComponent(shopDomain));
    }
    session.setSession(res, shopDomain, secret);
    return res.redirect('/admin?shop=' + encodeURIComponent(shopDomain));
  }
  if (sessionShop(req) !== shopDomain) {
    return denied(res, 401, shopDomain);
  }
  const cookie = req.cookies[session.COOKIE_NAME];

  const shop = db.getShop(shopDomain);
  if (!shop) {
    return res.status(404).send('Shop chưa cài đặt. Truy cập /auth?shop=' + encodeURIComponent(shopDomain));
  }
  const orders = db.listOrdersByShop(shopDomain, 50);
  const ordersHtml = orders
    .map(
      (o) => `
      <tr>
        <td>${escapeHtml(o.ref_code)}</td>
        <td>${escapeHtml(o.shopify_order_id)}</td>
        <td>${Number(o.amount).toLocaleString('vi-VN')}</td>
        <td><span class="status status-${escapeHtml(o.status)}">${escapeHtml(o.status)}</span></td>
        <td>${o.paid_at ? new Date(o.paid_at * 1000).toLocaleString('vi-VN') : '—'}</td>
      </tr>`
    )
    .join('');

  const html = render(loadTemplate(), {
    shop_domain: escapeHtml(shopDomain),
    csrf_token: escapeHtml(session.csrfToken(cookie, secret)),
    account_number: escapeHtml(shop.account_number),
    bank_bin: escapeHtml(shop.bank_bin),
    account_holder: escapeHtml(shop.account_holder),
    api_token: escapeHtml(shop.api_token),
    webhook_secret: escapeHtml(shop.webhook_secret),
    qr_template: escapeHtml(shop.qr_template || 'compact'),
    orders_rows: ordersHtml || '<tr><td colspan="5" class="empty">Chưa có đơn hàng</td></tr>',
  });
  res.set({ 'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'no-store' }).send(html);
});

// POST /admin/settings — save settings for a shop
router.post('/settings', express.urlencoded({ extended: true }), (req, res) => {
  const { shop, account_number, bank_bin, account_holder, api_token, webhook_secret, qr_template, csrf_token } = req.body;
  const secret = apiSecret();
  if (!secret) return res.status(503).send('Server misconfigured: SHOPIFY_API_SECRET is required');
  if (!isValidShopDomain(shop)) return res.status(400).send('Missing shop');
  const cookie = req.cookies?.[session.COOKIE_NAME];
  if (sessionShop(req) !== shop || !session.verifyCsrf(cookie, csrf_token, secret)) {
    return denied(res, 403, shop);
  }
  const existing = db.getShop(shop);
  if (!existing) return res.status(404).send('Shop not installed');
  db.updateShopSettings(shop, {
    account_number,
    bank_bin,
    account_holder,
    api_token,
    webhook_secret,
    qr_template,
  });
  res.redirect('/admin?shop=' + encodeURIComponent(shop));
});

module.exports = router;
