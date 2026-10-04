'use strict';

const crypto = require('crypto');

const { verifyOAuthHmac } = require('./shopify-hmac');

const COOKIE_NAME = 'npay_admin_session';
const SESSION_TTL_SECONDS = 8 * 60 * 60;
const LAUNCH_MAX_AGE_SECONDS = 10 * 60;

function sessionKey(secret) {
  return crypto.createHash('sha256').update(`npay-shopify-admin-session:${secret}`).digest();
}

function sign(payload, secret) {
  return crypto.createHmac('sha256', sessionKey(secret)).update(payload).digest('base64url');
}

function safeEqual(a, b) {
  const ba = Buffer.from(String(a));
  const bb = Buffer.from(String(b));
  if (ba.length !== bb.length) return false;
  return crypto.timingSafeEqual(ba, bb);
}

function issueSession(shop, secret, now = Math.floor(Date.now() / 1000)) {
  if (!secret) throw new Error('SHOPIFY_API_SECRET is required');
  const payload = Buffer.from(JSON.stringify({ shop, exp: now + SESSION_TTL_SECONDS })).toString('base64url');
  return `${payload}.${sign(payload, secret)}`;
}

function readSession(token, secret, now = Math.floor(Date.now() / 1000)) {
  if (!token || !secret || typeof token !== 'string') return null;
  const dot = token.indexOf('.');
  if (dot <= 0) return null;
  const payload = token.slice(0, dot);
  const mac = token.slice(dot + 1);
  if (!safeEqual(mac, sign(payload, secret))) return null;
  let data;
  try {
    data = JSON.parse(Buffer.from(payload, 'base64url').toString('utf8'));
  } catch {
    return null;
  }
  if (!data || typeof data.shop !== 'string' || typeof data.exp !== 'number' || data.exp < now) return null;
  return data;
}

function csrfToken(sessionCookie, secret) {
  return sign(`csrf:${sessionCookie}`, secret);
}

function verifyCsrf(sessionCookie, token, secret) {
  if (!sessionCookie || !token || !secret) return false;
  return safeEqual(token, csrfToken(sessionCookie, secret));
}

/**
 * Shopify opens the App URL with `shop`, `timestamp`, `hmac` (+ `host`) signed by the
 * app secret, same algorithm as the OAuth callback.
 */
function verifyLaunch(query, secret, now = Math.floor(Date.now() / 1000)) {
  if (!query || !query.hmac || !query.shop || !query.timestamp) return false;
  const ts = Number(query.timestamp);
  if (!Number.isFinite(ts) || Math.abs(now - ts) > LAUNCH_MAX_AGE_SECONDS) return false;
  return verifyOAuthHmac(query, secret);
}

function cookieOptions() {
  const secure = String(process.env.HOST || '').startsWith('https://');
  return { httpOnly: true, sameSite: 'lax', secure, maxAge: SESSION_TTL_SECONDS * 1000, path: '/' };
}

function setSession(res, shop, secret) {
  const token = issueSession(shop, secret);
  res.cookie(COOKIE_NAME, token, cookieOptions());
  return token;
}

module.exports = {
  COOKIE_NAME,
  issueSession,
  readSession,
  csrfToken,
  verifyCsrf,
  verifyLaunch,
  setSession,
  cookieOptions,
};
