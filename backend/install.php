<?php
/**
 * One-Click Installer
 *
 * Visit /backend/install.php in your browser to set up the database.
 * DELETE THIS FILE AFTER INSTALLATION.
 */

require_once __DIR__ . '/config/config.php';

$installed = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Test connection
        $pdo = new PDO("mysql:host=" . DB_HOST . ";charset=utf8mb4", DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Read schema
        $schema = file_get_contents(__DIR__ . '/../database/schema.sql');
        if (!$schema) throw new Exception('schema.sql not found at ../database/schema.sql');

        // Execute as multi-query
        $pdo->exec($schema);

        // Test access via configured DB user
        Database::row("SELECT 1");

        $installed = true;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Check if already installed
$alreadyInstalled = false;
try {
    Database::row("SELECT 1 FROM users LIMIT 1");
    $alreadyInstalled = true;
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html>
<head>
    <title>VastuKundali AI - Installer</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0A0E27, #131938);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            padding: 48px;
            border-radius: 16px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 24px 64px rgba(0,0,0,0.4);
        }
        h1 { font-size: 28px; margin-bottom: 8px; }
        .subtitle { color: #4B5563; margin-bottom: 24px; }
        .alert { padding: 16px; border-radius: 8px; margin-bottom: 20px; }
        .alert-success { background: rgba(16, 185, 129, 0.1); color: #10B981; border-left: 4px solid #10B981; }
        .alert-error { background: rgba(239, 68, 68, 0.1); color: #EF4444; border-left: 4px solid #EF4444; }
        .alert-info { background: rgba(59, 130, 246, 0.1); color: #3B82F6; border-left: 4px solid #3B82F6; }
        .info-box { background: #FFF9E6; padding: 16px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; }
        .info-box strong { color: #B8941F; }
        code { background: #F5F5F5; padding: 2px 6px; border-radius: 4px; font-family: monospace; }
        .btn {
            display: inline-block;
            padding: 14px 28px;
            background: linear-gradient(135deg, #D4AF37, #B8941F);
            color: #0A0E27;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            font-size: 15px;
            margin-right: 8px;
        }
        .check-list { list-style: none; padding: 0; }
        .check-list li { padding: 8px 0; display: flex; align-items: center; gap: 8px; }
        .check-list li.ok::before { content: '✓'; color: #10B981; font-weight: bold; }
        .check-list li.fail::before { content: '✗'; color: #EF4444; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🏛️ VastuKundali AI Installer</h1>
        <p class="subtitle">Set up your database and verify the installation</p>

        <?php if ($installed): ?>
            <div class="alert alert-success">
                <strong>✓ Installation Successful!</strong><br>
                Database created and seeded with sample data.
            </div>
            <div class="info-box">
                <strong>Default Admin Credentials:</strong><br>
                Email: <code>admin@vastukundali.com</code><br>
                Password: <code>admin123</code><br>
                <em>Change immediately after first login!</em>
            </div>
            <div class="alert alert-error">
                <strong>⚠ IMPORTANT:</strong> DELETE this <code>install.php</code> file now for security.
            </div>
            <a href="admin/index.php" class="btn">Go to Admin Panel →</a>
            <a href="../frontend/index.html" class="btn" style="background: #0A0E27; color: white;">View Frontend →</a>

        <?php elseif ($alreadyInstalled): ?>
            <div class="alert alert-info">
                <strong>ℹ Already Installed</strong><br>
                Database tables already exist. You can re-run installation to reset (will not delete existing data).
            </div>
            <a href="admin/index.php" class="btn">Go to Admin Panel →</a>

        <?php else: ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><strong>Error:</strong> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <h3 style="margin-bottom: 12px;">System Requirements Check</h3>
            <ul class="check-list">
                <li class="<?= version_compare(PHP_VERSION, '7.0.0', '>=') ? 'ok' : 'fail' ?>">PHP 7.0+ (current: <?= PHP_VERSION ?>)</li>
                <li class="<?= extension_loaded('pdo_mysql') ? 'ok' : 'fail' ?>">PDO MySQL extension</li>
                <li class="<?= extension_loaded('curl') ? 'ok' : 'fail' ?>">cURL extension</li>
                <li class="<?= extension_loaded('mbstring') ? 'ok' : 'fail' ?>">mbstring extension</li>
                <li class="<?= extension_loaded('json') ? 'ok' : 'fail' ?>">JSON extension</li>
                <li class="<?= extension_loaded('openssl') ? 'ok' : 'fail' ?>">OpenSSL extension</li>
                <li class="<?= is_writable(__DIR__ . '/uploads') ? 'ok' : 'fail' ?>">uploads/ directory writable</li>
                <li class="<?= is_writable(__DIR__ . '/reports') ? 'ok' : 'fail' ?>">reports/ directory writable</li>
            </ul>

            <h3 style="margin: 24px 0 12px;">Database Configuration</h3>
            <div class="info-box">
                Update these in <code>backend/config/config.php</code>:<br>
                Host: <code><?= DB_HOST ?></code><br>
                Database: <code><?= DB_NAME ?></code><br>
                User: <code><?= DB_USER ?></code>
            </div>

            <form method="POST">
                <button type="submit" class="btn">⚡ Run Installation</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
