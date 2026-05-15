<?php
require_once __DIR__ . '/../config/config.php';
requireAdminLogin();
$pageTitle = 'Orders';

// Update order status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['update_status'])) {
    $orderId = intval($_POST['order_id']);
    $newStatus = clean($_POST['status']);
    $tracking = clean($_POST['tracking'] ?? '');
    if (in_array($newStatus, ['pending','paid','processing','shipped','delivered','cancelled','refunded'])) {
        Database::exec("UPDATE orders SET status = ?, tracking_number = ? WHERE id = ?", [$newStatus, $tracking, $orderId]);
        header('Location: orders.php?updated=1');
        exit;
    }
}

$status = $_GET['status'] ?? '';
$where = "WHERE 1=1";
$params = [];
if ($status) { $where .= " AND status = ?"; $params[] = $status; }

$orders = Database::all("SELECT * FROM orders {$where} ORDER BY id DESC LIMIT 100", $params);

include '_header.php';
?>

<?php if (!empty($_GET['updated'])): ?>
    <div style="background: #10B981; color: white; padding: 12px 20px; border-radius: 8px; margin-bottom: 16px;">
        <i class="fas fa-check-circle"></i> Order updated successfully!
    </div>
<?php endif; ?>

<form method="GET" class="admin-card" style="display: flex; gap: 12px; align-items: end;">
    <div>
        <label style="display:block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Status</label>
        <select name="status" onchange="this.form.submit()" style="padding: 10px 14px; border: 1.5px solid #E5E5E5; border-radius: 8px;">
            <option value="">All Orders</option>
            <?php foreach (['pending','paid','processing','shipped','delivered','cancelled'] as $s): ?>
                <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<div class="admin-card">
    <h2 style="margin-bottom: 20px;">Orders (<?= count($orders) ?>)</h2>
    <table class="admin-table">
        <thead>
            <tr><th>Order #</th><th>Customer</th><th>Items</th><th>Amount</th><th>Method</th><th>Status</th><th>Tracking</th><th>Date</th><th>Actions</th></tr>
        </thead>
        <tbody>
            <?php foreach ($orders as $o):
                $items = json_decode($o['items_json'] ?? '[]', true);
                $method = $o['payment_method'] ?? 'online';
            ?>
                <tr>
                    <td><strong>#<?= $o['id'] ?></strong></td>
                    <td>
                        <strong><?= htmlspecialchars($o['customer_name']) ?></strong><br>
                        <small style="color:#4B5563;"><?= htmlspecialchars($o['customer_email']) ?></small>
                        <?php if (!empty($o['phone_verified'])): ?>
                            <br><span style="color:#10B981;font-size:11px;"><i class="fas fa-check-circle"></i> Verified</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= $o['items_count'] ?> items
                        <?php if (!empty($items)): ?>
                            <br><small style="color:#4B5563;"><?= htmlspecialchars(implode(', ', array_slice(array_column($items, 'name'), 0, 2))) ?>...</small>
                        <?php endif; ?>
                    </td>
                    <td><strong>₹<?= number_format($o['amount'], 0) ?></strong></td>
                    <td>
                        <?php if ($method === 'cod'): ?>
                            <span style="background:rgba(245,158,11,0.15);color:#F59E0B;padding:3px 10px;border-radius:100px;font-size:11px;font-weight:700;">
                                <i class="fas fa-money-bill"></i> COD
                            </span>
                        <?php else: ?>
                            <span style="background:rgba(59,130,246,0.15);color:#3B82F6;padding:3px 10px;border-radius:100px;font-size:11px;font-weight:700;">
                                <i class="fas fa-credit-card"></i> Online
                            </span>
                        <?php endif; ?>
                    </td>
                    <td><span class="status-badge <?= in_array($o['status'], ['paid','delivered','shipped']) ? 'success' : 'pending' ?>"><?= $o['status'] ?></span></td>
                    <td><?= htmlspecialchars($o['tracking_number'] ?: '-') ?></td>
                    <td><?= date('M j', strtotime($o['created_at'])) ?></td>
                    <td>
                        <button class="action-btn edit" onclick="document.getElementById('edit-<?= $o['id'] ?>').style.display='block'"><i class="fas fa-edit"></i></button>
                    </td>
                </tr>
                <tr id="edit-<?= $o['id'] ?>" style="display:none;background:#FFF9E6;">
                    <td colspan="9" style="padding: 16px;">
                        <form method="POST" style="display: flex; gap: 12px; align-items: end; flex-wrap: wrap;">
                            <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                            <input type="hidden" name="update_status" value="1">
                            <div>
                                <label style="display:block;font-size:12px;font-weight:600;">Status</label>
                                <select name="status" style="padding: 8px; border: 1px solid #E5E5E5; border-radius: 6px;">
                                    <?php foreach (['pending','paid','processing','shipped','delivered','cancelled','refunded'] as $s): ?>
                                        <option value="<?= $s ?>" <?= $o['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div style="flex:1; min-width: 180px;">
                                <label style="display:block;font-size:12px;font-weight:600;">Tracking Number</label>
                                <input type="text" name="tracking" value="<?= htmlspecialchars($o['tracking_number']) ?>" style="width: 100%; padding: 8px; border: 1px solid #E5E5E5; border-radius: 6px;">
                            </div>
                            <button class="btn btn-primary btn-sm" type="submit"><i class="fas fa-save"></i> Save</button>
                            <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('edit-<?= $o['id'] ?>').style.display='none'">Cancel</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($orders)): ?>
                <tr><td colspan="9" style="text-align: center; padding: 40px; color: #4B5563;">No orders found</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include '_footer.php'; ?>
