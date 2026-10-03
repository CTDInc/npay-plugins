'use strict';

const crypto = require('crypto');

const BANK_ALIASES = {
  vcb: 'vietcombank', tcb: 'techcombank', ctg: 'vietinbank', icb: 'vietinbank',
  mb: 'mbbank', vpb: 'vpbank', tpb: 'tpbank', stb: 'sacombank',
  hdb: 'hdbank', eib: 'eximbank', vba: 'agribank', agr: 'agribank',
  lpb: 'lienvietpostbank', lpbank: 'lienvietpostbank', nab: 'namabank',
  abb: 'abbank', bab: 'bacabank', pvcb: 'pvcombank', seab: 'seabank',
  klb: 'kienlongbank', vab: 'vietabank', sgicb: 'saigonbank', bvb: 'banviet',
};

/**
 * Build an NPay gen-qr image URL:
 *   https://qr.npay.vn/qrcard?ma_bin=<bin>&tai_khoan=<acc>&so_tien=<amount>&noi_dung=<ref>&chu_tai_khoan=<name>
 * `template: 'qr_only'` gives the bare QR from `/qrpay`. `bankBin` takes a Napas BIN
 * (`ma_bin`) or a bank key / short code (`ngan_hang`, e.g. `mbbank`, `VCB`).
 *
 * @param {{ accountNumber: string, bankBin: string, amount: number|string, refCode: string, template?: string, accountHolder?: string }} opts
 * @returns {string}
 */
function buildQrUrl(opts) {
  if (!opts || !opts.accountNumber || !opts.bankBin) {
    throw new Error('buildQrUrl: accountNumber and bankBin are required');
  }
  const bareQr = opts.template === 'qr_only' || opts.template === 'qronly';
  const bank = String(opts.bankBin).trim().toLowerCase().replace(/[\s_-]+/g, '');
  const params = new URLSearchParams();
  if (/^\d{6}$/.test(bank)) {
    params.set('ma_bin', bank);
  } else {
    params.set('ngan_hang', BANK_ALIASES[bank] || bank);
  }
  params.set('tai_khoan', String(opts.accountNumber));
  if (opts.amount !== undefined && opts.amount !== null && opts.amount !== '') {
    params.set('so_tien', String(Math.round(Number(opts.amount))));
  }
  if (opts.refCode) {
    params.set('noi_dung', String(opts.refCode));
  }
  if (opts.accountHolder && !bareQr) {
    params.set('chu_tai_khoan', String(opts.accountHolder));
  }
  return `https://qr.npay.vn/${bareQr ? 'qrpay' : 'qrcard'}?${params.toString()}`;
}

function safeEqual(provided, expected) {
  const a = Buffer.from(String(provided));
  const b = Buffer.from(String(expected));
  if (a.length !== b.length) return false;
  try {
    return crypto.timingSafeEqual(a, b);
  } catch {
    return false;
  }
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
  return safeEqual(match[1].trim(), expectedToken);
}

/**
 * Verify `X-Npay-Signature` = hex HMAC-SHA256(raw body, webhook secret).
 * `X-Npay-Timestamp`, when sent, must be within `maxSkewSec` of now.
 *
 * @param {Buffer|string} rawBody
 * @param {string|undefined} signature
 * @param {string|undefined} secret
 * @param {string|undefined} timestamp
 * @param {number} [maxSkewSec=300]
 * @returns {boolean}
 */
function verifySignature(rawBody, signature, secret, timestamp, maxSkewSec = 300) {
  if (!signature || !secret) return false;
  const expected = crypto.createHmac('sha256', secret).update(rawBody).digest('hex');
  if (!safeEqual(String(signature).trim().toLowerCase(), expected)) return false;
  if (timestamp === undefined || timestamp === null || timestamp === '') return true;
  if (!/^\d+$/.test(String(timestamp))) return false;
  return Math.abs(Date.now() / 1000 - Number(timestamp)) <= maxSkewSec;
}

/**
 * Extract the npay reference code from a transaction payload, normalised to
 * `NPAY-<orderId>`. Banks often drop the hyphen, so `NPAY1001` / `NPAY 1001`
 * match too. Looks in `code`, then `content` / `transferContent` / `description`.
 */
function extractRefCode(payload) {
  if (!payload) return null;
  const fields = ['code', 'content', 'transferContent', 'description', 'remark', 'note'];
  for (const f of fields) {
    const v = payload[f];
    if (typeof v === 'string') {
      const m = /NPAY[-_ ]?([A-Za-z0-9]+)/i.exec(v);
      if (m) return `NPAY-${m[1].toUpperCase()}`;
    }
  }
  return null;
}

module.exports = {
  buildQrUrl,
  verifyWebhook,
  verifySignature,
  extractRefCode,
};
