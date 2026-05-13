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

// POST /webhooks/npay — npay/SePay transaction webhook
router.post('/npay', express.json({ limit: '2mb' }), async (req, res) => {
  try {
    const auth = req.get('Authorization');
    const payload = req.body || {};

    let refCode = npayClient.extractRefCode(payload);
    if (!refCode && typeof payload.code === 'string') refCode = payload.code;
    if (!refCode) {
      return res.status(400).json({ success: false, message: 'Missing reference code' });
    }
    refCode = refCode.toUpperCase();
    const order = db.getOrderByRef(refCode);
    if (!order) {
      return res.status(404).json({ success: false, message: 'Order not found' });
    }
    const shop = db.getShop(order.shop_domain);
    if (!shop) {
      return res.status(404).json({ success: false, message: 'Shop not found' });
    }
    if (!npayClient.verifyWebhook(auth, shop.api_token)) {
      return res.status(401).json({ success: false, message: 'Invalid API token' });
    }
    if (order.status === 'paid') {
      return res.status(200).json({ success: true, message: 'Already paid' });
    }

    // Optionally cross-check amount (npay payload field varies)
    const paidAmount = parseFloat(
      payload.transferAmount ?? payload.amount ?? payload.transfer_amount ?? 0
    );
    if (paidAmount && paidAmount + 0.01 < parseFloat(order.amount)) {
      return res.status(400).json({ success: false, message: 'Underpaid' });
    }

    // Mark paid on Shopify
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
    res.json({ success: true, ref_code: refCode });
  } catch (err) {
    console.error('[npay-shopify] /webhooks/npay error:', err);
    res.status(500).json({ success: false, message: err.message });
  }
});

module.exports = router;
