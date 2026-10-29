-- ============================================================
-- FAWZ PRO — Products & Product Orders Schema
-- Mengelola katalog produk yang bisa dibeli dengan uang, point,
-- atau kombinasi keduanya.
-- ============================================================

-- ── TABEL PRODUCTS ──────────────────────────────────────────
-- Katalog produk. Admin dapat CRUD produk di sini.
CREATE TABLE IF NOT EXISTS products (
  id               UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
  name             TEXT        NOT NULL,
  description      TEXT,
  category         TEXT        DEFAULT 'umum',      -- e.g. 'voucher', 'merchandise', 'layanan', 'umum'
  image_url        TEXT,                            -- URL gambar produk (opsional)
  price_idr        NUMERIC     DEFAULT 0,           -- Harga dalam rupiah (0 = gratis / hanya pakai point)
  price_points     INTEGER     DEFAULT 0,           -- Harga dalam Fawz Point (0 = tidak bisa pakai point)
  allow_mixed      BOOLEAN     DEFAULT false,       -- Boleh bayar kombinasi uang + point?
  stock            INTEGER     DEFAULT -1,          -- -1 = unlimited
  is_active        BOOLEAN     DEFAULT true,
  created_by       TEXT,
  created_at       TIMESTAMPTZ DEFAULT NOW(),
  updated_at       TIMESTAMPTZ DEFAULT NOW()
);

-- ── TABEL PRODUCT_ORDERS ────────────────────────────────────
-- Riwayat pembelian produk oleh nasabah.
CREATE TABLE IF NOT EXISTS product_orders (
  id               UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
  product_id       UUID        REFERENCES products(id) ON DELETE SET NULL,
  product_name     TEXT        NOT NULL,            -- snapshot nama produk saat order
  client_id        TEXT        NOT NULL,
  client_name      TEXT,
  payment_method   TEXT        NOT NULL             -- 'money' | 'points' | 'mixed'
                   CHECK (payment_method IN ('money','points','mixed')),
  paid_idr         NUMERIC     DEFAULT 0,           -- rupiah yang dibayarkan
  paid_points      INTEGER     DEFAULT 0,           -- point yang dikurangkan
  notes            TEXT,                            -- catatan tambahan
  status           TEXT        DEFAULT 'completed'  -- 'completed' | 'cancelled'
                   CHECK (status IN ('completed','cancelled')),
  processed_by     TEXT,                            -- username admin yang proses
  order_date       DATE        DEFAULT CURRENT_DATE,
  created_at       TIMESTAMPTZ DEFAULT NOW()
);

-- ── INDEXES ────────────────────────────────────────────────
CREATE INDEX IF NOT EXISTS idx_products_active      ON products(is_active);
CREATE INDEX IF NOT EXISTS idx_products_category    ON products(category);
CREATE INDEX IF NOT EXISTS idx_product_orders_client ON product_orders(client_id);
CREATE INDEX IF NOT EXISTS idx_product_orders_date   ON product_orders(order_date DESC);
CREATE INDEX IF NOT EXISTS idx_product_orders_product ON product_orders(product_id);

-- ── RLS (enable jika perlu) ─────────────────────────────────
-- ALTER TABLE products ENABLE ROW LEVEL SECURITY;
-- CREATE POLICY "anon_read_products"    ON products        FOR SELECT TO anon USING (true);
-- CREATE POLICY "anon_insert_products"  ON products        FOR INSERT TO anon WITH CHECK (true);
-- CREATE POLICY "anon_update_products"  ON products        FOR UPDATE TO anon USING (true);
-- CREATE POLICY "anon_delete_products"  ON products        FOR DELETE TO anon USING (true);

-- ALTER TABLE product_orders ENABLE ROW LEVEL SECURITY;
-- CREATE POLICY "anon_read_orders"      ON product_orders  FOR SELECT TO anon USING (true);
-- CREATE POLICY "anon_insert_orders"    ON product_orders  FOR INSERT TO anon WITH CHECK (true);
-- CREATE POLICY "anon_update_orders"    ON product_orders  FOR UPDATE TO anon USING (true);

-- ── CONTOH DATA AWAL (opsional) ─────────────────────────────
-- INSERT INTO products (name, description, category, price_idr, price_points, allow_mixed, created_by) VALUES
--   ('Voucher Makan Rp 100.000',   'Voucher makan di restoran pilihan', 'voucher',       100000, 0,   false, 'admin'),
--   ('Kaos Fawz Pro',              'Kaos eksklusif berlogo Fawz Pro',   'merchandise',   150000, 50,  true,  'admin'),
--   ('Konsultasi Saham 1 Jam',     'Sesi konsultasi pribadi 1 jam',     'layanan',       0,      100, false, 'admin'),
--   ('Akses Premium 1 Bulan',      'Fitur premium selama 30 hari',      'layanan',       200000, 80,  true,  'admin');
