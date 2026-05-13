'use strict';

require('dotenv').config();

const express = require('express');
const cookieParser = require('cookie-parser');
const path = require('path');

const db = require('./src/lib/db');
const authRoutes = require('./src/routes/auth');
const webhookRoutes = require('./src/routes/webhooks');
const paymentRoutes = require('./src/routes/payment');
const adminRoutes = require('./src/routes/admin');

const app = express();

// Initialize DB schema
db.init();

// Webhooks must use raw body for HMAC verification — register BEFORE json parsers
app.use('/webhooks', webhookRoutes);

// Standard middleware for the rest
app.use(express.json());
app.use(express.urlencoded({ extended: true }));
app.use(cookieParser());
app.use('/assets', express.static(path.join(__dirname, 'public/assets')));

app.get('/', (req, res) => {
  res.send('NPay Shopify plugin is running. Install via /auth?shop=<your-store>.myshopify.com');
});

app.use('/auth', authRoutes);
app.use('/payment', paymentRoutes);
app.use('/status', paymentRoutes.statusRouter);
app.use('/admin', adminRoutes);

app.use((err, req, res, _next) => {
  console.error('[npay-shopify]', err);
  res.status(500).json({ error: err.message || 'Internal error' });
});

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
  console.log(`[npay-shopify] Listening on :${PORT}`);
});
