<?php
/**
 * api/jobs.php — Job tasks & replies
 * GET    /api/jobs.php                      — list tasks
 * POST   /api/jobs.php                      — create task
 * PUT    /api/jobs.php?id=X                 — update task
 * DELETE /api/jobs.php?id=X                 — delete task
 * GET    /api/jobs.php?scope=replies&task=X — list replies
 * POST   /api/jobs.php?scope=replies        — create reply
 * DELETE /api/jobs.php?scope=replies&id=X   — delete reply
 * GET    /api/jobs.php?scope=pending_count  — badge count
 */
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/db.php';

$user  = requireAuth();
$pdo   = getDB();
$m     = method();
$id    = isset($_GET['id'])   ? (int)$_GET['id']   : null;
$scope = $_GET['scope'] ?? 'tasks';

// ── PENDING COUNT (untuk navbar badge) ───────────────────
if ($scope === 'pending_count') {
    $where = ['status = ?']; $params = ['pending'];
    if ($user['role'] !== 'head_account') {
        $where[] = 'assigned_role = ?'; $params[] = $user['role'];
    }
    $ws   = implode(' AND ', $where);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM job_tasks WHERE $ws");
    $stmt->execute($params);
    jsonOk(['count' => (int)$stmt->fetchColumn()]);
}

// ── REPLIES ───────────────────────────────────────────────
if ($scope === 'replies') {
    if ($m === 'GET') {
        $taskId = (int)($_GET['task'] ?? 0);
        if (!$taskId) jsonError('task ID wajib');
        $stmt = $pdo->prepare('SELECT * FROM job_task_replies WHERE task_id = ? ORDER BY created_at ASC');
        $stmt->execute([$taskId]);
        jsonList($stmt->fetchAll());
    }
    if ($m === 'POST') {
        $b = getBody();
        if (empty($b['task_id']) || empty($b['message'])) jsonError('task_id dan message wajib');
        $pdo->prepare('INSERT INTO job_task_replies (task_id, message, created_by, role) VALUES (?,?,?,?)')
            ->execute([(int)$b['task_id'], $b['message'], $user['name'], $user['role']]);
        jsonOk(['id' => $pdo->lastInsertId()], 201);
    }
    if ($m === 'DELETE' && $id) {
        if (!in_array($user['role'], ['admin','head_account'])) jsonError('Tidak diizinkan', 403);
        $pdo->prepare('DELETE FROM job_task_replies WHERE id = ?')->execute([$id]);
        jsonOk(['deleted' => $id]);
    }
}

// ── TASKS LIST ────────────────────────────────────────────
if ($m === 'GET') {
    $where = ['1=1']; $params = [];
    if ($user['role'] !== 'head_account') {
        $where[] = 'assigned_role = ?'; $params[] = $user['role'];
    }
    if (!empty($_GET['status'])) { $where[] = 'status = ?'; $params[] = $_GET['status']; }
    if (!empty($_GET['priority'])) { $where[] = 'priority = ?'; $params[] = $_GET['priority']; }

    $ws   = implode(' AND ', $where);
    $stmt = $pdo->prepare("SELECT * FROM job_tasks WHERE $ws ORDER BY created_at DESC");
    $stmt->execute($params);
    jsonList($stmt->fetchAll());
}

// ── CREATE ────────────────────────────────────────────────
if ($m === 'POST') {
    if (!in_array($user['role'], ['admin','head_account'])) jsonError('Tidak diizinkan', 403);
    $b = getBody();
    if (empty($b['title'])) jsonError('title wajib');
    $pdo->prepare('INSERT INTO job_tasks (title,description,assigned_role,assigned_to,priority,due_date,created_by) VALUES (?,?,?,?,?,?,?)')
        ->execute([$b['title'],$b['description'] ?? null,$b['assigned_role'] ?? null,$b['assigned_to'] ?? null,$b['priority'] ?? 'medium',$b['due_date'] ?? null,$user['name']]);
    jsonOk(['id' => $pdo->lastInsertId()], 201);
}

// ── UPDATE ────────────────────────────────────────────────
if ($m === 'PUT' && $id) {
    $b = getBody();
    $fields = ['title','description','assigned_role','assigned_to','status','priority','due_date','notes'];
    $sets = []; $vals = [];
    foreach ($fields as $f) {
        if (array_key_exists($f, $b)) { $sets[] = "`$f` = ?"; $vals[] = ($b[$f] === '' ? null : $b[$f]); }
    }
    // Kalau set done, catat waktu dan siapa
    if (isset($b['status']) && $b['status'] === 'done') {
        $sets[] = 'done_at = ?'; $vals[] = date('Y-m-d H:i:s');
        $sets[] = 'done_by = ?'; $vals[] = $user['name'];
    }
    if (empty($sets)) jsonError('Tidak ada data');
    $vals[] = $id;
    $pdo->prepare('UPDATE job_tasks SET ' . implode(',', $sets) . ' WHERE id = ?')->execute($vals);
    jsonOk(['id' => $id]);
}

// ── DELETE ────────────────────────────────────────────────
if ($m === 'DELETE' && $id) {
    if (!in_array($user['role'], ['admin','head_account'])) jsonError('Tidak diizinkan', 403);
    $pdo->prepare('DELETE FROM job_task_replies WHERE task_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM job_tasks WHERE id = ?')->execute([$id]);
    jsonOk(['deleted' => $id]);
}

jsonError('Endpoint tidak ditemukan', 404);
