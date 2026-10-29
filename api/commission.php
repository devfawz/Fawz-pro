<?php
/**
 * api/commission.php — Komisi sales
 * GET  /api/commission.php                     — list commission data
 * GET  /api/commission.php?export=csv          — export CSV
 * GET  /api/commission.php?scope=status        — list sales_commission_status
 * POST /api/commission.php?scope=status        — upsert status komisi
 * GET  /api/commission.php?scope=settings      — list settings
 * POST /api/commission.php?scope=settings      — upsert settings
 */
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/db.php';

$user  = requireAuth();
$pdo   = getDB();
$m     = method();
$scope = $_GET['scope'] ?? 'report';

// ── STATUS ────────────────────────────────────────────────
if ($scope === 'status') {
    if ($m === 'GET') {
        $where = ['1=1']; $params = [];
        if (!empty($_GET['period'])) { $where[] = 'period = ?'; $params[] = $_GET['period']; }
        if (!empty($_GET['sales_name'])) { $where[] = 'sales_name = ?'; $params[] = $_GET['sales_name']; }
        $ws   = implode(' AND ', $where);
        $stmt = $pdo->prepare("SELECT * FROM sales_commission_status WHERE $ws ORDER BY period DESC, sales_name");
        $stmt->execute($params);
        jsonList($stmt->fetchAll());
    }
    if ($m === 'POST') {
        if (!in_array($user['role'], ['admin','head_account'])) jsonError('Tidak diizinkan', 403);
        $b = getBody();
        if (empty($b['sales_name']) || empty($b['period'])) jsonError('sales_name dan period wajib');
        $status = $b['status'] ?? 'paid';
        $paidAt = $status === 'paid' ? date('Y-m-d H:i:s') : null;
        $pdo->prepare(
            'INSERT INTO sales_commission_status (sales_name, period, status, paid_at, paid_by, notes)
             VALUES (?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE status=VALUES(status), paid_at=VALUES(paid_at), paid_by=VALUES(paid_by), notes=VALUES(notes)'
        )->execute([$b['sales_name'],$b['period'],$status,$paidAt,$user['name'],$b['notes'] ?? null]);
        jsonOk(['ok' => true]);
    }
}

// ── SETTINGS ──────────────────────────────────────────────
if ($scope === 'settings') {
    if ($m === 'GET') {
        $stmt = $pdo->prepare('SELECT * FROM sales_commission_settings WHERE period = ? ORDER BY sales_name');
        $stmt->execute([$_GET['period'] ?? date('Y-m')]);
        jsonList($stmt->fetchAll());
    }
    if ($m === 'POST') {
        if (!in_array($user['role'], ['admin','head_account'])) jsonError('Tidak diizinkan', 403);
        $b = getBody();
        $pdo->prepare(
            'INSERT INTO sales_commission_settings (sales_name,period,target_fee,rate_above,rate_below) VALUES (?,?,?,?,?)
             ON DUPLICATE KEY UPDATE target_fee=VALUES(target_fee),rate_above=VALUES(rate_above),rate_below=VALUES(rate_below)'
        )->execute([$b['sales_name'],$b['period'],$b['target_fee'] ?? 10000000,$b['rate_above'] ?? 0.12,$b['rate_below'] ?? 0.05]);
        jsonOk(['ok' => true]);
    }
}

// ── EXPORT ────────────────────────────────────────────────
if ($m === 'GET' && isset($_GET['export'])) {
    $period = $_GET['period'] ?? date('Y-m');
    [$y, $mo] = explode('-', $period);
    $rows = $pdo->prepare(
        'SELECT sales_name, SUM(fee) as total_fee, COUNT(*) as jml_transaksi
         FROM saham_transaksi WHERE YEAR(tanggal) = ? AND MONTH(tanggal) = ?
         GROUP BY sales_name ORDER BY total_fee DESC'
    );
    $rows->execute([$y, $mo]);
    $data = $rows->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="commission_' . $period . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Sales Name','Total Fee','Jumlah Transaksi','Periode']);
    foreach ($data as $r) {
        fputcsv($out, [$r['sales_name'], $r['total_fee'], $r['jml_transaksi'], $period]);
    }
    fclose($out);
    exit;
}

// ── REPORT LIST ───────────────────────────────────────────
if ($m === 'GET') {
    $period = $_GET['period'] ?? date('Y-m');
    [$y, $mo] = explode('-', $period);

    // Saham
    $saham = $pdo->prepare(
        'SELECT sales_name, SUM(fee) as total_fee, SUM(nilai) as total_nilai, COUNT(*) as trx_count
         FROM saham_transaksi WHERE YEAR(tanggal) = ? AND MONTH(tanggal) = ?
         GROUP BY sales_name'
    );
    $saham->execute([$y, $mo]);

    // Obligasi
    $bonds = $pdo->prepare(
        'SELECT sales_pic as sales_name, SUM(nominal) as total_nominal, SUM(commission_sales) as total_komisi, COUNT(*) as trx_count
         FROM bb_orders WHERE YEAR(created_at) = ? AND MONTH(created_at) = ? AND status = ?
         GROUP BY sales_pic'
    );
    $bonds->execute([$y, $mo, 'verified']);

    // Status komisi per sales
    $status = $pdo->prepare('SELECT * FROM sales_commission_status WHERE period = ?');
    $status->execute([$period]);
    $statusMap = [];
    foreach ($status->fetchAll() as $s) $statusMap[$s['sales_name']] = $s['status'];

    jsonOk([
        'period'    => $period,
        'saham'     => $saham->fetchAll(),
        'bonds'     => $bonds->fetchAll(),
        'statusMap' => $statusMap,
    ]);
}

jsonError('Endpoint tidak ditemukan', 404);
