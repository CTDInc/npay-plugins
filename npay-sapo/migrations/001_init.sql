-- NPay Sapo - initial schema (MySQL 5.7+/8).

CREATE TABLE IF NOT EXISTS stores (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sapo_store      VARCHAR(190) NOT NULL COMMENT 'mystore.mysapo.net',
    access_token    VARCHAR(255) NOT NULL,
    scope           VARCHAR(255) DEFAULT NULL,
    api_key         VARCHAR(190) DEFAULT NULL COMMENT 'NPay api token for this store',
    account_number  VARCHAR(64)  DEFAULT NULL,
    account_name    VARCHAR(190) DEFAULT NULL,
    bank_code       VARCHAR(32)  DEFAULT NULL,
    webhook_secret  VARCHAR(190) DEFAULT NULL COMMENT 'Sapo HMAC shared secret',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_sapo_store (sapo_store)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS orders (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sapo_order_id   VARCHAR(64)  NOT NULL,
    sapo_store_id   BIGINT UNSIGNED NOT NULL,
    ref_code        VARCHAR(64)  NOT NULL COMMENT 'NPAY-{order_id}',
    amount          DECIMAL(15,2) NOT NULL DEFAULT 0,
    currency        VARCHAR(8)   NOT NULL DEFAULT 'VND',
    status          ENUM('pending','paid','expired','cancelled') NOT NULL DEFAULT 'pending',
    payload         JSON DEFAULT NULL,
    paid_at         DATETIME DEFAULT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_ref_code (ref_code),
    KEY idx_store_order (sapo_store_id, sapo_order_id),
    CONSTRAINT fk_orders_store FOREIGN KEY (sapo_store_id) REFERENCES stores(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS webhook_logs (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    source      ENUM('sapo','npay') NOT NULL,
    topic       VARCHAR(64) DEFAULT NULL,
    payload     JSON DEFAULT NULL,
    received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_source_time (source, received_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
