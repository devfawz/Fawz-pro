<?php
/**
 * api/auth.php
 * POST /api/auth.php?action=login   — login
 * POST /api/auth.php?action=logout  — logout
 * GET  /api/auth.php?action=me      — cek session
 */
require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/db.php';

$action = $_GET['action'] ?? '';

switch ($action) {

    // ── LOGIN ──────────────────────────────────────────────
    case 'login': {
        if (method() !== 'POST') jsonError('Method not allowed', 405);

        $body     = getBody();
        $username = trim($body['username'] ?? '');
        $password = $body['password'] ?? '';

        if (!$username || !$password) {
            jsonError('Username dan password wajib diisi');
        }

        $pdo  = getDB();
        $stmt = $pdo->prepare(
            'SELECT id, username, password, name, role, photo_url FROM accounts
             WHERE username = ? AND is_active = 1 LIMIT 1'
        );
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user) {
            jsonError('Username atau password salah', 401);
        }

        // Verifikasi password (bcrypt)
        if (!password_verify($password, $user['password'] ?? '')) {
            jsonError('Username atau password salah', 401);
        }

        // Buat token
        $payload = [
            'id'       => $user['id'],
            'username' => $user['username'],
            'name'     => $user['name'],
            'role'     => $user['role'],
        ];
        $token = createToken($payload);

        // Simpan hash token ke session_tokens
        $hash    = hash('sha256', $token);
        $expires = date('Y-m-d H:i:s', time() + 86400 * 30);
        $ins = $pdo->prepare(
            'INSERT INTO session_tokens (account_id, token_hash, expires_at) VALUES (?, ?, ?)'
        );
        $ins->execute([$user['id'], $hash, $expires]);

        // Buang password dari response
        unset($user['password']);
        jsonOk([
            'token' => $token,
            'user'  => $user,
        ]);
    }

    // ── ME ─────────────────────────────────────────────────
    case 'me': {
        $payload = requireAuth();
        jsonOk($payload);
    }

    // ── LOGOUT ─────────────────────────────────────────────
    case 'logout': {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            $hash  = hash('sha256', $token);
            $pdo   = getDB();
            $pdo->prepare('DELETE FROM session_tokens WHERE token_hash = ?')->execute([$hash]);
        }
        jsonOk(['message' => 'Logout berhasil']);
    }

    default:
        jsonError('Action tidak dikenal', 404);
}
