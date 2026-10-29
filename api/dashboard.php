<?php
/**
 * api/dashboard.php — Aggregated stats untuk dashboard
 * GET /api/dashboard.php?scope=summary&bulan=X&tahun=X
 * GET /api/dashboard.php?scope=birthday
 * GET /api/dashboard.php?scope=sales_stats&bulan=X&tahun=X
 */
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/db.php';

$user  = requireAuth();
$pdo   = getDB();
$m     = method();
$scope = $_GET['scope'] ?? 'summary';

$tahun = (int)($_GET['tahun'] ?? date('Y'));
$bulan = (int)($_GET['bulan'] ?? date('n'));
$first = sprintf('%04d-%02d-01', $tahun, $bulan);
$last  = date('Y-m-t', strtotime($first));

// ── BIRTHDAY WIDGET ───────────────────────────────────────
if ($scope === 'birthday') {
    $today    = date('m-d');
    $next7    = date('m-d', strtotime('+7 days'));
    $stmt     = $pdo->prepare(
        "SELECT client_name, birth_date, sales_person_name
         FROM customers
         WHERE birth_date IS NOT NULL
         AND DATE_FORMAT(birth_date, '%m-%d') BETWEEN ? AND ?
         ORDER BY DATE_FORMAT(birth_date, '%m-%d')
         LIMIT 20"
    );
    $stmt->execute([$today, $next7]);
    jsonList($stmt->fetchAll());
}

// ── SALES STATS (per sales untuk dashboard) ───────────────
if ($scope === 'sales_stats') {
    $salesName = $user['role'] === 'sales' ? $user['name'] : ($_GET['sales_name'] ?? null);

    $result = [];

    // Saham
    $q = $pdo->prepare(
        'SELECT SUM(nilai) as total_nilai, SUM(fee) as total_fee, COUNT(*) as trx_count
         FROM saham_transaksi WHERE tanggal BETWEEN ? AND ?' .
        ($salesName ? ' AND sales_name = ?' : '')
    );
    $params = [$first, $last];
    if ($salesName) $params[] = $salesName;
    $q->execute($params);
    $result['saham'] = $q->fetch();

    // Obligasi
    $q2 = $pdo->prepare(
        'SELECT SUM(nominal) as total_nominal, SUM(commission_sales) as total_komisi, COUNT(*) as trx_count
         FROM bb_orders WHERE DATE(created_at) BETWEEN ? AND ? AND status = ?' .
        ($salesName ? ' AND sales_pic = ?' : '')
    );
    $params2 = [$first, $last, 'verified'];
    if ($salesName) $params2[] = $salesName;
    $q2->execute($params2);
    $result['obligasi'] = $q2->fetch();

    // Reksadana
    $q3 = $pdo->prepare(
        'SELECT SUM(nominal) as total_nominal, COUNT(*) as trx_count
         FROM reksadana_transactions WHERE DATE(created_at) BETWEEN ? AND ?' .
        ($salesName ? ' AND sales_name = ?' : '')
    );
    $params3 = [$first, $last];
    if ($salesName) $params3[] = $salesName;
    $q3->execute($params3);
    $result['reksadana'] = $q3->fetch();

    jsonOk($result);
}

// ── SUMMARY (total counts) ────────────────────────────────
if ($scope === 'summary') {
    $result = [];

    // Total customers
    $result['total_customers']   = (int)$pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn();
    $result['active_customers']  = (int)$pdo->query("SELECT COUNT(*) FROM customers WHERE client_status = 'Aktif'")->fetchColumn();

    // Obligasi products
    $result['obligasi_products'] = (int)$pdo->query("SELECT COUNT(*) FROM obligasi_products WHERE is_active = 1")->fetchColumn();

    // Transaksi bulan ini
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM bb_orders WHERE DATE(created_at) BETWEEN ? AND ?');
    $stmt->execute([$first, $last]);
    $result['bb_orders_bulan_ini'] = (int)$stmt->fetchColumn();

    // Leads pending
    $result['leads_pending'] = (int)$pdo->query("SELECT COUNT(*) FROM referral_registrations WHERE confirmed_status = 'pending'")->fetchColumn();

    // Job tasks pending
    $result['tasks_pending'] = (int)$pdo->query("SELECT COUNT(*) FROM job_tasks WHERE status = 'pending'")->fetchColumn();

    jsonOk($result);
}

jsonError('Endpoint tidak ditemukan', 404);
