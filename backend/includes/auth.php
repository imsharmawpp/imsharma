<?php
/**
 * Authentication Helpers (JWT + Sessions)
 *
 * Lightweight JWT implementation - no external dependencies needed.
 */

class JWT {
    public static function encode($payload) {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload['iat'] = time();
        $payload['exp'] = time() + JWT_EXPIRY;
        $payloadJson = json_encode($payload);

        $base64Header = self::base64UrlEncode($header);
        $base64Payload = self::base64UrlEncode($payloadJson);

        $signature = hash_hmac('sha256', $base64Header . '.' . $base64Payload, JWT_SECRET, true);
        $base64Signature = self::base64UrlEncode($signature);

        return $base64Header . '.' . $base64Payload . '.' . $base64Signature;
    }

    public static function decode($token) {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;

        list($headerB64, $payloadB64, $signatureB64) = $parts;

        $signature = hash_hmac('sha256', $headerB64 . '.' . $payloadB64, JWT_SECRET, true);
        $expectedSig = self::base64UrlEncode($signature);

        if (!hash_equals($expectedSig, $signatureB64)) return null;

        $payload = json_decode(self::base64UrlDecode($payloadB64), true);
        if (!$payload) return null;

        if (isset($payload['exp']) && $payload['exp'] < time()) return null;

        return $payload;
    }

    private static function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode($data) {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
    }
}

/**
 * Get authenticated user from request (or null)
 */
function getAuthUser() {
    $headers = function_exists('apache_request_headers') ? apache_request_headers() : $_SERVER;
    $auth = $headers['Authorization'] ?? $headers['HTTP_AUTHORIZATION'] ?? '';
    if (!$auth || !preg_match('/Bearer\s+(.+)/i', $auth, $m)) return null;
    $payload = JWT::decode($m[1]);
    if (!$payload || empty($payload['user_id'])) return null;
    try {
        return Database::row("SELECT id, name, email, phone, city, role FROM users WHERE id = ?", [$payload['user_id']]);
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Require authentication or fail
 */
function requireAuth() {
    $user = getAuthUser();
    if (!$user) {
        jsonResponse(['success' => false, 'message' => 'Authentication required'], 401);
    }
    return $user;
}

/**
 * Require admin role or fail
 */
function requireAdmin() {
    $user = requireAuth();
    if (($user['role'] ?? 'user') !== 'admin') {
        jsonResponse(['success' => false, 'message' => 'Admin access required'], 403);
    }
    return $user;
}

/**
 * Hash password
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

/**
 * Verify password
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Admin session helpers (simple session-based for admin panel)
 */
function startAdminSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function adminLogin($userId) {
    startAdminSession();
    $_SESSION['admin_id'] = $userId;
    $_SESSION['admin_login_time'] = time();
}

function adminLogout() {
    startAdminSession();
    $_SESSION = [];
    session_destroy();
}

function isAdminLoggedIn() {
    startAdminSession();
    if (empty($_SESSION['admin_id'])) return false;
    // 8 hour session
    if (time() - ($_SESSION['admin_login_time'] ?? 0) > 8 * 3600) {
        adminLogout();
        return false;
    }
    return true;
}

function getCurrentAdmin() {
    if (!isAdminLoggedIn()) return null;
    try {
        return Database::row("SELECT id, name, email, role FROM users WHERE id = ? AND role = 'admin'", [$_SESSION['admin_id']]);
    } catch (Exception $e) {
        return null;
    }
}

function requireAdminLogin() {
    if (!isAdminLoggedIn() || !getCurrentAdmin()) {
        header('Location: index.php');
        exit;
    }
}
