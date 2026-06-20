<?php
require_once __DIR__ . '/../config/config.php';
requireAdminLogin();
$pageTitle = 'Leads';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['update_status'])) {
    Database::exec("UPDATE leads SET status = ?, notes = ? WHERE id = ?", [
        clean($_POST['status']), clean($_POST['notes'] ?? ''), intval($_POST['lead_id'])
    ]);
    header('Location: leads.php?updated=1');
    exit;
}

$leads = Database::all("SELECT * FROM leads ORDER BY id DESC LIMIT 200");

include '_header.php';
?>

<div class="admin-card">
    <h2 style="margin-bottom: 16px;">Leads (<?= count($leads) ?>)</h2>
    <p style="color:#4B5563;margin-bottom:16px;">All form submissions, partial uploads, and contact requests.</p>
    <table class="admin-table">
        <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Source</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
        <tbody>
            <?php foreach ($leads as $l): ?>
                <tr>
                    <td>#<?= $l['id'] ?></td>
                    <td><?= htmlspecialchars($l['name']) ?></td>
                    <td><?= htmlspecialchars($l['email']) ?></td>
                    <td><?= htmlspecialchars($l['phone']) ?></td>
                    <td><span class="status-badge pending"><?= $l['source'] ?></span></td>
                    <td>
                        <span class="status-badge <?= $l['status'] === 'converted' ? 'success' : 'pending' ?>"><?= $l['status'] ?></span>
                    </td>
                    <td><?= date('M j', strtotime($l['created_at'])) ?></td>
                    <td>
                        <a href="https://wa.me/91<?= preg_replace('/\D/', '', $l['phone']) ?>" target="_blank" class="action-btn view"><i class="fab fa-whatsapp"></i></a>
                        <a href="mailto:<?= htmlspecialchars($l['email']) ?>" class="action-btn edit"><i class="fas fa-envelope"></i></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($leads)): ?>
                <tr><td colspan="8" style="text-align:center;padding:40px;color:#4B5563;">No leads yet</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include '_footer.php'; ?>
