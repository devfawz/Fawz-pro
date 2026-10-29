<?php
/**
 * api/portfolio.php — Customer portfolio & transactions
 * GET/POST/PUT/DELETE /api/portfolio.php?scope=portfolio
 * GET/POST/DELETE     /api/portfolio.php?scope=transactions
 * GET                 /api/portfolio.php?export=csv&scope=X&customer_id=X
 */
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/db.php';

$user  = requireAuth();
$pdo   = getDB();
$m     = method();
$id    = isset($_GET['id']) ? (int)$_GET['id'] : null;
$scope = $_GET['scope'] ?? 'portfolio';

// ── EXPORT ───────────────────────────────────────────────
if ($m === 'GET' && isset($_GET['export'])) {
    $cid   = (int)($_GET['customer_id'] ?? 0);
    $table = ($scope === 'transactions') ? 'portfolio_transactions' : 'customer_portfolio';
    $where = $cid ? 'WHERE customer_id = ' . $cid : '';
    $rows  = $pdo->query("SELECT * FROM $table $where ORDER BY id DESC")->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $table . '_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    if (!empty($rows)) { fputcsv($out, array_keys($rows[0])); foreach ($rows as $r) fputcsv($out, $r); }
    fclose($out);
    exit;
}

// ── PORTFOLIO ─────────────────────────────────────────────
if ($scope === 'portfolio') {
    if ($m === 'GET') {
        $cid = (int)($_GET['customer_id'] ?? 0);
        if (!$cid) jsonError('customer_id wajib');
        $stmt = $pdo->prepare('SELECT * FROM customer_portfolio WHERE customer_id = ? ORDER BY kode_saham');
        $stmt->execute([$cid]);
        jsonList($stmt->fetchAll());
    }

    if ($m === 'POST') {
        $b = getBody();
        if (empty($b['customer_id']) || empty($b['kode_saham'])) jsonError('customer_id dan kode_saham wajib');
        $fields = ['customer_id','client_id','kode_saham','jenis','qty_lot','buy_price','current_price','cash_balance','buy_fee','sell_fee'];
        $cols = []; $vals = [];
        foreach ($fields as $f) {
            if (array_key_exists($f, $b)) { $cols[] = "`$f`"; $vals[] = ($b[$f] === '' ? null : $b[$f]); }
        }
        $pdo->prepare('INSERT INTO customer_portfolio (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')')->execute($vals);
        jsonOk(['id' => $pdo->lastInsertId()], 201);
    }

    if ($m === 'PUT' && $id) {
        $b = getBody();
        $fields = ['kode_saham','jenis','qty_lot','buy_price','current_price','cash_balance','buy_fee','sell_fee'];
        $sets = []; $vals = [];
        foreach ($fields as $f) {
            if (array_key_exists($f, $b)) { $sets[] = "`$f` = ?"; $vals[] = ($b[$f] === '' ? null : $b[$f]); }
        }
        if (empty($sets)) jsonError('Tidak ada data');
        $vals[] = $id;
        $pdo->prepare('UPDATE customer_portfolio SET ' . implode(',', $sets) . ' WHERE id = ?')->execute($vals);
        jsonOk(['id' => $id]);
    }

    if ($m === 'DELETE' && $id) {
        $pdo->prepare('DELETE FROM customer_portfolio WHERE id = ?')->execute([$id]);
        jsonOk(['deleted' => $id]);
    }
}

// ── TRANSACTIONS ──────────────────────────────────────────
if ($scope === 'transactions') {
    if ($m === 'GET') {
        $cid = (int)($_GET['customer_id'] ?? 0);
        if (!$cid) jsonError('customer_id wajib');
        $stmt = $pdo->prepare('SELECT * FROM portfolio_transactions WHERE customer_id = ? ORDER BY tanggal DESC');
        $stmt->execute([$cid]);
        jsonList($stmt->fetchAll());
    }

    if ($m === 'POST') {
        $b = getBody();
        if (empty($b['customer_id']) || empty($b['jenis']) || empty($b['tanggal'])) {
            jsonError('customer_id, jenis, dan tanggal wajib');
        }
        $fields = ['portfolio_id','customer_id','client_id','kode_saham','jenis','qty_lot','price','nilai','fee','notes','tanggal'];
        $cols = []; $vals = [];
        foreach ($fields as $f) {
            if (array_key_exists($f, $b)) { $cols[] = "`$f`"; $vals[] = ($b[$f] === '' ? null : $b[$f]); }
        }
        $pdo->prepare('INSERT INTO portfolio_transactions (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')')->execute($vals);
        jsonOk(['id' => $pdo->lastInsertId()], 201);
    }

    if ($m === 'DELETE' && $id) {
        $pdo->prepare('DELETE FROM portfolio_transactions WHERE id = ?')->execute([$id]);
        jsonOk(['deleted' => $id]);
    }
}

jsonError('Endpoint tidak ditemukan', 404);
