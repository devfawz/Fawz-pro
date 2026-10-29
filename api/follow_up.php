<?php
/**
 * api/follow_up.php — Follow up customers
 * GET    /api/follow_up.php            — list
 * POST   /api/follow_up.php            — create
 * PUT    /api/follow_up.php?id=X       — update
 * DELETE /api/follow_up.php?id=X       — delete
 * GET    /api/follow_up.php?export=csv — export CSV
 * POST   /api/follow_up.php?import=csv — import CSV
 */
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/db.php';

$user = requireAuth();
$pdo  = getDB();
$m    = method();
$id   = isset($_GET['id']) ? (int)$_GET['id'] : null;

$FIELDS = ['email','nama','client_id','rdn','amount','porto','total_nilai_porto','phone','alamat','status','sales_name'];

// ── EXPORT ───────────────────────────────────────────────
if ($m === 'GET' && isset($_GET['export'])) {
    $rows = $pdo->query('SELECT * FROM follow_up_customers ORDER BY nama')->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="follow_up_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    if (!empty($rows)) {
        fputcsv($out, array_keys($rows[0]));
        foreach ($rows as $r) fputcsv($out, $r);
    }
    fclose($out);
    exit;
}

// ── IMPORT ────────────────────────────────────────────────
if ($m === 'POST' && isset($_GET['import'])) {
    if (!in_array($user['role'], ['admin','head_account'])) jsonError('Tidak diizinkan', 403);
    $file = $_FILES['file'] ?? null;
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) jsonError('File tidak valid');

    $handle  = fopen($file['tmp_name'], 'r');
    $headers = array_map(fn($h) => strtolower(trim($h)), fgetcsv($handle));

    $cols = array_intersect($headers, $FIELDS);
    $stmt = $pdo->prepare(
        'INSERT INTO follow_up_customers (' . implode(',', array_map(fn($c) => "`$c`", $cols)) . ') VALUES (' .
        implode(',', array_fill(0, count($cols), '?')) . ')'
    );

    $imported = 0;
    while (($row = fgetcsv($handle)) !== false) {
        $vals = [];
        foreach ($cols as $col) {
            $idx    = array_search($col, $headers);
            $val    = ($idx !== false && isset($row[$idx])) ? trim($row[$idx]) : null;
            $vals[] = ($val === '' ? null : $val);
        }
        $stmt->execute($vals);
        $imported++;
    }
    fclose($handle);
    jsonOk(['imported' => $imported]);
}

// ── LIST ──────────────────────────────────────────────────
if ($m === 'GET') {
    $where = ['1=1']; $params = [];
    if ($user['role'] === 'sales') {
        $where[] = 'sales_name = ?'; $params[] = $user['name'];
    }
    if (!empty($_GET['status'])) { $where[] = 'status = ?'; $params[] = $_GET['status']; }
    if (!empty($_GET['q'])) {
        $like = '%' . $_GET['q'] . '%';
        $where[] = '(nama LIKE ? OR client_id LIKE ? OR email LIKE ? OR phone LIKE ?)';
        $params  = array_merge($params, [$like,$like,$like,$like]);
    }
    $ws      = implode(' AND ', $where);
    $page    = max(1, (int)($_GET['page'] ?? 1));
    $perPage = min(500, max(20, (int)($_GET['per_page'] ?? 50)));
    $offset  = ($page - 1) * $perPage;

    $total = $pdo->prepare("SELECT COUNT(*) FROM follow_up_customers WHERE $ws");
    $total->execute($params);

    $stmt = $pdo->prepare("SELECT * FROM follow_up_customers WHERE $ws ORDER BY nama ASC LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);
    jsonList($stmt->fetchAll(), (int)$total->fetchColumn());
}

// ── CREATE ────────────────────────────────────────────────
if ($m === 'POST') {
    $b = getBody();
    $cols = []; $vals = [];
    foreach ($FIELDS as $f) {
        if (array_key_exists($f, $b)) { $cols[] = "`$f`"; $vals[] = ($b[$f] === '' ? null : $b[$f]); }
    }
    if (empty($cols)) jsonError('Data tidak valid');
    $pdo->prepare('INSERT INTO follow_up_customers (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')')->execute($vals);
    jsonOk(['id' => $pdo->lastInsertId()], 201);
}

// ── UPDATE ────────────────────────────────────────────────
if ($m === 'PUT' && $id) {
    $b = getBody();
    $sets = []; $vals = [];
    foreach ($FIELDS as $f) {
        if (array_key_exists($f, $b)) { $sets[] = "`$f` = ?"; $vals[] = ($b[$f] === '' ? null : $b[$f]); }
    }
    if (empty($sets)) jsonError('Tidak ada data');
    $vals[] = $id;
    $pdo->prepare('UPDATE follow_up_customers SET ' . implode(',', $sets) . ' WHERE id = ?')->execute($vals);
    jsonOk(['id' => $id]);
}

// ── DELETE ────────────────────────────────────────────────
if ($m === 'DELETE' && $id) {
    if (!in_array($user['role'], ['admin','head_account'])) jsonError('Tidak diizinkan', 403);
    $pdo->prepare('DELETE FROM follow_up_customers WHERE id = ?')->execute([$id]);
    jsonOk(['deleted' => $id]);
}

jsonError('Endpoint tidak ditemukan', 404);
