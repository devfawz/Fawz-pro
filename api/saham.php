<?php
/**
 * api/saham.php — Saham transaksi
 * GET  /api/saham.php               — list (filter: sales_name, tahun, bulan, q)
 * POST /api/saham.php               — create
 * PUT  /api/saham.php?id=X          — update
 * DELETE /api/saham.php?id=X        — delete
 * GET  /api/saham.php?export=csv    — export CSV
 * POST /api/saham.php?import=csv    — import CSV (bulk upsert)
 */
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/db.php';

$user = requireAuth();
$pdo  = getDB();
$m    = method();
$id   = isset($_GET['id']) ? (int)$_GET['id'] : null;

// ── EXPORT ───────────────────────────────────────────────
if ($m === 'GET' && isset($_GET['export'])) {
    $rows = $pdo->query('SELECT * FROM saham_transaksi ORDER BY tanggal DESC')->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="saham_transaksi_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    if (!empty($rows)) {
        fputcsv($out, array_keys($rows[0]));
        foreach ($rows as $r) fputcsv($out, $r);
    }
    fclose($out);
    exit;
}

// ── IMPORT CSV ────────────────────────────────────────────
if ($m === 'POST' && isset($_GET['import'])) {
    if (!in_array($user['role'], ['admin', 'head_account', 'head_sales'])) {
        jsonError('Tidak diizinkan', 403);
    }
    $file = $_FILES['file'] ?? null;
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) jsonError('File tidak valid');

    $handle  = fopen($file['tmp_name'], 'r');
    $headers = array_map(fn($h) => strtolower(trim($h)), fgetcsv($handle));
    if (!$headers) jsonError('CSV kosong');

    $allowed = ['tanggal','client_id','client_name','sales_name','kode_saham','jenis','volume','harga','nilai','fee'];
    $cols    = array_intersect($headers, $allowed);
    $colList = implode(',', array_map(fn($c) => "`$c`", $cols));
    $phList  = implode(',', array_fill(0, count($cols), '?'));

    $stmt = $pdo->prepare(
        "INSERT INTO saham_transaksi ($colList) VALUES ($phList)"
    );

    $imported = 0;
    $errors   = [];
    while (($row = fgetcsv($handle)) !== false) {
        try {
            $vals = [];
            foreach ($cols as $col) {
                $idx    = array_search($col, $headers);
                $val    = ($idx !== false && isset($row[$idx])) ? trim($row[$idx]) : null;
                $vals[] = ($val === '' ? null : $val);
            }
            $stmt->execute($vals);
            $imported++;
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
    fclose($handle);
    jsonOk(['imported' => $imported, 'errors' => array_slice($errors, 0, 10)]);
}

// ── LIST ──────────────────────────────────────────────────
if ($m === 'GET') {
    $where  = ['1=1'];
    $params = [];

    if ($user['role'] === 'sales') {
        $where[]  = 'sales_name = ?';
        $params[] = $user['name'];
    } elseif (!empty($_GET['sales_name'])) {
        $where[]  = 'sales_name = ?';
        $params[] = $_GET['sales_name'];
    }

    if (!empty($_GET['tahun'])) {
        $where[]  = 'YEAR(tanggal) = ?';
        $params[] = (int)$_GET['tahun'];
    }
    if (!empty($_GET['bulan'])) {
        $where[]  = 'MONTH(tanggal) = ?';
        $params[] = (int)$_GET['bulan'];
    }
    if (!empty($_GET['q'])) {
        $like     = '%' . $_GET['q'] . '%';
        $where[]  = '(client_id LIKE ? OR client_name LIKE ? OR kode_saham LIKE ?)';
        $params   = array_merge($params, [$like,$like,$like]);
    }

    $whereStr = implode(' AND ', $where);
    $page     = max(1, (int)($_GET['page'] ?? 1));
    $perPage  = min(1000, max(50, (int)($_GET['per_page'] ?? 100)));
    $offset   = ($page - 1) * $perPage;

    $total = $pdo->prepare("SELECT COUNT(*) FROM saham_transaksi WHERE $whereStr");
    $total->execute($params);
    $totalCount = (int)$total->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT * FROM saham_transaksi WHERE $whereStr
         ORDER BY tanggal DESC LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($params);
    jsonList($stmt->fetchAll(), $totalCount);
}

// ── CREATE ────────────────────────────────────────────────
if ($m === 'POST') {
    $b = getBody();
    if (empty($b['tanggal'])) jsonError('tanggal wajib diisi');

    $fields = ['tanggal','client_id','client_name','sales_name','kode_saham','jenis','volume','harga','nilai','fee'];
    $cols = []; $vals = [];
    foreach ($fields as $f) {
        if (array_key_exists($f, $b)) {
            $cols[] = "`$f`";
            $vals[] = ($b[$f] === '' ? null : $b[$f]);
        }
    }
    $pdo->prepare('INSERT INTO saham_transaksi (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')')->execute($vals);
    jsonOk(['id' => $pdo->lastInsertId()], 201);
}

// ── UPDATE ────────────────────────────────────────────────
if ($m === 'PUT' && $id) {
    $b = getBody();
    $fields = ['tanggal','client_id','client_name','sales_name','kode_saham','jenis','volume','harga','nilai','fee'];
    $sets = []; $vals = [];
    foreach ($fields as $f) {
        if (array_key_exists($f, $b)) {
            $sets[] = "`$f` = ?";
            $vals[] = ($b[$f] === '' ? null : $b[$f]);
        }
    }
    if (empty($sets)) jsonError('Tidak ada data');
    $vals[] = $id;
    $pdo->prepare('UPDATE saham_transaksi SET ' . implode(',', $sets) . ' WHERE id = ?')->execute($vals);
    jsonOk(['id' => $id]);
}

// ── DELETE ────────────────────────────────────────────────
if ($m === 'DELETE' && $id) {
    if (!in_array($user['role'], ['admin', 'head_account'])) jsonError('Tidak diizinkan', 403);
    $pdo->prepare('DELETE FROM saham_transaksi WHERE id = ?')->execute([$id]);
    jsonOk(['deleted' => $id]);
}

jsonError('Endpoint tidak ditemukan', 404);
