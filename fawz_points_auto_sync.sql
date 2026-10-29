-- =============================================================
-- AUTO-SYNC fawz_points  (Opsi A: Database Trigger, sisi server)
--
-- Setiap ada perubahan di saham_transaksi atau point_redemptions,
-- baris fawz_points untuk client terkait dihitung ulang otomatis.
-- Tidak bergantung halaman fawz-point.html dibuka atau tidak,
-- tidak bergantung RLS anon key.
--
-- Jalankan SEKALI di Supabase SQL Editor. Aman diulang.
-- =============================================================

-- Pastikan tabel + primary key sudah ada (lihat fawz_points_schema.sql)
CREATE TABLE IF NOT EXISTS fawz_points (
  client_id        TEXT PRIMARY KEY,
  client_name      TEXT,
  email            TEXT,
  total_volume     NUMERIC     DEFAULT 0,
  earned_points    INTEGER     DEFAULT 0,
  redeemed_points  INTEGER     DEFAULT 0,
  available_points INTEGER     DEFAULT 0,
  updated_at       TIMESTAMPTZ DEFAULT NOW()
);
ALTER TABLE fawz_points ADD COLUMN IF NOT EXISTS email TEXT;

-- Pastikan tabel point_redemptions ada. Kolom mengikuti pemakaian di
-- fawz-point.html (processRedemption / loadRedemptionHistory):
--   client_id, client_name, points_redeemed, redeemed_by, redemption_date
CREATE TABLE IF NOT EXISTS point_redemptions (
  id              BIGSERIAL PRIMARY KEY,
  client_id       TEXT        NOT NULL,
  client_name     TEXT,
  points_redeemed INTEGER     NOT NULL DEFAULT 0,
  redeemed_by     TEXT,
  redemption_date DATE        DEFAULT CURRENT_DATE,
  created_at      TIMESTAMPTZ DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_point_redemptions_client ON point_redemptions(client_id);
CREATE INDEX IF NOT EXISTS idx_point_redemptions_date   ON point_redemptions(redemption_date DESC);


-- -------------------------------------------------------------
-- (1) Fungsi: hitung ulang SATU client dari sumber kebenaran
--     (saham_transaksi + point_redemptions + customers.email)
-- -------------------------------------------------------------
CREATE OR REPLACE FUNCTION fawz_points_recalc(p_client_id TEXT)
RETURNS void
LANGUAGE plpgsql
SECURITY DEFINER          -- jalan sebagai owner, tak terhalang RLS
AS $$
DECLARE
  v_name       TEXT;
  v_email      TEXT;
  v_volume     NUMERIC := 0;
  v_earned     INT     := 0;
  v_redeemed   INT     := 0;
  v_available  INT     := 0;
  v_tx_count   INT     := 0;
BEGIN
  IF p_client_id IS NULL THEN
    RETURN;
  END IF;

  -- Agregasi volume + nama dari transaksi
  SELECT COALESCE(SUM(volume),0), MAX(client_name), COUNT(*)
    INTO v_volume, v_name, v_tx_count
  FROM saham_transaksi
  WHERE client_id = p_client_id;

  -- Kalau client sudah tidak punya transaksi sama sekali -> hapus barisnya
  IF v_tx_count = 0 THEN
    DELETE FROM fawz_points WHERE client_id = p_client_id;
    RETURN;
  END IF;

  -- Total point yang sudah di-redeem
  SELECT COALESCE(SUM(points_redeemed),0)::int
    INTO v_redeemed
  FROM point_redemptions
  WHERE client_id = p_client_id;

  -- Email dari customers (kalau ada)
  SELECT email INTO v_email
  FROM customers
  WHERE client_id = p_client_id
  LIMIT 1;

  v_earned    := FLOOR(v_volume / 10000000)::int;
  v_available := GREATEST(v_earned - v_redeemed, 0);

  INSERT INTO fawz_points (
    client_id, client_name, email,
    total_volume, earned_points, redeemed_points, available_points, updated_at
  )
  VALUES (
    p_client_id, v_name, v_email,
    v_volume, v_earned, v_redeemed, v_available, NOW()
  )
  ON CONFLICT (client_id) DO UPDATE SET
    client_name      = EXCLUDED.client_name,
    email            = EXCLUDED.email,
    total_volume     = EXCLUDED.total_volume,
    earned_points    = EXCLUDED.earned_points,
    redeemed_points  = EXCLUDED.redeemed_points,
    available_points = EXCLUDED.available_points,
    updated_at       = EXCLUDED.updated_at;
END;
$$;


-- -------------------------------------------------------------
-- (2) Fungsi trigger: tentukan client_id yang terpengaruh,
--     lalu panggil recalc. Menangani INSERT/UPDATE/DELETE dan
--     kasus client_id berpindah (UPDATE mengubah client_id).
-- -------------------------------------------------------------
CREATE OR REPLACE FUNCTION fawz_points_trigger_fn()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
  IF (TG_OP = 'DELETE') THEN
    PERFORM fawz_points_recalc(OLD.client_id);
    RETURN OLD;
  ELSIF (TG_OP = 'UPDATE') THEN
    PERFORM fawz_points_recalc(NEW.client_id);
    IF NEW.client_id IS DISTINCT FROM OLD.client_id THEN
      PERFORM fawz_points_recalc(OLD.client_id);
    END IF;
    RETURN NEW;
  ELSE  -- INSERT
    PERFORM fawz_points_recalc(NEW.client_id);
    RETURN NEW;
  END IF;
END;
$$;


-- -------------------------------------------------------------
-- (3) Pasang trigger di kedua tabel sumber
-- -------------------------------------------------------------
DROP TRIGGER IF EXISTS trg_fawz_points_saham ON saham_transaksi;
CREATE TRIGGER trg_fawz_points_saham
AFTER INSERT OR UPDATE OR DELETE ON saham_transaksi
FOR EACH ROW EXECUTE FUNCTION fawz_points_trigger_fn();

DROP TRIGGER IF EXISTS trg_fawz_points_redemption ON point_redemptions;
CREATE TRIGGER trg_fawz_points_redemption
AFTER INSERT OR UPDATE OR DELETE ON point_redemptions
FOR EACH ROW EXECUTE FUNCTION fawz_points_trigger_fn();


-- -------------------------------------------------------------
-- (4) Isi awal / backfill: samakan seluruh fawz_points sekarang
--     (menjalankan recalc untuk setiap client unik yang ada)
-- -------------------------------------------------------------
DO $$
DECLARE c TEXT;
BEGIN
  FOR c IN
    SELECT DISTINCT client_id FROM saham_transaksi WHERE client_id IS NOT NULL
  LOOP
    PERFORM fawz_points_recalc(c);
  END LOOP;
END $$;


-- -------------------------------------------------------------
-- (5) Verifikasi: jumlah harus sama dengan client unik transaksi
-- -------------------------------------------------------------
SELECT 'fawz_points'        AS tbl, COUNT(*) AS n FROM fawz_points
UNION ALL
SELECT 'client unik saham'  AS tbl, COUNT(DISTINCT client_id) AS n
FROM saham_transaksi WHERE client_id IS NOT NULL;
