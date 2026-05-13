-- NPay Haravan initial schema (MySQL 5.7+/8.0+, utf8mb4)

CREATE TABLE IF NOT EXISTS haravan_shops (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    shop VARCHAR(255) NOT NULL UNIQUE,
    access_token VARCHAR(255) NOT NULL,
    scope VARCHAR(255) NULL,
    installed_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    shop VARCHAR(255) NOT NULL,
    order_id VARCHAR(64) NOT NULL,
    order_number VARCHAR(64) NULL,
    order_code VARCHAR(64) NOT NULL UNIQUE,
    amount DECIMAL(15,2) NOT NULL,
    currency VARCHAR(8) NOT NULL DEFAULT 'VND',
    customer_name VARCHAR(255) NULL,
    customer_email VARCHAR(255) NULL,
    customer_phone VARCHAR(64) NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'pending',
    npay_txn_id VARCHAR(128) NULL,
    paid_at DATETIME NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    INDEX idx_payments_order (shop, order_id),
    INDEX idx_payments_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
