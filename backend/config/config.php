<?php
/**
 * VastuKundali - Application Configuration
 * 
 * IMPORTANT: Update these values for your hosting environment.
 * For shared hosting (cPanel/Plesk), update database credentials below.
 */

// Display errors only in development
define('APP_ENV', 'production'); // 'development' or 'production'
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Set timezone
date_default_timezone_set('Asia/Kolkata');

// ============== LOCAL SECRETS (git-ignored, never overwritten by deploy) ======
// If backend/config/secrets.local.php exists, it defines the real credentials
// (DB, API keys, JWT, Razorpay, Twilio, SMTP). Anything it defines takes
// precedence; config.php below only fills in safe placeholder defaults for
// constants the secrets file did NOT set. See secrets.local.sample.php.
if (file_exists(__DIR__ . '/secrets.local.php')) {
    require_once __DIR__ . '/secrets.local.php';
}

// Small helper: define a constant only if it wasn't already set (by secrets file)
if (!function_exists('cfg_default')) {
    function cfg_default($name, $value) {
        if (!defined($name)) define($name, $value);
    }
}

// ============== DATABASE CONFIG ==============
// Update these with your hosting database credentials
//
// SHARED HOSTING (Hostinger, cPanel, GoDaddy, etc.):
//   Your DB name and user will have a prefix like "u770423744_"
//   Example: DB_NAME = 'u770423744_vastu_kundali'
//            DB_USER = 'u770423744_vastu'
//   Find these in: Hostinger hPanel -> Databases -> MySQL Databases
//
// LOCAL/VPS:
//   You can use simple names like 'vastu_kundali' / 'root'
cfg_default('DB_HOST', 'localhost');
cfg_default('DB_NAME', 'vastu_kundali');           // e.g., 'u770423744_vastu_kundali' on Hostinger
cfg_default('DB_USER', 'root');                    // e.g., 'u770423744_vastu' on Hostinger
cfg_default('DB_PASS', '');                        // Your DB user password
cfg_default('DB_CHARSET', 'utf8mb4');

// ============== SITE CONFIG ==============
cfg_default('SITE_URL', 'https://yourdomain.com');
cfg_default('SITE_NAME', 'VastuKundali');
cfg_default('ADMIN_EMAIL', 'admin@vastukundali.com');

// Path to backend (relative)
define('BACKEND_PATH', __DIR__ . '/..');
define('UPLOADS_PATH', BACKEND_PATH . '/uploads');
define('REPORTS_PATH', BACKEND_PATH . '/reports');

// Public URL paths (used in API responses for client-side access)
define('UPLOADS_URL', '/backend/uploads');
define('REPORTS_URL', '/backend/reports');

// ============== JWT / AUTH ==============
// Change this to a random 64-char string in production!
cfg_default('JWT_SECRET', 'CHANGE_THIS_TO_A_RANDOM_64_CHAR_STRING_IN_PRODUCTION_xyz123abc456');
define('JWT_EXPIRY', 60 * 60 * 24 * 30); // 30 days

// ============== RAZORPAY CONFIG ==============
// Get keys from https://dashboard.razorpay.com/app/keys
// Use rzp_test_... for testing, rzp_live_... for production
cfg_default('RAZORPAY_KEY_ID', 'rzp_test_DEMO_KEY');           // Replace with your key
cfg_default('RAZORPAY_KEY_SECRET', 'DEMO_SECRET');             // Replace with your secret
cfg_default('RAZORPAY_WEBHOOK_SECRET', '');                    // Optional: for webhook verification

// ============== AI: ANTHROPIC CLAUDE / AWS BEDROCK ==============
// RECOMMENDED: Direct Anthropic Claude API (OPTION 3) - simplest, no AWS setup.
//   Put your key in secrets.local.php:  CLAUDE_API_KEY = 'sk-ant-api03-...'
//   Use a VISION-capable model, e.g. CLAUDE_MODEL = 'claude-3-5-sonnet-20241022'.
//
// Priority order used by the app: Bedrock API key -> Anthropic key -> AWS IAM.
// So when using the direct Claude API, leave BEDROCK_API_KEY empty.
//
// After setting the key, open backend/api/ai_diagnostics.php to confirm ok=true.

// OPTION 1: Bedrock Long-Term API Key (leave empty if using the Claude API)
cfg_default('BEDROCK_API_KEY', '');
cfg_default('AWS_REGION', 'us-east-1');
// If you DO use Bedrock, BEDROCK_MODEL must be a valid id from the console;
// newer Opus/Sonnet models need a cross-Region inference profile id
// (e.g. us.anthropic.claude-3-5-sonnet-20241022-v2:0).
cfg_default('BEDROCK_MODEL', 'us.anthropic.claude-3-5-sonnet-20241022-v2:0');

// OPTION 2: AWS IAM credentials (leave empty unless using IAM SigV4)
cfg_default('AWS_ACCESS_KEY', '');
cfg_default('AWS_SECRET_KEY', '');

// OPTION 3: Direct Anthropic API (RECOMMENDED) - set CLAUDE_API_KEY in secrets.local.php
cfg_default('CLAUDE_API_KEY', '');     // sk-ant-api03-... from console.anthropic.com
cfg_default('CLAUDE_MODEL', 'claude-3-5-sonnet-20241022');

// ============== EMAIL CONFIG ==============
cfg_default('MAIL_FROM', 'noreply@vastukundali.com');
cfg_default('MAIL_FROM_NAME', 'VastuKundali');
cfg_default('SMTP_HOST', '');
cfg_default('SMTP_PORT', 587);
cfg_default('SMTP_USER', '');
cfg_default('SMTP_PASS', '');
cfg_default('SMTP_SECURE', 'tls'); // 'tls' or 'ssl'

// ============== UPLOAD CONFIG ==============
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf']);
define('ALLOWED_MIMETYPES', ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf']);

// ============== TWILIO / WHATSAPP OTP ==============
// Get from: https://console.twilio.com
// For sandbox testing: use whatsapp:+14155238886 as from number
// IMPORTANT: For sandbox, recipient must first send "join <sandbox-word>" to the Twilio number
cfg_default('TWILIO_SID', '');             // e.g., 'ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'
cfg_default('TWILIO_TOKEN', '');           // e.g., 'your_auth_token_here'
cfg_default('TWILIO_WHATSAPP_FROM', 'whatsapp:+14155238886');  // Your Twilio WhatsApp number
cfg_default('TWILIO_CONTENT_SID', '');     // Optional: Content Template SID for production

// ============== AUTOLOAD HELPERS ==============
require_once __DIR__ . '/database.php';
require_once BACKEND_PATH . '/includes/helpers.php';
require_once BACKEND_PATH . '/includes/auth.php';

// Ensure required directories exist
foreach ([UPLOADS_PATH, REPORTS_PATH, UPLOADS_PATH . '/plans', REPORTS_PATH . '/pdf'] as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
        // Add index.html to prevent directory listing
        @file_put_contents($dir . '/index.html', '');
    }
}
