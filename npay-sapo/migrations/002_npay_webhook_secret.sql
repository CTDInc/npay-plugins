-- NPay Sapo 1.1.0: per-store NPay webhook secret (verifies X-Npay-Signature).
ALTER TABLE stores
    ADD COLUMN npay_webhook_secret VARCHAR(190) DEFAULT NULL COMMENT 'NPay Webhook.secret (HMAC X-Npay-Signature)' AFTER webhook_secret;
