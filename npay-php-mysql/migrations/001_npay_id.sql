-- NPay PHP+MySQL 1.1.0 — chống trùng theo `id` webhook thay vì referenceCode.
-- referenceCode có thể null (nhiều nguồn ngân hàng không gửi), nên khoá UNIQUE
-- cũ trên reference_number từ chối mọi giao dịch như vậy.
-- Chạy một lần trên DB đã import db.sql bản 1.0.x:
--   mysql -u <user> -p <db> < migrations/001_npay_id.sql

ALTER TABLE `tb_transactions`
  ADD COLUMN `npay_id` varchar(64) NULL COMMENT 'id giao dịch NPay (tx_…) — khoá chống trùng' AFTER `id`;

UPDATE `tb_transactions`
   SET `npay_id` = CONCAT('ref:', `reference_number`)
 WHERE `npay_id` IS NULL AND `reference_number` IS NOT NULL AND `reference_number` <> '';
UPDATE `tb_transactions`
   SET `npay_id` = CONCAT('legacy:', `id`)
 WHERE `npay_id` IS NULL;

ALTER TABLE `tb_transactions`
  MODIFY `npay_id` varchar(64) NOT NULL COMMENT 'id giao dịch NPay (tx_…) — khoá chống trùng',
  DROP INDEX `uq_reference`,
  ADD UNIQUE KEY `uq_npay_id` (`npay_id`),
  ADD KEY `idx_reference` (`reference_number`);
