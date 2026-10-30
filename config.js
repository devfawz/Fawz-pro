/**
 * config.js — Fawz Pro
 * Konfigurasi Supabase. Tidak ada kredensial database selain anon key di sini.
 */
window.__FAWZ_CONFIG__ = {
  supabaseUrl: 'https://yhmrfluehibfapvtxcfi.supabase.co',
  supabaseKey: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InlobXJmbHVlaGliZmFwdnR4Y2ZpIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzkxNjEzODUsImV4cCI6MjA5NDczNzM4NX0.se_H4n_eLsf81d_1GH5sqsPCpX89MHewlPuuNkY6qcU',

  /* Petik Profit integration untuk sync membership */
  petikProfitUrl: 'https://pp.ikutin.id',
};

/* ── SUPABASE CLIENT ──
   Inisialisasi singleton window._supabase sekali saja. */
(function() {
  if (window._supabase) return;
  const cfg = window.__FAWZ_CONFIG__;
  if (window.supabase && window.supabase.createClient) {
    window._supabase = window.supabase.createClient(cfg.supabaseUrl, cfg.supabaseKey);
    console.log('[Fawz] Supabase client ready →', cfg.supabaseUrl);
  }
})();
