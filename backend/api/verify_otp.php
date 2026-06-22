<?php
require_once __DIR__ . '/../config/config.php';
require_once BACKEND_PATH . '/lib/Twilio.php';
handleCors();
requirePost();

$input = jsonInput();
$phone = $input['phone'] ?? '';
$otp = $input['otp'] ?? '';
$purpose = $input['purpose'] ?? 'verification';
$name = clean($input['name'] ?? '');
$email = strtolower(trim($input['email'] ?? ''));

if (!$phone || !$otp) {
    jsonResponse(['success' => false, 'message' => 'Phone and OTP required']);
}

$twilio = new Twilio();
$result = $twilio->verifyOtp($phone, $otp, $purpose);

// On success: auto-create / link the customer account so they can track all
// their reports & remedy orders using the same mobile number in future.
if (!empty($result['success'])) {
    $verifiedPhone = preg_replace('/\D/', '', $result['phone'] ?? $phone);

    $userId = null;
    try {
        // 1) Find by verified phone
        $existing = Database::row("SELECT id, email FROM users WHERE phone = ?", [$verifiedPhone]);
        if ($existing) {
            $userId = $existing['id'];
            // Backfill name/email if newly provided and missing before
            $updates = [];
            $params = [];
            if ($name) { $updates[] = "name = ?"; $params[] = $name; }
            if ($email && empty($existing['email'])) { $updates[] = "email = ?"; $params[] = $email; }
            $updates[] = "phone_verified = 1";
            if ($updates) {
                $params[] = $userId;
                Database::exec("UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?", $params);
            }
        }

        // 2) Else find by email (link phone to that account)
        if (!$userId && $email) {
            $byEmail = Database::row("SELECT id FROM users WHERE email = ?", [$email]);
            if ($byEmail) {
                $userId = $byEmail['id'];
                Database::exec("UPDATE users SET phone = ?, phone_verified = 1 WHERE id = ?", [$verifiedPhone, $userId]);
            }
        }

        // 3) Else create a fresh account. email column is UNIQUE NOT NULL, so
        //    fall back to a stable placeholder when no email was supplied.
        if (!$userId) {
            $accountEmail = $email ?: ($verifiedPhone . '@phone.vastukundali.local');
            $insertData = [
                'name' => $name ?: 'Customer',
                'email' => $accountEmail,
                'phone' => $verifiedPhone,
                'phone_verified' => 1,
            ];
            $userId = Database::insert('users', $insertData);
        }
    } catch (Exception $e) {
        logDebug('Auto-account creation failed', ['error' => $e->getMessage()]);
        // Non-fatal: verification still succeeds even if account upsert fails.
    }

    // Issue a short-lived verification token (used by checkout) + an account
    // session token tied to the user id for dashboard access.
    $result['verification_token'] = JWT::encode([
        'phone' => $verifiedPhone,
        'verified_at' => time(),
        'purpose' => $purpose,
        'exp_short' => time() + 1800  // 30 minutes
    ]);

    if ($userId) {
        $result['user_id'] = $userId;
        $result['account_token'] = JWT::encode([
            'user_id' => $userId,
            'phone' => $verifiedPhone,
        ]);
    }
}

jsonResponse($result);
