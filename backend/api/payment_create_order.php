<?php
require_once __DIR__ . '/../config/config.php';
require_once BACKEND_PATH . '/lib/Razorpay.php';
handleCors();
requirePost();

$input = jsonInput();
$amount = floatval($input['amount'] ?? 99);
$reportId = intval($input['report_id'] ?? 0);
$customer = $input['customer'] ?? [];

if ($amount < 1) {
    jsonResponse(['success' => false, 'message' => 'Invalid amount']);
}

// Validate report
if ($reportId) {
    $report = Database::row("SELECT id, customer_email, status FROM reports WHERE id = ?", [$reportId]);
    if (!$report) {
        jsonResponse(['success' => false, 'message' => 'Report not found']);
    }
    if ($report['status'] === 'completed' || $report['status'] === 'paid') {
        jsonResponse(['success' => false, 'message' => 'Report already paid']);
    }
}

try {
    // Use Razorpay credentials from settings (admin can override)
    $rzpKeyId = getSetting('razorpay_key_id', RAZORPAY_KEY_ID);
    $rzpSecret = getSetting('razorpay_key_secret', RAZORPAY_KEY_SECRET);
    $rzp = new Razorpay($rzpKeyId, $rzpSecret);

    $receipt = 'rpt_' . $reportId . '_' . substr(uniqid(), -6);
    $order = $rzp->createOrder($amount, $receipt, [
        'report_id' => $reportId,
        'customer_email' => $customer['email'] ?? '',
        'type' => 'report'
    ]);

    if (isset($order['error'])) {
        jsonResponse(['success' => false, 'message' => 'Payment gateway error: ' . ($order['error']['description'] ?? 'Unknown')]);
    }

    // Save payment record
    Database::insert('payments', [
        'razorpay_order_id' => $order['id'],
        'amount' => $amount,
        'currency' => 'INR',
        'status' => 'created',
        'type' => 'report',
        'reference_id' => $reportId,
        'customer_email' => $customer['email'] ?? '',
        'customer_phone' => $customer['contact'] ?? '',
        'raw_response' => json_encode($order)
    ]);

    jsonResponse([
        'success' => true,
        'order_id' => $order['id'],
        'amount' => $order['amount'],
        'currency' => 'INR',
        'razorpay_key' => $rzp->getKeyId()
    ]);

} catch (Exception $e) {
    logDebug('Order creation error', ['error' => $e->getMessage()]);
    jsonResponse(['success' => false, 'message' => 'Failed to create order']);
}
