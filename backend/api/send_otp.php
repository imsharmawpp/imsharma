<?php
require_once __DIR__ . '/../config/config.php';
require_once BACKEND_PATH . '/lib/Twilio.php';
handleCors();
requirePost();

$input = jsonInput();
$phone = $input['phone'] ?? '';
$purpose = $input['purpose'] ?? 'checkout';

if (!$phone) {
    jsonResponse(['success' => false, 'message' => 'Phone number required']);
}

$twilio = new Twilio();
$result = $twilio->sendOtp($phone, $purpose);

jsonResponse($result);
