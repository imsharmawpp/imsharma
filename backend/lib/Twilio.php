<?php
/**
 * Twilio WhatsApp OTP Client
 *
 * Uses Twilio REST API directly via cURL — no Composer / SDK required.
 * Compatible with shared hosting.
 *
 * Two modes supported:
 *   1. Content Template (production) - uses pre-approved template
 *   2. Plain message body (sandbox testing)
 *
 * The OTP is generated locally (6 digits) and stored in otp_codes table.
 * Twilio just delivers the WhatsApp message.
 */

class Twilio {

    private $sid;
    private $token;
    private $fromNumber;
    private $contentSid;

    public function __construct() {
        $this->sid = getSetting('twilio_sid', '');
        $this->token = getSetting('twilio_token', '');
        $this->fromNumber = getSetting('twilio_whatsapp_from', 'whatsapp:+14155238886');
        $this->contentSid = getSetting('twilio_content_sid', '');
    }

    public function isConfigured() {
        return !empty($this->sid) && !empty($this->token);
    }

    /**
     * Generate and send a 6-digit OTP via WhatsApp.
     */
    public function sendOtp($phone, $purpose = 'verification') {
        $phone = $this->normalizePhone($phone);
        if (!$phone) {
            return ['success' => false, 'message' => 'Invalid phone number'];
        }

        // Rate limit: max 3 OTPs per phone per 10 minutes
        try {
            $recent = Database::row(
                "SELECT COUNT(*) AS c FROM otp_codes WHERE phone = ? AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)",
                [$phone]
            );
            if (($recent['c'] ?? 0) >= 3) {
                return ['success' => false, 'message' => 'Too many OTP requests. Please wait 10 minutes.'];
            }
        } catch (Exception $e) {
            // Table may not exist yet
        }

        $otp = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

        try {
            Database::insert('otp_codes', [
                'phone' => $phone,
                'otp_hash' => hash('sha256', $otp),
                'purpose' => $purpose,
                'expires_at' => date('Y-m-d H:i:s', time() + 600),
                'attempts' => 0
            ]);
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to store OTP'];
        }

        // Demo mode if not configured
        if (!$this->isConfigured()) {
            logDebug('Twilio demo mode', ['phone' => $phone, 'otp' => $otp]);
            return [
                'success' => true,
                'message' => 'OTP sent (demo mode - check below)',
                'expires_in' => 600,
                '_demo_otp' => $otp,
                '_demo_mode' => true
            ];
        }

        $result = $this->sendWhatsApp($phone, $otp);

        if ($result['success']) {
            return [
                'success' => true,
                'message' => 'OTP sent to your WhatsApp',
                'expires_in' => 600,
                'message_sid' => $result['sid'] ?? null
            ];
        }
        return ['success' => false, 'message' => $result['message']];
    }

    /**
     * Verify the OTP for a given phone number.
     */
    public function verifyOtp($phone, $otp, $purpose = 'verification') {
        $phone = $this->normalizePhone($phone);
        if (!$phone || !$otp) {
            return ['success' => false, 'message' => 'Phone and OTP required'];
        }

        try {
            $row = Database::row(
                "SELECT * FROM otp_codes WHERE phone = ? AND purpose = ? AND verified_at IS NULL AND expires_at > NOW() ORDER BY id DESC LIMIT 1",
                [$phone, $purpose]
            );

            if (!$row) {
                return ['success' => false, 'message' => 'No valid OTP found. Please request a new one.'];
            }

            if (($row['attempts'] ?? 0) >= 5) {
                return ['success' => false, 'message' => 'Too many wrong attempts. Please request a new OTP.'];
            }

            if (!hash_equals($row['otp_hash'], hash('sha256', trim($otp)))) {
                Database::exec("UPDATE otp_codes SET attempts = attempts + 1 WHERE id = ?", [$row['id']]);
                return ['success' => false, 'message' => 'Invalid OTP. Please try again.'];
            }

            Database::exec("UPDATE otp_codes SET verified_at = NOW() WHERE id = ?", [$row['id']]);
            Database::exec("UPDATE users SET phone_verified = 1 WHERE phone = ?", [$this->stripCountryCode($phone)]);

            return ['success' => true, 'message' => 'Phone verified successfully', 'phone' => $phone];

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Verification error'];
        }
    }

    /**
     * Send WhatsApp via Twilio Messages API.
     */
    private function sendWhatsApp($toPhone, $otp) {
        $to = strpos($toPhone, 'whatsapp:') === 0 ? $toPhone : 'whatsapp:' . $toPhone;
        $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->sid}/Messages.json";

        $payload = ['To' => $to, 'From' => $this->fromNumber];

        if (!empty($this->contentSid)) {
            $payload['ContentSid'] = $this->contentSid;
            $payload['ContentVariables'] = json_encode(['1' => $otp, '2' => '10']);
        } else {
            $payload['Body'] = "Your VastuKundali verification code is: {$otp}\n\nThis code expires in 10 minutes. Don't share it with anyone.";
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $this->sid . ':' . $this->token);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            logDebug('Twilio cURL error', ['error' => $error]);
            return ['success' => false, 'message' => 'Network error'];
        }

        $data = json_decode($response, true);
        if ($httpCode >= 400) {
            $msg = $data['message'] ?? 'Twilio API error';
            logDebug('Twilio API error', ['status' => $httpCode, 'response' => $response]);
            return ['success' => false, 'message' => $msg];
        }

        return ['success' => true, 'sid' => $data['sid'] ?? null];
    }

    private function normalizePhone($phone) {
        $phone = preg_replace('/[^\d+]/', '', $phone);
        if (empty($phone)) return null;
        if (strpos($phone, '+') === 0 && strlen($phone) >= 11) return $phone;
        if (strlen($phone) === 10 && $phone[0] >= '6') return '+91' . $phone;
        if (strlen($phone) === 12 && substr($phone, 0, 2) === '91') return '+' . $phone;
        return null;
    }

    private function stripCountryCode($phone) {
        if (strpos($phone, '+91') === 0) return substr($phone, 3);
        if (strpos($phone, '91') === 0 && strlen($phone) === 12) return substr($phone, 2);
        return $phone;
    }
}
