<?php
/**
 * api/bonds.php — bb_orders (obligasi bookbuilding) + pasar_sekunder_orders
 * GET    /api/bonds.php                  — list bb_orders
 * POST   /api/bonds.php                  — create bb_order
 * PUT    /api/bonds.php?id=X             — update bb_order
 * DELETE /api/bonds.php?id=X             — delete
 * POST   /api/bonds.php?action=approve   — approve (body: {id})
 * POST   /api/bonds.php?action=reject    — reject  (body: {id, notes})
 * GET    /api/bonds.php?export=csv       — export CSV
 * GET    /api/bonds.php?scope=sekunder   — list pasar_sekunder_orders
 * POST   /api/bonds.php?scope=sekunder   — create pasar_sekunder_order
 */
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/db.php';

$user   = requireAuth();
$pdo    = getDB();
$m      = method();
$id     = isset($_GET['id'])   ? (int)$_GET['id']   : null;
$action = $_GET['action'] ?? '';
$scope  = $_GET['scope']  ?? 'bb';

// ── EXPORT CSV ────────────────────────────────────────────
if ($m === 'GET' && isset($_GET['export'])) {
    $rows = $pdo->query('SELECT * FROM bb_orders ORDER BY created_at DESC')->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="bb_orders_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    if (!empty($rows)) {
        fputcsv($out, array_keys($rows[0]));
        foreach ($rows as $r) fputcsv($out, $r);
    }
    fclose($out);
    exit;
}

// ── APPROVE ───────────────────────────────────────────────
if ($m === 'POST' && $action === 'approve') {
    if (!in_array($user['role'], ['admin','head_account','treasury'])) jsonError('Tidak diizinkan', 403);
    $b  = getBody();
    $oid = (int)($b['id'] ?? 0);
    if (!$oid) jsonError('ID wajib diisi');
    $pdo->prepare("UPDATE bb_orders SET status='verified', approved_by=? WHERE id=?")
        ->execute([$user['name'], $oid]);
    jsonOk(['id' => $oid, 'status' => 'verified']);
}

// ── REJECT ────────────────────────────────────────────────
if ($m === 'POST' && $action === 'reject') {
    if (!in_array($user['role'], ['admin','head_account','treasury'])) jsonError('Tidak diizinkan', 403);
    $b   = getBody();
    $oid = (int)($b['id'] ?? 0);
    if (!$oid) jsonError('ID wajib diisi');
    $pdo->prepare("UPDATE bb_orders SET status='rejected', rejected_by=?, notes=? WHERE id=?")
        ->execute([$user['name'], $b['notes'] ?? null, $oid]);
    jsonOk(['id' => $oid, 'status' => 'rejected']);
}

// ── PASAR SEKUNDER ────────────────────────────────────────
if ($scope === 'sekunder') {
    if ($m === 'GET') {
        $where = ['1=1']; $params = [];
        if ($user['role'] === 'sales') {
            $where[] = 'sales_name = ?'; $params[] = $user['name'];
        } elseif (!empty($_GET['sales_name'])) {
            $where[] = 'sales_name = ?'; $params[] = $_GET['sales_name'];
        }
        if (!empty($_GET['bulan'])) {
            $where[] = 'MONTH(created_at) = ?'; $params[] = (int)$_GET['bulan'];
        }
        if (!empty($_GET['tahun'])) {
            $where[] = 'YEAR(created_at) = ?'; $params[] = (int)$_GET['tahun'];
        }
        $ws   = implode(' AND ', $where);
        $stmt = $pdo->prepare("SELECT * FROM pasar_sekunder_orders WHERE $ws ORDER BY created_at DESC");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        jsonList($rows);
    }
    if ($m === 'POST') {
        $b = getBody();
        $fields = ['seri','customer_name','client_id','sales_name','nominal','harga','jenis','komisi','status'];
        $cols = []; $vals = [];
        foreach ($fields as $f) {
            if (array_key_exists($f, $b)) { $cols[] = "`$f`"; $vals[] = $b[$f] ?: null; }
        }
        $pdo->prepare('INSERT INTO pasar_sekunder_orders (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')')->execute($vals);
        jsonOk(['id' => $pdo->lastInsertId()], 201);
    }
}

