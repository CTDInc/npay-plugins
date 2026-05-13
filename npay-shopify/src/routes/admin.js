'use strict';

const express = require('express');
const fs = require('fs');
const path = require('path');

const db = require('../lib/db');
const { render } = require('./payment');

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

// GET /admin?shop=<shop>
router.get('/', (req, res) => {
  const shopDomain = req.query.shop;
  if (!shopDomain) {
    return res.status(400).send('Missing ?shop=<store>.myshopify.com');
  }
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
    account_number: escapeHtml(shop.account_number),
    bank_bin: escapeHtml(shop.bank_bin),
    account_holder: escapeHtml(shop.account_holder),
    api_token: escapeHtml(shop.api_token),
    qr_template: escapeHtml(shop.qr_template || 'compact'),
    orders_rows: ordersHtml || '<tr><td colspan="5" class="empty">Chưa có đơn hàng</td></tr>',
  });
  res.set('Content-Type', 'text/html; charset=utf-8').send(html);
});

// POST /admin/settings — save settings for a shop
router.post('/settings', express.urlencoded({ extended: true }), (req, res) => {
  const { shop, account_number, bank_bin, account_holder, api_token, qr_template } = req.body;
  if (!shop) return res.status(400).send('Missing shop');
  const existing = db.getShop(shop);
  if (!existing) return res.status(404).send('Shop not installed');
  db.updateShopSettings(shop, {
    account_number,
    bank_bin,
    account_holder,
    api_token,
    qr_template,
  });
  res.redirect('/admin?shop=' + encodeURIComponent(shop));
});

// GET /admin/shops — JSON list (debug)
router.get('/shops.json', (_req, res) => {
  res.json(db.listShops());
});

module.exports = router;
