-- =============================================================
-- SYNC fawz_points  <-  saham_transaksi + point_redemptions
-- Jalankan di Supabase SQL Editor (akses penuh, bypass RLS).
--
-- Tujuan: menyamakan isi tabel fawz_points dengan SEMUA nasabah
-- yang punya transaksi di saham_transaksi (234 client), sehingga
-- angka di tabel = angka yang dihitung halaman fawz-point.html.
--
-- Aman dijalankan berulang (idempotent).
-- =============================================================

-- (1) CEK SEBELUM ---------------------------------------------
SELECT 'sebelum: fawz_points'   AS info, COUNT(*) AS n FROM fawz_points;
SELECT 'target: client unik'    AS info, COUNT(DISTINCT client_id) AS n
FROM saham_transaksi WHERE client_id IS NOT NULL;


-- (2) SYNC / UPSERT -------------------------------------------
INSERT INTO fawz_points (
  client_id, client_name, email,
  total_volume, earned_points, redeemed_points, available_points, updated_at
)
SELECT
  t.client_id,
  MAX(t.client_name)                                            AS client_name,
  c.email                                                       AS email,
  COALESCE(SUM(t.volume), 0)                                    AS total_volume,
  FLOOR(COALESCE(SUM(t.volume), 0) / 10000000)::int             AS earned_points,
  COALESCE(r.redeemed_points, 0)                                AS redeemed_points,
  GREATEST(
    FLOOR(COALESCE(SUM(t.volume), 0) / 10000000)::int
      - COALESCE(r.redeemed_points, 0),
    0
  )                                                             AS available_points,
  NOW()                                                         AS updated_at
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


-- (3) CEK SESUDAH ---------------------------------------------
-- fawz_points sekarang harus sama dengan jumlah client unik (mis. 234)
SELECT 'sesudah: fawz_points' AS info, COUNT(*) AS n FROM fawz_points;

-- Pastikan tidak ada lagi client di saham_transaksi yang belum masuk fawz_points
SELECT DISTINCT t.client_id AS client_belum_tersync
FROM saham_transaksi t
LEFT JOIN fawz_points f ON f.client_id = t.client_id
WHERE t.client_id IS NOT NULL
  AND f.client_id IS NULL
ORDER BY t.client_id;
-- (query di atas harus 0 baris kalau sync berhasil)
