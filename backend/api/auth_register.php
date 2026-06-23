<?php
require_once __DIR__ . '/../config/config.php';
handleCors();
requirePost();

$input = jsonInput();
$name = clean($input['name'] ?? '');
$email = strtolower(trim($input['email'] ?? ''));
$phone = preg_replace('/\D/', '', $input['phone'] ?? '');
$password = $input['password'] ?? '';

if (!$name || !$email || !$password) {
    jsonResponse(['success' => false, 'message' => 'Name, email, and password are required']);
}
if (!isValidEmail($email)) {
    jsonResponse(['success' => false, 'message' => 'Invalid email address']);
}
if ($phone && !isValidPhone($phone)) {
    jsonResponse(['success' => false, 'message' => 'Invalid phone number']);
}
if (strlen($password) < 6) {
    jsonResponse(['success' => false, 'message' => 'Password must be at least 6 characters']);
}

try {
    $existing = Database::row("SELECT id FROM users WHERE email = ?", [$email]);
    if ($existing) {
        jsonResponse(['success' => false, 'message' => 'Email already registered. Please login.']);
    }

    $userId = Database::insert('users', [
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'password_hash' => hashPassword($password),
        'role' => 'user'
    ]);

    $user = Database::row("SELECT id, name, email, phone, role FROM users WHERE id = ?", [$userId]);
    $token = JWT::encode(['user_id' => $userId, 'role' => 'user']);

    // Welcome email (best-effort)
    @sendEmail($email, 'Welcome to VastuKundali!', "
        <h2>Welcome, {$name}!</h2>
        <p>Thank you for joining VastuKundali. Your account has been created successfully.</p>
        <p>You can now generate AI-powered Vastu reports for just ₹99 and shop authentic remedies.</p>
        <p><a href='" . SITE_URL . "/frontend/pages/upload.html'>Generate Your First Report</a></p>
    ");

    jsonResponse(['success' => true, 'token' => $token, 'user' => $user]);

} catch (Exception $e) {
    logDebug('Register error', ['error' => $e->getMessage()]);
    jsonResponse(['success' => false, 'message' => 'Registration failed. Please try again.']);
}
