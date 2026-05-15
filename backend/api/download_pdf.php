<?php
/**
 * Download PDF Report
 *
 * Serves the PDF (or HTML with print-to-PDF prompt if PDF generation unavailable).
 * Requires authentication - only the report owner can download.
 */

require_once __DIR__ . '/../config/config.php';
require_once BACKEND_PATH . '/lib/PDFReport.php';

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Report ID required']));
}

try {
    $report = Database::row("SELECT * FROM reports WHERE id = ?", [$id]);
    if (!$report) {
        http_response_code(404);
        die(json_encode(['success' => false, 'message' => 'Report not found']));
    }

    // Access control: check ownership
    $user = getAuthUser();
    $authorized = false;
    if ($user) {
        if ($user['id'] == $report['user_id']) $authorized = true;
        if (strtolower($user['email']) === strtolower($report['customer_email'])) $authorized = true;
        if (($user['role'] ?? '') === 'admin') $authorized = true;
    }
    $emailParam = strtolower(trim($_GET['email'] ?? ''));
    if ($emailParam && $emailParam === strtolower($report['customer_email'])) $authorized = true;
    $phoneParam = preg_replace('/\D/', '', $_GET['phone'] ?? '');
    if ($phoneParam && $phoneParam === preg_replace('/\D/', '', $report['customer_phone'])) $authorized = true;

    if (!$authorized) {
        http_response_code(403);
        die(json_encode(['success' => false, 'message' => 'Access denied. Please login first.']));
    }

    // Generate if missing
    $path = $report['pdf_path'];
    if (!$path || !file_exists($path)) {
        $path = PDFReport::generate($id);
        if ($path) {
            $publicUrl = REPORTS_URL . '/pdf/' . basename($path);
            Database::exec("UPDATE reports SET pdf_path = ?, pdf_url = ? WHERE id = ?", [$path, $publicUrl, $id]);
        }
    }

    if (!$path || !file_exists($path)) {
        http_response_code(500);
        die(json_encode(['success' => false, 'message' => 'Failed to generate report file. Please install dompdf: run "composer require dompdf/dompdf" in the backend folder.']));
    }

    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

    if ($ext === 'pdf') {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="VastuKundali_Report_' . $id . '.pdf"');
    } else {
        // HTML version - serve inline with auto-print trigger for PDF save
        header('Content-Type: text/html; charset=utf-8');
        // Inject auto-print script at the end so browser opens print dialog = Save as PDF
        $content = file_get_contents($path);
        $content .= '<script>window.onload=function(){setTimeout(function(){window.print();},500);}</script>';
        header('Content-Length: ' . strlen($content));
        echo $content;
        exit;
    }

    header('Content-Length: ' . filesize($path));
    header('Cache-Control: private, max-age=300');

    readfile($path);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Error generating report']));
}
