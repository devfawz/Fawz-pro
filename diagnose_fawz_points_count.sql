-- =============================================================
-- DIAGNOSA: kenapa fawz-point.html menampilkan 230 nasabah
-- tetapi tabel fawz_points hanya berisi 224 baris.
--
-- Jalankan di Supabase SQL Editor (punya akses penuh, bypass RLS).
-- Setiap blok berdiri sendiri — jalankan satu per satu / sekaligus.
-- =============================================================

-- (0) Angka acuan --------------------------------------------------
-- Jumlah baris tersimpan di fawz_points (harusnya 224)
SELECT 'fawz_points rows' AS metric, COUNT(*) AS n FROM fawz_points;

-- Jumlah nasabah versi FRONTEND: JS memakai client_id APA ADANYA
-- (case-sensitive, tanpa trim). Angka ini harusnya = 230.
SELECT 'JS unique (as-is)' AS metric, COUNT(DISTINCT client_id) AS n
FROM saham_transaksi
WHERE client_id IS NOT NULL;

-- Jumlah nasabah versi TABEL: PRIMARY KEY client_id di Postgres
-- membandingkan teks apa adanya juga, TAPI upsert menimpa baris
-- dengan client_id yang identik. Selisih muncul kalau ada client_id
-- yang JS anggap beda tetapi sebenarnya sama setelah normalisasi,
-- ATAU baris yang hilang karena error saat upsert.
SELECT 'unique after TRIM+UPPER' AS metric,
       COUNT(DISTINCT upper(btrim(client_id))) AS n
FROM saham_transaksi
WHERE client_id IS NOT NULL;

-- Apakah ada client_id NULL / kosong? (JS mengubah null -> 'unknown'
-- dan menghitungnya sebagai 1 nasabah; SQL schema membuang NULL.)
SELECT 'null client_id' AS metric, COUNT(*) AS n
FROM saham_transaksi WHERE client_id IS NULL;
SELECT 'empty/blank client_id' AS metric, COUNT(*) AS n
FROM saham_transaksi WHERE btrim(coalesce(client_id,'')) = '';


-- (1) TERSANGKA UTAMA: client_id yang sama setelah TRIM+UPPER
--     tapi ditulis dengan variasi kapitalisasi / spasi berbeda.
--     Inilah yang membuat JS menghitung lebih banyak (230) daripada
--     jumlah nasabah unik sebenarnya.
SELECT upper(btrim(client_id))                    AS client_id_normal,
       COUNT(DISTINCT client_id)                  AS jumlah_variasi,
       array_agg(DISTINCT client_id)              AS variasi_penulisan
FROM saham_transaksi
WHERE client_id IS NOT NULL
GROUP BY upper(btrim(client_id))
HAVING COUNT(DISTINCT client_id) > 1
ORDER BY jumlah_variasi DESC, client_id_normal;


-- (2) Cek arah sebaliknya: client_id ada di saham_transaksi
--     tapi TIDAK punya baris di fawz_points (baris yang "hilang").
SELECT DISTINCT t.client_id
FROM saham_transaksi t
LEFT JOIN fawz_points f ON f.client_id = t.client_id
WHERE t.client_id IS NOT NULL
  AND f.client_id IS NULL
ORDER BY t.client_id;
