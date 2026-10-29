-- Create fawz_points table
-- Stores a persisted snapshot of each client's Fawz Point balance.
-- Points are still calculated from saham_transaksi volume (volume / 10.000.000),
-- but this table keeps the latest earned/redeemed/available balance in Supabase
-- so it does not have to be recomputed elsewhere and can be queried directly.

CREATE TABLE IF NOT EXISTS fawz_points (
  client_id        TEXT PRIMARY KEY,
  client_name      TEXT,
  email            TEXT,                     -- dicocokkan dari customers.client_id
  total_volume     NUMERIC     DEFAULT 0,   -- total volume transaksi saham keseluruhan
  earned_points    INTEGER     DEFAULT 0,   -- floor(total_volume / 10.000.000)
  redeemed_points  INTEGER     DEFAULT 0,   -- total point yang sudah di-redeem
  available_points INTEGER     DEFAULT 0,   -- earned_points - redeemed_points (min 0)
  updated_at       TIMESTAMPTZ DEFAULT NOW()
);

-- Jika tabel sudah dibuat sebelumnya tanpa kolom email, tambahkan:
ALTER TABLE fawz_points ADD COLUMN IF NOT EXISTS email TEXT;

-- Indexes to support leaderboard ordering
CREATE INDEX IF NOT EXISTS idx_fawz_points_earned ON fawz_points(earned_points DESC);
CREATE INDEX IF NOT EXISTS idx_fawz_points_available ON fawz_points(available_points DESC);

-- RLS (optional) — mirror influencer_registrations approach
-- ALTER TABLE fawz_points ENABLE ROW LEVEL SECURITY;
-- CREATE POLICY "Allow anon read fawz_points"
--   ON fawz_points FOR SELECT TO anon USING (true);
-- CREATE POLICY "Allow anon upsert fawz_points"
--   ON fawz_points FOR INSERT TO anon WITH CHECK (true);
-- CREATE POLICY "Allow anon update fawz_points"
--   ON fawz_points FOR UPDATE TO anon USING (true);

-- ============================================================
-- ISI / REFRESH DATA fawz_points
-- Menghitung dari saham_transaksi + point_redemptions, email di-join
-- dari customers berdasarkan client_id. Aman dijalankan berulang.
-- ============================================================
INSERT INTO fawz_points (client_id, client_name, email, total_volume, earned_points, redeemed_points, available_points, updated_at)
SELECT
  t.client_id,
  MAX(t.client_name)                                   AS client_name,
  c.email                                              AS email,
  COALESCE(SUM(t.volume), 0)                           AS total_volume,
  FLOOR(COALESCE(SUM(t.volume), 0) / 10000000)::int    AS earned_points,
  COALESCE(r.redeemed_points, 0)                       AS redeemed_points,
  GREATEST(
    FLOOR(COALESCE(SUM(t.volume), 0) / 10000000)::int - COALESCE(r.redeemed_points, 0),
    0
  )                                                    AS available_points,
  NOW()                                                AS updated_at
FROM saham_transaksi t
LEFT JOIN (
  SELECT client_id, SUM(points_redeemed)::int AS redeemed_points
  FROM point_redemptions
  GROUP BY client_id
) r ON r.client_id = t.client_id
LEFT JOIN customers c ON c.client_id = t.client_id
WHERE t.client_id IS NOT NULL
GROUP BY t.client_id, c.email, r.redeemed_points
ON CONFLICT (client_id) DO UPDATE SET
  client_name      = EXCLUDED.client_name,
  email            = EXCLUDED.email,
  total_volume     = EXCLUDED.total_volume,
  earned_points    = EXCLUDED.earned_points,
  redeemed_points  = EXCLUDED.redeemed_points,
  available_points = EXCLUDED.available_points,
  updated_at       = EXCLUDED.updated_at;
