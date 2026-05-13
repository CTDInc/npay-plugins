'use strict';

const express = require('express');
const fs = require('fs');
const path = require('path');

const db = require('../lib/db');
const npayClient = require('../lib/npay-client');

const router = express.Router();
const statusRouter = express.Router();

const templatePath = path.join(__dirname, '..', 'templates', 'payment.html');
let templateCache = null;
function loadTemplate() {
  if (!templateCache || process.env.NODE_ENV !== 'production') {
    templateCache = fs.readFileSync(templatePath, 'utf8');
  }
  return templateCache;
}

function render(tpl, vars) {
  return tpl.replace(/\{\{\s*(\w+)\s*\}\}/g, (_, k) => (vars[k] !== undefined ? String(vars[k]) : ''));
}

// GET /payment/:orderId  (orderId here is the ref_code e.g. NPAY-12345)
router.get('/:orderId', (req, res) => {
  const refCode = String(req.params.orderId).toUpperCase();
  const order = db.getOrderByRef(refCode);
  if (!order) {
    return res.status(404).send('Order not found');
  }
  const shop = db.getShop(order.shop_domain);
  if (!shop || !shop.account_number || !shop.bank_bin) {
    return res
      .status(503)
      .send('NPay chưa được cấu hình cho cửa hàng này. Vui lòng vào trang Admin để thiết lập.');
  }
  const qrUrl = npayClient.buildQrUrl({
    accountNumber: shop.account_number,
    bankBin: shop.bank_bin,
    amount: order.amount,
    refCode: order.ref_code,
    template: shop.qr_template || 'compact',
    accountHolder: shop.account_holder,
  });

  const html = render(loadTemplate(), {
    ref_code: order.ref_code,
    amount: order.amount,
    amount_formatted: Number(order.amount).toLocaleString('vi-VN'),
    qr_url: qrUrl,
    account_number: shop.account_number,
    account_holder: shop.account_holder || '',
    bank_bin: shop.bank_bin,
    status: order.status,
    shop_domain: order.shop_domain,
  });
  res.set('Content-Type', 'text/html; charset=utf-8').send(html);
});

// GET /status/:orderId — JSON
statusRouter.get('/:orderId', (req, res) => {
  const refCode = String(req.params.orderId).toUpperCase();
  const order = db.getOrderByRef(refCode);
  if (!order) return res.status(404).json({ error: 'not_found' });
  res.json({
    ref_code: order.ref_code,
    amount: order.amount,
    status: order.status,
    paid_at: order.paid_at,
  });
});

module.exports = router;
module.exports.statusRouter = statusRouter;
module.exports.render = render;