// ── BB_ORDERS LIST ────────────────────────────────────────
if ($m === 'GET') {
    $where = ['1=1']; $params = [];

    if ($user['role'] === 'sales') {
        $where[] = 'sales_pic = ?'; $params[] = $user['name'];
    } elseif (!empty($_GET['sales_pic'])) {
        $where[] = 'sales_pic = ?'; $params[] = $_GET['sales_pic'];
    }
    if (!empty($_GET['status'])) {
        $where[] = 'status = ?'; $params[] = $_GET['status'];
    }
    if (!empty($_GET['bulan'])) {
        $where[] = 'MONTH(created_at) = ?'; $params[] = (int)$_GET['bulan'];
    }
    if (!empty($_GET['tahun'])) {
        $where[] = 'YEAR(created_at) = ?'; $params[] = (int)$_GET['tahun'];
    }
    if (!empty($_GET['q'])) {
        $like = '%' . $_GET['q'] . '%';
        $where[] = '(client_id LIKE ? OR customer_name LIKE ? OR seri LIKE ?)';
        $params  = array_merge($params, [$like,$like,$like]);
    }

    $ws      = implode(' AND ', $where);
    $page    = max(1, (int)($_GET['page'] ?? 1));
    $perPage = min(500, max(20, (int)($_GET['per_page'] ?? 50)));
    $offset  = ($page - 1) * $perPage;

    $total = $pdo->prepare("SELECT COUNT(*) FROM bb_orders WHERE $ws");
    $total->execute($params);
    $totalCount = (int)$total->fetchColumn();

    $stmt = $pdo->prepare("SELECT * FROM bb_orders WHERE $ws ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);
    jsonList($stmt->fetchAll(), $totalCount);
}

// ── CREATE ────────────────────────────────────────────────
if ($m === 'POST') {
    $b = getBody();
    if (empty($b['customer_name']) && empty($b['client_id'])) jsonError('customer_name atau client_id wajib diisi');
    $fields = ['seri','category','customer_name','client_id','sales_pic','nominal','harga','jenis','komisi','commission_sales','status','notes'];
    $cols = []; $vals = [];
    foreach ($fields as $f) {
        if (array_key_exists($f, $b)) { $cols[] = "`$f`"; $vals[] = ($b[$f] === '' ? null : $b[$f]); }
    }
    $pdo->prepare('INSERT INTO bb_orders (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')')->execute($vals);
    jsonOk(['id' => $pdo->lastInsertId()], 201);
}

// ── UPDATE ────────────────────────────────────────────────
if ($m === 'PUT' && $id) {
    $b = getBody();
    $fields = ['seri','category','customer_name','client_id','sales_pic','nominal','harga','jenis','komisi','commission_sales','status','notes'];
    $sets = []; $vals = [];
    foreach ($fields as $f) {
        if (array_key_exists($f, $b)) { $sets[] = "`$f` = ?"; $vals[] = ($b[$f] === '' ? null : $b[$f]); }
    }
    if (empty($sets)) jsonError('Tidak ada data');
    $vals[] = $id;
    $pdo->prepare('UPDATE bb_orders SET ' . implode(',', $sets) . ' WHERE id = ?')->execute($vals);
    jsonOk(['id' => $id]);
}

// ── DELETE ────────────────────────────────────────────────
if ($m === 'DELETE' && $id) {
    if (!in_array($user['role'], ['admin','treasury'])) jsonError('Tidak diizinkan', 403);
    $pdo->prepare('DELETE FROM bb_orders WHERE id = ?')->execute([$id]);
    jsonOk(['deleted' => $id]);
}

jsonError('Endpoint tidak ditemukan', 404);
