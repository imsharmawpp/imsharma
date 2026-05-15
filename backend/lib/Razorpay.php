<?php
/**
 * Razorpay PHP Client (no external dependencies)
 *
 * Implements core Razorpay API features:
 *   - Create Order
 *   - Verify Payment Signature
 *   - Verify Webhook Signature
 *   - Fetch Payment
 *   - Refund
 *
 * Uses cURL only. Compatible with shared hosting (PHP 7.0+).
 */

class Razorpay {
    private $keyId;
    private $keySecret;
    private $apiUrl = 'https://api.razorpay.com/v1';

    public function __construct($keyId = null, $keySecret = null) {
        $this->keyId = $keyId ?: RAZORPAY_KEY_ID;
        $this->keySecret = $keySecret ?: RAZORPAY_KEY_SECRET;
    }

    /**
     * Check if running in demo/test mode (no real keys configured)
     */
    public function isDemoMode() {
        return (
            empty($this->keyId) ||
            empty($this->keySecret) ||
            $this->keyId === 'rzp_test_DEMO_KEY' ||
            $this->keySecret === 'DEMO_SECRET' ||
            strpos($this->keyId, 'DEMO') !== false
        );
    }

    /**
     * Create a Razorpay Order.
     * @param float $amount Amount in INR (will be converted to paise)
     * @param string $receipt Internal receipt ID
     * @param array $notes Extra metadata
     * @return array ['id' => 'order_xxx', 'amount' => ..., ...] or ['error' => '...']
     */
    public function createOrder($amount, $receipt = null, $notes = []) {
        // Demo mode: return fake order
        if ($this->isDemoMode()) {
            return [
                'id' => 'order_DEMO_' . uniqid(),
                'amount' => intval($amount * 100),
                'currency' => 'INR',
                'receipt' => $receipt,
                'status' => 'created',
                '_demo' => true
            ];
        }

        $payload = [
            'amount' => intval($amount * 100), // paise
            'currency' => 'INR',
            'receipt' => $receipt ?: 'rcpt_' . uniqid(),
            'payment_capture' => 1,
            'notes' => $notes
        ];

        return $this->request('POST', '/orders', $payload);
    }

    /**
     * Verify payment signature.
     */
    public function verifySignature($orderId, $paymentId, $signature) {
        if ($this->isDemoMode()) return true; // bypass in demo mode

        $expected = hash_hmac('sha256', $orderId . '|' . $paymentId, $this->keySecret);
        return hash_equals($expected, $signature);
    }

    /**
     * Verify webhook signature.
     */
    public function verifyWebhookSignature($body, $signature, $webhookSecret) {
        $expected = hash_hmac('sha256', $body, $webhookSecret);
        return hash_equals($expected, $signature);
    }

    /**
     * Fetch payment details from Razorpay.
     */
    public function fetchPayment($paymentId) {
        if ($this->isDemoMode()) {
            return [
                'id' => $paymentId,
                'status' => 'captured',
                'amount' => 9900,
                '_demo' => true
            ];
        }
        return $this->request('GET', "/payments/{$paymentId}");
    }

    /**
     * Refund a payment (full or partial).
     */
    public function refund($paymentId, $amount = null) {
        if ($this->isDemoMode()) {
            return ['id' => 'rfnd_DEMO_' . uniqid(), 'status' => 'processed', '_demo' => true];
        }
        $payload = [];
        if ($amount !== null) $payload['amount'] = intval($amount * 100);
        return $this->request('POST', "/payments/{$paymentId}/refund", $payload);
    }

    /**
     * Get public Razorpay key ID for frontend.
     */
    public function getKeyId() {
        return $this->isDemoMode() ? 'DEMO_MODE' : $this->keyId;
    }

    /**
     * Internal: make authenticated cURL request to Razorpay API.
     */
    private function request($method, $endpoint, $payload = null) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl . $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $this->keyId . ':' . $this->keySecret);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['error' => ['description' => $error, 'code' => 'CURL_ERROR']];
        }

        $data = json_decode($response, true);
        if ($httpCode >= 400) {
            return ['error' => $data['error'] ?? ['description' => 'API error', 'code' => $httpCode]];
        }

        return $data;
    }
}
