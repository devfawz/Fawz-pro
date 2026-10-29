-- migration_widen_customer_fields.sql — Sales Petik Profit
-- Perbesar kolom yang terlalu pendek untuk data CSV broker.
-- Penyebab: import gagal "Data too long for column 'phone'" karena satu
-- customer bisa punya beberapa nomor telepon yang digabung.
--
-- Jalankan: mysql -h 127.0.0.1 -P 3306 -u sales_petikprofit_id -p sales_petikprofit_id < migration_widen_customer_fields.sql
-- =============================================================

ALTER TABLE `customers`
  MODIFY COLUMN `phone`      VARCHAR(100) NULL,
  MODIFY COLUMN `email`      VARCHAR(255) NULL,
  MODIFY COLUMN `ktp_number` VARCHAR(50)  NULL,
  MODIFY COLUMN `npwp`       VARCHAR(50)  NULL;
