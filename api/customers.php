<?php
/**
 * api/customers.php — CRUD + import/export customers
 * GET    /api/customers.php               — list (with filter & pagination)
 * GET    /api/customers.php?id=X          — detail
 * POST   /api/customers.php               — create
 * PUT    /api/customers.php?id=X          — update
 * DELETE /api/customers.php?id=X          — delete
 * DELETE /api/customers.php?ids=1,2,3     — bulk delete
 * GET    /api/customers.php?export=csv    — export CSV
 * POST   /api/customers.php?import=csv    — import CSV
 */
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/db.php';

$user = requireAuth();
$pdo  = getDB();
$m    = method();

// ── Securities ────────────────────────────────────────────
// 1 customer = 1 sekuritas; disimpan sebagai kolom langsung di tabel customers.
const SEC_FIELDS = ['sekuritas_name','rdn_bank_name','rdn_account_no','sec_client_id',
                    'sid','personal_bank_name','personal_account_no','personal_account_name'];

// Bentuk ulang row customer -> tambah field 'securities' array (kompat frontend).
// Frontend (mapCustomerRow) membaca r.securities sebagai array berisi 1 objek.
function attachSecurities(array $row): array {
    $hasData = false;
    foreach (SEC_FIELDS as $f) {
        if ($f === 'sekuritas_name') continue;
        if (!empty($row[$f])) { $hasData = true; break; }
    }
    if ($hasData || !empty($row['sekuritas_name'])) {
        $sec = [];
        foreach (SEC_FIELDS as $f) $sec[$f] = $row[$f] ?? null;
        $sec['sort_order'] = 0;
        $row['securities'] = [$sec];
    } else {
        $row['securities'] = [];
    }
    return $row;
}

// Ambil nilai securities[0] dari body (frontend kirim nested array) -> map flat.
function securitiesFromBody(array $b): array {
    $src = [];
    if (isset($b['securities']) && is_array($b['securities']) && isset($b['securities'][0]) && is_array($b['securities'][0])) {
        $src = $b['securities'][0];
    }
    $out = [];
    foreach (SEC_FIELDS as $f) {
        if (array_key_exists($f, $src)) {
            $out[$f] = ($src[$f] === '' ? null : $src[$f]);
        }
    }
    return $out;
}

