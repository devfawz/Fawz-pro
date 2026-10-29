<?php
/**
 * api/sales.php — Data sales & sync
 * GET  /api/sales.php            — list sales
 * POST /api/sales.php            — create/upsert sales
 * PUT  /api/sales.php?id=X       — update sales
 * GET  /api/sales.php?action=sync_from_accounts — sync dari tabel accounts
 * GET  /api/sales.php?export=csv — export CSV
 * GET  /api/sales.php?import=csv — import CSV
 */
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/db.php';

$user   = requireAuth();
$pdo    = getDB();
$m      = method();
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;
$action = $_GET['action'] ?? '';

// ── EXPORT ───────────────────────────────────────────────
if ($m === 'GET' && isset($_GET['export'])) {
    $rows = $pdo->query('SELECT * FROM sales ORDER BY name')->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="sales_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    if (!empty($rows)) { fputcsv($out, array_keys($rows[0])); foreach ($rows as $r) fputcsv($out, $r); }
    fclose($out);
    exit;
}

// ── SYNC FROM ACCOUNTS ────────────────────────────────────
if ($action === 'sync_from_accounts') {
    if (!in_array($user['role'], ['admin','head_account'])) jsonError('Tidak diizinkan', 403);
    $stmt = $pdo->query("SELECT id, name FROM accounts WHERE role IN ('sales','head_sales') AND is_active = 1");
    $accs = $stmt->fetchAll();
    $synced = 0;
    foreach ($accs as $a) {
        $chk = $pdo->prepare('SELECT id FROM sales WHERE name = ?');
        $chk->execute([$a['name']]);
        if (!$chk->fetch()) {
            $pdo->prepare('INSERT INTO sales (name, account_id) VALUES (?,?)')->execute([$a['name'], $a['id']]);
            $synced++;
        }
    }
    jsonOk(['synced' => $synced]);
}

// ── LIST ──────────────────────────────────────────────────
if ($m === 'GET') {
    $where = ['1=1']; $params = [];
    if (!empty($_GET['q'])) {
        $like    = '%' . $_GET['q'] . '%';
        $where[] = '(name LIKE ? OR kode LIKE ? OR referral_code LIKE ?)';
        $params  = array_merge($params, [$like,$like,$like]);
    }
    if (isset($_GET['is_active'])) { $where[] = 'is_active = ?'; $params[] = (int)$_GET['is_active']; }
    $ws   = implode(' AND ', $where);
    $stmt = $pdo->prepare("SELECT * FROM sales WHERE $ws ORDER BY name");
    $stmt->execute($params);
    jsonList($stmt->fetchAll());
}

// ── CREATE ────────────────────────────────────────────────
if ($m === 'POST') {
    if (!in_array($user['role'], ['admin','head_account'])) jsonError('Tidak diizinkan', 403);
    $b = getBody();
    if (empty($b['name'])) jsonError('name wajib');
    $fields = ['name','kode','email','phone','office','branch','account_id','referral_code','is_active'];
    $cols = []; $vals = [];
    foreach ($fields as $f) {
        if (array_key_exists($f, $b)) { $cols[] = "`$f`"; $vals[] = ($b[$f] === '' ? null : $b[$f]); }
    }
    $pdo->prepare('INSERT INTO sales (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')')->execute($vals);
    jsonOk(['id' => $pdo->lastInsertId()], 201);
}

// ── UPDATE ────────────────────────────────────────────────
if ($m === 'PUT' && $id) {
    if (!in_array($user['role'], ['admin','head_account'])) jsonError('Tidak diizinkan', 403);
    $b = getBody();
    $fields = ['name','kode','email','phone','office','branch','referral_code','is_active'];
    $sets = []; $vals = [];
    foreach ($fields as $f) {
        if (array_key_exists($f, $b)) { $sets[] = "`$f` = ?"; $vals[] = ($b[$f] === '' ? null : $b[$f]); }
    }
    if (empty($sets)) jsonError('Tidak ada data');
    $vals[] = $id;
    $pdo->prepare('UPDATE sales SET ' . implode(',', $sets) . ' WHERE id = ?')->execute($vals);
    jsonOk(['id' => $id]);
}

jsonError('Endpoint tidak ditemukan', 404);
