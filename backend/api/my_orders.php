<?php
require_once __DIR__ . '/../config/config.php';
handleCors();

$user = getAuthUser();

try {
    if ($user) {
        $orders = Database::all(
            "SELECT id, items_count, amount, status, created_at FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 50",
            [$user['id']]
        );
    } else {
        $email = strtolower(trim($_GET['email'] ?? ''));
        if (!$email) {
            jsonResponse(['success' => true, 'orders' => []]);
        }
        $orders = Database::all(
            "SELECT id, items_count, amount, status, created_at FROM orders WHERE customer_email = ? ORDER BY id DESC LIMIT 50",
            [$email]
        );
    }
    jsonResponse(['success' => true, 'orders' => $orders]);
} catch (Exception $e) {
    jsonResponse(['success' => true, 'orders' => []]);
}
