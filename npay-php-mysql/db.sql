-- NPay PHP + MySQL example schema
-- Tested on MySQL 5.7+ / MariaDB 10.3+

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- Bảng lưu giao dịch nhận về từ webhook NPay
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tb_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `gateway` varchar(100) NOT NULL,
  `transaction_date` timestamp NOT NULL,
  `account_number` varchar(100) DEFAULT NULL,
  `sub_account` varchar(250) DEFAULT NULL,
  `amount_in` decimal(20,2) NOT NULL DEFAULT 0.00,
  `amount_out` decimal(20,2) NOT NULL DEFAULT 0.00,
  `accumulated` decimal(20,2) NOT NULL DEFAULT 0.00,
  `code` varchar(250) DEFAULT NULL,
  `transaction_content` text DEFAULT NULL,
  `reference_number` varchar(255) DEFAULT NULL,
  `body` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_reference` (`reference_number`),
  KEY `idx_code` (`code`),
  KEY `idx_transaction_date` (`transaction_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Bảng đơn hàng chờ thanh toán
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tb_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(64) NOT NULL,
  `amount` decimal(20,2) NOT NULL,
  `status` enum('pending','paid','cancelled') NOT NULL DEFAULT 'pending',
  `paid_transaction_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `paid_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_code` (`code`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
