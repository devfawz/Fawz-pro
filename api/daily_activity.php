<?php
/**
 * api/daily_activity.php — Daily activity jobs & entries
 * GET/POST/PUT/DELETE /api/daily_activity.php?scope=jobs
 * GET/POST/PUT/DELETE /api/daily_activity.php?scope=entries
 * GET                 /api/daily_activity.php?export=csv&scope=X
 */
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/db.php';

$user  = requireAuth();
$pdo   = getDB();
$m     = method();
$id    = isset($_GET['id']) ? (int)$_GET['id'] : null;
$scope = $_GET['scope'] ?? 'jobs';

// ── EXPORT ───────────────────────────────────────────────
if ($m === 'GET' && isset($_GET['export'])) {
    $table = ($scope === 'entries') ? 'daily_activity_entries' : 'daily_activity_jobs';
    $rows  = $pdo->query("SELECT * FROM $table ORDER BY created_at DESC")->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $table . '_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    if (!empty($rows)) { fputcsv($out, array_keys($rows[0])); foreach ($rows as $r) fputcsv($out, $r); }
    fclose($out);
    exit;
}

// ── JOBS ──────────────────────────────────────────────────
if ($scope === 'jobs') {
    if ($m === 'GET') {
        $where = ['1=1']; $params = [];
        // Admin/head_account bisa lihat semua; lain hanya milik sendiri
        if (!in_array($user['role'], ['admin','head_account'])) {
            $where[] = 'created_by = ?'; $params[] = $user['name'];
        } elseif (!empty($_GET['created_by'])) {
            $where[] = 'created_by = ?'; $params[] = $_GET['created_by'];
        }
        if (!empty($_GET['bulan'])) {
            $where[] = 'MONTH(start_date) = ?'; $params[] = (int)$_GET['bulan'];
        }
        if (!empty($_GET['tahun'])) {
            $where[] = 'YEAR(start_date) = ?'; $params[] = (int)$_GET['tahun'];
        }
        $ws   = implode(' AND ', $where);
        $stmt = $pdo->prepare("SELECT * FROM daily_activity_jobs WHERE $ws ORDER BY start_date DESC");
        $stmt->execute($params);
        jsonList($stmt->fetchAll());
    }

    if ($m === 'POST') {
        $b = getBody();
        if (empty($b['name'])) jsonError('name wajib');
        $pdo->prepare('INSERT INTO daily_activity_jobs (name, start_date, end_date, description, created_by) VALUES (?,?,?,?,?)')
            ->execute([$b['name'], $b['start_date'] ?? null, $b['end_date'] ?? null, $b['description'] ?? null, $user['name']]);
        jsonOk(['id' => $pdo->lastInsertId()], 201);
    }

    if ($m === 'PUT' && $id) {
        $b = getBody();
        $fields = ['name','start_date','end_date','description'];
        $sets = []; $vals = [];
        foreach ($fields as $f) {
            if (array_key_exists($f, $b)) { $sets[] = "`$f` = ?"; $vals[] = ($b[$f] === '' ? null : $b[$f]); }
        }
        if (empty($sets)) jsonError('Tidak ada data');
        $vals[] = $id;
        $pdo->prepare('UPDATE daily_activity_jobs SET ' . implode(',', $sets) . ' WHERE id = ?')->execute($vals);
        jsonOk(['id' => $id]);
    }

    if ($m === 'DELETE' && $id) {
        $pdo->prepare('DELETE FROM daily_activity_entries WHERE job_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM daily_activity_jobs WHERE id = ?')->execute([$id]);
        jsonOk(['deleted' => $id]);
    }
}

// ── ENTRIES ───────────────────────────────────────────────
if ($scope === 'entries') {
    if ($m === 'GET') {
        $jobId = (int)($_GET['job_id'] ?? 0);
        if (!$jobId) jsonError('job_id wajib');
        $stmt = $pdo->prepare('SELECT * FROM daily_activity_entries WHERE job_id = ? ORDER BY entry_date DESC');
        $stmt->execute([$jobId]);
        jsonList($stmt->fetchAll());
    }

    if ($m === 'POST') {
        $b = getBody();
        if (empty($b['job_id']) || empty($b['entry_date'])) jsonError('job_id dan entry_date wajib');
        $pdo->prepare('INSERT INTO daily_activity_entries (job_id, entry_date, status, notes, created_by) VALUES (?,?,?,?,?)')
            ->execute([(int)$b['job_id'], $b['entry_date'], $b['status'] ?? 'inprogress', $b['notes'] ?? null, $user['name']]);
        jsonOk(['id' => $pdo->lastInsertId()], 201);
    }

    if ($m === 'PUT' && $id) {
        $b = getBody();
        $fields = ['entry_date','status','notes'];
        $sets = []; $vals = [];
        foreach ($fields as $f) {
            if (array_key_exists($f, $b)) { $sets[] = "`$f` = ?"; $vals[] = ($b[$f] === '' ? null : $b[$f]); }
        }
        if (empty($sets)) jsonError('Tidak ada data');
        $vals[] = $id;
        $pdo->prepare('UPDATE daily_activity_entries SET ' . implode(',', $sets) . ' WHERE id = ?')->execute($vals);
        jsonOk(['id' => $id]);
    }

    if ($m === 'DELETE' && $id) {
        $pdo->prepare('DELETE FROM daily_activity_entries WHERE id = ?')->execute([$id]);
        jsonOk(['deleted' => $id]);
    }
}

jsonError('Endpoint tidak ditemukan', 404);
