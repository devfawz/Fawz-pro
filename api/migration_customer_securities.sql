-- migration_customer_securities.sql — Sales Petik Profit
-- Gabungkan data sekuritas langsung ke tabel customers (1 customer = 1 sekuritas),
-- lalu buang tabel customer_securities terpisah.
--
-- Jalankan: mysql -u sales_petikprofit_id -p sales_petikprofit_id < migration_customer_securities.sql
-- =============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- Tambah kolom securities ke tabel customers (aman diulang: cek dulu kalau perlu)
ALTER TABLE `customers`
  ADD COLUMN `sekuritas_name`        VARCHAR(100) NULL DEFAULT 'Maybank' AFTER `closed_date`,
  ADD COLUMN `rdn_bank_name`         VARCHAR(100) NULL AFTER `sekuritas_name`,
  ADD COLUMN `rdn_account_no`        VARCHAR(50)  NULL AFTER `rdn_bank_name`,
  ADD COLUMN `sec_client_id`         VARCHAR(50)  NULL AFTER `rdn_account_no`,
  ADD COLUMN `sid`                   VARCHAR(50)  NULL AFTER `sec_client_id`,
  ADD COLUMN `personal_bank_name`    VARCHAR(100) NULL AFTER `sid`,
  ADD COLUMN `personal_account_no`   VARCHAR(50)  NULL AFTER `personal_bank_name`,
  ADD COLUMN `personal_account_name` VARCHAR(150) NULL AFTER `personal_account_no`;

-- Buang tabel securities terpisah (tidak dipakai lagi)
DROP TABLE IF EXISTS `customer_securities`;

SET FOREIGN_KEY_CHECKS = 1;
