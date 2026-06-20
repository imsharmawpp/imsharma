<?php
require_once __DIR__ . '/../config/config.php';
require_once BACKEND_PATH . '/lib/Razorpay.php';
handleCors();
requirePost();

$input = jsonInput();
$paymentId = $input['razorpay_payment_id'] ?? '';
$orderId = $input['razorpay_order_id'] ?? '';
$signature = $input['razorpay_signature'] ?? '';
$internalOrderId = intval($input['order_id'] ?? 0);

if (!$paymentId || !$orderId || !$signature || !$internalOrderId) {
    jsonResponse(['success' => false, 'message' => 'Missing parameters']);
}

try {
    $rzpKeyId = getSetting('razorpay_key_id', RAZORPAY_KEY_ID);
    $rzpSecret = getSetting('razorpay_key_secret', RAZORPAY_KEY_SECRET);
    $rzp = new Razorpay($rzpKeyId, $rzpSecret);

    if (!$rzp->verifySignature($orderId, $paymentId, $signature)) {
        jsonResponse(['success' => false, 'message' => 'Signature verification failed']);
    }

    // Update payment & order
    Database::exec(
        "UPDATE payments SET razorpay_payment_id = ?, razorpay_signature = ?, status = 'captured' WHERE razorpay_order_id = ?",
        [$paymentId, $signature, $orderId]
    );
    Database::exec(
        "UPDATE orders SET status = 'paid', payment_id = ? WHERE id = ?",
        [$paymentId, $internalOrderId]
    );

    // Decrement inventory
    $order = Database::row("SELECT items_json FROM orders WHERE id = ?", [$internalOrderId]);
    if ($order) {
        $items = json_decode($order['items_json'], true);
        foreach ($items as $item) {
            Database::exec(
                "UPDATE products SET inventory = GREATEST(0, inventory - ?) WHERE id = ?",
                [$item['qty'], $item['id']]
            );
        }
    }

    jsonResponse(['success' => true, 'message' => 'Order placed successfully']);

} catch (Exception $e) {
    logDebug('Order verify error', ['error' => $e->getMessage()]);
    jsonResponse(['success' => false, 'message' => 'Verification failed']);
}
