'use strict';

const express = require('express');
const crypto = require('crypto');

const db = require('../lib/db');
const shopifyClient = require('../lib/shopify-client');
const { verifyOAuthHmac } = require('../lib/shopify-hmac');

const router = express.Router();

function isValidShopDomain(shop) {
  return typeof shop === 'string' && /^[a-zA-Z0-9][a-zA-Z0-9-]*\.myshopify\.com$/.test(shop);
}

// GET /auth?shop=<shop>.myshopify.com — start OAuth
router.get('/', (req, res) => {
  const shop = req.query.shop;
  if (!isValidShopDomain(shop)) {
    return res.status(400).send('Missing or invalid ?shop=<store>.myshopify.com');
  }
  const apiKey = process.env.SHOPIFY_API_KEY;
  const scopes = process.env.SCOPES || 'read_orders,write_orders';
  const host = process.env.HOST;
  if (!apiKey || !host) {
    return res.status(500).send('Server misconfigured: SHOPIFY_API_KEY and HOST are required');
  }
  const state = crypto.randomBytes(16).toString('hex');
  res.cookie('npay_oauth_state', state, { httpOnly: true, sameSite: 'lax', maxAge: 5 * 60 * 1000 });

  const redirectUri = `${host.replace(/\/$/, '')}/auth/callback`;
  const params = new URLSearchParams({
    client_id: apiKey,
    scope: scopes,
    redirect_uri: redirectUri,
    state,
    'grant_options[]': '',
  });
  const installUrl = `https://${shop}/admin/oauth/authorize?${params.toString()}`;
  res.redirect(installUrl);
});

// GET /auth/callback — exchange code for access_token, register webhooks
router.get('/callback', async (req, res) => {
  try {
    const { shop, code, state, hmac } = req.query;
    if (!isValidShopDomain(shop) || !code || !hmac) {
      return res.status(400).send('Invalid OAuth callback parameters');
    }
    const cookieState = req.cookies?.npay_oauth_state;
    if (!cookieState || cookieState !== state) {
      return res.status(400).send('Invalid OAuth state');
    }
    const secret = process.env.SHOPIFY_API_SECRET;
    if (!verifyOAuthHmac(req.query, secret)) {
      return res.status(400).send('Invalid OAuth HMAC');
    }

    // Exchange code for access_token
    const tokenRes = await fetch(`https://${shop}/admin/oauth/access_token`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({
        client_id: process.env.SHOPIFY_API_KEY,
        client_secret: secret,
        code,
      }),
    });
    if (!tokenRes.ok) {
      const text = await tokenRes.text();
      return res.status(502).send(`Token exchange failed: ${text}`);
    }
    const tokenJson = await tokenRes.json();
    const accessToken = tokenJson.access_token;
    if (!accessToken) {
      return res.status(502).send('No access_token returned from Shopify');
    }

    db.upsertShop(shop, accessToken);

    // Subscribe to orders/create webhook
    const host = process.env.HOST.replace(/\/$/, '');
    try {
      await shopifyClient.subscribeWebhook(shop, accessToken, {
        topic: 'orders/create',
        address: `${host}/webhooks/shopify`,
        format: 'json',
      });
    } catch (e) {
      // Already exists is OK; log and continue.
      console.warn('[npay-shopify] webhook subscribe warning:', e.message);
    }

    res.clearCookie('npay_oauth_state');
    res.redirect(`/admin?shop=${encodeURIComponent(shop)}`);
  } catch (err) {
    console.error('[npay-shopify] /auth/callback error:', err);
    res.status(500).send('OAuth callback error: ' + err.message);
  }
});

module.exports = router;
module.exports.isValidShopDomain = isValidShopDomain;
