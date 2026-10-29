<?php
/**
 * api/referral.php — Referral agents, registrations, leads
 * GET  /api/referral.php?scope=agents          — list referral_agents
 * POST /api/referral.php?scope=agents          — create agent
 * PUT  /api/referral.php?scope=agents&id=X     — update agent
 * GET  /api/referral.php?scope=registrations   — list referral_registrations
 * POST /api/referral.php?scope=registrations   — create registration (publik)
 * PUT  /api/referral.php?scope=registrations&id=X — update status
 * DELETE /api/referral.php?scope=registrations&id=X — delete
 * GET  /api/referral.php?scope=landing         — list register_landing
 * GET  /api/referral.php?export=csv&scope=X    — export CSV
 * GET  /api/referral.php?scope=sync_pp         — sync dari pp_ikutin_id
 */
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/db.php';

$scope = $_GET['scope'] ?? 'agents';
$m     = method();
$id    = isset($_GET['id']) ? (int)$_GET['id'] : null;
$pdo   = getDB();

// Endpoint publik — registrasi & landing tidak butuh auth
$isPublic = ($m === 'POST' && in_array($scope, ['registrations', 'landing']));
if (!$isPublic) {
    $user = requireAuth();
} else {
    $user = ['role' => 'public', 'name' => 'guest'];
}

// ── EXPORT CSV ────────────────────────────────────────────
if ($m === 'GET' && isset($_GET['export'])) {
    $table = match($scope) {
        'registrations' => 'referral_registrations',
        'landing'       => 'register_landing',
        default         => 'referral_agents',
    };
    $rows = $pdo->query("SELECT * FROM $table ORDER BY created_at DESC")->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $table . '_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    if (!empty($rows)) {
        fputcsv($out, array_keys($rows[0]));
        foreach ($rows as $r) fputcsv($out, $r);
    }
    fclose($out);
    exit;
}

// ── SYNC FROM pp_ikutin_id ────────────────────────────────
if ($scope === 'sync_pp' && $m === 'GET') {
    if (!in_array($user['role'], ['admin', 'head_account'])) jsonError('Tidak diizinkan', 403);
    try {
        $ppPdo = getPPDB();
        // Sesuaikan nama tabel/kolom dengan struktur pp_ikutin_id
        $stmt    = $ppPdo->query('SELECT * FROM users WHERE is_active = 1 LIMIT 1000');
        $ppUsers = $stmt->fetchAll();
        $synced  = 0;
        foreach ($ppUsers as $pu) {
            $pdo->prepare(
                'INSERT INTO referral_agents (agent_code, client_name, email, phone)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE client_name=VALUES(client_name), phone=VALUES(phone)'
            )->execute([
                $pu['referral_code'] ?? (string)$pu['id'],
                $pu['name'] ?? $pu['full_name'] ?? '',
                $pu['email'] ?? '',
                $pu['phone'] ?? '',
            ]);
            $synced++;
        }
        jsonOk(['synced' => $synced]);
    } catch (Exception $e) {
        jsonError('Gagal sync dari pp_ikutin_id: ' . $e->getMessage(), 500);
    }
}

// ── REFERRAL AGENTS ───────────────────────────────────────
if ($scope === 'agents') {
    if ($m === 'GET') {
        $where  = ['1=1'];
        $params = [];
        if (!empty($_GET['agent_code'])) {
            $where[]  = 'agent_code = ?';
            $params[] = $_GET['agent_code'];
        }
        if (!empty($_GET['q'])) {
            $like     = '%' . $_GET['q'] . '%';
            $where[]  = '(agent_code LIKE ? OR client_name LIKE ? OR email LIKE ?)';
            $params   = array_merge($params, [$like, $like, $like]);
        }
        $ws   = implode(' AND ', $where);
        $stmt = $pdo->prepare("SELECT * FROM referral_agents WHERE $ws ORDER BY client_name");
        $stmt->execute($params);
        jsonList($stmt->fetchAll());
    }

    if ($m === 'POST') {
        if (!in_array($user['role'], ['admin', 'head_account', 'head_sales'])) jsonError('Tidak diizinkan', 403);
        $b = getBody();
        if (empty($b['agent_code']) || empty($b['client_name'])) jsonError('agent_code dan client_name wajib');
        $fields = ['agent_code','client_name','client_id','email','phone','fee_status','fee_below_pct','fee_target_pct','target_fee'];
        $cols = []; $vals = [];
        foreach ($fields as $f) {
            if (array_key_exists($f, $b)) { $cols[] = "`$f`"; $vals[] = ($b[$f] === '' ? null : $b[$f]); }
        }
        $pdo->prepare('INSERT INTO referral_agents (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')')->execute($vals);
        jsonOk(['id' => $pdo->lastInsertId()], 201);
    }

    if ($m === 'PUT' && $id) {
        $b = getBody();
        $fields = ['client_name','client_id','email','phone','fee_status','fee_below_pct','fee_target_pct','target_fee','is_active'];
        $sets = []; $vals = [];
        foreach ($fields as $f) {
            if (array_key_exists($f, $b)) { $sets[] = "`$f` = ?"; $vals[] = ($b[$f] === '' ? null : $b[$f]); }
        }
        if (empty($sets)) jsonError('Tidak ada data');
        $vals[] = $id;
        $pdo->prepare('UPDATE referral_agents SET ' . implode(',', $sets) . ' WHERE id = ?')->execute($vals);
        jsonOk(['id' => $id]);
    }
}

