<?php
require_once __DIR__ . '/../config/config.php';
requireAdminLogin();
$pageTitle = 'Coupons';

if (!empty($_GET['delete'])) {
    Database::exec("UPDATE coupons SET is_active = 0 WHERE id = ?", [intval($_GET['delete'])]);
    header('Location: coupons.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Database::insert('coupons', [
        'code' => strtoupper(clean($_POST['code'])),
        'description' => clean($_POST['description']),
        'type' => clean($_POST['type']),
        'value' => floatval($_POST['value']),
        'min_order' => floatval($_POST['min_order'] ?: 0),
        'usage_limit' => intval($_POST['usage_limit'] ?: 0),
        'applies_to' => clean($_POST['applies_to']),
        'valid_from' => date('Y-m-d H:i:s'),
        'valid_until' => $_POST['valid_until'] ?: date('Y-m-d H:i:s', strtotime('+1 year')),
        'is_active' => 1
    ]);
    header('Location: coupons.php?saved=1');
    exit;
}

$coupons = Database::all("SELECT * FROM coupons WHERE is_active = 1 ORDER BY id DESC");

include '_header.php';
?>

<div class="admin-card">
    <h2 style="margin-bottom:16px;">Add New Coupon</h2>
    <form method="POST">
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;">
            <div class="form-group">
                <label>Code *</label>
                <input type="text" name="code" required placeholder="VASTU20" style="text-transform:uppercase;">
            </div>
            <div class="form-group">
                <label>Type *</label>
                <select name="type">
                    <option value="percentage">Percentage Off</option>
                    <option value="fixed">Fixed Amount Off</option>
                </select>
            </div>
            <div class="form-group">
                <label>Value *</label>
                <input type="number" name="value" required step="0.01" placeholder="20">
            </div>
            <div class="form-group">
                <label>Min Order (₹)</label>
                <input type="number" name="min_order" step="0.01" placeholder="0">
            </div>
            <div class="form-group">
                <label>Usage Limit (0 = unlimited)</label>
                <input type="number" name="usage_limit" placeholder="0">
            </div>
            <div class="form-group">
                <label>Applies To</label>
                <select name="applies_to">
                    <option value="all">All</option>
                    <option value="reports">Reports Only</option>
                    <option value="products">Products Only</option>
                </select>
            </div>
            <div class="form-group">
                <label>Valid Until</label>
                <input type="datetime-local" name="valid_until">
            </div>
            <div class="form-group" style="grid-column: 1 / -1;">
                <label>Description</label>
                <input type="text" name="description" placeholder="20% off for first-time users">
            </div>
        </div>
        <button class="btn btn-primary" type="submit"><i class="fas fa-plus"></i> Create Coupon</button>
    </form>
</div>

<div class="admin-card">
    <h2 style="margin-bottom: 16px;">Active Coupons (<?= count($coupons) ?>)</h2>
    <table class="admin-table">
        <thead><tr><th>Code</th><th>Description</th><th>Discount</th><th>Min Order</th><th>Used</th><th>Limit</th><th>Valid Until</th><th>Actions</th></tr></thead>
        <tbody>
            <?php foreach ($coupons as $c): ?>
                <tr>
                    <td><strong style="color:#D4AF37;"><?= $c['code'] ?></strong></td>
                    <td><?= htmlspecialchars($c['description']) ?></td>
                    <td><?= $c['type'] === 'percentage' ? $c['value'] . '%' : '₹' . $c['value'] ?></td>
                    <td>₹<?= number_format($c['min_order'], 0) ?></td>
                    <td><?= $c['used_count'] ?></td>
                    <td><?= $c['usage_limit'] ?: '∞' ?></td>
                    <td><?= $c['valid_until'] ? date('M j, Y', strtotime($c['valid_until'])) : '-' ?></td>
                    <td><a href="?delete=<?= $c['id'] ?>" onclick="return confirmDelete('Disable this coupon?')" class="action-btn delete"><i class="fas fa-trash"></i></a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include '_footer.php'; ?>
