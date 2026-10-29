-- =============================================================
-- schema.sql — Sales Petik Profit
-- Database: sales_petikprofit_id
-- Jalankan: mysql -u sales_petikprofit_id -p sales_petikprofit_id < schema.sql
-- =============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- =============================================================
-- 1. ACCOUNTS (user login)
-- =============================================================
CREATE TABLE IF NOT EXISTS `accounts` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `username`   VARCHAR(100) NOT NULL UNIQUE,
  `password`   VARCHAR(255) NOT NULL COMMENT 'bcrypt hash',
  `name`       VARCHAR(150) NOT NULL,
  `role`       ENUM('admin','head_account','head_sales','sales','treasury') NOT NULL DEFAULT 'sales',
  `photo_url`  VARCHAR(500) NULL,
  `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_role (`role`),
  INDEX idx_active (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 2. CUSTOMERS
-- =============================================================
CREATE TABLE IF NOT EXISTS `customers` (
  `id`                        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `fawz_id`                   VARCHAR(20)  NULL,
  `client_id`                 VARCHAR(50)  NULL UNIQUE,
  `client_name`               VARCHAR(200) NOT NULL,
  `ktp_number`                VARCHAR(50)  NULL,
  `birth_date`                DATE         NULL,
  `npwp`                      VARCHAR(50)  NULL,
  `email`                     VARCHAR(255) NULL,
  `phone`                     VARCHAR(100) NULL,
  `occupation`                VARCHAR(100) NULL,
  `company_name`              VARCHAR(200) NULL,
  `nature_of_business`        VARCHAR(200) NULL,
  `position`                  VARCHAR(100) NULL,
  `address`                   TEXT         NULL,
  `ksei_single_id`            VARCHAR(50)  NULL,
  `ksei_sub_account_no`       VARCHAR(50)  NULL,
  `kpei_sub_account_no`       VARCHAR(50)  NULL,
  `stp_ksei_sub_account_no`   VARCHAR(50)  NULL,
  `stp_kpei_sub_account_no`   VARCHAR(50)  NULL,
  `sales_person_id`           INT UNSIGNED NULL,
  `sales_person_name`         VARCHAR(150) NULL,
  `referral_agents`           VARCHAR(100) NULL,
  `office_id`                 VARCHAR(50)  NULL,
  `office_name`               VARCHAR(150) NULL,
  `department_id`             VARCHAR(50)  NULL,
  `client_status`             ENUM('Aktif','Open','Suspended','Closed') NOT NULL DEFAULT 'Open',
  `client_status_description` TEXT         NULL,
  `created_date`              DATE         NULL,
  `active_date`               DATE         NULL,
  `closed_date`               DATE         NULL,
  -- Data sekuritas & rekening (1 customer = 1 sekuritas)
  `sekuritas_name`            VARCHAR(100) NULL DEFAULT 'Maybank',
  `rdn_bank_name`             VARCHAR(100) NULL,
  `rdn_account_no`            VARCHAR(50)  NULL,
  `sec_client_id`             VARCHAR(50)  NULL,
  `sid`                       VARCHAR(50)  NULL,
  `personal_bank_name`        VARCHAR(100) NULL,
  `personal_account_no`       VARCHAR(50)  NULL,
  `personal_account_name`     VARCHAR(150) NULL,
  `created_at`                DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`                DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_client_status (`client_status`),
  INDEX idx_sales_person_id (`sales_person_id`),
  INDEX idx_client_name (`client_name`),
  INDEX idx_birth_date (`birth_date`),
  INDEX idx_fawz_id (`fawz_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 3. SALES
-- =============================================================
CREATE TABLE IF NOT EXISTS `sales` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`          VARCHAR(150) NOT NULL,
  `kode`          VARCHAR(50)  NULL UNIQUE,
  `email`         VARCHAR(150) NULL,
  `phone`         VARCHAR(30)  NULL,
  `office`        VARCHAR(150) NULL,
  `branch`        VARCHAR(150) NULL,
  `account_id`    INT UNSIGNED NULL,
  `referral_code` VARCHAR(50)  NULL UNIQUE,
  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_name (`name`),
  INDEX idx_referral_code (`referral_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 5. SAHAM TRANSAKSI
-- =============================================================
CREATE TABLE IF NOT EXISTS `saham_transaksi` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tanggal`     DATE         NOT NULL,
  `client_id`   VARCHAR(50)  NULL,
  `client_name` VARCHAR(200) NULL,
  `sales_name`  VARCHAR(150) NULL,
  `kode_saham`  VARCHAR(20)  NULL,
  `jenis`       ENUM('Buy','Sell') NULL,
  `volume`      BIGINT       NOT NULL DEFAULT 0,
  `harga`       DECIMAL(15,2) NOT NULL DEFAULT 0,
  `nilai`       DECIMAL(20,2) NOT NULL DEFAULT 0,
  `fee`         DECIMAL(20,2) NOT NULL DEFAULT 0,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_tanggal (`tanggal`),
  INDEX idx_client_id (`client_id`),
  INDEX idx_sales_name (`sales_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 6. BB ORDERS (obligasi bookbuilding)
-- =============================================================
CREATE TABLE IF NOT EXISTS `bb_orders` (
  `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `seri`            VARCHAR(50)  NULL,
  `category`        VARCHAR(50)  NULL,
  `customer_name`   VARCHAR(200) NULL,
  `client_id`       VARCHAR(50)  NULL,
  `sales_pic`       VARCHAR(150) NULL,
  `nominal`         DECIMAL(20,2) NOT NULL DEFAULT 0,
  `harga`           DECIMAL(10,4) NULL,
  `jenis`           ENUM('Beli','Jual') NULL DEFAULT 'Beli',
  `komisi`          DECIMAL(20,2) NULL,
  `commission_sales` DECIMAL(20,2) NULL,
  `status`          ENUM('pending','approved','rejected','verified') NOT NULL DEFAULT 'pending',
  `approved_by`     VARCHAR(150) NULL,
  `rejected_by`     VARCHAR(150) NULL,
  `notes`           TEXT         NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_status (`status`),
  INDEX idx_sales_pic (`sales_pic`),
  INDEX idx_created_at (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 7. PASAR SEKUNDER ORDERS
-- =============================================================
CREATE TABLE IF NOT EXISTS `pasar_sekunder_orders` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `seri`         VARCHAR(50)  NULL,
  `customer_name` VARCHAR(200) NULL,
  `client_id`    VARCHAR(50)  NULL,
  `sales_name`   VARCHAR(150) NULL,
  `nominal`      DECIMAL(20,2) NOT NULL DEFAULT 0,
  `harga`        DECIMAL(10,4) NULL,
  `jenis`        ENUM('Beli','Jual') NULL,
  `komisi`       DECIMAL(20,2) NULL,
  `status`       VARCHAR(50)  NULL DEFAULT 'pending',
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_sales_name (`sales_name`),
  INDEX idx_created_at (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 8. OBLIGASI PRODUCTS
-- =============================================================
CREATE TABLE IF NOT EXISTS `obligasi_products` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `kode`         VARCHAR(50)  NOT NULL UNIQUE,
  `nama`         VARCHAR(200) NOT NULL,
  `issuer`       VARCHAR(200) NULL,
  `kupon`        DECIMAL(8,4) NULL COMMENT 'persentase per tahun',
  `jatuh_tempo`  DATE         NULL,
  `rating`       VARCHAR(20)  NULL,
  `currency`     VARCHAR(10)  NOT NULL DEFAULT 'IDR',
  `nilai_nominal` DECIMAL(20,2) NULL,
  `is_active`    TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_is_active (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 9. OBLIGASI ORDERS
-- =============================================================
CREATE TABLE IF NOT EXISTS `obligasi_orders` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `product_id`  INT UNSIGNED NULL,
  `seri`        VARCHAR(50)  NULL,
  `client_id`   VARCHAR(50)  NULL,
  `client_name` VARCHAR(200) NULL,
  `sales_id`    INT UNSIGNED NULL,
  `sales_name`  VARCHAR(150) NULL,
  `nominal`     DECIMAL(20,2) NOT NULL DEFAULT 0,
  `harga`       DECIMAL(10,4) NULL,
  `status`      VARCHAR(50)  NULL DEFAULT 'pending',
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_sales_id (`sales_id`),
  INDEX idx_created_at (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 10. REKSADANA ORDERS / TRANSACTIONS
-- =============================================================
CREATE TABLE IF NOT EXISTS `reksadana_transactions` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `client_id`    VARCHAR(50)  NULL,
  `client_name`  VARCHAR(200) NULL,
  `sales_id`     INT UNSIGNED NULL,
  `sales_name`   VARCHAR(150) NULL,
  `produk`       VARCHAR(200) NULL,
  `jenis`        ENUM('Subscribe','Redeem','Switch') NULL,
  `nominal`      DECIMAL(20,2) NOT NULL DEFAULT 0,
  `status`       VARCHAR(50)  NULL DEFAULT 'pending',
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_sales_id (`sales_id`),
  INDEX idx_created_at (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 11. WARAN ORDERS
-- =============================================================
CREATE TABLE IF NOT EXISTS `waran_orders` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `client_id`    VARCHAR(50)  NULL,
  `client_name`  VARCHAR(200) NULL,
  `sales_id`     INT UNSIGNED NULL,
  `sales_name`   VARCHAR(150) NULL,
  `seri`         VARCHAR(50)  NULL,
  `nominal`      DECIMAL(20,2) NOT NULL DEFAULT 0,
  `status`       VARCHAR(50)  NULL DEFAULT 'pending',
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_sales_id (`sales_id`),
  INDEX idx_created_at (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 12. COMMISSIONS
-- =============================================================
CREATE TABLE IF NOT EXISTS `commissions` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `sales_id`   INT UNSIGNED NOT NULL,
  `sales_name` VARCHAR(150) NULL,
  `period`     VARCHAR(7)   NULL COMMENT 'YYYY-MM',
  `type`       VARCHAR(50)  NULL COMMENT 'saham/obligasi/reksadana/waran',
  `amount`     DECIMAL(20,2) NOT NULL DEFAULT 0,
  `status`     ENUM('unpaid','paid') NOT NULL DEFAULT 'unpaid',
  `paid_at`    DATETIME     NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_sales_id (`sales_id`),
  INDEX idx_period (`period`),
  INDEX idx_status (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 13. SALES COMMISSION STATUS
-- =============================================================
CREATE TABLE IF NOT EXISTS `sales_commission_status` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `sales_name` VARCHAR(150) NOT NULL,
  `period`     VARCHAR(7)   NOT NULL COMMENT 'YYYY-MM',
  `status`     ENUM('unpaid','paid') NOT NULL DEFAULT 'unpaid',
  `paid_at`    DATETIME NULL,
  `paid_by`    VARCHAR(150) NULL,
  `notes`      TEXT         NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_sales_period (`sales_name`, `period`),
  INDEX idx_period (`period`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 14. SALES COMMISSION SETTINGS
-- =============================================================
CREATE TABLE IF NOT EXISTS `sales_commission_settings` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `sales_name`  VARCHAR(150) NOT NULL,
  `period`      VARCHAR(7)   NOT NULL COMMENT 'YYYY-MM',
  `target_fee`  DECIMAL(20,2) NOT NULL DEFAULT 10000000,
  `rate_above`  DECIMAL(8,4) NOT NULL DEFAULT 0.12,
  `rate_below`  DECIMAL(8,4) NOT NULL DEFAULT 0.05,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_sales_period (`sales_name`, `period`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 15. DAILY ACTIVITY JOBS
-- =============================================================
CREATE TABLE IF NOT EXISTS `daily_activity_jobs` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`        VARCHAR(200) NOT NULL,
  `start_date`  DATE         NULL,
  `end_date`    DATE         NULL,
  `description` TEXT         NULL,
  `created_by`  VARCHAR(150) NULL,
  `account_id`  INT UNSIGNED NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_created_by (`created_by`),
  INDEX idx_start_date (`start_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 16. DAILY ACTIVITY ENTRIES
-- =============================================================
CREATE TABLE IF NOT EXISTS `daily_activity_entries` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `job_id`      INT UNSIGNED NOT NULL,
  `entry_date`  DATE         NOT NULL,
  `status`      ENUM('inprogress','pending','finished','cancelled','returned') NOT NULL DEFAULT 'inprogress',
  `notes`       TEXT         NULL,
  `created_by`  VARCHAR(150) NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_job_id (`job_id`),
  INDEX idx_entry_date (`entry_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 17. JOB TASKS
-- =============================================================
CREATE TABLE IF NOT EXISTS `job_tasks` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `title`         VARCHAR(300) NOT NULL,
  `description`   TEXT         NULL,
  `assigned_role` VARCHAR(50)  NULL,
  `assigned_to`   VARCHAR(150) NULL,
  `status`        ENUM('pending','in_progress','done') NOT NULL DEFAULT 'pending',
  `priority`      ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
  `due_date`      DATE         NULL,
  `created_by`    VARCHAR(150) NULL,
  `done_at`       DATETIME     NULL,
  `done_by`       VARCHAR(150) NULL,
  `notes`         TEXT         NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_status (`status`),
  INDEX idx_assigned_role (`assigned_role`),
  INDEX idx_assigned_to (`assigned_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 18. JOB TASK REPLIES
-- =============================================================
CREATE TABLE IF NOT EXISTS `job_task_replies` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `task_id`    INT UNSIGNED NOT NULL,
  `message`    TEXT         NOT NULL,
  `created_by` VARCHAR(150) NOT NULL,
  `role`       VARCHAR(50)  NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_task_id (`task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 19. FOLLOW UP CUSTOMERS
-- =============================================================
CREATE TABLE IF NOT EXISTS `follow_up_customers` (
  `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `email`            VARCHAR(150) NULL,
  `nama`             VARCHAR(200) NULL,
  `client_id`        VARCHAR(50)  NULL,
  `rdn`              VARCHAR(50)  NULL,
  `amount`           DECIMAL(20,2) NULL,
  `porto`            DECIMAL(20,2) NULL,
  `total_nilai_porto` DECIMAL(20,2) NULL,
  `phone`            VARCHAR(30)  NULL,
  `alamat`           TEXT         NULL,
  `status`           VARCHAR(50)  NULL,
  `sales_name`       VARCHAR(150) NULL,
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_client_id (`client_id`),
  INDEX idx_sales_name (`sales_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 20. REFERRAL AGENTS
-- =============================================================
CREATE TABLE IF NOT EXISTS `referral_agents` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `agent_code`     VARCHAR(50)  NOT NULL UNIQUE,
  `client_name`    VARCHAR(200) NULL,
  `client_id`      VARCHAR(50)  NULL,
  `email`          VARCHAR(150) NULL,
  `phone`          VARCHAR(30)  NULL,
  `fee_status`     VARCHAR(50)  NULL,
  `fee_below_pct`  DECIMAL(8,4) NULL,
  `fee_target_pct` DECIMAL(8,4) NULL,
  `target_fee`     DECIMAL(20,2) NULL,
  `is_active`      TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_agent_code (`agent_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 21. REFERRAL REGISTRATIONS
-- =============================================================
CREATE TABLE IF NOT EXISTS `referral_registrations` (
  `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nama`             VARCHAR(200) NOT NULL,
  `nik`              VARCHAR(30)  NULL,
  `email`            VARCHAR(150) NULL,
  `phone`            VARCHAR(30)  NULL,
  `no_rdn`           VARCHAR(50)  NULL,
  `sekuritas`        VARCHAR(100) NULL,
  `client_code`      VARCHAR(50)  NULL,
  `status`           ENUM('sudah_terdaftar','belum_terdaftar') NULL,
  `referral_code`    VARCHAR(50)  NULL,
  `influencer_code`  VARCHAR(50)  NULL,
  `confirmed_status` VARCHAR(50)  NULL DEFAULT 'pending',
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_referral_code (`referral_code`),
  INDEX idx_confirmed_status (`confirmed_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 22. REGISTER LANDING
-- =============================================================
CREATE TABLE IF NOT EXISTS `register_landing` (
  `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nama`             VARCHAR(200) NOT NULL,
  `email`            VARCHAR(150) NULL,
  `phone`            VARCHAR(30)  NULL,
  `referral_code`    VARCHAR(50)  NULL,
  `confirmed_status` VARCHAR(50)  NULL DEFAULT 'pending',
  `notes`            TEXT         NULL,
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_referral_code (`referral_code`),
  INDEX idx_confirmed_status (`confirmed_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 23. INFLUENCERS
-- =============================================================
CREATE TABLE IF NOT EXISTS `influencers` (
  `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `influencer_code`  VARCHAR(50)  NOT NULL UNIQUE,
  `name`             VARCHAR(200) NOT NULL,
  `client_id`        VARCHAR(50)  NULL,
  `email`            VARCHAR(150) NULL,
  `phone`            VARCHAR(30)  NULL,
  `platform`         VARCHAR(50)  NULL,
  `followers`        INT          NULL,
  `status`           VARCHAR(50)  NULL DEFAULT 'active',
  `notes`            TEXT         NULL,
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_influencer_code (`influencer_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 24. INFLUENCER REGISTRATIONS
-- =============================================================
CREATE TABLE IF NOT EXISTS `influencer_registrations` (
  `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `influencer_code`  VARCHAR(50)  NULL,
  `name`             VARCHAR(200) NOT NULL,
  `email`            VARCHAR(150) NULL,
  `phone`            VARCHAR(30)  NULL,
  `platform`         VARCHAR(50)  NULL,
  `followers`        INT          NULL,
  `confirmed_status` VARCHAR(50)  NULL DEFAULT 'pending',
  `notes`            TEXT         NULL,
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_confirmed_status (`confirmed_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 25. CUSTOMER PORTFOLIO
-- =============================================================
CREATE TABLE IF NOT EXISTS `customer_portfolio` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `customer_id`    INT UNSIGNED NOT NULL,
  `client_id`      VARCHAR(50)  NULL,
  `kode_saham`     VARCHAR(20)  NOT NULL,
  `jenis`          ENUM('saham','obligasi','reksadana','lainnya') NOT NULL DEFAULT 'saham',
  `qty_lot`        DECIMAL(20,4) NOT NULL DEFAULT 0,
  `buy_price`      DECIMAL(15,4) NOT NULL DEFAULT 0,
  `current_price`  DECIMAL(15,4) NULL,
  `cash_balance`   DECIMAL(20,2) NULL,
  `buy_fee`        DECIMAL(8,6)  NULL,
  `sell_fee`       DECIMAL(8,6)  NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_customer_id (`customer_id`),
  INDEX idx_client_id (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 26. PORTFOLIO TRANSACTIONS
-- =============================================================
CREATE TABLE IF NOT EXISTS `portfolio_transactions` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `portfolio_id` INT UNSIGNED NULL,
  `customer_id`  INT UNSIGNED NOT NULL,
  `client_id`    VARCHAR(50)  NULL,
  `kode_saham`   VARCHAR(20)  NULL,
  `jenis`        ENUM('Buy','Sell','Lainnya') NOT NULL,
  `qty_lot`      DECIMAL(20,4) NULL,
  `price`        DECIMAL(15,4) NULL,
  `nilai`        DECIMAL(20,2) NULL,
  `fee`          DECIMAL(20,2) NULL,
  `notes`        TEXT         NULL,
  `tanggal`      DATE         NOT NULL,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_customer_id (`customer_id`),
  INDEX idx_tanggal (`tanggal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 27. FAWZ POINTS
-- =============================================================
CREATE TABLE IF NOT EXISTS `fawz_points` (
  `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `client_id`        VARCHAR(50)  NOT NULL UNIQUE,
  `client_name`      VARCHAR(200) NULL,
  `email`            VARCHAR(150) NULL,
  `total_volume`     BIGINT       NOT NULL DEFAULT 0,
  `earned_points`    INT          NOT NULL DEFAULT 0,
  `redeemed_points`  INT          NOT NULL DEFAULT 0,
  `available_points` INT          NOT NULL DEFAULT 0,
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_client_id (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 28. POINT REDEMPTIONS
-- =============================================================
CREATE TABLE IF NOT EXISTS `point_redemptions` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `client_id`   VARCHAR(50)  NOT NULL,
  `client_name` VARCHAR(200) NULL,
  `points`      INT          NOT NULL,
  `reward`      VARCHAR(200) NULL,
  `status`      VARCHAR(50)  NULL DEFAULT 'pending',
  `processed_by` VARCHAR(150) NULL,
  `notes`       TEXT         NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_client_id (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 29. TRANSACTIONS (generik)
-- =============================================================
CREATE TABLE IF NOT EXISTS `transactions` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `type`        VARCHAR(50)  NULL,
  `client_id`   VARCHAR(50)  NULL,
  `client_name` VARCHAR(200) NULL,
  `sales_name`  VARCHAR(150) NULL,
  `amount`      DECIMAL(20,2) NULL,
  `notes`       TEXT         NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_client_id (`client_id`),
  INDEX idx_type (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 30. BRAND LOGOS
-- =============================================================
CREATE TABLE IF NOT EXISTS `brand_logos` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(150) NOT NULL,
  `url`        VARCHAR(500) NOT NULL,
  `type`       VARCHAR(50)  NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 31. SESSION TOKENS (untuk invalidasi token)
-- =============================================================
CREATE TABLE IF NOT EXISTS `session_tokens` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `account_id` INT UNSIGNED NOT NULL,
  `token_hash` VARCHAR(64)  NOT NULL,
  `expires_at` DATETIME     NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_token_hash (`token_hash`),
  INDEX idx_account_id (`account_id`),
  INDEX idx_expires_at (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================
-- 32. DEALER NASABAH (dealer-report: kelola nasabah)
-- =============================================================
CREATE TABLE IF NOT EXISTS `dealer_nasabah` (
  `id`         VARCHAR(50)  NOT NULL PRIMARY KEY,
  `nama`       VARCHAR(200) NOT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 33. DEALER TRANSAKSI (dealer-report: input order)
--     id = epoch ms dari client (Date.now()), bukan auto-increment
-- =============================================================
CREATE TABLE IF NOT EXISTS `dealer_transaksi` (
  `id`         BIGINT       NOT NULL PRIMARY KEY,
  `tgl`        DATE         NULL,
  `cid`        VARCHAR(50)  NULL,
  `nama`       VARCHAR(200) NULL,
  `kode`       VARCHAR(20)  NULL,
  `trx`        ENUM('BUY','SELL') NULL,
  `qty`        DECIMAL(20,4) NOT NULL DEFAULT 0,
  `harga`      DECIMAL(20,2) NOT NULL DEFAULT 0,
  `total`      DECIMAL(24,2) NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_tgl (`tgl`),
  INDEX idx_cid (`cid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 34. DEALER DIVIDEN (dealer-report: riwayat dividen)
--     id = epoch ms dari client (Date.now()), bukan auto-increment
-- =============================================================
CREATE TABLE IF NOT EXISTS `dealer_dividen` (
  `id`         BIGINT       NOT NULL PRIMARY KEY,
  `tgl`        DATE         NULL,
  `cid`        VARCHAR(50)  NULL,
  `nama`       VARCHAR(200) NULL,
  `stock`      VARCHAR(20)  NULL,
  `qty`        DECIMAL(20,4) NOT NULL DEFAULT 0,
  `div`        DECIMAL(20,4) NOT NULL DEFAULT 0,
  `amt`        DECIMAL(24,2) NOT NULL DEFAULT 0,
  `txt`        TEXT         NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_cid (`cid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 35. DEALER OUTSTANDING
--     id = client_id (string PK, diisi manual)
-- =============================================================
CREATE TABLE IF NOT EXISTS `dealer_outstanding` (
  `id`             VARCHAR(50)   NOT NULL PRIMARY KEY,
  `nama`           VARCHAR(200)  NOT NULL DEFAULT '',
  `limit_diajukan` BIGINT        NOT NULL DEFAULT 0,
  `asset`          DECIMAL(24,2) NOT NULL DEFAULT 0,
  `outstanding`    DECIMAL(24,2) NOT NULL DEFAULT 0,
  `tgl`            DATE          NULL,
  `status`         VARCHAR(20)   NOT NULL DEFAULT 'Kosong',
  `porto_list`     TEXT          NULL,
  `created_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
