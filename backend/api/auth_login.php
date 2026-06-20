<?php
require_once __DIR__ . '/../config/config.php';
handleCors();
requirePost();

$input = jsonInput();
$email = strtolower(trim($input['email'] ?? ''));
$password = $input['password'] ?? '';

if (!$email || !$password) {
    jsonResponse(['success' => false, 'message' => 'Email and password required']);
}

try {
    $user = Database::row("SELECT id, name, email, phone, city, role, password_hash FROM users WHERE email = ?", [$email]);

    if (!$user || !verifyPassword($password, $user['password_hash'])) {
        jsonResponse(['success' => false, 'message' => 'Invalid email or password']);
    }

    Database::exec("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);
    unset($user['password_hash']);

    $token = JWT::encode(['user_id' => $user['id'], 'role' => $user['role']]);

    jsonResponse(['success' => true, 'token' => $token, 'user' => $user]);

} catch (Exception $e) {
    logDebug('Login error', ['error' => $e->getMessage()]);
    jsonResponse(['success' => false, 'message' => 'Login failed. Please try again.']);
}
