<?php
require_once __DIR__ . '/../config/config.php';
require_once BACKEND_PATH . '/lib/Razorpay.php';
handleCors();
requirePost();

$input = jsonInput();
$paymentId = $input['razorpay_payment_id'] ?? '';
$orderId = $input['razorpay_order_id'] ?? '';
$signature = $input['razorpay_signature'] ?? '';
$reportId = intval($input['report_id'] ?? 0);

if (!$paymentId || !$orderId || !$signature) {
    jsonResponse(['success' => false, 'message' => 'Missing payment parameters']);
}

try {
    $rzpKeyId = getSetting('razorpay_key_id', RAZORPAY_KEY_ID);
    $rzpSecret = getSetting('razorpay_key_secret', RAZORPAY_KEY_SECRET);
    $rzp = new Razorpay($rzpKeyId, $rzpSecret);

    if (!$rzp->verifySignature($orderId, $paymentId, $signature)) {
        jsonResponse(['success' => false, 'message' => 'Payment signature verification failed']);
    }

    // Update payment record
    Database::exec(
        "UPDATE payments SET razorpay_payment_id = ?, razorpay_signature = ?, status = 'captured', updated_at = NOW() WHERE razorpay_order_id = ?",
        [$paymentId, $signature, $orderId]
    );

    // Update report status to paid
    if ($reportId) {
        Database::exec(
            "UPDATE reports SET status = 'paid', payment_id = ? WHERE id = ?",
            [$paymentId, $reportId]
        );
    }

    jsonResponse([
        'success' => true,
        'message' => 'Payment verified successfully',
        'payment_id' => $paymentId
    ]);

} catch (Exception $e) {
    logDebug('Payment verify error', ['error' => $e->getMessage()]);
    jsonResponse(['success' => false, 'message' => 'Payment verification error']);
}
