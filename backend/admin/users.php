<?php
require_once __DIR__ . '/../config/config.php';
requireAdminLogin();
$pageTitle = 'Users';

$search = trim($_GET['q'] ?? '');
$where = "WHERE role = 'user'";
$params = [];
if ($search) {
    $where .= " AND (name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $params = ["%{$search}%", "%{$search}%", "%{$search}%"];
}
$users = Database::all("SELECT u.*,
    (SELECT COUNT(*) FROM reports WHERE user_id = u.id) AS reports_count,
    (SELECT COUNT(*) FROM orders WHERE user_id = u.id) AS orders_count,
    (SELECT COALESCE(SUM(amount),0) FROM orders WHERE user_id = u.id AND status = 'paid') AS total_spent
    FROM users u {$where} ORDER BY u.id DESC LIMIT 100", $params);

include '_header.php';
?>

<form method="GET" class="admin-card" style="display: flex; gap: 12px; align-items: end;">
    <div style="flex: 1;">
        <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;">Search Users</label>
        <input type="text" name="q" placeholder="Name, email, phone..." value="<?= htmlspecialchars($search) ?>" style="width:100%;padding:10px 14px;border:1.5px solid #E5E5E5;border-radius:8px;">
    </div>
    <button class="btn btn-dark" type="submit"><i class="fas fa-search"></i> Search</button>
</form>

<div class="admin-card">
    <h2 style="margin-bottom: 16px;">Users (<?= count($users) ?>)</h2>
    <table class="admin-table">
        <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>City</th><th>Reports</th><th>Orders</th><th>Spent</th><th>Joined</th></tr></thead>
        <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td>#<?= $u['id'] ?></td>
                    <td><strong><?= htmlspecialchars($u['name']) ?></strong></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><?= htmlspecialchars($u['phone'] ?: '-') ?></td>
                    <td><?= htmlspecialchars($u['city'] ?: '-') ?></td>
                    <td><strong style="color:#D4AF37;"><?= $u['reports_count'] ?></strong></td>
                    <td><?= $u['orders_count'] ?></td>
                    <td>₹<?= number_format($u['total_spent'], 0) ?></td>
                    <td><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($users)): ?>
                <tr><td colspan="9" style="text-align:center;padding:40px;color:#4B5563;">No users found</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include '_footer.php'; ?>
