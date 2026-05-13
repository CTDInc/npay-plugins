'use strict';

const API_VERSION = process.env.SHOPIFY_API_VERSION || '2024-10';

function apiUrl(shopDomain, path) {
  return `https://${shopDomain}/admin/api/${API_VERSION}${path}`;
}

async function shopifyFetch(shopDomain, accessToken, path, options = {}) {
  const url = apiUrl(shopDomain, path);
  const headers = Object.assign(
    {
      'X-Shopify-Access-Token': accessToken,
      'Content-Type': 'application/json',
      Accept: 'application/json',
    },
    options.headers || {}
  );
  const res = await fetch(url, {
    method: options.method || 'GET',
    headers,
    body: options.body ? JSON.stringify(options.body) : undefined,
  });
  const text = await res.text();
  let json = null;
  try {
    json = text ? JSON.parse(text) : null;
  } catch {
    /* not JSON */
  }
  if (!res.ok) {
    const err = new Error(`Shopify API ${res.status} ${res.statusText}: ${text}`);
    err.status = res.status;
    err.body = json;
    throw err;
  }
  return json;
}

async function getOrder(shopDomain, accessToken, orderId) {
  return shopifyFetch(shopDomain, accessToken, `/orders/${orderId}.json`);
}

/**
 * Mark an order as paid by creating a capture transaction.
 */
async function createTransaction(shopDomain, accessToken, orderId, { amount, currency, gateway = 'NPay' }) {
  const body = {
    transaction: {
      kind: 'capture',
      status: 'success',
      gateway,
    },
  };
  if (amount !== undefined) body.transaction.amount = String(amount);
  if (currency) body.transaction.currency = currency;
  return shopifyFetch(shopDomain, accessToken, `/orders/${orderId}/transactions.json`, {
    method: 'POST',
    body,
  });
}

async function subscribeWebhook(shopDomain, accessToken, { topic, address, format = 'json' }) {
  return shopifyFetch(shopDomain, accessToken, `/webhooks.json`, {
    method: 'POST',
    body: {
      webhook: { topic, address, format },
    },
  });
}

async function listWebhooks(shopDomain, accessToken) {
  return shopifyFetch(shopDomain, accessToken, `/webhooks.json`);
}

module.exports = {
  API_VERSION,
  apiUrl,
  shopifyFetch,
  getOrder,
  createTransaction,
  subscribeWebhook,
  listWebhooks,
};
