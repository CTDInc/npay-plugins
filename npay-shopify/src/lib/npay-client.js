'use strict';

const crypto = require('crypto');

/**
 * Build a SePay/NPay-compatible VietQR URL.
 * Format follows https://docs.sepay.vn/lay-link-anh-qr-code.html style:
 *   https://qr.sepay.vn/img?acc=<accountNumber>&bank=<bankBin>&amount=<amount>&des=<refCode>&template=<template>
 *
 * @param {{ accountNumber: string, bankBin: string, amount: number|string, refCode: string, template?: string, accountHolder?: string }} opts
 * @returns {string}
 */
function buildQrUrl(opts) {
  if (!opts || !opts.accountNumber || !opts.bankBin) {
    throw new Error('buildQrUrl: accountNumber and bankBin are required');
  }
  const params = new URLSearchParams();
  params.set('acc', String(opts.accountNumber));
  params.set('bank', String(opts.bankBin));
  if (opts.amount !== undefined && opts.amount !== null && opts.amount !== '') {
    params.set('amount', String(opts.amount));
  }
  if (opts.refCode) {
    params.set('des', String(opts.refCode));
  }
  if (opts.template) {
    params.set('template', String(opts.template));
  }
  if (opts.accountHolder) {
    params.set('accountName', String(opts.accountHolder));
  }
  return `https://qr.sepay.vn/img?${params.toString()}`;
}

/**
 * Verify the npay/SePay webhook `Authorization: Apikey <token>` header.
 *
 * @param {string|undefined} headerValue
 * @param {string|undefined} expectedToken
 * @returns {boolean}
 */
function verifyWebhook(headerValue, expectedToken) {
  if (!headerValue || !expectedToken) return false;
  const match = /^Apikey\s+(.+)$/i.exec(headerValue.trim());
  if (!match) return false;
  const provided = match[1].trim();
  const a = Buffer.from(provided);
  const b = Buffer.from(expectedToken);
  if (a.length !== b.length) return false;
  try {
    return crypto.timingSafeEqual(a, b);
  } catch {
    return false;
  }
}

/**
 * Extract the npay reference code from a transaction payload.
 * Looks for `NPAY-<orderId>` substring in `content` / `transferContent` / `description`.
 */
function extractRefCode(payload) {
  if (!payload) return null;
  const fields = ['content', 'transferContent', 'description', 'remark', 'note'];
  for (const f of fields) {
    const v = payload[f];
    if (typeof v === 'string') {
      const m = /NPAY-\w+/i.exec(v);
      if (m) return m[0].toUpperCase();
    }
  }
  return null;
}

module.exports = {
  buildQrUrl,
  verifyWebhook,
  extractRefCode,
};
