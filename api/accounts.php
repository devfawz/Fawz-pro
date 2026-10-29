<?php
/**
 * api/accounts.php — Manajemen akun user
 * GET    /api/accounts.php         — list (admin only)
 * POST   /api/accounts.php         — create akun baru
 * PUT    /api/accounts.php?id=X    — update (ganti password / data)
 * DELETE /api/accounts.php?id=X    — hapus akun
 */
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/db.php';

$user = requireAuth();
$pdo  = getDB();
$m    = method();
$id   = isset($_GET['id']) ? (int)$_GET['id'] : null;

// Hanya admin dan head_account yang bisa manage akun
if (!in_array($user['role'], ['admin','head_account'])) {
    // Sales/lainnya hanya boleh update profil sendiri
    if ($m !== 'PUT' || $id !== (int)($user['id'] ?? 0)) {
        jsonError('Tidak diizinkan', 403);
    }
}

// ── LIST ──────────────────────────────────────────────────
if ($m === 'GET') {
    $where = ['1=1']; $params = [];
    if (!empty($_GET['role'])) {
        $where[] = 'role = ?'; $params[] = $_GET['role'];
    }
    $ws   = implode(' AND ', $where);
    $stmt = $pdo->prepare("SELECT id, username, name, role, photo_url, is_active, created_at FROM accounts WHERE $ws ORDER BY name");
    $stmt->execute($params);
    jsonList($stmt->fetchAll());
}

// ── CREATE ────────────────────────────────────────────────
if ($m === 'POST') {
    if ($user['role'] !== 'admin') jsonError('Tidak diizinkan', 403);
    $b = getBody();
    foreach (['username','password','name','role'] as $req) {
        if (empty($b[$req])) jsonError("$req wajib diisi");
    }
    // Cek duplikat username
    $chk = $pdo->prepare('SELECT id FROM accounts WHERE username = ?');
    $chk->execute([$b['username']]);
    if ($chk->fetch()) jsonError('Username sudah digunakan');

    $hash = password_hash($b['password'], PASSWORD_BCRYPT);
    $pdo->prepare('INSERT INTO accounts (username, password, name, role) VALUES (?,?,?,?)')
        ->execute([$b['username'], $hash, $b['name'], $b['role']]);
    $newId = $pdo->lastInsertId();

    // Auto-create sales record jika role sales/head_sales
    if (in_array($b['role'], ['sales','head_sales'])) {
        $chkSales = $pdo->prepare('SELECT id FROM sales WHERE name = ?');
        $chkSales->execute([$b['name']]);
        if (!$chkSales->fetch()) {
            $pdo->prepare('INSERT INTO sales (name, account_id) VALUES (?,?)')
                ->execute([$b['name'], $newId]);
        }
    }

    jsonOk(['id' => $newId, 'username' => $b['username'], 'name' => $b['name'], 'role' => $b['role']], 201);
}

// ── UPDATE ────────────────────────────────────────────────
if ($m === 'PUT' && $id) {
    $b    = getBody();
    $sets = []; $vals = [];

    if (!empty($b['name']))     { $sets[] = 'name = ?';     $vals[] = $b['name']; }
    if (!empty($b['role']) && $user['role'] === 'admin') {
                                  $sets[] = 'role = ?';     $vals[] = $b['role']; }
    if (!empty($b['photo_url'])){ $sets[] = 'photo_url = ?'; $vals[] = $b['photo_url']; }
    if (isset($b['is_active']) && $user['role'] === 'admin') {
                                  $sets[] = 'is_active = ?'; $vals[] = (int)$b['is_active']; }
    if (!empty($b['password'])) {
        $sets[] = 'password = ?';
        $vals[] = password_hash($b['password'], PASSWORD_BCRYPT);
    }

    if (empty($sets)) jsonError('Tidak ada data yang diupdate');
    $vals[] = $id;
    $pdo->prepare('UPDATE accounts SET ' . implode(',', $sets) . ' WHERE id = ?')->execute($vals);
    jsonOk(['id' => $id]);
}

// ── DELETE ────────────────────────────────────────────────
if ($m === 'DELETE' && $id) {
    if ($user['role'] !== 'admin') jsonError('Tidak diizinkan', 403);
    if ($id === (int)$user['id']) jsonError('Tidak bisa hapus akun sendiri');
    $pdo->prepare('DELETE FROM accounts WHERE id = ?')->execute([$id]);
    jsonOk(['deleted' => $id]);
}

jsonError('Endpoint tidak ditemukan', 404);
