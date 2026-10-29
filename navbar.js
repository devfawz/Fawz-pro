/* ============================================================
   navbar.js — Sales Petik Profit Navbar Logic
   Di-load via <script src="navbar.js"> di setiap halaman.
   Menghandle: session sync, user info, dropdown, active link.
   v2.5.0 — avatar ring notif
   ============================================================ */
console.log('[Fawz navbar.js] v2.5.0 loaded');

(function () {

  /* ── 0. FAVICON ── */
  (function() {
    const link = document.querySelector("link[rel~='icon']") || document.createElement('link');
    link.rel  = 'icon';
    link.type = 'image/png';
    link.href = 'assets/logo/fawz.png';
    document.head.appendChild(link);
  })();

  /* ── 0a. INJECT AVATAR RING CSS — langsung dari JS, tidak bergantung pada fetch navbar.html ── */
  (function() {
    if (document.getElementById('fawz-avatar-ring-css')) return; // sudah diinject
    const style = document.createElement('style');
    style.id = 'fawz-avatar-ring-css';
    style.textContent = `
      .avatar-wrapper { position: relative; flex-shrink: 0; }
      .avatar-wrapper.has-notif::before {
        content: '';
        position: absolute;
        top: -3px; left: -3px; right: -3px; bottom: -3px;
        border-radius: 50%;
        border: 2.5px solid #e74c3c;
        animation: fawz-ring-pulse 1.8s ease-in-out infinite;
        z-index: 1;
        pointer-events: none;
      }
      @keyframes fawz-ring-pulse {
        0%, 100% { opacity: 1; transform: scale(1); box-shadow: 0 0 0 0 rgba(231,76,60,0.5); }
        50%       { opacity: 0.85; transform: scale(1.08); box-shadow: 0 0 0 5px rgba(231,76,60,0); }
      }
      .avatar-notif-count {
        position: absolute;
        top: -4px; right: -4px;
        min-width: 17px; height: 17px;
        background: #e74c3c;
        color: #fff; font-size: .6rem; font-weight: 700;
        border-radius: 10px; padding: 0 4px;
        display: none; align-items: center; justify-content: center;
        border: 2px solid #0d0d2b;
        z-index: 2;
        animation: fawz-badge-pulse 1.8s infinite;
      }
      .avatar-notif-count.visible { display: flex !important; }
      @keyframes fawz-badge-pulse {
        0%,100% { transform: scale(1); }
        50%      { transform: scale(1.15); }
      }
    `;
    document.head.appendChild(style);
  })();

  /* ── 0a2. INJECT PROFIT AMBASSADOR PENDING NOTIFICATION CSS ── */
  (function() {
    if (document.getElementById('fawz-pa-notif-css')) return;
    const style = document.createElement('style');
    style.id = 'fawz-pa-notif-css';
    style.textContent = `
      .nav-item.has-pa-pending > .nav-link::after {
        content: '';
        position: absolute;
        bottom: 2px; left: 14px; right: 14px;
        height: 3px;
        border-radius: 2px;
        background: #e74c3c;
        animation: fawz-pa-blink 1s ease-in-out infinite;
      }
      .nav-item.has-pa-pending > .nav-link {
        position: relative;
      }
      .dropdown-item.has-pa-pending {
        position: relative;
      }
      .dropdown-item.has-pa-pending::after {
        content: '';
        position: absolute;
        bottom: 4px; left: 12px; right: 12px;
        height: 2.5px;
        border-radius: 2px;
        background: #e74c3c;
        animation: fawz-pa-blink 1s ease-in-out infinite;
      }
      .pa-pending-badge {
        display: inline-flex; align-items: center; justify-content: center;
        min-width: 16px; height: 16px;
        background: #e74c3c; color: #fff;
        font-size: 0.6rem; font-weight: 700;
        border-radius: 8px; padding: 0 4px;
        margin-left: 6px;
        animation: fawz-pa-blink 1s ease-in-out infinite;
      }
      @keyframes fawz-pa-blink {
        0%, 100% { opacity: 1; }
        50%       { opacity: 0.2; }
      }
    `;
    document.head.appendChild(style);
  })();

  /* ── 0b. API CLIENT GUARD — pastikan window._api sudah siap ── */
  (function() {
    // config.js sudah inject window._api — tidak perlu Supabase SDK sama sekali
    if (!window._api) {
      console.warn('[Fawz navbar] window._api belum siap, menunggu config.js...');
    }
  })();

  /* ── 1. SESSION SYNC ── */
  const ss  = sessionStorage.getItem('fawz_user');
  let ls    = localStorage.getItem('fawz_user_remember');

  // Cek expiry pada remember-me
  if (ls) {
    try {
      const parsed = JSON.parse(ls);
      if (parsed._exp && Date.now() > parsed._exp) {
        localStorage.removeItem('fawz_user_remember');
        ls = null;
      }
    } catch(e) { localStorage.removeItem('fawz_user_remember'); ls = null; }
  }

  const raw = ss || ls;

  if (!raw) {
    window.location.replace('login.html');
    return;
  }
  // Selalu sync ke sessionStorage agar tersedia di semua halaman
  sessionStorage.setItem('fawz_user', raw);

  /* ── 2. CONSTANTS ── */
  const ROLE_LABELS = {
    head_account : 'Head Account',
    admin        : 'Admin',
    sales        : 'Sales',
    treasury     : 'Bonds',
    head_sales   : 'Head Sales'
  };

  /* ── 3. INIT USER INFO ── */
  function initNavbar() {
    const user     = JSON.parse(raw);
    const roleName = ROLE_LABELS[user.role] || user.role;
    const initial  = (user.name || 'U')[0].toUpperCase();

    const setTxt = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };

    setTxt('navUserName',  user.name || 'User');
    setTxt('navUserRole',  roleName);
    setTxt('ddUserName',   user.name || 'User');
    setTxt('ddUserEmail',  user.username || '');

    // Avatar
    const avatarEl = document.getElementById('navAvatar');
    if (avatarEl) {
      if (user.photoURL) {
        avatarEl.innerHTML = `<img src="${user.photoURL}" alt="avatar"
          onerror="this.parentElement.textContent='${initial}'"/>`;
      } else {
        avatarEl.textContent = initial;
      }
    }

    // Daily Activity Report — semua role kecuali treasury
    if (['admin', 'treasury', 'head_account', 'sales', 'head_sales'].includes(user.role)) {
      const el = document.getElementById('dailyActivityLink');
      if (el) el.style.display = 'flex';
      const mob = document.getElementById('mobDailyActivity');
      if (mob) mob.style.display = 'flex';
    }

    // Kelola Akun hanya untuk head_account
    if (user.role === 'head_account') {
      const el = document.getElementById('manageAccountsLink');
      if (el) el.style.display = 'flex';
    }

    // ── PERMISSION MATRIX ──
    // Definisi akses per role: true = tampil, false = hidden
    const PERMISSIONS = {
      // format: { navItemId atau href : [roles yang BOLEH lihat] }
      // Nav menu utama
      navSales      : ['head_account', 'admin', 'treasury', 'sales', 'head_sales'],
      navPetikProfit: ['head_account', 'admin', 'head_sales'],
      navCustomer   : ['head_account', 'admin', 'treasury', 'sales', 'head_sales'],
      navProduct    : ['head_account', 'admin', 'treasury', 'sales', 'head_sales'],
      navRevenue    : ['head_account', 'admin', 'treasury', 'sales', 'head_sales'],
      navTools      : ['head_account', 'admin', 'treasury', 'sales', 'head_sales'],

      // Dropdown items — by href
      // Sales dropdown
      'sales-activity.html'    : ['head_account', 'admin', 'treasury', 'sales', 'head_sales'],
      'sales-transaction.html' : ['head_account', 'admin', 'treasury', 'sales', 'head_sales'],
      'leads-tracker.html'     : ['head_account', 'admin', 'head_sales'],
      'sales.html'             : ['head_account', 'admin', 'head_sales'],
      'profit-ambassador.html' : ['head_account', 'admin', 'head_sales'],
      'influencer.html'        : ['head_account', 'admin', 'head_sales'],
      'influencer-fans.html'   : ['head_account', 'admin', 'head_sales'],
      'inhouse-tracker.html'   : ['head_account', 'admin', 'treasury', 'sales', 'head_sales'],

      // Customer dropdown
      'customer.html'           : ['head_account', 'admin', 'treasury', 'sales', 'head_sales'],
      'follow-up-customer.html' : ['head_account', 'admin', 'head_sales'],
      'portofolio.html'            : ['head_account', 'admin', 'treasury', 'sales', 'head_sales'],
      'client-by-portfolio.html'  : ['head_account', 'admin', 'treasury', 'sales', 'head_sales'],
      'birthday-calendar.html'    : ['head_account', 'admin', 'treasury', 'sales', 'head_sales'],

      // Product dropdown
      'obligasi-bookbuilding.html'    : ['head_account', 'admin', 'treasury', 'sales', 'head_sales'],
      'obligasi-pasar-sekunder.html'  : ['head_account', 'admin', 'treasury', 'sales', 'head_sales'],
      'obligasi-ipo.html'             : ['head_account', 'admin', 'treasury', 'sales', 'head_sales'],
      'bond-transaction.html'         : ['head_account', 'admin', 'treasury', 'sales', 'head_sales'],
      'obligasi-coupon-calendar.html' : ['head_account', 'admin', 'treasury', 'sales', 'head_sales'],
      // reksadana & waran locked (shown as "soon" — no href)
      'saham.html'                    : ['head_account', 'admin', 'treasury', 'sales', 'head_sales'],
      'saham-ranking.html'            : ['head_account', 'admin', 'head_sales'],
      'fawz-point.html'               : ['head_account', 'admin', 'head_sales'],

      // Reporting dropdown (Product section)
      'reporting-harian-sales.html' : ['admin'],

      // Revenue dropdown
      'analytics.html'         : ['head_account', 'admin', 'sales', 'head_sales'],
      'revenue-data.html'      : ['head_account', 'admin', 'sales', 'head_sales'],
      'sales-commission.html'  : ['head_account', 'admin', 'sales', 'head_sales'],

      // User dropdown
      'manageAccountsLink'     : ['head_account'],  // Kelola Akun di profile

      // Tools dropdown
      'logo.html'              : ['head_account', 'admin', 'treasury', 'head_sales'],
      // Newsletter Editor — akses lebih lanjut dibatasi ke username 'bondsdealer' di bawah
      'newsletter-editor.html' : ['head_account', 'admin', 'treasury', 'head_sales'],
      // Dealer Report — role 'admin' + head_account (username 'fawzheadaccount' dibatasi di bawah)
      'dealer-report.html'     : ['admin', 'head_account'],
    };

    const role = user.role;

    // Sembunyikan nav menu utama
    ['navSales','navPetikProfit','navCustomer','navProduct','navRevenue','navTools'].forEach(id => {
      const allowed = PERMISSIONS[id];
      const el = document.getElementById(id);
      if (el && allowed && !allowed.includes(role)) el.style.display = 'none';
    });

    // Sembunyikan dropdown items berdasarkan href
    document.querySelectorAll('.dropdown-item[href]').forEach(el => {
      const href    = el.getAttribute('href');
      const allowed = PERMISSIONS[href];
      if (allowed && !allowed.includes(role)) el.style.display = 'none';
    });

    // Setelah sembunyikan items, cek dropdown section yang jadi kosong — hapus juga
    document.querySelectorAll('.dropdown-section').forEach(section => {
      const visibleItems = [...section.querySelectorAll('.dropdown-item')]
        .filter(i => i.style.display !== 'none');
      if (visibleItems.length === 0) section.style.display = 'none';
    });

    // Sembunyikan nav-item yang semua dropdown-item-nya hidden
    document.querySelectorAll('.nav-menu .nav-item[id]').forEach(item => {
      const dd = item.querySelector('.dropdown');
      if (!dd) return;
      const visibleItems = [...dd.querySelectorAll('.dropdown-item')]
        .filter(i => i.style.display !== 'none');
      if (visibleItems.length === 0) item.style.display = 'none';
    });

    // Mobile drawer: sembunyikan mob-link berdasarkan href yang sama
    document.querySelectorAll('.mobile-drawer .mob-link[href]').forEach(el => {
      const href    = el.getAttribute('href');
      const allowed = PERMISSIONS[href];
      if (allowed && !allowed.includes(role)) el.style.display = 'none';
    });

    // Mobile drawer: sembunyikan drawer-group jika semua mob-link di dalamnya hidden
    document.querySelectorAll('.drawer-group').forEach(group => {
      const visibleLinks = [...group.querySelectorAll('.mob-link[href]')]
        .filter(l => l.style.display !== 'none');
      if (visibleLinks.length === 0) group.style.display = 'none';
    });

    // ── USERNAME EXCEPTIONS ──
    // Sembunyikan logo.html khusus untuk user 'husni'
    const username = (user.username || user.name || '').toLowerCase();
    if (username === 'husni') {
      document.querySelectorAll('.dropdown-item[href="logo.html"]').forEach(el => el.style.display = 'none');
      document.querySelectorAll('.mob-link[href="logo.html"]').forEach(el => el.style.display = 'none');
    }

    // Newsletter Editor hanya untuk user 'bondsdealer' — sembunyikan untuk semua user lain
    if (username !== 'bondsdealer') {
      document.querySelectorAll('.dropdown-item[href="newsletter-editor.html"]').forEach(el => el.style.display = 'none');
      document.querySelectorAll('.mob-link[href="newsletter-editor.html"]').forEach(el => el.style.display = 'none');
    }

    // Dealer Report — hanya role 'admin' ATAU username 'fawzheadaccount'
    // (permission map sudah batasi ke admin + head_account; di sini persempit head_account → hanya fawzheadaccount)
    if (role !== 'admin' && username !== 'fawzheadaccount') {
      document.querySelectorAll('.dropdown-item[href="dealer-report.html"]').forEach(el => el.style.display = 'none');
      document.querySelectorAll('.mob-link[href="dealer-report.html"]').forEach(el => el.style.display = 'none');
    }

    // Bersihkan lagi section/group yang jadi kosong setelah username exceptions di atas
    document.querySelectorAll('.dropdown-section').forEach(section => {
      const visibleItems = [...section.querySelectorAll('.dropdown-item')]
        .filter(i => i.style.display !== 'none');
      if (visibleItems.length === 0) section.style.display = 'none';
    });
    document.querySelectorAll('.nav-menu .nav-item[id]').forEach(item => {
      const dd = item.querySelector('.dropdown');
      if (!dd) return;
      const visibleItems = [...dd.querySelectorAll('.dropdown-item')]
        .filter(i => i.style.display !== 'none');
      if (visibleItems.length === 0) item.style.display = 'none';
    });
    document.querySelectorAll('.drawer-group').forEach(group => {
      const visibleLinks = [...group.querySelectorAll('.mob-link[href]')]
        .filter(l => l.style.display !== 'none');
      if (visibleLinks.length === 0) group.style.display = 'none';
    });

    // Active link — highlight menu sesuai halaman aktif
    const currentFile = window.location.pathname.split('/').pop() || 'dashboard.html';
    let matched = false;

    document.querySelectorAll('.nav-link').forEach(link => {
      link.classList.remove('active');
      if (link.getAttribute('href') === currentFile) {
        link.classList.add('active');
        matched = true;
      }
    });

    if (!matched) {
      document.querySelectorAll('.dropdown-item').forEach(item => {
        if (item.getAttribute('href') === currentFile) {
          const parent = item.closest('.nav-item');
          if (parent) parent.querySelector('.nav-link')?.classList.add('active');
        }
      });
    }

  }

  /* ── 4b. TASK PENDING INDICATOR (semua role) ── */
  function initTaskIndicator(user) {
    // Semua role yang punya akses job-report
    const allowedRoles = ['admin', 'treasury', 'head_account', 'sales', 'head_sales'];
    if (!allowedRoles.includes(user.role)) return;

    waitForApi(() => {
      fetchPendingTasks(user);
      setInterval(() => fetchPendingTasks(user), 60000);
    });
  }

  async function fetchPendingTasks(user) {
    if (!window._supabase) return;
    try {
      let pendingCount = 0;

      if (user.role === 'head_account') {
        // Head account: hitung semua task pending
        const { count: c, error } = await window._supabase
          .from('job_tasks')
          .select('id', { count: 'exact', head: true })
          .eq('status', 'pending');
        if (!error) pendingCount = c || 0;
      } else {
        // Fetch semua task pending untuk role ini (gabung assigned_to null + user spesifik)
        const { data: taskData, error: taskErr } = await window._supabase
          .from('job_tasks')
          .select('id, assigned_to')
          .eq('status', 'pending')
          .eq('assigned_role', user.role);

        if (!taskErr && taskData) {
          // Filter: assigned_to null (untuk semua role) ATAU assigned_to = username saya
          pendingCount = taskData.filter(t =>
            t.assigned_to === null || t.assigned_to === user.username
          ).length;
        }
      }

  /* ── 4b. TASK PENDING INDICATOR ── */
  function initTaskIndicator(user) {
    const allowedRoles = ['admin', 'treasury', 'head_account', 'sales', 'head_sales'];
    if (!allowedRoles.includes(user.role)) return;
    waitForApi(() => {
      fetchPendingTasks(user);
      setInterval(() => fetchPendingTasks(user), 60000);
    });
  }

  async function fetchPendingTasks(user) {
    if (!window._api) return;
    try {
      const res = await window._api.get('jobs.php', { scope: 'pending_count' });
      const pendingCount = res.data?.count || 0;
      const badge    = document.getElementById('jobReportBadge');
      const mobBadge = document.getElementById('mobJobReportBadge');
      if (badge)    { badge.textContent = pendingCount > 99 ? '99+' : pendingCount; badge.style.display = pendingCount > 0 ? 'inline-flex' : 'none'; }
      if (mobBadge) { mobBadge.textContent = pendingCount > 99 ? '99+' : pendingCount; mobBadge.style.display = pendingCount > 0 ? 'inline-flex' : 'none'; }
      updateAvatarNotifRing(pendingCount);
    } catch(e) { /* silent */ }
  }

  /* Update lingkaran merah & angka di avatar profile */
  function updateAvatarNotifRing(count) {
    const wrapper = document.getElementById('avatarWrapper');
    const badge   = document.getElementById('avatarNotifCount');
    if (!wrapper || !badge) { setTimeout(() => updateAvatarNotifRing(count), 200); return; }
    if (count > 0) {
      wrapper.classList.add('has-notif');
      badge.textContent = count > 99 ? '99+' : count;
      badge.classList.add('visible');
    } else {
      wrapper.classList.remove('has-notif');
      badge.classList.remove('visible');
    }
  }

  let _notifReadIds = [];

  function initNotifications(user) {
    if (user.role !== 'treasury') return;
    const bell = document.getElementById('notifBell');
    if (!bell) return;
    bell.style.display = 'flex';
    const stored = localStorage.getItem('fawz_notif_read_' + (user.username || user.name));
    _notifReadIds = stored ? JSON.parse(stored) : [];
    waitForApi(() => {
      fetchPendingOrders(user);
      setInterval(() => fetchPendingOrders(user), 60000);
    });
  }

  function waitForApi(cb, tries) {
    tries = tries || 0;
    if (window._api) { cb(); return; }
    if (tries > 20) return;
    setTimeout(() => waitForApi(cb, tries + 1), 300);
  }

  async function fetchPendingOrders(user) {
    if (!window._api) return;
    try {
      const res  = await window._api.get('bonds.php', { status: 'pending', per_page: 20 });
      const data = res.data || [];      if (mobBadge) { mobBadge.textContent = pendingCount > 99 ? '99+' : pendingCount; mobBadge.style.display = pendingCount > 0 ? 'inline-flex' : 'none'; }
      updateAvatarNotifRing(pendingCount);
    } catch(e) { /* silent */ }
  }

      const unreadCount = data.filter(o => !_notifReadIds.includes(o.id)).length;

      // Update badge
      const badge = document.getElementById('notifBadge');
      if (badge) {
        if (unreadCount > 0) {
          badge.textContent = unreadCount > 99 ? '99+' : unreadCount;
          badge.style.display = 'flex';
        } else {
          badge.style.display = 'none';
        }
      }

      // Render list
      const list = document.getElementById('notifList');
      if (!list) return;

      if (data.length === 0) {
        list.innerHTML = '<div class="notif-empty">✅ Tidak ada order pending</div>';
        return;
      }

      // Escape helper untuk mencegah XSS
      const _esc = s => String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');

      list.innerHTML = data.map(o => {
        const isUnread = !_notifReadIds.includes(o.id);
        const nominal  = Number(o.nominal || 0).toLocaleString('id-ID');
        const seri     = _esc(o.seri || '—');
        const nasabah  = _esc(o.customer_name || '—');
        const sales    = _esc(o.sales_pic || '—');
        const time     = o.created_at ? fmtTimeAgo(o.created_at) : '';
        const cat      = { 'gov-idr':'Gov IDR','gov-usd':'Gov USD','korp-idr':'Korp IDR','korp-usd':'Korp USD' }[o.category] || '';
        const safeId   = _esc(String(o.id));
        return `<div class="notif-item ${isUnread ? 'unread' : ''}" onclick="onNotifClick('${safeId}')">
          <div class="notif-dot"></div>
          <div class="notif-content">
            <div class="notif-title">📋 Order ${seri} ${cat ? '· '+_esc(cat) : ''}</div>
            <div class="notif-sub">Nasabah: <strong>${nasabah}</strong> · Sales: ${sales}</div>
            <div class="notif-sub">Nominal: Rp ${nominal}</div>
            <div class="notif-time">${time}</div>
          </div>
        </div>`;
      }).join('');

    } catch(e) { console.warn('Notif fetch error:', e); }
  }

  window.toggleNotifPanel = function() {
    const bell = document.getElementById('notifBell');
    if (bell) bell.classList.toggle('open');
    // Close user dropdown if open
    document.getElementById('navUser')?.classList.remove('open');
  };

  window.onNotifClick = function(id) {
    // Mark as read
    if (!_notifReadIds.includes(id)) {
      _notifReadIds.push(id);
      const raw2 = sessionStorage.getItem('fawz_user') || localStorage.getItem('fawz_user_remember');
      const u = raw2 ? JSON.parse(raw2) : {};
      localStorage.setItem('fawz_notif_read_' + (u.username||u.name), JSON.stringify(_notifReadIds));
      // Update badge
      const badge = document.getElementById('notifBadge');
      const list  = document.getElementById('notifList');
      const item  = list?.querySelector(`[onclick="onNotifClick('${id}')"]`);
      if (item) { item.classList.remove('unread'); item.querySelector('.notif-dot').style.background = 'transparent'; }
      const unread = list?.querySelectorAll('.notif-item.unread').length || 0;
      if (badge) { badge.textContent = unread; badge.style.display = unread > 0 ? 'flex' : 'none'; }
    }
    // Navigate
    window.location.href = 'obligasi-order.html';
  };

  window.markAllRead = function(e) {
    e.stopPropagation();
    const list = document.getElementById('notifList');
    const items = list?.querySelectorAll('.notif-item') || [];
    items.forEach(el => {
      const match = el.getAttribute('onclick')?.match(/'([^']+)'/);
      if (match) _notifReadIds.push(match[1]);
      el.classList.remove('unread');
      el.querySelector('.notif-dot').style.background = 'transparent';
    });
    const raw2 = sessionStorage.getItem('fawz_user') || localStorage.getItem('fawz_user_remember');
    const u = raw2 ? JSON.parse(raw2) : {};
    localStorage.setItem('fawz_notif_read_' + (u.username||u.name), JSON.stringify(_notifReadIds));
    const badge = document.getElementById('notifBadge');
    if (badge) badge.style.display = 'none';
  };

  function fmtTimeAgo(iso) {
    const diff = Date.now() - new Date(iso).getTime();
    const m = Math.floor(diff / 60000);
    const h = Math.floor(m / 60);
    const d = Math.floor(h / 24);
    if (d > 0)  return d + ' hari lalu';
    if (h > 0)  return h + ' jam lalu';
    if (m > 0)  return m + ' menit lalu';
    return 'Baru saja';
  }

  /* ── 5. DROPDOWN HOVER ── */
  function setupDropdowns() {
    const items = document.querySelectorAll('.nav-menu .nav-item');

    items.forEach(item => {
      let timer = null;
      const open  = () => { clearTimeout(timer); items.forEach(o => { if (o !== item) o.classList.remove('open'); }); item.classList.add('open'); };
      const close = () => { timer = setTimeout(() => item.classList.remove('open'), 180); };
      const hold  = () => clearTimeout(timer);

      item.addEventListener('mouseenter', open);
      item.addEventListener('mouseleave', close);

      const dd = item.querySelector('.dropdown');
      if (dd) {
        dd.addEventListener('mouseenter', hold);
        dd.addEventListener('mouseleave', close);
      }
    });

    // Tutup saat klik di luar
    document.addEventListener('click', e => {
      if (!e.target.closest('.nav-item') && !e.target.closest('.nav-user')) {
        items.forEach(i => i.classList.remove('open'));
        document.getElementById('navUser')?.classList.remove('open');
      }
      if (!e.target.closest('.notif-bell')) {
        document.getElementById('notifBell')?.classList.remove('open');
      }
    });

    // User dropdown
    const navUser = document.getElementById('navUser');
    const userDd  = navUser?.querySelector('.user-dropdown');
    let userTimer = null;
    if (navUser && userDd) {
      const openU  = () => { clearTimeout(userTimer); navUser.classList.add('open'); };
      const closeU = () => { userTimer = setTimeout(() => navUser.classList.remove('open'), 180); };
      const holdU  = () => clearTimeout(userTimer);
      navUser.addEventListener('mouseenter', openU);
      navUser.addEventListener('mouseleave', closeU);
      userDd.addEventListener('mouseenter', holdU);
      userDd.addEventListener('mouseleave', closeU);
    }
  }

  /* ── 5. GLOBAL FUNCTIONS ── */
  window.doLogout = function () {
    const raw2 = sessionStorage.getItem('fawz_user') || localStorage.getItem('fawz_user_remember');
    if (raw2 && window._api) {
      try { window._api.post('auth.php?action=logout', {}); } catch(e) {}
    }
    sessionStorage.removeItem('fawz_user');
    localStorage.removeItem('fawz_user_remember');
    window.location.href = 'login.html';
  };

  window.toggleMobile = function () {
    const drawer = document.getElementById('mobileDrawer');
    const overlay = document.getElementById('drawerOverlay');
    const hamburger = document.getElementById('hamburger');
    const waFloat = document.querySelector('.wa-float');
    const isOpen = drawer?.classList.contains('open');
    drawer?.classList.toggle('open');
    overlay?.classList.toggle('open');
    hamburger?.classList.toggle('active');
    document.body.style.overflow = isOpen ? '' : 'hidden';
    // Sembunyikan WA float saat drawer terbuka
    if (waFloat) waFloat.style.display = isOpen ? '' : 'none';
  };

  window.toggleDrawerGroup = function (header) {
    const items = header.nextElementSibling;
    const isOpen = items.classList.contains('open');
    document.querySelectorAll('.drawer-group-items.open').forEach(el => {
      el.classList.remove('open');
      el.previousElementSibling.classList.remove('active');
    });
    if (!isOpen) {
      items.classList.add('open');
      header.classList.add('active');
    }
  };
  /* ── 5b. GLOBAL MODAL ESCAPE — tutup semua modal overlay saat tekan Escape ── */
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      document.querySelectorAll('.modal-overlay.open, .detail-modal-overlay.open').forEach(function(el) {
        el.classList.remove('open');
      });
    }
  });

  /* ── 4d. PROFIT AMBASSADOR PENDING NOTIFICATION (admin only) ── */
  function initProfitAmbassadorNotif(user) {
    // Hanya untuk admin, head_account, head_sales
    const allowedRoles = ['admin', 'head_account', 'head_sales'];
    if (!allowedRoles.includes(user.role)) return;

    waitForApi(() => {
      checkPendingAmbassador();
      setInterval(checkPendingAmbassador, 60000);
    });
  }

  async function checkPendingAmbassador() {
    if (!window._api) return;
    try {
      const res   = await window._api.get('referral.php', { scope: 'registrations', status: 'pending', per_page: 1 });
      const count = res.total || 0;

      const navPetikProfit = document.getElementById('navPetikProfit');
      // Cari dropdown-item Profit Ambassador di dalam navPetikProfit
      let paItem = null;
      if (navPetikProfit) {
        navPetikProfit.querySelectorAll('.dropdown-item').forEach(el => {
          if (el.getAttribute('href') === 'profit-ambassador.html') paItem = el;
        });
      }

      if (count && count > 0) {
        // Tambah class blinking ke Petik Profit nav-item
        if (navPetikProfit) navPetikProfit.classList.add('has-pa-pending');
        // Tambah class blinking ke Profit Ambassador dropdown-item
        if (paItem) {
          paItem.classList.add('has-pa-pending');
          // Tambah badge angka jika belum ada
          if (!paItem.querySelector('.pa-pending-badge')) {
            const badge = document.createElement('span');
            badge.className = 'pa-pending-badge';
            badge.textContent = count > 99 ? '99+' : count;
            paItem.appendChild(badge);
          } else {
            paItem.querySelector('.pa-pending-badge').textContent = count > 99 ? '99+' : count;
          }
        }

        // Mobile drawer — cari mob-link ke profit-ambassador.html
        document.querySelectorAll('.mob-link[href="profit-ambassador.html"]').forEach(mobEl => {
          if (!mobEl.querySelector('.pa-pending-badge')) {
            const badge = document.createElement('span');
            badge.className = 'pa-pending-badge';
            badge.textContent = count > 99 ? '99+' : count;
            mobEl.appendChild(badge);
          } else {
            mobEl.querySelector('.pa-pending-badge').textContent = count > 99 ? '99+' : count;
          }
        });
      } else {
        // Hapus indicator jika tidak ada pending
        if (navPetikProfit) navPetikProfit.classList.remove('has-pa-pending');
        if (paItem) {
          paItem.classList.remove('has-pa-pending');
          const badge = paItem.querySelector('.pa-pending-badge');
          if (badge) badge.remove();
        }
        document.querySelectorAll('.mob-link[href="profit-ambassador.html"] .pa-pending-badge').forEach(b => b.remove());
      }
    } catch(e) { /* silent */ }
  }

  /* ── 4c. INFLUENCER & REFERRAL MEMBERSHIP CHECK ── */
  function initMembershipCheck(user) {
    waitForApi(() => {
      checkInfluencerStatus(user);
      checkReferralStatus(user);
    });
  }

  async function checkInfluencerStatus(user) {
    if (!window._api) return;
    try {
      const clientCode = user.client_code || user.client_id || '';
      if (!clientCode) return;
      const res = await window._api.get('influencer.php', { scope: 'registrations', q: clientCode });
      if (res.data && res.data.length > 0) {
        const link    = document.getElementById('influencerMenuLink');
        const mobLink = document.getElementById('mobInfluencerLink');
        if (link)    { link.href = 'dashboard-influencer.html'; link.innerHTML = '<span class="d-icon">📣</span> Dashboard'; }
        if (mobLink) { mobLink.href = 'dashboard-influencer.html'; mobLink.textContent = 'Dashboard'; }
      }
    } catch(e) { /* silent */ }
  }

  async function checkReferralStatus(user) {
    if (!window._api) return;
    try {
      const agentCode = user.agent_code || user.client_code || user.client_id || '';
      if (!agentCode) return;
      const res = await window._api.get('referral.php', { scope: 'agents', agent_code: agentCode });
      if (res.data && res.data.length > 0) {
        const link    = document.getElementById('referralMenuLink');
        const mobLink = document.getElementById('mobReferralLink');
        if (link)    { link.href = 'dashboard-referral.html'; link.innerHTML = '<span class="d-icon">🤝</span> Dashboard'; }
        if (mobLink) { mobLink.href = 'dashboard-referral.html'; mobLink.textContent = 'Dashboard'; }
      }
    } catch(e) { /* silent */ }
  }

  /* ── 6. FETCH & INJECT NAVBAR HTML, LALU INIT ── */
  fetch('navbar.html?v=' + Date.now())
    .then(r => r.text())
    .then(html => {
      const parser = new DOMParser();
      const doc    = parser.parseFromString(html, 'text/html');

      // Inject styles
      doc.querySelectorAll('style').forEach(s => {
        const clone = document.createElement('style');
        clone.textContent = s.textContent;
        document.head.appendChild(clone);
      });

      // Inject nav + drawer HTML
      const ph     = document.getElementById('navbar-placeholder');
      const nav    = doc.querySelector('nav.fawz-navbar');
      const drawer = doc.querySelector('.mobile-drawer');
      if (nav    && ph) ph.appendChild(nav);
      if (drawer && ph) ph.appendChild(drawer);

      // Init setelah HTML ada di DOM — TIDAK inject ulang script dari navbar.html
      // karena semua logika sudah ada di file navbar.js ini
      initNavbar();
      setupDropdowns();

      // Init notifications untuk treasury
      const _rawUser = sessionStorage.getItem('fawz_user') || localStorage.getItem('fawz_user_remember');
      if (_rawUser) initNotifications(JSON.parse(_rawUser));

      // Init task pending indicator untuk semua role
      if (_rawUser) initTaskIndicator(JSON.parse(_rawUser));

      // Check Influencer & Referral registration status
      if (_rawUser) initMembershipCheck(JSON.parse(_rawUser));

      // Check pending Profit Ambassador registrations (admin notif)
      if (_rawUser) initProfitAmbassadorNotif(JSON.parse(_rawUser));

      // Event delegation untuk drawer group accordion
      document.addEventListener('click', function(e) {
        const header = e.target.closest('.drawer-group-header');
        if (!header) return;
        const items = header.nextElementSibling;
        if (!items || !items.classList.contains('drawer-group-items')) return;
        const isOpen = items.classList.contains('open');
        document.querySelectorAll('.drawer-group-items.open').forEach(el => {
          el.classList.remove('open');
          el.previousElementSibling.classList.remove('active');
        });
        if (!isOpen) {
          items.classList.add('open');
          header.classList.add('active');
        }
      });
    })
    .catch(err => console.warn('Navbar gagal dimuat:', err));

})();
