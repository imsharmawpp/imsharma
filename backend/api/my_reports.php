<?php
require_once __DIR__ . '/../config/config.php';
handleCors();

$user = getAuthUser();

try {
    if ($user) {
        $reports = Database::all(
            "SELECT id, direction, overall_score, status, created_at FROM reports WHERE user_id = ? ORDER BY id DESC LIMIT 50",
            [$user['id']]
        );
    } else {
        // Allow lookup by email param (for guests with shared link)
        $email = strtolower(trim($_GET['email'] ?? ''));
        if (!$email) {
            jsonResponse(['success' => true, 'reports' => []]);
        }
        $reports = Database::all(
            "SELECT id, direction, overall_score, status, created_at FROM reports WHERE customer_email = ? ORDER BY id DESC LIMIT 50",
            [$email]
        );
    }

    jsonResponse(['success' => true, 'reports' => $reports]);

} catch (Exception $e) {
    jsonResponse(['success' => true, 'reports' => []]);
}
