<?php
/**
 * Download PDF Report
 *
 * Serves the PDF (or HTML if PDF generation unavailable).
 * If file doesn't exist, regenerate on-the-fly.
 */

require_once __DIR__ . '/../config/config.php';
require_once BACKEND_PATH . '/lib/PDFReport.php';

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    http_response_code(400);
    die('Report ID required');
}

try {
    $report = Database::row("SELECT * FROM reports WHERE id = ?", [$id]);
    if (!$report) {
        http_response_code(404);
        die('Report not found');
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
        die('Failed to generate report file');
    }

    $filename = basename($path);
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

    if ($ext === 'pdf') {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="VastuKundali_Report_' . $id . '.pdf"');
    } else {
        // HTML version - serve inline so browser can print to PDF
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: inline; filename="VastuKundali_Report_' . $id . '.html"');
    }

    header('Content-Length: ' . filesize($path));
    header('Cache-Control: private, max-age=300');

    readfile($path);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    die('Error generating report');
}