// ── REFERRAL REGISTRATIONS ────────────────────────────────
if ($scope === 'registrations') {
    if ($m === 'GET') {
        $where  = ['1=1'];
        $params = [];
        if (!empty($_GET['status']))        { $where[] = 'confirmed_status = ?'; $params[] = $_GET['status']; }
        if (!empty($_GET['referral_code'])) { $where[] = 'referral_code = ?';    $params[] = $_GET['referral_code']; }
        if (!empty($_GET['q'])) {
            $like    = '%' . $_GET['q'] . '%';
            $where[] = '(nama LIKE ? OR email LIKE ? OR phone LIKE ?)';
            $params  = array_merge($params, [$like, $like, $like]);
        }
        $ws      = implode(' AND ', $where);
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(500, max(20, (int)($_GET['per_page'] ?? 50)));
        $offset  = ($page - 1) * $perPage;
        $total   = $pdo->prepare("SELECT COUNT(*) FROM referral_registrations WHERE $ws");
        $total->execute($params);
        $stmt    = $pdo->prepare("SELECT * FROM referral_registrations WHERE $ws ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
        $stmt->execute($params);
        jsonList($stmt->fetchAll(), (int)$total->fetchColumn());
    }

    if ($m === 'POST') {
        $b = getBody();
        if (empty($b['nama'])) jsonError('nama wajib');
        $fields = ['nama','nik','email','phone','no_rdn','sekuritas','client_code','status','referral_code','influencer_code'];
        $cols = []; $vals = [];
        foreach ($fields as $f) {
            if (array_key_exists($f, $b)) { $cols[] = "`$f`"; $vals[] = ($b[$f] === '' ? null : $b[$f]); }
        }
        $pdo->prepare('INSERT INTO referral_registrations (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')')->execute($vals);
        jsonOk(['id' => $pdo->lastInsertId()], 201);
    }

    if ($m === 'PUT' && $id) {
        $b = getBody();
        $fields = ['nama','nik','email','phone','no_rdn','sekuritas','client_code','status','confirmed_status'];
        $sets = []; $vals = [];
        foreach ($fields as $f) {
            if (array_key_exists($f, $b)) { $sets[] = "`$f` = ?"; $vals[] = ($b[$f] === '' ? null : $b[$f]); }
        }
        if (empty($sets)) jsonError('Tidak ada data');
        $vals[] = $id;
        $pdo->prepare('UPDATE referral_registrations SET ' . implode(',', $sets) . ' WHERE id = ?')->execute($vals);
        jsonOk(['id' => $id]);
    }

    if ($m === 'DELETE' && $id) {
        if (!in_array($user['role'], ['admin', 'head_account', 'head_sales'])) jsonError('Tidak diizinkan', 403);
        $pdo->prepare('DELETE FROM referral_registrations WHERE id = ?')->execute([$id]);
        jsonOk(['deleted' => $id]);
    }
}

// ── REGISTER LANDING ──────────────────────────────────────
if ($scope === 'landing') {
    if ($m === 'GET') {
        $where  = ['1=1'];
        $params = [];
        if (!empty($_GET['status'])) { $where[] = 'confirmed_status = ?'; $params[] = $_GET['status']; }
        $ws   = implode(' AND ', $where);
        $stmt = $pdo->prepare("SELECT * FROM register_landing WHERE $ws ORDER BY created_at DESC");
        $stmt->execute($params);
        jsonList($stmt->fetchAll());
    }

    if ($m === 'POST') {
        $b = getBody();
        if (empty($b['nama'])) jsonError('nama wajib');
        $pdo->prepare('INSERT INTO register_landing (nama, email, phone, referral_code, notes) VALUES (?,?,?,?,?)')
            ->execute([$b['nama'], $b['email'] ?? null, $b['phone'] ?? null, $b['referral_code'] ?? null, $b['notes'] ?? null]);
        jsonOk(['id' => $pdo->lastInsertId()], 201);
    }

    if ($m === 'PUT' && $id) {
        $b = getBody();
        $pdo->prepare('UPDATE register_landing SET confirmed_status=?, notes=? WHERE id=?')
            ->execute([$b['confirmed_status'] ?? 'pending', $b['notes'] ?? null, $id]);
        jsonOk(['id' => $id]);
    }

    if ($m === 'DELETE' && $id) {
        if (!in_array($user['role'], ['admin', 'head_account', 'head_sales'])) jsonError('Tidak diizinkan', 403);
        $pdo->prepare('DELETE FROM register_landing WHERE id = ?')->execute([$id]);
        jsonOk(['deleted' => $id]);
    }
}

jsonError('Endpoint tidak ditemukan', 404);
