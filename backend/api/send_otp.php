<?php
require_once __DIR__ . '/../config/config.php';
require_once BACKEND_PATH . '/lib/Twilio.php';
handleCors();
requirePost();

$input = jsonInput();
$phone = $input['phone'] ?? '';
$purpose = $input['purpose'] ?? 'verification';

if (!$phone) {
    jsonResponse(['success' => false, 'message' => 'Phone number required']);
}

$twilio = new Twilio();

// Log for debugging
logDebug('OTP request', [
    'phone' => $phone,
    'purpose' => $purpose,
    'twilio_configured' => $twilio->isConfigured(),
    'twilio_sid_set' => !empty(getSetting('twilio_sid', '')),
    'twilio_token_set' => !empty(getSetting('twilio_token', ''))
]);

$result = $twilio->sendOtp($phone, $purpose);

// Log result
logDebug('OTP result', ['phone' => $phone, 'success' => $result['success'], 'message' => $result['message'] ?? '']);

jsonResponse($result);
