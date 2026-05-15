<?php
require_once __DIR__ . '/../config/config.php';
requireAdminLogin();
$pageTitle = 'All Reports';

// Filters
$status = $_GET['status'] ?? '';
$search = trim($_GET['q'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

$where = "WHERE 1=1";
$params = [];
if ($status) {
    $where .= " AND status = ?";
    $params[] = $status;
}
if ($search) {
    $where .= " AND (customer_name LIKE ? OR customer_email LIKE ? OR id = ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = intval($search);
}

$total = Database::row("SELECT COUNT(*) as c FROM reports {$where}", $params)['c'] ?? 0;
$reports = Database::all("SELECT id, customer_name, customer_email, customer_phone, direction, overall_score, status, amount, created_at FROM reports {$where} ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}", $params);

include '_header.php';
?>

<form method="GET" class="admin-card" style="display: flex; gap: 12px; align-items: end; flex-wrap: wrap;">
    <div style="flex: 1; min-width: 200px;">
        <label style="display:block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Search</label>
        <input type="text" name="q" placeholder="Name, email or ID..." value="<?= htmlspecialchars($search) ?>" style="width: 100%; padding: 10px 14px; border: 1.5px solid #E5E5E5; border-radius: 8px;">
    </div>
    <div>
        <label style="display:block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Status</label>
        <select name="status" style="padding: 10px 14px; border: 1.5px solid #E5E5E5; border-radius: 8px;">
            <option value="">All Statuses</option>
            <?php foreach (['pending','paid','processing','completed','failed'] as $s): ?>
                <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button class="btn btn-dark" type="submit"><i class="fas fa-search"></i> Filter</button>
</form>

<div class="admin-card">
    <div style="display:flex; justify-content: space-between; margin-bottom: 16px;">
        <h2 style="margin: 0;">Reports (<?= $total ?>)</h2>
    </div>
    <div style="overflow-x: auto;">
    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th><th>Customer</th><th>Phone</th><th>Direction</th><th>Score</th><th>Amount</th><th>Status</th><th>Date</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($reports)): ?>
                <tr><td colspan="9" style="text-align: center; padding: 40px; color: #4B5563;">No reports match your filters</td></tr>
            <?php else: foreach ($reports as $r): ?>
                <tr>
                    <td><strong>#<?= $r['id'] ?></strong></td>
                    <td>
                        <strong><?= htmlspecialchars($r['customer_name']) ?></strong><br>
                        <small style="color:#4B5563;"><?= htmlspecialchars($r['customer_email']) ?></small>
                    </td>
                    <td><?= htmlspecialchars($r['customer_phone']) ?></td>
                    <td><?= htmlspecialchars(formatDirection($r['direction'])) ?></td>
                    <td><strong style="color: #B8941F;"><?= $r['overall_score'] ?: '-' ?>/100</strong></td>
                    <td>₹<?= number_format($r['amount'], 0) ?></td>
                    <td><span class="status-badge <?= $r['status'] === 'completed' ? 'success' : 'pending' ?>"><?= $r['status'] ?></span></td>
                    <td><?= date('M j, Y', strtotime($r['created_at'])) ?></td>
                    <td class="actions">
                        <a href="../../frontend/pages/report.html?id=<?= $r['id'] ?>" target="_blank" class="action-btn view" title="View"><i class="fas fa-eye"></i></a>
                        <a href="../api/download_pdf.php?id=<?= $r['id'] ?>" target="_blank" class="action-btn edit" title="Download"><i class="fas fa-download"></i></a>
                        <a href="report_view.php?id=<?= $r['id'] ?>" class="action-btn view" title="Manage"><i class="fas fa-cog"></i></a>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
    </div>

    <!-- Pagination -->
    <?php if ($total > $perPage): ?>
        <?php $totalPages = ceil($total / $perPage); ?>
        <div style="display: flex; gap: 8px; justify-content: center; margin-top: 24px;">
            <?php for ($i = 1; $i <= min(10, $totalPages); $i++): ?>
                <a href="?page=<?= $i ?>&status=<?= urlencode($status) ?>&q=<?= urlencode($search) ?>"
                   class="btn btn-sm <?= $i === $page ? 'btn-dark' : 'btn-outline' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<?php include '_footer.php'; ?>
