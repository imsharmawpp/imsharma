<?php
/**
 * Common Helper Functions
 */

/**
 * Send JSON response and exit
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Get JSON request body as array
 */
function jsonInput() {
    $input = file_get_contents('php://input');
    return json_decode($input, true) ?: [];
}

/**
 * Sanitize string
 */
function clean($value) {
    if (is_array($value)) return array_map('clean', $value);
    return trim(htmlspecialchars(strip_tags($value), ENT_QUOTES, 'UTF-8'));
}

/**
 * Validate email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate Indian phone (10 digits)
 */
function isValidPhone($phone) {
    $digits = preg_replace('/\D/', '', $phone);
    return strlen($digits) === 10 && $digits[0] >= '6';
}

/**
 * Generate unique reference (e.g., for orders / reports)
 */
function generateRef($prefix = 'VK') {
    return $prefix . date('ymd') . strtoupper(substr(uniqid(), -6));
}

/**
 * Slugify a string
 */
function slugify($text) {
    $text = preg_replace('/[^A-Za-z0-9\-\s]/', '', $text);
    $text = preg_replace('/\s+/', '-', strtolower(trim($text)));
    return preg_replace('/-+/', '-', $text);
}

/**
 * Get setting from DB with fallback
 */
function getSetting($key, $default = '') {
    static $cache = [];
    if (isset($cache[$key])) return $cache[$key];
    try {
        $row = Database::row("SELECT setting_value FROM settings WHERE setting_key = ?", [$key]);
        $cache[$key] = $row ? $row['setting_value'] : $default;
    } catch (Exception $e) {
        $cache[$key] = $default;
    }
    return $cache[$key];
}

/**
 * Set setting in DB
 */
function setSetting($key, $value) {
    try {
        $exists = Database::row("SELECT id FROM settings WHERE setting_key = ?", [$key]);
        if ($exists) {
            Database::exec("UPDATE settings SET setting_value = ? WHERE setting_key = ?", [$value, $key]);
        } else {
            Database::insert('settings', ['setting_key' => $key, 'setting_value' => $value]);
        }
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * CORS preflight handler - call at top of API endpoints
 */
function handleCors() {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        http_response_code(200);
        exit;
    }
    header('Access-Control-Allow-Origin: *');
}

/**
 * Log to file (for debugging on shared hosting)
 */
function logDebug($message, $context = []) {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message;
    if (!empty($context)) {
        $line .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE);
    }
    @file_put_contents(BACKEND_PATH . '/debug.log', $line . PHP_EOL, FILE_APPEND);
}

/**
 * Send simple email (uses PHP mail() - upgrade to SMTP for production)
 */
function sendEmail($to, $subject, $htmlBody, $attachments = []) {
    $from = MAIL_FROM;
    $fromName = MAIL_FROM_NAME;

    // If SMTP configured, use it (would require PHPMailer for real implementation)
    // For shared hosting, basic mail() function works for low volume
    $boundary = md5(time());
    $headers = "From: {$fromName} <{$from}>\r\n";
    $headers .= "Reply-To: {$from}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";

    if (empty($attachments)) {
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body = $htmlBody;
    } else {
        $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";
        $body = "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
        $body .= $htmlBody . "\r\n\r\n";
        foreach ($attachments as $file) {
            if (file_exists($file)) {
                $name = basename($file);
                $content = chunk_split(base64_encode(file_get_contents($file)));
                $body .= "--{$boundary}\r\n";
                $body .= "Content-Type: application/octet-stream; name=\"{$name}\"\r\n";
                $body .= "Content-Transfer-Encoding: base64\r\n";
                $body .= "Content-Disposition: attachment; filename=\"{$name}\"\r\n\r\n";
                $body .= $content . "\r\n\r\n";
            }
        }
        $body .= "--{$boundary}--";
    }

    return @mail($to, $subject, $body, $headers);
}

/**
 * Format direction code to label
 */
function formatDirection($code) {
    $map = ['N' => 'North', 'S' => 'South', 'E' => 'East', 'W' => 'West',
            'NE' => 'North-East', 'NW' => 'North-West', 'SE' => 'South-East', 'SW' => 'South-West'];
    return $map[$code] ?? $code;
}

/**
 * Sanitize filename
 */
function sanitizeFilename($filename) {
    $filename = preg_replace('/[^A-Za-z0-9\-\.\_]/', '', $filename);
    return substr($filename, 0, 100);
}

/**
 * Require POST or fail
 */
function requirePost() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['success' => false, 'message' => 'POST method required'], 405);
    }
}

/**
 * Require GET or fail
 */
function requireGet() {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonResponse(['success' => false, 'message' => 'GET method required'], 405);
    }
}

/**
 * Get client IP
 */
function getClientIp() {
    return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}
