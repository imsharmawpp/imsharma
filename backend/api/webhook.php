<?php
/**
 * Razorpay Webhook Receiver
 *
 * Configure this URL in Razorpay Dashboard:
 *   https://yourdomain.com/backend/api/webhook.php
 *
 * Events handled:
 *   - payment.captured
 *   - payment.failed
 *   - order.paid
 *   - refund.processed
 */

require_once __DIR__ . '/../config/config.php';
require_once BACKEND_PATH . '/lib/Razorpay.php';

$body = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';
$webhookSecret = getSetting('razorpay_webhook_secret', RAZORPAY_WEBHOOK_SECRET);

// Verify signature if configured
if ($webhookSecret) {
    $rzp = new Razorpay();
    if (!$rzp->verifyWebhookSignature($body, $signature, $webhookSecret)) {
        http_response_code(400);
        die('Invalid signature');
    }
}

$payload = json_decode($body, true);
$event = $payload['event'] ?? '';

logDebug('Webhook received', ['event' => $event]);

try {
    switch ($event) {
        case 'payment.captured':
            $payment = $payload['payload']['payment']['entity'];
            $orderId = $payment['order_id'];
            $paymentId = $payment['id'];

            Database::exec(
                "UPDATE payments SET razorpay_payment_id = ?, status = 'captured', method = ?, raw_response = ? WHERE razorpay_order_id = ?",
                [$paymentId, $payment['method'] ?? '', json_encode($payment), $orderId]
            );

            // Find linked record
            $paymentRow = Database::row("SELECT type, reference_id FROM payments WHERE razorpay_order_id = ?", [$orderId]);
            if ($paymentRow) {
                if ($paymentRow['type'] === 'report') {
                    Database::exec("UPDATE reports SET status = 'paid', payment_id = ? WHERE id = ?", [$paymentId, $paymentRow['reference_id']]);
                } elseif ($paymentRow['type'] === 'order') {
                    Database::exec("UPDATE orders SET status = 'paid', payment_id = ? WHERE id = ?", [$paymentId, $paymentRow['reference_id']]);
                }
            }
            break;

        case 'payment.failed':
            $payment = $payload['payload']['payment']['entity'];
            Database::exec(
                "UPDATE payments SET status = 'failed', error_code = ?, error_description = ? WHERE razorpay_order_id = ?",
                [$payment['error_code'] ?? '', $payment['error_description'] ?? '', $payment['order_id']]
            );
            break;

        case 'refund.processed':
            $refund = $payload['payload']['refund']['entity'];
            Database::exec(
                "UPDATE payments SET status = 'refunded', refund_id = ? WHERE razorpay_payment_id = ?",
                [$refund['id'], $refund['payment_id']]
            );
            break;
    }

    http_response_code(200);
    echo json_encode(['ok' => true]);

} catch (Exception $e) {
    logDebug('Webhook error', ['error' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode(['error' => 'processing failed']);
}
