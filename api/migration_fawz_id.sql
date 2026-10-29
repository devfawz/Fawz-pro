-- migration_fawz_id.sql — Sales Petik Profit
-- Tambah kolom fawz_id ke tabel customers.
-- Fawz ID format: F + 6 digit (contoh: F123456).
-- CATATAN: tidak unik — customer dengan base Client ID sama (mis. RW028Y & RW028N)
-- berbagi Fawz ID yang sama. Nilai di-generate oleh frontend (customer.html).
--
-- Jalankan: mysql -h 127.0.0.1 -P 3306 -u sales_petikprofit_id -p sales_petikprofit_id < migration_fawz_id.sql
-- =============================================================

ALTER TABLE `customers`
  ADD COLUMN `fawz_id` VARCHAR(20) NULL AFTER `id`,
  ADD INDEX `idx_fawz_id` (`fawz_id`);
