'use strict';

const Database = require('better-sqlite3');
const path = require('path');

let dbInstance = null;

function getDb() {
  if (dbInstance) return dbInstance;
  const dbPath = process.env.DATABASE_PATH || path.join(process.cwd(), 'data.sqlite');
  dbInstance = new Database(dbPath);
  dbInstance.pragma('journal_mode = WAL');
  return dbInstance;
}

function init() {
  const db = getDb();
  db.exec(`
    CREATE TABLE IF NOT EXISTS shops (
      shop_domain TEXT PRIMARY KEY,
      access_token TEXT,
      account_number TEXT,
      bank_bin TEXT,
      account_holder TEXT,
      api_token TEXT,
      qr_template TEXT DEFAULT 'compact',
      created_at INTEGER DEFAULT (strftime('%s','now'))
    );

    CREATE TABLE IF NOT EXISTS orders (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      shop_domain TEXT NOT NULL,
      shopify_order_id TEXT NOT NULL,
      ref_code TEXT NOT NULL UNIQUE,
      amount REAL NOT NULL,
      status TEXT NOT NULL DEFAULT 'pending',
      paid_at INTEGER,
      created_at INTEGER DEFAULT (strftime('%s','now'))
    );

    CREATE INDEX IF NOT EXISTS idx_orders_shop ON orders(shop_domain);
    CREATE INDEX IF NOT EXISTS idx_orders_ref ON orders(ref_code);
    CREATE INDEX IF NOT EXISTS idx_orders_shopify ON orders(shopify_order_id);
  `);
}

function upsertShop(shopDomain, accessToken) {
  const db = getDb();
  db.prepare(`
    INSERT INTO shops (shop_domain, access_token)
    VALUES (?, ?)
    ON CONFLICT(shop_domain) DO UPDATE SET access_token = excluded.access_token
  `).run(shopDomain, accessToken);
}

function updateShopSettings(shopDomain, settings) {
  const db = getDb();
  const fields = ['account_number', 'bank_bin', 'account_holder', 'api_token', 'qr_template'];
  const sets = [];
  const values = [];
  for (const f of fields) {
    if (settings[f] !== undefined) {
      sets.push(`${f} = ?`);
      values.push(settings[f]);
    }
  }
  if (!sets.length) return;
  values.push(shopDomain);
  db.prepare(`UPDATE shops SET ${sets.join(', ')} WHERE shop_domain = ?`).run(...values);
}

function getShop(shopDomain) {
  return getDb().prepare('SELECT * FROM shops WHERE shop_domain = ?').get(shopDomain);
}

function getShopByApiToken(token) {
  return getDb().prepare('SELECT * FROM shops WHERE api_token = ?').get(token);
}

function listShops() {
  return getDb().prepare('SELECT shop_domain, account_number, bank_bin, account_holder, qr_template, created_at FROM shops').all();
}

function createOrder({ shopDomain, shopifyOrderId, refCode, amount }) {
  const db = getDb();
  const info = db.prepare(`
    INSERT INTO orders (shop_domain, shopify_order_id, ref_code, amount)
    VALUES (?, ?, ?, ?)
  `).run(shopDomain, String(shopifyOrderId), refCode, amount);
  return info.lastInsertRowid;
}

function getOrderByRef(refCode) {
  return getDb().prepare('SELECT * FROM orders WHERE ref_code = ?').get(refCode);
}

function getOrderById(id) {
  return getDb().prepare('SELECT * FROM orders WHERE id = ?').get(id);
}

function getOrderByShopifyId(shopDomain, shopifyOrderId) {
  return getDb().prepare('SELECT * FROM orders WHERE shop_domain = ? AND shopify_order_id = ?').get(shopDomain, String(shopifyOrderId));
}

function markPaid(refCode) {
  const db = getDb();
  db.prepare(`UPDATE orders SET status = 'paid', paid_at = strftime('%s','now') WHERE ref_code = ?`).run(refCode);
}

function listOrdersByShop(shopDomain, limit = 50) {
  return getDb().prepare('SELECT * FROM orders WHERE shop_domain = ? ORDER BY id DESC LIMIT ?').all(shopDomain, limit);
}

module.exports = {
  init,
  getDb,
  upsertShop,
  updateShopSettings,
  getShop,
  getShopByApiToken,
  listShops,
  createOrder,
  getOrderByRef,
  getOrderById,
  getOrderByShopifyId,
  markPaid,
  listOrdersByShop,
};
