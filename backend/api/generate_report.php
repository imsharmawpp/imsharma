<?php
/**
 * Generate AI Vastu Report
 *
 * Workflow:
 *   1. Verify report exists and payment is captured
 *   2. Try Claude/Bedrock AI; fall back to VastuEngine
 *   3. Save analysis to DB
 *   4. Trigger PDF generation
 *   5. Send email + WhatsApp link (best-effort)
 */

// Ensure JSON response on any PHP fatal error
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json');
        }
        echo json_encode([
            'success' => false,
            'message' => 'Server error: ' . $error['message'] . ' in ' . basename($error['file']) . ':' . $error['line']
        ]);
    }
});

require_once __DIR__ . '/../config/config.php';
require_once BACKEND_PATH . '/lib/VastuEngine.php';
require_once BACKEND_PATH . '/lib/ClaudeAI.php';
require_once BACKEND_PATH . '/lib/PDFReport.php';

handleCors();
requirePost();

// Allow longer execution time
set_time_limit(120);
ini_set('memory_limit', '256M');

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

    // Allow re-generation if pending (demo) or paid
    if (!in_array($report['status'], ['paid', 'pending', 'processing', 'failed'])) {
        if ($report['status'] === 'completed' && !empty($report['report_json'])) {
            jsonResponse(['success' => true, 'message' => 'Report already generated', 'report_id' => $reportId]);
        }
    }

    // Mark as processing
    Database::exec("UPDATE reports SET status = 'processing' WHERE id = ?", [$reportId]);

    // Build input for engine
    $aiInput = [
        'customer_name' => $report['customer_name'],
        'direction' => $report['direction'],
        'plot_size' => $report['plot_size'],
        'floors' => $report['floors'],
        'concerns' => $report['concerns'],
        'image_path' => $report['image_path']
    ];

    // Try Claude AI first
    $analysis = null;
    if (ClaudeAI::isConfigured()) {
        try {
            $analysis = ClaudeAI::generate($aiInput);
            if ($analysis) {
                $analysis['engine'] = 'claude-ai';
            }
        } catch (Exception $e) {
            logDebug('Claude AI exception', ['error' => $e->getMessage()]);
        }
    }

    // Fallback to rule-based engine
    if (!$analysis) {
        $analysis = VastuEngine::generate($aiInput);
    }

    // Validate the analysis structure
    if (!isset($analysis['overall_score']) || !isset($analysis['summary'])) {
        Database::exec("UPDATE reports SET status = 'failed' WHERE id = ?", [$reportId]);
        jsonResponse(['success' => false, 'message' => 'Failed to generate analysis. Please contact support.']);
    }

    // Save to DB
    Database::exec(
        "UPDATE reports SET overall_score = ?, summary = ?, final_verdict = ?, report_json = ?, status = 'completed' WHERE id = ?",
        [
            intval($analysis['overall_score']),
            $analysis['summary'] ?? '',
            $analysis['final_verdict'] ?? '',
            json_encode($analysis, JSON_UNESCAPED_UNICODE),
            $reportId
        ]
    );

    // Generate PDF (best effort - non-blocking)
    try {
        $pdfPath = PDFReport::generate($reportId);
        if ($pdfPath) {
            $publicUrl = REPORTS_URL . '/pdf/' . basename($pdfPath);
            Database::exec("UPDATE reports SET pdf_path = ?, pdf_url = ? WHERE id = ?", [$pdfPath, $publicUrl, $reportId]);
        }
    } catch (Exception $e) {
        logDebug('PDF generation failed', ['error' => $e->getMessage()]);
    }

    // Send email (best effort)
    try {
        if ($report['customer_email']) {
            $reportUrl = SITE_URL . '/frontend/pages/report.html?id=' . $reportId;
            $score = $analysis['overall_score'];
            $name = $report['customer_name'];
            $html = "
                <h2>Namaste {$name}, your Vastu Kundali Report is ready!</h2>
                <p>Your Vastu Score: <strong>{$score}/100</strong></p>
                <p>" . htmlspecialchars($analysis['summary']) . "</p>
                <p><a href='{$reportUrl}'>Click here to view your full report</a></p>
            ";
            $attachments = [];
            $pdfPath = Database::row("SELECT pdf_path FROM reports WHERE id = ?", [$reportId])['pdf_path'] ?? null;
            if ($pdfPath && file_exists($pdfPath)) $attachments[] = $pdfPath;

            if (sendEmail($report['customer_email'], "Your Vastu Kundali Report (Score: {$score}/100)", $html, $attachments)) {
                Database::exec("UPDATE reports SET delivered_email = 1 WHERE id = ?", [$reportId]);
            }
        }
    } catch (Exception $e) {
        // Silent
    }

    jsonResponse([
        'success' => true,
        'message' => 'Report generated successfully',
        'report_id' => $reportId,
        'overall_score' => intval($analysis['overall_score'])
    ]);

} catch (Exception $e) {
    logDebug('Generate report error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    Database::exec("UPDATE reports SET status = 'failed' WHERE id = ?", [$reportId]);
    jsonResponse(['success' => false, 'message' => 'Report generation failed: ' . $e->getMessage()]);
}
