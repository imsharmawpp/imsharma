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

    // Decode the AI report JSON
    $reportData = [];
    if (!empty($report['report_json'])) {
        $reportData = json_decode($report['report_json'], true) ?: [];
    }

    // Build PDF URL if available
    $pdfUrl = null;
    if (!empty($report['pdf_path']) && file_exists($report['pdf_path'])) {
        $pdfUrl = $report['pdf_url'] ?: ('/backend/api/download_pdf.php?id=' . $id);
    } else {
        $pdfUrl = '/backend/api/download_pdf.php?id=' . $id;
    }

    $response = array_merge([
        'id' => $report['id'],
        'customer_name' => $report['customer_name'],
        'customer_email' => $report['customer_email'],
        'direction' => $report['direction'],
        'overall_score' => intval($report['overall_score']),
        'summary' => $report['summary'],
        'final_verdict' => $report['final_verdict'],
        'status' => $report['status'],
        'pdf_url' => $pdfUrl,
        'image_url' => $report['image_url'],
        'created_at' => $report['created_at'],
    ], $reportData);

    jsonResponse(['success' => true, 'report' => $response]);

} catch (Exception $e) {
    logDebug('Get report error', ['error' => $e->getMessage()]);
    jsonResponse(['success' => false, 'message' => 'Failed to load report']);
}
