<?php
/**
 * LOCAL SECRETS — copy this file to "secrets.local.php" and fill in real values.
 * ============================================================================
 *
 *   cp secrets.local.sample.php secrets.local.php   (or copy via cPanel File Manager)
 *
 * WHY THIS FILE EXISTS:
 *   secrets.local.php is GIT-IGNORED, so it is NEVER committed and NEVER
 *   overwritten when you deploy/pull new code. Put ALL your real credentials
 *   here. config.php loads this file first and only falls back to its safe
 *   placeholder defaults for anything you leave unset.
 *
 *   This permanently fixes the problem where deploying code wiped your API
 *   keys and DB password.
 *
 * SECURITY:
 *   - Never paste these keys into chat, screenshots, or commits.
 *   - The .htaccess in backend/ already blocks direct web access to config files,
 *     but this file is plain PHP and is never served as text anyway.
 */

// ============== DATABASE (Hostinger hPanel -> Databases -> MySQL) ==============
define('DB_HOST', 'localhost');
define('DB_NAME', 'REPLACE_with_your_prefixed_db_name');   // e.g. u770423744_vastu_kundali
define('DB_USER', 'REPLACE_with_your_db_user');            // e.g. u770423744_vastu
define('DB_PASS', 'REPLACE_with_your_db_password');

// ============== JWT / AUTH ==============
define('JWT_SECRET', 'REPLACE_with_a_long_random_64_char_string');

// ============== RAZORPAY (dashboard.razorpay.com/app/keys) ==============
define('RAZORPAY_KEY_ID', 'REPLACE');        // rzp_live_... or rzp_test_...
define('RAZORPAY_KEY_SECRET', 'REPLACE');
define('RAZORPAY_WEBHOOK_SECRET', '');       // optional

// ============== AI — DIRECT ANTHROPIC CLAUDE API (recommended) ==============
// Get from https://console.anthropic.com  (key looks like sk-ant-api03-...)
define('CLAUDE_API_KEY', 'REPLACE_with_sk-ant-api03-...');
// A current, VISION-capable Claude model. claude-3-5-sonnet-20241022 is reliable.
define('CLAUDE_MODEL', 'claude-3-5-sonnet-20241022');

// Force the direct Claude API (ignore any Bedrock key still stored in the DB).
define('AI_PROVIDER', 'anthropic');

// ----- (Leave Bedrock empty when using the direct Claude API above) -----
define('BEDROCK_API_KEY', '');
define('AWS_REGION', 'us-east-1');
define('BEDROCK_MODEL', 'us.anthropic.claude-3-5-sonnet-20241022-v2:0');
define('AWS_ACCESS_KEY', '');
define('AWS_SECRET_KEY', '');

// ============== TWILIO / WHATSAPP OTP (console.twilio.com) ==============
define('TWILIO_SID', 'REPLACE');
define('TWILIO_TOKEN', 'REPLACE');
define('TWILIO_WHATSAPP_FROM', 'whatsapp:+14155238886');
define('TWILIO_CONTENT_SID', '');

// ============== EMAIL / SMTP ==============
define('SMTP_HOST', '');
define('SMTP_PORT', 587);
define('SMTP_USER', '');
define('SMTP_PASS', '');
define('SMTP_SECURE', 'tls');

// ============== SITE ==============
define('SITE_URL', 'https://aheadads.co.in/vastu');
