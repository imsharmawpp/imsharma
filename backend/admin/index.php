<?php
/**
 * Admin Login Page
 */
require_once __DIR__ . '/../config/config.php';

// Already logged in? Redirect to dashboard
if (isAdminLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        try {
            $user = Database::row("SELECT id, name, email, password_hash, role FROM users WHERE email = ? AND role = 'admin'", [$email]);
            if ($user && verifyPassword($password, $user['password_hash'])) {
                adminLogin($user['id']);
                Database::insert('admin_logs', [
                    'admin_id' => $user['id'],
                    'action' => 'login',
                    'ip_address' => getClientIp(),
                    'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)
                ]);
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Invalid credentials or no admin access.';
            }
        } catch (Exception $e) {
            $error = 'Database error. Please check your config.';
        }
    } else {
        $error = 'Email and password are required.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Login - VastuKundali</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0A0E27 0%, #131938 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-card {
            background: white;
            padding: 48px;
            border-radius: 16px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 24px 64px rgba(0,0,0,0.4);
            border: 2px solid #D4AF37;
        }
        .logo {
            text-align: center;
            font-family: 'Playfair Display', serif;
            font-size: 28px;
            font-weight: 700;
            color: #0A0E27;
            margin-bottom: 8px;
        }
        .logo span { color: #D4AF37; }
        .subtitle {
            text-align: center;
            color: #4B5563;
            margin-bottom: 32px;
            font-size: 14px;
        }
        h1 { font-family: 'Playfair Display', serif; text-align: center; margin-bottom: 24px; color: #0A0E27; }
        .form-group { margin-bottom: 18px; }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
            font-size: 14px;
            color: #1F2937;
        }
        .form-group input {
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid #E5E5E5;
            border-radius: 8px;
            font-size: 15px;
            font-family: inherit;
        }
        .form-group input:focus { outline: none; border-color: #D4AF37; box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.15); }
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
        .error {
            background: rgba(239, 68, 68, 0.1);
            color: #EF4444;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 14px;
            border-left: 4px solid #EF4444;
        }
        .hint {
            margin-top: 16px;
            padding: 12px;
            background: #FFF9E6;
            border-radius: 8px;
            font-size: 12px;
            color: #B8941F;
            border-left: 3px solid #D4AF37;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="logo">🏛️ Vastu<span>Kundali</span></div>
        <p class="subtitle">Admin Panel</p>
        <h1>Admin Login</h1>

        <?php if ($error): ?>
            <div class="error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required autofocus value="admin@vastukundali.com">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required placeholder="••••••••">
            </div>
            <button type="submit" class="btn"><i class="fas fa-sign-in-alt"></i> Login to Admin Panel</button>
        </form>

        <div class="hint">
            <strong>Default Credentials:</strong><br>
            Email: admin@vastukundali.com<br>
            Password: admin123<br>
            <em>Change immediately after first login!</em>
        </div>
    </div>
</body>
</html>
