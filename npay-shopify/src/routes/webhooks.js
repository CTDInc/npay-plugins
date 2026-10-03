'use strict';

const express = require('express');

const db = require('../lib/db');
const shopifyClient = require('../lib/shopify-client');
const npayClient = require('../lib/npay-client');
const { verifyShopifyHmac } = require('../lib/shopify-hmac');

const router = express.Router();

// Use raw body for HMAC verification.
const rawJson = express.raw({ type: '*/*', limit: '5mb' });

// POST /webhooks/shopify — Shopify orders/create
router.post('/shopify', rawJson, async (req, res) => {
  try {
    const hmacHeader = req.get('X-Shopify-Hmac-Sha256');
    const topic = req.get('X-Shopify-Topic');
    const shopDomain = req.get('X-Shopify-Shop-Domain');
    const secret = process.env.SHOPIFY_API_SECRET;

    if (!verifyShopifyHmac(req.body, hmacHeader, secret)) {
      return res.status(401).send('Invalid HMAC');
    }
    const payload = JSON.parse(req.body.toString('utf8'));

    if (topic === 'orders/create') {
      const orderId = payload.id;
      const amount = parseFloat(payload.total_price || payload.current_total_price || '0');
      const refCode = `NPAY-${orderId}`;
      const existing = db.getOrderByShopifyId(shopDomain, orderId);
      if (!existing) {
        db.createOrder({ shopDomain, shopifyOrderId: orderId, refCode, amount });
      }
      const host = (process.env.HOST || '').replace(/\/$/, '');
      const qrPageUrl = `${host}/payment/${encodeURIComponent(refCode)}`;
      return res.status(200).json({ ok: true, ref_code: refCode, qr_page_url: qrPageUrl });
    }

    res.status(200).json({ ok: true, ignored: topic });
  } catch (err) {
    console.error('[npay-shopify] /webhooks/shopify error:', err);
    res.status(500).json({ error: err.message });
  }
});

// POST /webhooks/npay — NPay transaction webhook.
// Unmatched transfers answer 200 so NPay doesn't retry them forever; auth
// failures stay 401.
router.post('/npay', express.raw({ type: '*/*', limit: '2mb' }), async (req, res) => {
  try {
    const raw = Buffer.isBuffer(req.body) ? req.body : Buffer.from('');
    let payload;
    try {
      payload = JSON.parse(raw.toString('utf8') || '{}');
    } catch {
      return res.status(400).json({ success: false, message: 'Invalid JSON' });
    }

    if (String(payload.transferType || 'in').toLowerCase() !== 'in') {
      return res.status(200).json({ success: true, ignored: 'not an incoming transfer' });
    }

    const refCode = npayClient.extractRefCode(payload);
    if (!refCode) {
      return res.status(200).json({ success: true, ignored: 'no reference code' });
    }
    const order = db.getOrderByRef(refCode);
    if (!order) {
      return res.status(200).json({ success: true, ignored: 'order not found', ref_code: refCode });
    }
    const shop = db.getShop(order.shop_domain);
    if (!shop) {
      return res.status(200).json({ success: true, ignored: 'shop not found' });
    }
    const apikeyOk = npayClient.verifyWebhook(req.get('Authorization'), shop.api_token);
    const signatureOk = npayClient.verifySignature(
      raw,
      req.get('X-Npay-Signature'),
      shop.webhook_secret,
      req.get('X-Npay-Timestamp')
    );
    if (!apikeyOk && !signatureOk) {
      return res.status(401).json({ success: false, message: 'Invalid API key or signature' });
    }
    if (order.status === 'paid') {
      return res.status(200).json({ success: true, message: 'Already paid' });
    }

    const paidAmount = parseFloat(payload.transferAmount ?? payload.amount ?? 0) || 0;
    if (paidAmount + 0.01 < parseFloat(order.amount)) {
      return res.status(200).json({ success: true, ignored: 'underpaid', expected: order.amount, received: paidAmount });
    }

    try {
      await shopifyClient.createTransaction(shop.shop_domain, shop.access_token, order.shopify_order_id, {
        amount: order.amount,
        currency: payload.currency || 'VND',
        gateway: 'NPay',
      });
    } catch (e) {
      console.error('[npay-shopify] Shopify transaction error:', e.message);
      return res.status(502).json({ success: false, message: 'Shopify capture failed' });
    }

    db.markPaid(refCode);
    res.json({ success: true, ref_code: refCode, transaction_id: payload.id || null });
  } catch (err) {
    console.error('[npay-shopify] /webhooks/npay error:', err);
    res.status(500).json({ success: false, message: err.message });
  }
});

module.exports = router;
