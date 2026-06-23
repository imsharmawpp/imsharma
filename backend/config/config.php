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
define('DB_HOST', 'localhost');
define('DB_NAME', 'vastu_kundali');           // e.g., 'u770423744_vastu_kundali' on Hostinger
define('DB_USER', 'root');                    // e.g., 'u770423744_vastu' on Hostinger
define('DB_PASS', '');                        // Your DB user password
define('DB_CHARSET', 'utf8mb4');

// ============== SITE CONFIG ==============
define('SITE_URL', 'https://yourdomain.com');
define('SITE_NAME', 'VastuKundali');
define('ADMIN_EMAIL', 'admin@vastukundali.com');

// Path to backend (relative)
define('BACKEND_PATH', __DIR__ . '/..');
define('UPLOADS_PATH', BACKEND_PATH . '/uploads');
define('REPORTS_PATH', BACKEND_PATH . '/reports');

// Public URL paths (used in API responses for client-side access)
define('UPLOADS_URL', '/backend/uploads');
define('REPORTS_URL', '/backend/reports');

// ============== JWT / AUTH ==============
// Change this to a random 64-char string in production!
define('JWT_SECRET', 'CHANGE_THIS_TO_A_RANDOM_64_CHAR_STRING_IN_PRODUCTION_xyz123abc456');
define('JWT_EXPIRY', 60 * 60 * 24 * 30); // 30 days

// ============== RAZORPAY CONFIG ==============
// Get keys from https://dashboard.razorpay.com/app/keys
// Use rzp_test_... for testing, rzp_live_... for production
define('RAZORPAY_KEY_ID', 'rzp_test_DEMO_KEY');           // Replace with your key
define('RAZORPAY_KEY_SECRET', 'DEMO_SECRET');             // Replace with your secret
define('RAZORPAY_WEBHOOK_SECRET', '');                    // Optional: for webhook verification

// ============== AWS BEDROCK / CLAUDE AI ==============
// THREE OPTIONS (use any ONE):
//
// OPTION 1: Bedrock Long-Term API Key (SIMPLEST - recommended)
//   Get from: AWS Console > Bedrock > API Keys
//   Just paste the ABSK... key below. No IAM credentials needed.
//
// OPTION 2: Bedrock IAM Access Key + Secret Key (traditional AWS)
//   Create an IAM user with bedrock:InvokeModel permission
//
// OPTION 3: Direct Anthropic API Key
//   Get from: console.anthropic.com (sk-ant-api03-...)
//
// If NONE are set, the system uses the built-in rule-based VastuEngine (still produces good reports).

// OPTION 1: Bedrock Long-Term API Key (paste your ABSK... key here)
define('BEDROCK_API_KEY', '');    // e.g., 'ABSKQmVkcm9ja...'
define('AWS_REGION', 'us-east-1');
// BEDROCK_MODEL must be a VALID, VISION-CAPABLE model id from the AWS Bedrock
// console (Model catalog). Two valid forms:
//   1) Bare model id (older models, on-demand):  anthropic.claude-3-5-sonnet-20241022-v2:0
//   2) Cross-Region INFERENCE PROFILE id (required by newer Claude Opus 4.x /
//      Sonnet 4.x models): prefixed us. / eu. / apac. / global.
//        e.g.  us.anthropic.claude-3-5-sonnet-20241022-v2:0
//        e.g.  global.anthropic.claude-opus-4-...-v1:0
// IMPORTANT:
//   - Copy the EXACT id from the console; a wrong id => every call fails (400/404)
//     and the app silently falls back to heuristics (colored CAD rejected,
//     hand-drawn accepted).
//   - Newer Opus/Sonnet models WILL NOT work with a bare id ("on-demand
//     throughput isn't supported") - you must use the inference profile id.
//   - Run backend/api/ai_diagnostics.php after editing to confirm it returns ok=true.
define('BEDROCK_MODEL', 'us.anthropic.claude-3-5-sonnet-20241022-v2:0');

// OPTION 2: AWS IAM credentials (leave empty if using Option 1 or 3)
define('AWS_ACCESS_KEY', '');
define('AWS_SECRET_KEY', '');

// OPTION 3: Anthropic Direct API (leave empty if using Option 1 or 2)
define('CLAUDE_API_KEY', '');     // sk-ant-api03-... from console.anthropic.com
define('CLAUDE_MODEL', 'claude-3-5-sonnet-20241022');

// ============== EMAIL CONFIG ==============
define('MAIL_FROM', 'noreply@vastukundali.com');
define('MAIL_FROM_NAME', 'VastuKundali');
define('SMTP_HOST', '');
define('SMTP_PORT', 587);
define('SMTP_USER', '');
define('SMTP_PASS', '');
define('SMTP_SECURE', 'tls'); // 'tls' or 'ssl'

// ============== UPLOAD CONFIG ==============
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf']);
define('ALLOWED_MIMETYPES', ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf']);

// ============== TWILIO / WHATSAPP OTP ==============
// Get from: https://console.twilio.com
// For sandbox testing: use whatsapp:+14155238886 as from number
// IMPORTANT: For sandbox, recipient must first send "join <sandbox-word>" to the Twilio number
define('TWILIO_SID', '');             // e.g., 'ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'
define('TWILIO_TOKEN', '');           // e.g., 'your_auth_token_here'
define('TWILIO_WHATSAPP_FROM', 'whatsapp:+14155238886');  // Your Twilio WhatsApp number
define('TWILIO_CONTENT_SID', '');     // Optional: Content Template SID for production

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
