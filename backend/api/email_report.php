<?php
require_once __DIR__ . '/../config/config.php';
handleCors();
requirePost();

$input = jsonInput();
$reportId = intval($input['report_id'] ?? 0);

if (!$reportId) {
    jsonResponse(['success' => false, 'message' => 'Report ID required']);
}

try {
    $report = Database::row("SELECT * FROM reports WHERE id = ?", [$reportId]);
    if (!$report) {
        jsonResponse(['success' => false, 'message' => 'Report not found']);
    }

    $email = $report['customer_email'];
    $name = $report['customer_name'];
    $score = $report['overall_score'];
    $reportUrl = SITE_URL . '/frontend/pages/report.html?id=' . $reportId;

    $html = "
    <!DOCTYPE html>
    <html><body style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
        <div style='background: linear-gradient(135deg, #0A0E27 0%, #131938 100%); color: white; padding: 40px; text-align: center; border-radius: 12px;'>
            <h1 style='color: #D4AF37; margin: 0;'>🏛️ Vastu Kundali Report</h1>
            <p>Your AI-Powered Vastu Analysis</p>
        </div>
        <div style='padding: 30px; background: #FAF8F1; border-radius: 12px; margin-top: 20px;'>
            <h2>Namaste, {$name} 🙏</h2>
            <p>Your personalized Vastu Home Kundali report is ready!</p>
            <div style='background: white; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: center;'>
                <div style='font-size: 14px; color: #666;'>Your Vastu Score</div>
                <div style='font-size: 48px; font-weight: bold; color: #D4AF37;'>{$score}/100</div>
            </div>
            <p>" . htmlspecialchars($report['summary']) . "</p>
            <p style='text-align: center; margin: 30px 0;'>
                <a href='{$reportUrl}' style='background: #D4AF37; color: #0A0E27; padding: 14px 32px; text-decoration: none; border-radius: 8px; font-weight: bold;'>View Full Report</a>
            </p>
            <hr style='border: none; border-top: 1px solid #ddd; margin: 30px 0;'>
            <p style='font-size: 13px; color: #666;'>Need help? Reply to this email or WhatsApp us at +919876543210.</p>
            <p style='font-size: 13px; color: #666;'>Vastu blessings,<br><strong>VastuKundali Team</strong></p>
        </div>
    </body></html>";

    $attachments = [];
    if (!empty($report['pdf_path']) && file_exists($report['pdf_path'])) {
        $attachments[] = $report['pdf_path'];
    }

    $sent = sendEmail($email, "Your Vastu Kundali Report (Score: {$score}/100)", $html, $attachments);

    if ($sent) {
        Database::exec("UPDATE reports SET delivered_email = 1 WHERE id = ?", [$reportId]);
        jsonResponse(['success' => true, 'message' => 'Report sent to ' . $email]);
    } else {
        jsonResponse(['success' => false, 'message' => 'Failed to send email. Mail server may not be configured.']);
    }

} catch (Exception $e) {
    logDebug('Email report error', ['error' => $e->getMessage()]);
    jsonResponse(['success' => false, 'message' => 'Failed to send email']);
}
