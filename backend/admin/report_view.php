<?php
require_once __DIR__ . '/../config/config.php';
require_once BACKEND_PATH . '/lib/PDFReport.php';
requireAdminLogin();

$id = intval($_GET['id'] ?? 0);
$report = $id ? Database::row("SELECT * FROM reports WHERE id = ?", [$id]) : null;

if (!$report) {
    header('Location: reports.php');
    exit;
}

$pageTitle = "Report #{$report['id']} - " . htmlspecialchars($report['customer_name']);

// Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['regenerate'])) {
        require_once BACKEND_PATH . '/lib/VastuEngine.php';
        require_once BACKEND_PATH . '/lib/ClaudeAI.php';

        $aiInput = [
            'customer_name' => $report['customer_name'],
            'direction' => $report['direction'],
            'plot_size' => $report['plot_size'],
            'floors' => $report['floors'],
            'concerns' => $report['concerns'],
            'image_path' => $report['image_path']
        ];

        $analysis = ClaudeAI::isConfigured() ? ClaudeAI::generate($aiInput) : null;
        if (!$analysis) $analysis = VastuEngine::generate($aiInput);

        Database::exec(
            "UPDATE reports SET overall_score = ?, summary = ?, final_verdict = ?, report_json = ?, status = 'completed' WHERE id = ?",
            [$analysis['overall_score'], $analysis['summary'], $analysis['final_verdict'], json_encode($analysis), $id]
        );

        // Regenerate PDF
        PDFReport::generate($id);

        header('Location: report_view.php?id=' . $id . '&regenerated=1');
        exit;
    }

    if (!empty($_POST['update_status'])) {
        Database::exec("UPDATE reports SET status = ? WHERE id = ?", [clean($_POST['status']), $id]);
        header('Location: report_view.php?id=' . $id . '&updated=1');
        exit;
    }
}

$report = Database::row("SELECT * FROM reports WHERE id = ?", [$id]);
$analysis = json_decode($report['report_json'] ?? '[]', true) ?: [];

include '_header.php';
?>

<?php if (!empty($_GET['regenerated'])): ?>
    <div style="background:#10B981;color:white;padding:12px 20px;border-radius:8px;margin-bottom:16px;">
        <i class="fas fa-check-circle"></i> Report regenerated successfully!
    </div>
