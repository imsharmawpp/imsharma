<?php
/**
 * Address management - List, Create, Update, Delete addresses for a user.
 */
require_once __DIR__ . '/../config/config.php';
handleCors();

$user = getAuthUser();
$method = $_SERVER['REQUEST_METHOD'];

// GET - list user's addresses
if ($method === 'GET') {
    if (!$user) {
        // Allow lookup by phone for guest checkout
        $phone = preg_replace('/\D/', '', $_GET['phone'] ?? '');
        if (!$phone) {
            jsonResponse(['success' => true, 'addresses' => []]);
        }
        $addresses = Database::all("SELECT * FROM addresses WHERE phone = ? ORDER BY is_default DESC, id DESC", [$phone]);
        jsonResponse(['success' => true, 'addresses' => $addresses]);
    }
    $addresses = Database::all("SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC", [$user['id']]);
    jsonResponse(['success' => true, 'addresses' => $addresses]);
}

// POST - create or update address
if ($method === 'POST') {
    $input = jsonInput();

    // Required fields
    foreach (['name', 'phone', 'address_line1', 'city', 'state', 'pincode'] as $f) {
        if (empty($input[$f])) {
            jsonResponse(['success' => false, 'message' => "Field '{$f}' is required"]);
        }
    }

    $phone = preg_replace('/\D/', '', $input['phone']);
    if (strlen($phone) !== 10) {
        jsonResponse(['success' => false, 'message' => 'Phone must be 10 digits']);
    }
    if (!preg_match('/^\d{6}$/', $input['pincode'])) {
        jsonResponse(['success' => false, 'message' => 'Pincode must be 6 digits']);
    }

    $data = [
        'user_id' => $user['id'] ?? null,
        'label' => clean($input['label'] ?? 'Home'),
        'name' => clean($input['name']),
        'phone' => $phone,
        'address_line1' => clean($input['address_line1']),
        'address_line2' => clean($input['address_line2'] ?? ''),
        'city' => clean($input['city']),
        'state' => clean($input['state']),
        'pincode' => clean($input['pincode']),
        'country' => clean($input['country'] ?? 'India'),
        'is_default' => !empty($input['is_default']) ? 1 : 0
    ];

    try {
        if (!empty($input['id'])) {
            $id = intval($input['id']);
            // Verify ownership
            $existing = Database::row("SELECT user_id, phone FROM addresses WHERE id = ?", [$id]);
            if (!$existing) jsonResponse(['success' => false, 'message' => 'Address not found']);
            if ($user && $existing['user_id'] && $existing['user_id'] != $user['id']) {
                jsonResponse(['success' => false, 'message' => 'Not authorized']);
            }
            Database::update('addresses', $id, $data);
        } else {
            $id = Database::insert('addresses', $data);
        }

        // If marked default, unset other defaults for this user
        if ($data['is_default']) {
            if ($user) {
                Database::exec("UPDATE addresses SET is_default = 0 WHERE user_id = ? AND id != ?", [$user['id'], $id]);
            } else {
                Database::exec("UPDATE addresses SET is_default = 0 WHERE phone = ? AND id != ?", [$phone, $id]);
            }
        }

        $address = Database::row("SELECT * FROM addresses WHERE id = ?", [$id]);
        jsonResponse(['success' => true, 'address' => $address]);

    } catch (Exception $e) {
        logDebug('Address save error', ['error' => $e->getMessage()]);
        jsonResponse(['success' => false, 'message' => 'Failed to save address']);
    }
}

// DELETE
if ($method === 'DELETE') {
    $id = intval($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['success' => false, 'message' => 'ID required']);

    if ($user) {
        Database::exec("DELETE FROM addresses WHERE id = ? AND user_id = ?", [$id, $user['id']]);
    }
    jsonResponse(['success' => true]);
}

jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
