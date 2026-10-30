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
   Inject SDK lalu init singleton window._supabase.
   config.js di-load sebelum SDK, jadi kita inject SDK di sini. */
(function() {
  if (window._supabase) return;

  const cfg = window.__FAWZ_CONFIG__;

  function _init() {
    if (window._supabase) return;
    if (window.__FAWZ_SB_INIT__) return;
    window.__FAWZ_SB_INIT__ = true;
    try {
      window._supabase = window.supabase.createClient(cfg.supabaseUrl, cfg.supabaseKey);
      console.log('[Fawz] Supabase client ready →', cfg.supabaseUrl);
    } catch(e) {
      window.__FAWZ_SB_INIT__ = false;
      console.warn('[Fawz] Supabase init gagal:', e);
    }
  }

  // Kalau SDK sudah ada (halaman load SDK sebelum config.js)
  if (window.supabase && window.supabase.createClient) {
    _init();
    return;
  }

  // Inject SDK lalu init
  var s = document.createElement('script');
  s.src = 'https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2/dist/umd/supabase.min.js';
  s.onload = _init;
  s.onerror = function() { console.warn('[Fawz] Gagal load Supabase SDK'); };
  var head = document.head || document.getElementsByTagName('head')[0];
  head.insertBefore(s, head.firstChild);
})();
