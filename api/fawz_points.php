<?php
/**
 * api/fawz_points.php — Fawz points leaderboard & redemptions
 * GET  /api/fawz_points.php                 — leaderboard
 * POST /api/fawz_points.php?action=sync     — recalculate & upsert dari saham_transaksi
 * POST /api/fawz_points.php?action=redeem   — proses redemption
 * GET  /api/fawz_points.php?scope=redemptions — list redemptions
 * GET  /api/fawz_points.php?export=csv      — export CSV
 */
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/db.php';

$user   = requireAuth();
$pdo    = getDB();
$m      = method();
$action = $_GET['action'] ?? '';
$scope  = $_GET['scope'] ?? '';

// Hanya role tertentu
if (!in_array($user['role'], ['admin','head_account','head_sales'])) {
    jsonError('Tidak diizinkan', 403);
}

// ── EXPORT ───────────────────────────────────────────────
if ($m === 'GET' && isset($_GET['export'])) {
    $rows = $pdo->query('SELECT * FROM fawz_points ORDER BY available_points DESC')->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="fawz_points_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    if (!empty($rows)) { fputcsv($out, array_keys($rows[0])); foreach ($rows as $r) fputcsv($out, $r); }
    fclose($out);
    exit;
}

// ── SYNC — hitung ulang dari saham_transaksi ─────────────
if ($m === 'POST' && $action === 'sync') {
    $POINTS_PER_NOMINAL = 10_000_000; // 1 point per Rp 10jt volume

    $stmt = $pdo->query(
        'SELECT client_id, MAX(client_name) as client_name, SUM(volume * harga) as total_nilai
         FROM saham_transaksi
         WHERE client_id IS NOT NULL AND client_id != \'\'
         GROUP BY client_id'
    );
    $rows   = $stmt->fetchAll();
    $synced = 0;

    foreach ($rows as $r) {
        $earned = (int)floor(($r['total_nilai'] ?? 0) / $POINTS_PER_NOMINAL);

        // Ambil redeemed_points existing
        $ex = $pdo->prepare('SELECT redeemed_points FROM fawz_points WHERE client_id = ?');
        $ex->execute([$r['client_id']]);
        $existing  = $ex->fetch();
        $redeemed  = (int)($existing['redeemed_points'] ?? 0);
        $available = max(0, $earned - $redeemed);

        $pdo->prepare(
            'INSERT INTO fawz_points (client_id, client_name, total_volume, earned_points, redeemed_points, available_points)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               client_name=VALUES(client_name),
               total_volume=VALUES(total_volume),
               earned_points=VALUES(earned_points),
               available_points=VALUES(available_points)'
        )->execute([$r['client_id'], $r['client_name'], (int)($r['total_nilai'] ?? 0), $earned, $redeemed, $available]);
        $synced++;
    }
    jsonOk(['synced' => $synced]);
}

// ── REDEEM ────────────────────────────────────────────────
if ($m === 'POST' && $action === 'redeem') {
    $b = getBody();
    if (empty($b['client_id']) || empty($b['points'])) jsonError('client_id dan points wajib');

    $pts = (int)$b['points'];
    $ex  = $pdo->prepare('SELECT * FROM fawz_points WHERE client_id = ?');
    $ex->execute([$b['client_id']]);
    $fp  = $ex->fetch();

    if (!$fp) jsonError('Client tidak ditemukan di fawz_points');
    if ($fp['available_points'] < $pts) jsonError('Poin tidak cukup');

    // Kurangi available & tambah redeemed
    $pdo->prepare(
        'UPDATE fawz_points SET redeemed_points = redeemed_points + ?, available_points = available_points - ? WHERE client_id = ?'
    )->execute([$pts, $pts, $b['client_id']]);

    // Catat redemption
    $pdo->prepare(
        'INSERT INTO point_redemptions (client_id, client_name, points, reward, status, notes) VALUES (?,?,?,?,?,?)'
    )->execute([$b['client_id'], $fp['client_name'], $pts, $b['reward'] ?? null, 'pending', $b['notes'] ?? null]);

    jsonOk(['redeemed' => $pts, 'remaining' => $fp['available_points'] - $pts]);
}

// ── REDEMPTIONS LIST ──────────────────────────────────────
if ($m === 'GET' && $scope === 'redemptions') {
    $stmt = $pdo->query('SELECT * FROM point_redemptions ORDER BY created_at DESC');
    jsonList($stmt->fetchAll());
}

// ── LEADERBOARD ───────────────────────────────────────────
if ($m === 'GET') {
    $page    = max(1, (int)($_GET['page'] ?? 1));
    $perPage = min(200, max(20, (int)($_GET['per_page'] ?? 50)));
    $offset  = ($page - 1) * $perPage;

    $total = (int)$pdo->query('SELECT COUNT(*) FROM fawz_points')->fetchColumn();
    $stmt  = $pdo->prepare('SELECT * FROM fawz_points ORDER BY available_points DESC LIMIT ? OFFSET ?');
    $stmt->execute([$perPage, $offset]);
    jsonList($stmt->fetchAll(), $total);
}

jsonError('Endpoint tidak ditemukan', 404);
