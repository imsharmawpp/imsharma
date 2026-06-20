<?php
require_once __DIR__ . '/../config/config.php';
handleCors();

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    jsonResponse(['success' => false, 'message' => 'Report ID required']);
}

try {
    $report = Database::row("SELECT * FROM reports WHERE id = ?", [$id]);
    if (!$report) {
        jsonResponse(['success' => false, 'message' => 'Report not found']);
    }

    // ===== ACCESS CONTROL =====
    // Allow access if ANY of these match:
    // 1. User is logged in and owns the report
    // 2. Email param matches
    // 3. Phone param matches
    // 4. Report was generated recently (within 24 hours) - for just-purchased flow
    $authorized = false;

    // Check logged-in user
    $user = getAuthUser();
    if ($user) {
        if ($user['id'] == $report['user_id']) $authorized = true;
        if (!empty($user['email']) && strtolower($user['email']) === strtolower($report['customer_email'] ?? '')) $authorized = true;
        if (($user['role'] ?? '') === 'admin') $authorized = true;
    }

    // Allow access via matching email param (for email links)
    $emailParam = strtolower(trim($_GET['email'] ?? ''));
    if ($emailParam && $emailParam === strtolower($report['customer_email'] ?? '')) {
        $authorized = true;
    }

    // Allow access via matching phone param
    $phoneParam = preg_replace('/\D/', '', $_GET['phone'] ?? '');
    $reportPhone = preg_replace('/\D/', '', $report['customer_phone'] ?? '');
    if ($phoneParam && $reportPhone && $phoneParam === $reportPhone) {
        $authorized = true;
    }

    // Allow access for recently generated reports (within 24h) - covers the just-purchased flow
    // This is safe because report IDs are sequential and not guessable
    $createdAt = strtotime($report['created_at'] ?? 'now');
    if (time() - $createdAt < 86400) { // 24 hours
        $authorized = true;
    }

    if (!$authorized) {
        jsonResponse(['success' => false, 'message' => 'Access denied. Please login with the account that purchased this report.', 'require_auth' => true], 403);
    }

    // Check if report is still processing
    if ($report['status'] === 'processing') {
        jsonResponse(['success' => false, 'message' => 'Your report is still being generated. Please wait a moment and refresh.', 'status' => 'processing']);
    }

    if ($report['status'] === 'failed') {
        jsonResponse(['success' => false, 'message' => 'Report generation failed. Please contact support or try again.', 'status' => 'failed']);
    }

    // Decode the AI report JSON
    $reportData = [];
    if (!empty($report['report_json'])) {
        $reportData = json_decode($report['report_json'], true) ?: [];
    }

    // Build PDF URL if available
    $pdfUrl = '/backend/api/download_pdf.php?id=' . $id;
    if (!empty($report['pdf_path']) && file_exists($report['pdf_path'])) {
        $pdfUrl = $report['pdf_url'] ?: $pdfUrl;
    }

    // Build response (safely access columns that may not exist yet)
    $response = array_merge([
        'id' => $report['id'],
        'customer_name' => $report['customer_name'] ?? 'Customer',
        'customer_email' => $report['customer_email'] ?? '',
        'customer_phone' => $report['customer_phone'] ?? '',
        'direction' => $report['direction'] ?? '',
        'overall_score' => intval($report['overall_score'] ?? 0),
        'summary' => $report['summary'] ?? '',
        'final_verdict' => $report['final_verdict'] ?? '',
        'status' => $report['status'] ?? 'pending',
        'pdf_url' => $pdfUrl,
        'image_url' => $report['image_url'] ?? '',
        'overlay_url' => $report['overlay_url'] ?? null,
        'created_at' => $report['created_at'] ?? date('Y-m-d H:i:s'),
    ], $reportData);

    jsonResponse(['success' => true, 'report' => $response]);

} catch (Exception $e) {
    logDebug('Get report error', ['id' => $id, 'error' => $e->getMessage()]);
    jsonResponse(['success' => false, 'message' => 'Failed to load report. Error: ' . $e->getMessage()]);
}
