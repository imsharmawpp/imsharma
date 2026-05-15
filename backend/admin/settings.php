<?php
require_once __DIR__ . '/../config/config.php';
requireAdminLogin();
$pageTitle = 'Settings';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'setting_') === 0) {
            $settingKey = substr($key, 8);
            setSetting($settingKey, clean($value));
        }
    }
    header('Location: settings.php?saved=1');
    exit;
}

// Group settings
$groups = [
    'general' => ['icon' => 'cog', 'title' => 'General Settings'],
    'pricing' => ['icon' => 'tag', 'title' => 'Pricing'],
    'payment' => ['icon' => 'credit-card', 'title' => 'Razorpay Payment'],
    'ai' => ['icon' => 'brain', 'title' => 'AI Configuration'],
    'aws' => ['icon' => 'cloud', 'title' => 'AWS Bedrock (optional)'],
    'email' => ['icon' => 'envelope', 'title' => 'Email / SMTP'],
    'shipping' => ['icon' => 'truck', 'title' => 'Shipping'],
    'tax' => ['icon' => 'percent', 'title' => 'Tax'],
];

$allSettings = Database::all("SELECT * FROM settings ORDER BY setting_group, id");
$grouped = [];
foreach ($allSettings as $s) {
    $grouped[$s['setting_group']][] = $s;
}

include '_header.php';
?>

<?php if (!empty($_GET['saved'])): ?>
    <div style="background: #10B981; color: white; padding: 12px 20px; border-radius: 8px; margin-bottom: 16px;">
        <i class="fas fa-check-circle"></i> Settings saved successfully!
    </div>
<?php endif; ?>

<form method="POST">
    <?php foreach ($groups as $groupKey => $groupInfo): ?>
        <?php if (empty($grouped[$groupKey])) continue; ?>
        <div class="admin-card">
            <h2 style="margin-bottom:20px;"><i class="fas fa-<?= $groupInfo['icon'] ?>" style="color:#D4AF37;"></i> <?= $groupInfo['title'] ?></h2>
            <div style="display:grid;grid-template-columns:repeat(2, 1fr);gap:20px;">
                <?php foreach ($grouped[$groupKey] as $s): ?>
                    <div class="form-group">
                        <label><?= htmlspecialchars($s['description'] ?: ucwords(str_replace('_', ' ', $s['setting_key']))) ?></label>
                        <?php
                        $isPassword = (strpos($s['setting_key'], 'secret') !== false) || (strpos($s['setting_key'], 'pass') !== false) || (strpos($s['setting_key'], 'api_key') !== false);
                        $isLong = in_array($s['setting_key'], ['site_description', 'support_message']);
                        ?>
                        <?php if ($isLong): ?>
                            <textarea name="setting_<?= $s['setting_key'] ?>" rows="3"><?= htmlspecialchars($s['setting_value']) ?></textarea>
                        <?php else: ?>
                            <input type="<?= $isPassword ? 'password' : 'text' ?>"
                                   name="setting_<?= $s['setting_key'] ?>"
                                   value="<?= htmlspecialchars($s['setting_value']) ?>"
                                   placeholder="<?= $isPassword ? '••••••••' : '' ?>">
                        <?php endif; ?>
                        <small style="color:#9CA3AF;font-size:11px;">Key: <?= $s['setting_key'] ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <div style="position:sticky;bottom:0;background:white;padding:16px;margin-top:24px;border-top:1px solid #E5E5E5;border-radius:8px;text-align:center;">
        <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> Save All Settings</button>
    </div>
</form>

<?php include '_footer.php'; ?>