<?php endif; ?>
<?php if (!empty($_GET['updated'])): ?>
    <div style="background:#10B981;color:white;padding:12px 20px;border-radius:8px;margin-bottom:16px;">
        <i class="fas fa-check-circle"></i> Status updated!
    </div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:24px;">
    <!-- Report Details -->
    <div>
        <div class="admin-card">
            <h2 style="margin-bottom:16px;">Customer Information</h2>
            <table class="admin-table">
                <tr><th>Name</th><td><?= htmlspecialchars($report['customer_name']) ?></td></tr>
                <tr><th>Email</th><td><?= htmlspecialchars($report['customer_email']) ?></td></tr>
                <tr><th>Phone</th><td><?= htmlspecialchars($report['customer_phone']) ?></td></tr>
                <tr><th>City</th><td><?= htmlspecialchars($report['city']) ?: '-' ?></td></tr>
                <tr><th>Direction</th><td><?= formatDirection($report['direction']) ?></td></tr>
                <tr><th>Plot Size</th><td><?= htmlspecialchars($report['plot_size']) ?: '-' ?></td></tr>
                <tr><th>Floors</th><td><?= htmlspecialchars($report['floors']) ?: '-' ?></td></tr>
                <tr><th>Concerns</th><td><?= htmlspecialchars($report['concerns']) ?: '-' ?></td></tr>
                <tr><th>Status</th><td><span class="status-badge <?= $report['status'] === 'completed' ? 'success' : 'pending' ?>"><?= $report['status'] ?></span></td></tr>
                <tr><th>Overall Score</th><td><strong style="color:#D4AF37;"><?= $report['overall_score'] ?: '-' ?>/100</strong></td></tr>
                <tr><th>Created</th><td><?= date('M j, Y H:i', strtotime($report['created_at'])) ?></td></tr>
                <tr><th>Payment ID</th><td><?= htmlspecialchars($report['payment_id'] ?: '-') ?></td></tr>
                <tr><th>Email Delivered</th><td><?= $report['delivered_email'] ? '✓ Yes' : '✗ No' ?></td></tr>
            </table>
        </div>

        <?php if (!empty($report['image_url'])): ?>
            <div class="admin-card">
                <h2 style="margin-bottom:16px;">Uploaded Plan</h2>
                <a href="<?= htmlspecialchars($report['image_url']) ?>" target="_blank">
                    <img src="<?= htmlspecialchars($report['image_url']) ?>" style="max-width: 100%; border-radius: 8px; border: 1px solid #E5E5E5;">
                </a>
            </div>
        <?php endif; ?>

        <?php if (!empty($analysis)): ?>
        <div class="admin-card">
            <h2 style="margin-bottom: 16px;">AI Analysis Output</h2>
            <p style="color:#4B5563;margin-bottom:12px;"><strong>Engine:</strong> <?= htmlspecialchars($analysis['engine'] ?? 'unknown') ?></p>

            <?php if (!empty($analysis['summary'])): ?>
                <h3 style="font-size:16px;margin-bottom:8px;">Summary</h3>
                <p style="margin-bottom:16px;color:#1F2937;line-height:1.7;"><?= htmlspecialchars($analysis['summary']) ?></p>
            <?php endif; ?>

            <details style="margin-top:16px;">
                <summary style="cursor:pointer;font-weight:600;color:#D4AF37;">View Raw JSON</summary>
                <pre style="background:#0A0E27;color:#84CC16;padding:16px;border-radius:8px;overflow-x:auto;font-size:12px;margin-top:12px;"><?= htmlspecialchars(json_encode($analysis, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
            </details>
        </div>
        <?php endif; ?>
    </div>

    <!-- Sidebar Actions -->
    <div>
        <div class="admin-card">
            <h3 style="margin-bottom:16px;">Actions</h3>
            <div style="display:flex;flex-direction:column;gap:8px;">
                <a href="../../frontend/pages/report.html?id=<?= $id ?>" target="_blank" class="btn btn-primary btn-block">
                    <i class="fas fa-eye"></i> View on Frontend
                </a>
                <a href="../api/download_pdf.php?id=<?= $id ?>" target="_blank" class="btn btn-dark btn-block">
                    <i class="fas fa-download"></i> Download Report
                </a>
                <form method="POST" style="margin:0;">
                    <input type="hidden" name="regenerate" value="1">
                    <button type="submit" onclick="return confirm('Regenerate report? This will replace the current analysis.');" class="btn btn-outline btn-block" style="width:100%;">
                        <i class="fas fa-redo"></i> Regenerate Analysis
                    </button>
                </form>
            </div>
        </div>

        <div class="admin-card">
            <h3 style="margin-bottom:16px;">Update Status</h3>
            <form method="POST">
                <input type="hidden" name="update_status" value="1">
                <select name="status" style="width:100%;padding:10px;border:1.5px solid #E5E5E5;border-radius:8px;margin-bottom:12px;">
                    <?php foreach (['pending','paid','processing','completed','failed'] as $s): ?>
                        <option value="<?= $s ?>" <?= $report['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-primary btn-block" type="submit"><i class="fas fa-save"></i> Update</button>
            </form>
        </div>

        <div class="admin-card">
            <h3 style="margin-bottom:16px;">Quick Contact</h3>
            <a href="mailto:<?= htmlspecialchars($report['customer_email']) ?>" class="btn btn-outline btn-block" style="margin-bottom:8px;">
                <i class="fas fa-envelope"></i> Email Customer
            </a>
            <a href="https://wa.me/91<?= preg_replace('/\D/', '', $report['customer_phone']) ?>" target="_blank" class="btn btn-outline btn-block">
                <i class="fab fa-whatsapp"></i> WhatsApp
            </a>
        </div>
    </div>
</div>

<?php include '_footer.php'; ?>