// ── EXPORT CSV ────────────────────────────────────────────
if ($m === 'GET' && isset($_GET['export'])) {
    $rows = $pdo->query('SELECT * FROM customers ORDER BY client_name')->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="customers_' . date('Ymd') . '.csv"');
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
    if (!in_array($user['role'], ['admin', 'head_account'])) {
        jsonError('Tidak diizinkan', 403);
    }
    $file = $_FILES['file'] ?? null;
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) jsonError('File tidak valid');

    $handle  = fopen($file['tmp_name'], 'r');
    $headers = fgetcsv($handle);
    if (!$headers) jsonError('CSV kosong atau tidak valid');

    // Normalisasi header
    $headers = array_map(fn($h) => strtolower(trim($h)), $headers);

    $allowed = ['fawz_id','client_id','client_name','ktp_number','birth_date','npwp','email','phone',
                'occupation','company_name','nature_of_business','position','address',
                'ksei_single_id','ksei_sub_account_no','kpei_sub_account_no',
                'stp_ksei_sub_account_no','stp_kpei_sub_account_no',
                'sales_person_name','referral_agents','office_name','department_id',
                'client_status','client_status_description','created_date','active_date','closed_date',
                // kolom securities (1 customer = 1 sekuritas), ikut langsung ke tabel customers
                'sekuritas_name','rdn_bank_name','rdn_account_no','sec_client_id','sid',
                'personal_bank_name','personal_account_no','personal_account_name'];

    $cols    = array_values(array_intersect($allowed, $headers));
    $colList = implode(',', array_map(fn($c) => "`$c`", $cols));
    $phList  = implode(',', array_fill(0, count($cols), '?'));

    // Semua kolom customer diupdate saat client_id duplikat (bukan hanya client_name)
    $updateCols = array_filter($cols, fn($c) => $c !== 'client_id');
    $updateSet  = implode(',', array_map(fn($c) => "`$c` = VALUES(`$c`)", $updateCols));

    $stmt = $pdo->prepare(
        "INSERT INTO customers ($colList) VALUES ($phList)" .
        ($updateSet ? " ON DUPLICATE KEY UPDATE $updateSet" : '')
    );

    // Helper: ambil nilai sel berdasar nama kolom
    $cellOf = function(array $row, string $col) use ($headers) {
        $idx = array_search($col, $headers);
        if ($idx === false || !isset($row[$idx])) return null;
        $v = trim($row[$idx]);
        return $v === '' ? null : $v;
    };

    $imported = 0;
    $errors   = [];
    while (($row = fgetcsv($handle)) !== false) {
        try {
            $vals = [];
            foreach ($cols as $col) $vals[] = $cellOf($row, $col);
            $stmt->execute($vals);
            $imported++;
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
    fclose($handle);
    jsonOk(['imported' => $imported, 'errors' => array_slice($errors, 0, 10)]);
}

// ── DELETE BULK ───────────────────────────────────────────
if ($m === 'DELETE' && isset($_GET['ids'])) {
    if (!in_array($user['role'], ['admin', 'head_account'])) {
        jsonError('Tidak diizinkan', 403);
    }
    $ids = array_filter(array_map('intval', explode(',', $_GET['ids'])));
    if (empty($ids)) jsonError('IDs tidak valid');
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    $pdo->prepare("DELETE FROM customers WHERE id IN ($ph)")->execute($ids);
    jsonOk(['deleted' => count($ids)]);
}

// ── SINGLE ID ─────────────────────────────────────────────
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if ($m === 'GET' && $id) {
    $stmt = $pdo->prepare('SELECT * FROM customers WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) jsonError('Customer tidak ditemukan', 404);
    jsonOk(attachSecurities($row));
}

// ── LIST ──────────────────────────────────────────────────
if ($m === 'GET') {
    $where  = ['1=1'];
    $params = [];

    // Filter status
    if (!empty($_GET['status'])) {
        $where[]  = 'client_status = ?';
        $params[] = $_GET['status'];
    }
    // Filter sales
    if (!empty($_GET['sales'])) {
        $where[]  = 'sales_person_name = ?';
        $params[] = $_GET['sales'];
    }
    // Filter office
    if (!empty($_GET['office'])) {
        $where[]  = 'office_name = ?';
        $params[] = $_GET['office'];
    }
    // Role-based: sales hanya lihat customer mereka sendiri
    if ($user['role'] === 'sales') {
        $where[]  = 'sales_person_name = ?';
        $params[] = $user['name'];
    }
    // Search
    if (!empty($_GET['q'])) {
        $like     = '%' . $_GET['q'] . '%';
        $where[]  = '(client_id LIKE ? OR client_name LIKE ? OR ksei_single_id LIKE ? OR email LIKE ? OR npwp LIKE ?)';
        $params   = array_merge($params, [$like,$like,$like,$like,$like]);
    }

    $whereStr = implode(' AND ', $where);
    $page     = max(1, (int)($_GET['page'] ?? 1));
    $perPage  = min(100000, max(10, (int)($_GET['per_page'] ?? 50)));
    $offset   = ($page - 1) * $perPage;

    $total = $pdo->prepare("SELECT COUNT(*) FROM customers WHERE $whereStr");
    $total->execute($params);
    $totalCount = (int)$total->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT * FROM customers WHERE $whereStr
         ORDER BY client_name ASC LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Lampirkan securities dari kolom customer sendiri
    foreach ($rows as &$r) {
        $r = attachSecurities($r);
    }
    unset($r);

    jsonList($rows, $totalCount);
}

// ── CREATE ────────────────────────────────────────────────
if ($m === 'POST') {
    $b = getBody();
    if (empty($b['client_name'])) jsonError('client_name wajib diisi');

    $fields = ['fawz_id','client_id','client_name','ktp_number','birth_date','npwp','email','phone',
               'occupation','company_name','nature_of_business','position','address',
               'ksei_single_id','ksei_sub_account_no','kpei_sub_account_no',
               'stp_ksei_sub_account_no','stp_kpei_sub_account_no',
               'sales_person_id','sales_person_name','referral_agents','office_id','office_name',
               'department_id','client_status','client_status_description',
               'created_date','active_date','closed_date'];

    $cols   = [];
    $vals   = [];
    foreach ($fields as $f) {
        if (array_key_exists($f, $b)) {
            $cols[] = "`$f`";
            $vals[] = ($b[$f] === '' ? null : $b[$f]);
        }
    }

    // Securities (nested array dari frontend) -> jadi kolom langsung
    foreach (securitiesFromBody($b) as $f => $v) {
        $cols[] = "`$f`";
        $vals[] = $v;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO customers (' . implode(',', $cols) . ') VALUES (' .
        implode(',', array_fill(0, count($cols), '?')) . ')'
    );
    $stmt->execute($vals);
    $newId = (int)$pdo->lastInsertId();

    $row = $pdo->prepare('SELECT * FROM customers WHERE id = ?');
    $row->execute([$newId]);
    jsonOk(attachSecurities($row->fetch()), 201);
}

// ── UPDATE ────────────────────────────────────────────────
if ($m === 'PUT' && $id) {
    $b = getBody();
    $fields = ['fawz_id','client_id','client_name','ktp_number','birth_date','npwp','email','phone',
               'occupation','company_name','nature_of_business','position','address',
               'ksei_single_id','ksei_sub_account_no','kpei_sub_account_no',
               'stp_ksei_sub_account_no','stp_kpei_sub_account_no',
               'sales_person_id','sales_person_name','referral_agents','office_id','office_name',
               'department_id','client_status','client_status_description',
               'created_date','active_date','closed_date'];

    $sets = [];
    $vals = [];
    foreach ($fields as $f) {
        if (array_key_exists($f, $b)) {
            $sets[] = "`$f` = ?";
            $vals[] = ($b[$f] === '' ? null : $b[$f]);
        }
    }

    // Securities (nested array dari frontend) -> jadi kolom langsung
    if (isset($b['securities']) && is_array($b['securities'])) {
        foreach (securitiesFromBody($b) as $f => $v) {
            $sets[] = "`$f` = ?";
            $vals[] = $v;
        }
    }

    if (empty($sets)) jsonError('Tidak ada data yang diupdate');
    $vals[] = $id;
    $pdo->prepare('UPDATE customers SET ' . implode(',', $sets) . ' WHERE id = ?')->execute($vals);

    $row = $pdo->prepare('SELECT * FROM customers WHERE id = ?');
    $row->execute([$id]);
    jsonOk(attachSecurities($row->fetch()));
}

// ── DELETE ────────────────────────────────────────────────
if ($m === 'DELETE' && $id) {
    if (!in_array($user['role'], ['admin', 'head_account'])) {
        jsonError('Tidak diizinkan', 403);
    }
    $pdo->prepare('DELETE FROM customers WHERE id = ?')->execute([$id]);
    jsonOk(['deleted' => $id]);
}

jsonError('Endpoint tidak ditemukan', 404);
