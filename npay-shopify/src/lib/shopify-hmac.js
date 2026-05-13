'use strict';

const crypto = require('crypto');

/**
 * Verify a Shopify webhook HMAC against the raw request body.
 * Header `X-Shopify-Hmac-Sha256` is base64(HMAC-SHA256(secret, rawBody)).
 *
 * @param {Buffer|string} rawBody
 * @param {string|undefined} headerHmac
 * @param {string|undefined} secret
 * @returns {boolean}
 */
function verifyShopifyHmac(rawBody, headerHmac, secret) {
  if (!headerHmac || !secret || rawBody === undefined || rawBody === null) return false;
  const body = Buffer.isBuffer(rawBody) ? rawBody : Buffer.from(String(rawBody));
  const digest = crypto.createHmac('sha256', secret).update(body).digest('base64');
  try {
    const a = Buffer.from(digest);
    const b = Buffer.from(headerHmac);
    if (a.length !== b.length) return false;
    return crypto.timingSafeEqual(a, b);
  } catch {
    return false;
  }
}

/**
 * Verify the HMAC on a Shopify OAuth callback query string.
 * Shopify hashes the URL-encoded query (sans `hmac`/`signature`) sorted alphabetically.
 *
 * @param {Record<string,string>} query
 * @param {string} secret
 * @returns {boolean}
 */
function verifyOAuthHmac(query, secret) {
  if (!query || !secret) return false;
  const { hmac, signature: _s, ...rest } = query;
  if (!hmac) return false;
  const message = Object.keys(rest)
    .sort()
    .map((k) => `${k}=${rest[k]}`)
    .join('&');
  const digest = crypto.createHmac('sha256', secret).update(message).digest('hex');
  try {
    const a = Buffer.from(digest, 'hex');
    const b = Buffer.from(hmac, 'hex');
    if (a.length !== b.length) return false;
    return crypto.timingSafeEqual(a, b);
  } catch {
    return false;
  }
}

module.exports = { verifyShopifyHmac, verifyOAuthHmac };
