<?php
require_once __DIR__ . '/../config/config.php';
requireAdminLogin();
$pageTitle = 'Dashboard';

// Aggregate stats
try {
    $stats = [
        'total_users' => Database::row("SELECT COUNT(*) AS c FROM users WHERE role='user'")['c'] ?? 0,
        'total_reports' => Database::row("SELECT COUNT(*) AS c FROM reports")['c'] ?? 0,
        'completed_reports' => Database::row("SELECT COUNT(*) AS c FROM reports WHERE status='completed'")['c'] ?? 0,
        'pending_reports' => Database::row("SELECT COUNT(*) AS c FROM reports WHERE status IN ('pending','processing')")['c'] ?? 0,
        'total_orders' => Database::row("SELECT COUNT(*) AS c FROM orders")['c'] ?? 0,
        'paid_orders' => Database::row("SELECT COUNT(*) AS c FROM orders WHERE status='paid'")['c'] ?? 0,
        'report_revenue' => Database::row("SELECT COALESCE(SUM(amount),0) AS s FROM reports WHERE status='completed' OR status='paid'")['s'] ?? 0,
        'order_revenue' => Database::row("SELECT COALESCE(SUM(amount),0) AS s FROM orders WHERE status='paid'")['s'] ?? 0,
        'total_leads' => Database::row("SELECT COUNT(*) AS c FROM leads")['c'] ?? 0,
        'today_revenue' => Database::row("SELECT COALESCE(SUM(amount),0) AS s FROM payments WHERE status='captured' AND DATE(created_at)=CURDATE()")['s'] ?? 0,
    ];

    $totalRevenue = $stats['report_revenue'] + $stats['order_revenue'];

    // Recent reports
    $recentReports = Database::all("SELECT id, customer_name, customer_email, direction, overall_score, status, created_at FROM reports ORDER BY id DESC LIMIT 8");

    // Recent orders
    $recentOrders = Database::all("SELECT id, customer_name, customer_email, items_count, amount, status, created_at FROM orders ORDER BY id DESC LIMIT 5");

} catch (Exception $e) {
    $stats = array_fill_keys(['total_users','total_reports','completed_reports','pending_reports','total_orders','paid_orders','report_revenue','order_revenue','total_leads','today_revenue'], 0);
    $totalRevenue = 0;
    $recentReports = [];
    $recentOrders = [];
}

include '_header.php';
?>

<!-- KPI Cards -->
<div class="dashboard-stats">
    <div class="dash-stat-card" style="border-left: 4px solid #D4AF37;">
        <div class="label">Total Revenue</div>
        <div class="value">₹<?= number_format($totalRevenue) ?></div>
        <div style="font-size: 12px; color: #10B981; margin-top: 4px;">
            <i class="fas fa-arrow-up"></i> Today: ₹<?= number_format($stats['today_revenue']) ?>
        </div>
    </div>
    <div class="dash-stat-card" style="border-left: 4px solid #10B981;">
        <div class="label">Reports Generated</div>
        <div class="value"><?= $stats['completed_reports'] ?></div>
        <div style="font-size: 12px; color: #4B5563; margin-top: 4px;">
            <?= $stats['pending_reports'] ?> pending &middot; <?= $stats['total_reports'] ?> total
        </div>
    </div>
    <div class="dash-stat-card" style="border-left: 4px solid #3B82F6;">
        <div class="label">Total Users</div>
        <div class="value"><?= $stats['total_users'] ?></div>
        <div style="font-size: 12px; color: #4B5563; margin-top: 4px;">
            <?= $stats['total_leads'] ?> leads captured
        </div>
    </div>
    <div class="dash-stat-card" style="border-left: 4px solid #F59E0B;">
        <div class="label">Orders</div>
        <div class="value"><?= $stats['paid_orders'] ?></div>
        <div style="font-size: 12px; color: #4B5563; margin-top: 4px;">
            <?= $stats['total_orders'] ?> total orders
        </div>
    </div>
</div>

<!-- Recent Reports -->
<div class="admin-card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
        <h2 style="margin: 0;">Recent Reports</h2>
        <a href="reports.php" class="btn btn-outline btn-sm">View All</a>
    </div>
    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th><th>Customer</th><th>Direction</th><th>Score</th><th>Status</th><th>Date</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($recentReports)): ?>
                <tr><td colspan="7" style="text-align: center; padding: 32px; color: #4B5563;">No reports yet</td></tr>
            <?php else: foreach ($recentReports as $r): ?>
                <tr>
                    <td><strong>#<?= $r['id'] ?></strong></td>
                    <td>
                        <strong><?= htmlspecialchars($r['customer_name']) ?></strong><br>
                        <small style="color:#4B5563;"><?= htmlspecialchars($r['customer_email']) ?></small>
                    </td>
                    <td><?= htmlspecialchars(formatDirection($r['direction'])) ?></td>
                    <td><strong style="color:#B8941F;"><?= $r['overall_score'] ?? '-' ?>/100</strong></td>
                    <td><span class="status-badge <?= $r['status'] === 'completed' ? 'success' : 'pending' ?>"><?= $r['status'] ?></span></td>
                    <td><?= date('M j, Y', strtotime($r['created_at'])) ?></td>
                    <td class="actions">
                        <a href="../../frontend/pages/report.html?id=<?= $r['id'] ?>" target="_blank" class="action-btn view"><i class="fas fa-eye"></i></a>
                        <a href="report_view.php?id=<?= $r['id'] ?>" class="action-btn edit"><i class="fas fa-edit"></i></a>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<!-- Recent Orders -->
<div class="admin-card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
        <h2 style="margin: 0;">Recent Orders</h2>
        <a href="orders.php" class="btn btn-outline btn-sm">View All</a>
    </div>
    <table class="admin-table">
        <thead>
            <tr>
                <th>Order #</th><th>Customer</th><th>Items</th><th>Amount</th><th>Status</th><th>Date</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($recentOrders)): ?>
                <tr><td colspan="6" style="text-align: center; padding: 32px; color: #4B5563;">No orders yet</td></tr>
            <?php else: foreach ($recentOrders as $o): ?>
                <tr>
                    <td><strong>#<?= $o['id'] ?></strong></td>
                    <td><?= htmlspecialchars($o['customer_name']) ?></td>
                    <td><?= $o['items_count'] ?> items</td>
                    <td><strong>₹<?= number_format($o['amount'], 0) ?></strong></td>
                    <td><span class="status-badge <?= $o['status'] === 'paid' ? 'success' : 'pending' ?>"><?= $o['status'] ?></span></td>
                    <td><?= date('M j, Y', strtotime($o['created_at'])) ?></td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php include '_footer.php'; ?>
