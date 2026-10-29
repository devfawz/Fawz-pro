<?php
/**
 * api/influencer.php — Influencers & registrations
 * GET/POST/PUT/DELETE /api/influencer.php?scope=influencers
 * GET/PUT/DELETE      /api/influencer.php?scope=registrations
 * GET                 /api/influencer.php?export=csv&scope=X
 */
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/db.php';

$scope = $_GET['scope'] ?? 'influencers';
$m     = method();
$id    = isset($_GET['id']) ? (int)$_GET['id'] : null;
$pdo   = getDB();

// POST registrations = publik (dari halaman influencer-register)
$isPublic = ($m === 'POST' && $scope === 'registrations');
if (!$isPublic) {
    $user = requireAuth();
    if (!in_array($user['role'], ['admin','head_account','head_sales'])) {
        jsonError('Tidak diizinkan', 403);
    }
} else {
    $user = ['role' => 'public', 'name' => 'guest'];
}

// ── EXPORT ───────────────────────────────────────────────
if ($m === 'GET' && isset($_GET['export'])) {
    $table = ($scope === 'registrations') ? 'influencer_registrations' : 'influencers';
    $rows  = $pdo->query("SELECT * FROM $table ORDER BY created_at DESC")->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $table . '_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    if (!empty($rows)) { fputcsv($out, array_keys($rows[0])); foreach ($rows as $r) fputcsv($out, $r); }
    fclose($out);
    exit;
}

// ── INFLUENCERS ───────────────────────────────────────────
if ($scope === 'influencers') {
    if ($m === 'GET') {
        $where = ['1=1']; $params = [];
        if (!empty($_GET['q'])) {
            $like    = '%' . $_GET['q'] . '%';
            $where[] = '(name LIKE ? OR influencer_code LIKE ? OR email LIKE ?)';
            $params  = array_merge($params, [$like,$like,$like]);
        }
        $ws   = implode(' AND ', $where);
        $stmt = $pdo->prepare("SELECT * FROM influencers WHERE $ws ORDER BY name");
        $stmt->execute($params);
        jsonList($stmt->fetchAll());
    }

    if ($m === 'POST') {
        $b = getBody();
        if (empty($b['influencer_code']) || empty($b['name'])) jsonError('influencer_code dan name wajib');
        $fields = ['influencer_code','name','client_id','email','phone','platform','followers','status','notes'];
        $cols = []; $vals = [];
        foreach ($fields as $f) {
            if (array_key_exists($f, $b)) { $cols[] = "`$f`"; $vals[] = ($b[$f] === '' ? null : $b[$f]); }
        }
        $pdo->prepare('INSERT INTO influencers (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')')->execute($vals);
        jsonOk(['id' => $pdo->lastInsertId()], 201);
    }

    if ($m === 'PUT' && $id) {
        $b = getBody();
        $fields = ['name','client_id','email','phone','platform','followers','status','notes'];
        $sets = []; $vals = [];
        foreach ($fields as $f) {
            if (array_key_exists($f, $b)) { $sets[] = "`$f` = ?"; $vals[] = ($b[$f] === '' ? null : $b[$f]); }
        }
        if (empty($sets)) jsonError('Tidak ada data');
        $vals[] = $id;
        $pdo->prepare('UPDATE influencers SET ' . implode(',', $sets) . ' WHERE id = ?')->execute($vals);
        jsonOk(['id' => $id]);
    }

    if ($m === 'DELETE' && $id) {
        $pdo->prepare('DELETE FROM influencers WHERE id = ?')->execute([$id]);
        jsonOk(['deleted' => $id]);
    }
}

// ── INFLUENCER REGISTRATIONS ──────────────────────────────
if ($scope === 'registrations') {
    if ($m === 'GET') {
        $where = ['1=1']; $params = [];
        if (!empty($_GET['status'])) { $where[] = 'confirmed_status = ?'; $params[] = $_GET['status']; }
        $ws   = implode(' AND ', $where);
        $stmt = $pdo->prepare("SELECT * FROM influencer_registrations WHERE $ws ORDER BY created_at DESC");
        $stmt->execute($params);
        jsonList($stmt->fetchAll());
    }

    if ($m === 'POST') {
        $b = getBody();
        if (empty($b['name'])) jsonError('name wajib');
        $fields = ['influencer_code','name','email','phone','platform','followers','notes'];
        $cols = []; $vals = [];
        foreach ($fields as $f) {
            if (array_key_exists($f, $b)) { $cols[] = "`$f`"; $vals[] = ($b[$f] === '' ? null : $b[$f]); }
        }
        $pdo->prepare('INSERT INTO influencer_registrations (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')')->execute($vals);
        jsonOk(['id' => $pdo->lastInsertId()], 201);
    }

    if ($m === 'PUT' && $id) {
        $b = getBody();
        $pdo->prepare('UPDATE influencer_registrations SET confirmed_status=?, notes=? WHERE id=?')
            ->execute([$b['confirmed_status'] ?? 'pending', $b['notes'] ?? null, $id]);
        jsonOk(['id' => $id]);
    }

    if ($m === 'DELETE' && $id) {
        $pdo->prepare('DELETE FROM influencer_registrations WHERE id = ?')->execute([$id]);
        jsonOk(['deleted' => $id]);
    }
}

jsonError('Endpoint tidak ditemukan', 404);
