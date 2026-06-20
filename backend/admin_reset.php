<?php
/**
 * Admin Password Reset Utility
 *
 * Use this if you can't login to the admin panel.
 * It will reset the admin user's password to a new value.
 *
 * USAGE:
 *   1. Upload this file to /backend/ on your hosting
 *   2. Visit https://yourdomain.com/backend/admin_reset.php
 *   3. Enter your desired new password
 *   4. Login at /backend/admin/ with admin@vastukundali.com + your new password
 *   5. DELETE THIS FILE after use!
 */

require_once __DIR__ . '/config/config.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? 'admin@vastukundali.com'));
    $newPassword = $_POST['password'] ?? '';
    $name = trim($_POST['name'] ?? 'Admin');

    if (strlen($newPassword) < 6) {
        $message = 'Password must be at least 6 characters.';
        $messageType = 'error';
    } else {
        try {
            $hash = password_hash($newPassword, PASSWORD_BCRYPT);

            $existing = Database::row("SELECT id FROM users WHERE email = ?", [$email]);
            if ($existing) {
                Database::exec(
                    "UPDATE users SET password_hash = ?, role = 'admin', name = ? WHERE email = ?",
                    [$hash, $name, $email]
                );
                $message = 'Password reset successfully! You can now login at /backend/admin/ with email "' . htmlspecialchars($email) . '" and your new password. DELETE this file now!';
            } else {
                Database::insert('users', [
                    'name' => $name,
                    'email' => $email,
                    'phone' => '9999999999',
                    'password_hash' => $hash,
                    'role' => 'admin',
                    'email_verified' => 1
                ]);
                $message = 'Admin user created successfully! You can now login at /backend/admin/ with email "' . htmlspecialchars($email) . '" and your new password. DELETE this file now!';
            }
            $messageType = 'success';
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Password Reset - VastuKundali</title>
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
        .card {
            background: white;
            padding: 40px;
            border-radius: 16px;
            max-width: 480px;
            width: 100%;
            box-shadow: 0 24px 64px rgba(0,0,0,0.4);
            border: 2px solid #D4AF37;
        }
        h1 { font-size: 26px; margin-bottom: 8px; color: #0A0E27; }
        .subtitle { color: #4B5563; margin-bottom: 24px; font-size: 14px; }
        .alert { padding: 14px; border-radius: 8px; margin-bottom: 18px; font-size: 14px; line-height: 1.5; }
        .alert-success { background: rgba(16, 185, 129, 0.1); color: #065F46; border-left: 4px solid #10B981; }
        .alert-error { background: rgba(239, 68, 68, 0.1); color: #991B1B; border-left: 4px solid #EF4444; }
        .warning {
            background: #FEF3C7;
            color: #92400E;
            padding: 12px 14px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
            border-left: 4px solid #F59E0B;
        }
        .form-group { margin-bottom: 16px; }
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
            font-size: 14px;
            color: #1F2937;
        }
        input {
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid #E5E5E5;
            border-radius: 8px;
            font-size: 15px;
            font-family: inherit;
        }
        input:focus { outline: none; border-color: #D4AF37; box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.15); }
        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #D4AF37, #B8941F);
            color: #0A0E27;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(212, 175, 55, 0.4); }
        code { background: #F5F5F5; padding: 2px 6px; border-radius: 4px; font-family: monospace; font-size: 13px; }
    </style>
</head>
<body>
    <div class="card">
        <h1>🔐 Admin Password Reset</h1>
        <p class="subtitle">Reset or create the admin account for VastuKundali AI</p>

        <div class="warning">
            <strong>⚠️ Security:</strong> Delete this file (<code>admin_reset.php</code>) immediately after you finish using it.
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>"><?= $message ?></div>
        <?php endif; ?>

        <?php if ($messageType !== 'success'): ?>
        <form method="POST">
            <div class="form-group">
                <label>Admin Name</label>
                <input type="text" name="name" value="Admin" required>
            </div>
            <div class="form-group">
                <label>Admin Email</label>
                <input type="email" name="email" value="admin@vastukundali.com" required>
            </div>
            <div class="form-group">
                <label>New Password (min 6 chars)</label>
                <input type="text" name="password" value="admin123" required minlength="6">
                <small style="color:#9CA3AF;font-size:12px;">Default: admin123 (change after login)</small>
            </div>
            <button type="submit" class="btn">🔑 Reset Admin Password</button>
        </form>
        <?php else: ?>
            <a href="admin/index.php" style="display:block;text-align:center;padding:14px;background:#0A0E27;color:#D4AF37;border-radius:8px;text-decoration:none;font-weight:700;">Go to Admin Login →</a>
        <?php endif; ?>
    </div>
</body>
</html>
