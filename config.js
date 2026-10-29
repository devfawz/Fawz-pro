/**
 * config.js — Sales Petik Profit
 * Konfigurasi API endpoint. Tidak ada kredensial database di sini.
 */
window.__FAWZ_CONFIG__ = {
  // URL API PHP — sesuaikan dengan domain production
  apiUrl: (function() {
    const host  = window.location.hostname;
    // Ikuti protokol halaman (http/https) agar tidak kena mixed-content block
    const proto = window.location.protocol === 'https:' ? 'https' : 'http';
    if (host === 'sales.petikprofit.id')         return 'https://sales.petikprofit.id/api';
    if (host === 'sales.petikprofit.local.test') return proto + '://sales.petikprofit.local.test/api';
    // localhost / development
    return proto + '://' + window.location.host + '/api';
  })(),

  /* Petik Profit integration untuk sync membership */
  petikProfitUrl: 'https://pp.ikutin.id',
};

/* ── API CLIENT ──
   Drop-in replacement untuk Supabase SDK.
   Semua halaman pakai window._api untuk query data. */
(function() {
  if (window._api) return;

  const BASE = window.__FAWZ_CONFIG__.apiUrl;

  function getToken() {
    try {
      const raw = sessionStorage.getItem('fawz_user') || localStorage.getItem('fawz_user_remember');
      if (!raw) return null;
      const u = JSON.parse(raw);
      return u.token || null;
    } catch(e) { return null; }
  }

  async function req(method, endpoint, body, params) {
    const url = new URL(BASE + '/' + endpoint);
    if (params) Object.entries(params).forEach(([k,v]) => v != null && url.searchParams.set(k, v));

    const opts = {
      method,
      headers: { 'Content-Type': 'application/json' },
    };
    const token = getToken();
    if (token) opts.headers['Authorization'] = 'Bearer ' + token;
    if (body && method !== 'GET') opts.body = JSON.stringify(body);

    const res  = await fetch(url.toString(), opts);
    const json = await res.json();
    if (!json.ok) throw new Error(json.error || 'API error ' + res.status);
    return json;
  }

  window._api = {
    get:    (ep, params)       => req('GET',    ep, null, params),
    post:   (ep, body, params) => req('POST',   ep, body, params),
    put:    (ep, body, params) => req('PUT',    ep, body, params),
    delete: (ep, params)       => req('DELETE', ep, null, params),

    // Helpers untuk upload file (import CSV)
    upload: async function(endpoint, file, extraParams) {
      const url    = new URL(BASE + '/' + endpoint);
      if (extraParams) Object.entries(extraParams).forEach(([k,v]) => url.searchParams.set(k, v));
      const form   = new FormData();
      form.append('file', file);
      const token  = getToken();
      const headers = {};
      if (token) headers['Authorization'] = 'Bearer ' + token;
      const res    = await fetch(url.toString(), { method: 'POST', headers, body: form });
      const json   = await res.json();
      if (!json.ok) throw new Error(json.error || 'Upload error');
      return json;
    },

    // Export CSV — buka di tab baru
    exportCsv: function(endpoint, params) {
      const url   = new URL(BASE + '/' + endpoint);
      url.searchParams.set('export', 'csv');
      if (params) Object.entries(params).forEach(([k,v]) => v != null && url.searchParams.set(k, v));
      const token = getToken();
      if (token) url.searchParams.set('_token', token);
      window.open(url.toString(), '_blank');
    },
  };

  console.log('[Fawz] API client ready →', BASE);
})();
