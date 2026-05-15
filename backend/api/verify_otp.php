<?php
require_once __DIR__ . '/../config/config.php';
require_once BACKEND_PATH . '/lib/Twilio.php';
handleCors();
requirePost();

$input = jsonInput();
$phone = $input['phone'] ?? '';
$otp = $input['otp'] ?? '';
$purpose = $input['purpose'] ?? 'checkout';

if (!$phone || !$otp) {
    jsonResponse(['success' => false, 'message' => 'Phone and OTP required']);
}

$twilio = new Twilio();
$result = $twilio->verifyOtp($phone, $otp, $purpose);

// Issue a short-lived verification token so checkout can confirm it
if (!empty($result['success'])) {
    $token = JWT::encode([
        'phone' => $result['phone'],
        'verified_at' => time(),
        'purpose' => $purpose,
        'exp_short' => time() + 1800  // 30 minutes
    ]);
    $result['verification_token'] = $token;
}

jsonResponse($result);
