<?php
/**
 * PDF Report Generator
 *
 * Generates a beautiful HTML-based report file that:
 *   1. Can be served as a downloadable HTML file (print-to-PDF in browser works perfectly)
 *   2. Optionally renders to actual PDF via TCPDF/dompdf if available
 *
 * For shared hosting without composer, the HTML approach works flawlessly:
 *   - Browsers can print to PDF (Ctrl+P -> Save as PDF)
 *   - The HTML is styled with print CSS for clean A4 layout
 *
 * If you have TCPDF or dompdf available (e.g., installed via composer), it will be auto-used.
 */

class PDFReport {

    /**
     * Generate report file. Returns full path to generated file (HTML or PDF).
     */
    public static function generate($reportId) {
        $report = Database::row("SELECT * FROM reports WHERE id = ?", [$reportId]);
        if (!$report) return null;

        $analysis = json_decode($report['report_json'], true) ?: [];

        $html = self::buildHTML($report, $analysis);

        // Save HTML version (always)
        $filename = 'vastu_report_' . $reportId . '_' . date('Ymd') . '.html';
        $filepath = REPORTS_PATH . '/pdf/' . $filename;

        if (!is_dir(dirname($filepath))) @mkdir(dirname($filepath), 0755, true);
        file_put_contents($filepath, $html);

        // Try to convert to actual PDF if a library is available
        $pdfPath = self::tryGeneratePDF($html, $reportId);
        return $pdfPath ?: $filepath;
    }

    /**
     * Try to use TCPDF / dompdf / wkhtmltopdf if available.
     */
    private static function tryGeneratePDF($html, $reportId) {
        $pdfFilename = 'vastu_report_' . $reportId . '_' . date('Ymd') . '.pdf';
        $pdfPath = REPORTS_PATH . '/pdf/' . $pdfFilename;

        // Try dompdf if available
        if (class_exists('Dompdf\\Dompdf')) {
            try {
                $dompdf = new \Dompdf\Dompdf(['isHtml5ParserEnabled' => true, 'isRemoteEnabled' => true]);
                $dompdf->loadHtml($html);
                $dompdf->setPaper('A4');
                $dompdf->render();
                file_put_contents($pdfPath, $dompdf->output());
                return $pdfPath;
            } catch (Exception $e) {
                logDebug('Dompdf error', ['error' => $e->getMessage()]);
            }
        }

        // Try TCPDF if available
        if (class_exists('TCPDF')) {
            try {
                $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
                $pdf->SetCreator('VastuKundali AI');
                $pdf->SetTitle('Vastu Kundali Report #' . $reportId);
                $pdf->SetMargins(15, 15, 15);
                $pdf->AddPage();
                $pdf->writeHTML($html, true, false, true, false, '');
                $pdf->Output($pdfPath, 'F');
                return $pdfPath;
            } catch (Exception $e) {
                logDebug('TCPDF error', ['error' => $e->getMessage()]);
            }
        }

        // Try wkhtmltopdf binary if available on server
        if (function_exists('exec')) {
            $bin = trim(shell_exec('which wkhtmltopdf 2>/dev/null'));
            if ($bin && is_executable($bin)) {
                $tmpHtml = tempnam(sys_get_temp_dir(), 'vk_') . '.html';
                file_put_contents($tmpHtml, $html);
                @exec(escapeshellcmd($bin) . " --quiet --enable-local-file-access " . escapeshellarg($tmpHtml) . " " . escapeshellarg($pdfPath) . " 2>&1", $output, $code);
                @unlink($tmpHtml);
                if ($code === 0 && file_exists($pdfPath)) return $pdfPath;
            }
        }

        return null; // HTML version only
    }

    /**
     * Build the styled HTML report.
     */
    private static function buildHTML($report, $analysis) {
        $score = intval($analysis['overall_score'] ?? $report['overall_score'] ?? 0);
        $name = htmlspecialchars($report['customer_name'] ?? 'Customer');
        $email = htmlspecialchars($report['customer_email'] ?? '');
        $direction = htmlspecialchars(formatDirection($report['direction'] ?? ''));
        $date = date('F j, Y', strtotime($report['created_at'] ?? 'now'));
        $reportId = $report['id'];
        $summary = htmlspecialchars($analysis['summary'] ?? $report['summary'] ?? '');
        $verdict = htmlspecialchars($analysis['final_verdict'] ?? $report['final_verdict'] ?? '');

        $rooms = $analysis['rooms'] ?? [];
        $positives = $analysis['positives'] ?? [];
        $negatives = $analysis['negatives'] ?? [];
        $remedies = $analysis['remedies'] ?? [];
        $impacts = $analysis['impacts'] ?? [];
        $heatmap = $analysis['heatmap'] ?? [];
        $products = $analysis['recommended_products'] ?? [];

        $rating = $score >= 85 ? 'Excellent' : ($score >= 70 ? 'Very Good' : ($score >= 55 ? 'Good' : ($score >= 40 ? 'Average' : 'Needs Attention')));

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Vastu Kundali Report #' . $reportId . '</title>';
        $html .= '<style>' . self::getStyles() . '</style></head><body>';

        // ===== Cover Page =====
        $html .= '<div class="page cover-page">';
        $html .= '<div class="cover-bg"></div>';
        $html .= '<div class="cover-content">';
        $html .= '<div class="brand">🏛️ VastuKundali AI</div>';
        $html .= '<div class="cover-subtitle">AI-Powered Vastu Analysis</div>';
        $html .= '<h1>Your Vastu Home Kundali</h1>';
        $html .= '<div class="cover-name">For: ' . $name . '</div>';
        $html .= '<div class="cover-score-box">';
        $html .= '<div class="cover-score-label">OVERALL VASTU SCORE</div>';
        $html .= '<div class="cover-score">' . $score . '<span>/100</span></div>';
        $html .= '<div class="cover-rating">' . $rating . '</div>';
        $html .= '</div>';
        $html .= '<div class="cover-meta">';
        $html .= '<div><strong>House Facing:</strong> ' . $direction . '</div>';
        $html .= '<div><strong>Generated On:</strong> ' . $date . '</div>';
        $html .= '<div><strong>Report ID:</strong> #' . $reportId . '</div>';
        $html .= '</div>';
        $html .= '</div></div>';

        // ===== Executive Summary =====
        $html .= '<div class="page">';
        $html .= '<h2 class="section-h2">Executive Summary</h2>';
        $html .= '<p class="lead">' . $summary . '</p>';

        // Quick stats
        $html .= '<div class="stats-row">';
        $html .= '<div class="stat-box"><div class="stat-num">' . count($positives) . '</div><div class="stat-label">Positive Aspects</div></div>';
        $html .= '<div class="stat-box"><div class="stat-num">' . count($negatives) . '</div><div class="stat-label">Areas of Concern</div></div>';
        $html .= '<div class="stat-box"><div class="stat-num">' . count($remedies) . '</div><div class="stat-label">Remedies</div></div>';
        $html .= '<div class="stat-box"><div class="stat-num">' . count($rooms) . '</div><div class="stat-label">Rooms Analyzed</div></div>';
        $html .= '</div>';

        // Life Impact
        if (!empty($impacts)) {
            $html .= '<h3 class="section-h3">Life Impact Analysis</h3>';
            $html .= '<div class="impact-grid">';
            foreach (['health' => 'Health', 'wealth' => 'Wealth', 'relations' => 'Relations', 'career' => 'Career'] as $key => $label) {
                $imp = $impacts[$key] ?? ['score' => $score, 'note' => '-'];
                $iconMap = ['health' => '❤️', 'wealth' => '💰', 'relations' => '👨‍👩‍👧', 'career' => '💼'];
                $html .= '<div class="impact-box">';
                $html .= '<div class="impact-icon">' . $iconMap[$key] . '</div>';
                $html .= '<div class="impact-label">' . $label . '</div>';
                $html .= '<div class="impact-score">' . intval($imp['score']) . '/100</div>';
                $html .= '<div class="impact-note">' . htmlspecialchars($imp['note']) . '</div>';
                $html .= '</div>';
            }
            $html .= '</div>';
        }
        $html .= '</div>';

        // ===== Findings =====
        $html .= '<div class="page">';
        $html .= '<h2 class="section-h2">Key Findings</h2>';

        if (!empty($positives)) {
            $html .= '<h3 class="section-h3 success-color">✓ Positive Aspects</h3><ul class="finding-list positive-list">';
            foreach ($positives as $p) {
                $text = is_string($p) ? $p : ($p['text'] ?? '');
                $html .= '<li>' . htmlspecialchars($text) . '</li>';
            }
            $html .= '</ul>';
        }

        if (!empty($negatives)) {
            $html .= '<h3 class="section-h3 danger-color">⚠ Areas of Concern</h3><ul class="finding-list negative-list">';
            foreach ($negatives as $n) {
                $text = is_string($n) ? $n : ($n['text'] ?? '');
                $html .= '<li>' . htmlspecialchars($text) . '</li>';
            }
            $html .= '</ul>';
        }

        $html .= '</div>';

        // ===== Heatmap =====
        if (!empty($heatmap)) {
            $html .= '<div class="page">';
            $html .= '<h2 class="section-h2">Energy Heatmap (16 Zones)</h2>';
            $html .= '<p class="muted">Visual representation of energy levels across the 16 directional zones of your home.</p>';
            $html .= '<table class="heatmap-table"><tr>';
            foreach ($heatmap as $i => $cell) {
                $level = $cell['level'] ?? 'average';
                $html .= '<td class="heatmap-cell ' . $level . '"><strong>' . $cell['name'] . '</strong><br>' . $cell['score'] . '</td>';
                if (($i + 1) % 4 === 0 && $i < count($heatmap) - 1) $html .= '</tr><tr>';
            }
            $html .= '</tr></table>';
            $html .= '<div class="heatmap-legend">';
            $html .= '<span class="legend-swatch excellent"></span> Excellent (80+) &nbsp; ';
            $html .= '<span class="legend-swatch good"></span> Good (65-79) &nbsp; ';
            $html .= '<span class="legend-swatch average"></span> Average (45-64) &nbsp; ';
            $html .= '<span class="legend-swatch poor"></span> Poor (30-44) &nbsp; ';
            $html .= '<span class="legend-swatch bad"></span> Bad (<30)';
            $html .= '</div>';
            $html .= '</div>';
        }

        // ===== Room Analysis =====
        if (!empty($rooms)) {
            $html .= '<div class="page">';
            $html .= '<h2 class="section-h2">Room-by-Room Analysis</h2>';
            $html .= '<table class="rooms-table">';
            $html .= '<thead><tr><th>Room</th><th>Direction</th><th>Score</th><th>Analysis</th></tr></thead><tbody>';
            foreach ($rooms as $r) {
                $cls = ($r['score'] ?? 0) >= 75 ? 'high' : (($r['score'] ?? 0) >= 50 ? 'med' : 'low');
                $html .= '<tr>';
                $html .= '<td><strong>' . htmlspecialchars($r['name'] ?? '') . '</strong></td>';
                $html .= '<td>' . htmlspecialchars(formatDirection($r['direction'] ?? '')) . '</td>';
                $html .= '<td><span class="score-pill ' . $cls . '">' . ($r['score'] ?? '-') . '/100</span></td>';
                $html .= '<td>' . htmlspecialchars($r['analysis'] ?? '');
                if (!empty($r['remedy'])) {
                    $html .= '<br><em class="remedy-text">Remedy: ' . htmlspecialchars($r['remedy']) . '</em>';
                }
                $html .= '</td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
            $html .= '</div>';
        }

        // ===== Remedies =====
        if (!empty($remedies)) {
            $html .= '<div class="page">';
            $html .= '<h2 class="section-h2">Recommended Remedies</h2>';
            $html .= '<p class="muted">Personalized remedies prioritized by impact level. Implement high-priority items first.</p>';
            foreach ($remedies as $i => $r) {
                $priority = $r['priority'] ?? 'medium';
                $html .= '<div class="remedy-row priority-' . $priority . '">';
                $html .= '<div class="remedy-num">' . ($i + 1) . '</div>';
                $html .= '<div class="remedy-body">';
                $html .= '<div class="remedy-head"><strong>' . htmlspecialchars($r['title'] ?? 'Remedy') . '</strong>';
                $html .= ' <span class="priority-tag ' . $priority . '">' . strtoupper($priority) . ' PRIORITY</span></div>';
                $html .= '<div class="remedy-desc">' . htmlspecialchars($r['description'] ?? '') . '</div>';
                $html .= '</div></div>';
            }
            $html .= '</div>';
        }

        // ===== Recommended Products =====
        if (!empty($products)) {
            $html .= '<div class="page">';
            $html .= '<h2 class="section-h2">Recommended Vastu Products</h2>';
            $html .= '<p class="muted">Authentic remedies curated specifically for your home\'s defects.</p>';
            $html .= '<table class="products-table"><thead><tr><th>Product</th><th>Description</th><th>Price</th></tr></thead><tbody>';
            foreach ($products as $p) {
                $html .= '<tr>';
                $html .= '<td><strong>' . htmlspecialchars($p['name'] ?? '') . '</strong></td>';
                $html .= '<td>' . htmlspecialchars($p['description'] ?? '') . '</td>';
                $html .= '<td><strong>₹' . ($p['price'] ?? '-') . '</strong>';
                if (!empty($p['original_price'])) $html .= ' <s>₹' . $p['original_price'] . '</s>';
                $html .= '</td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
            $html .= '<p class="store-cta">Visit our store: <strong>' . SITE_URL . '/frontend/pages/store.html</strong></p>';
            $html .= '</div>';
        }

        // ===== Final Verdict =====
        $html .= '<div class="page">';
        $html .= '<h2 class="section-h2">Final Verdict</h2>';
        $html .= '<div class="verdict-box">';
        $html .= '<p>' . $verdict . '</p>';
        $html .= '</div>';

        // Footer
        $html .= '<div class="report-footer">';
        $html .= '<p><strong>Disclaimer:</strong> This Vastu analysis is based on traditional Vastu Shastra principles and AI interpretation. ';
        $html .= 'Results may vary based on individual circumstances. For deeper analysis, consider booking a personalized expert consultation.</p>';
        $html .= '<p class="signature">Generated with ❤️ by VastuKundali AI<br>';
        $html .= 'Report ID: #' . $reportId . ' &middot; ' . $date . '<br>';
        $html .= 'support@vastukundali.com &middot; +91 9876543210</p>';
        $html .= '</div>';
        $html .= '</div>';

        $html .= '</body></html>';
        return $html;
    }

    /**
     * Get all CSS styles for the PDF.
     */
    private static function getStyles() {
        return <<<CSS
@page { size: A4; margin: 0; }
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: 'Helvetica Neue', Arial, sans-serif;
    color: #0A0E27;
    background: white;
    line-height: 1.6;
    font-size: 12pt;
}
.page {
    width: 210mm;
    min-height: 297mm;
    padding: 25mm 20mm;
    margin: 0 auto 10mm;
    background: white;
    page-break-after: always;
    position: relative;
}
.page:last-child { page-break-after: auto; }
@media print {
    body { background: white; }
    .page { margin: 0; box-shadow: none; }
}

/* ===== Cover Page ===== */
.cover-page {
    background: linear-gradient(135deg, #0A0E27 0%, #131938 100%);
    color: white;
    text-align: center;
    padding: 40mm 20mm;
    overflow: hidden;
}
.cover-page::before {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at 50% 30%, rgba(212, 175, 55, 0.25), transparent 60%);
}
.cover-content { position: relative; z-index: 2; }
.brand {
    font-size: 18pt;
    color: #D4AF37;
    margin-bottom: 8px;
    font-weight: bold;
}
.cover-subtitle {
    font-size: 11pt;
    letter-spacing: 4px;
    text-transform: uppercase;
    color: #D4AF37;
    margin-bottom: 30mm;
}
.cover-page h1 {
    font-size: 32pt;
    color: white;
    font-weight: bold;
    margin-bottom: 15mm;
    line-height: 1.1;
}
.cover-name {
    font-size: 14pt;
    color: rgba(255,255,255,0.9);
    margin-bottom: 15mm;
    font-style: italic;
}
.cover-score-box {
    background: rgba(255,255,255,0.08);
    border: 2px solid #D4AF37;
    border-radius: 12px;
    padding: 8mm;
    margin: 0 auto 15mm;
    max-width: 80mm;
}
.cover-score-label {
    font-size: 9pt;
    color: #D4AF37;
    letter-spacing: 2px;
    margin-bottom: 4px;
}
.cover-score {
    font-size: 56pt;
    color: #D4AF37;
    font-weight: 900;
    line-height: 1;
}
.cover-score span { font-size: 20pt; opacity: 0.7; }
.cover-rating {
    color: white;
    font-size: 12pt;
    margin-top: 4px;
    font-weight: 600;
}
.cover-meta {
    background: rgba(255,255,255,0.05);
    border-radius: 8px;
    padding: 8mm;
    font-size: 11pt;
    color: rgba(255,255,255,0.85);
}
.cover-meta div { margin: 4px 0; }

/* ===== Sections ===== */
.section-h2 {
    color: #0A0E27;
    font-size: 22pt;
    margin-bottom: 8mm;
    padding-bottom: 4mm;
    border-bottom: 3px solid #D4AF37;
    page-break-after: avoid;
}
.section-h3 {
    color: #0A0E27;
    font-size: 14pt;
    margin: 8mm 0 4mm;
    page-break-after: avoid;
}
.success-color { color: #10B981; }
.danger-color { color: #EF4444; }
.lead { font-size: 12pt; line-height: 1.7; margin-bottom: 8mm; color: #1F2937; }
.muted { color: #4B5563; font-size: 10pt; margin-bottom: 6mm; }

/* ===== Stats Row ===== */
.stats-row {
    display: table;
    width: 100%;
    margin: 8mm 0;
    border-collapse: separate;
    border-spacing: 4mm 0;
}
.stat-box {
    display: table-cell;
    width: 25%;
    background: linear-gradient(180deg, #FFF9E6 0%, #FAF8F1 100%);
    border-radius: 8px;
    padding: 5mm;
    text-align: center;
    border: 1px solid rgba(212, 175, 55, 0.3);
}
.stat-num { font-size: 26pt; font-weight: bold; color: #B8941F; }
.stat-label { font-size: 9pt; color: #4B5563; text-transform: uppercase; letter-spacing: 1px; }

/* ===== Impact Grid ===== */
.impact-grid {
    display: table;
    width: 100%;
    border-spacing: 3mm 0;
}
.impact-box {
    display: table-cell;
    background: #FFF9E6;
    border: 1px solid rgba(212, 175, 55, 0.3);
    border-radius: 8px;
    padding: 5mm;
    text-align: center;
    width: 25%;
}
.impact-icon { font-size: 20pt; }
.impact-label { font-size: 11pt; font-weight: bold; margin: 2mm 0; }
.impact-score { font-size: 18pt; color: #B8941F; font-weight: bold; }
.impact-note { font-size: 9pt; color: #4B5563; }

/* ===== Findings ===== */
.finding-list { padding-left: 0; list-style: none; margin-bottom: 8mm; }
.finding-list li {
    padding: 3mm 4mm;
    margin-bottom: 2mm;
    border-radius: 6px;
    font-size: 11pt;
    line-height: 1.5;
}
.positive-list li { background: rgba(16, 185, 129, 0.08); border-left: 4px solid #10B981; }
.negative-list li { background: rgba(239, 68, 68, 0.08); border-left: 4px solid #EF4444; }

/* ===== Heatmap ===== */
.heatmap-table {
    border-collapse: separate;
    border-spacing: 2mm;
    margin: 6mm auto;
    width: 100%;
}
.heatmap-cell {
    width: 25%;
    height: 28mm;
    text-align: center;
    vertical-align: middle;
    border-radius: 6px;
    color: white;
    font-size: 10pt;
    padding: 2mm;
}
.heatmap-cell.excellent { background: #10B981; }
.heatmap-cell.good { background: #84CC16; }
.heatmap-cell.average { background: #FBBF24; color: #0A0E27; }
.heatmap-cell.poor { background: #F97316; }
.heatmap-cell.bad { background: #EF4444; }
.heatmap-cell strong { display: block; font-size: 11pt; margin-bottom: 2mm; }
.heatmap-legend {
    margin-top: 6mm;
    font-size: 9pt;
    text-align: center;
    color: #4B5563;
}
.legend-swatch {
    display: inline-block;
    width: 12px;
    height: 12px;
    border-radius: 3px;
    vertical-align: middle;
    margin-right: 2px;
}
.legend-swatch.excellent { background: #10B981; }
.legend-swatch.good { background: #84CC16; }
.legend-swatch.average { background: #FBBF24; }
.legend-swatch.poor { background: #F97316; }
.legend-swatch.bad { background: #EF4444; }

/* ===== Tables ===== */
.rooms-table, .products-table {
    width: 100%;
    border-collapse: collapse;
    margin: 6mm 0;
    font-size: 10pt;
}
.rooms-table th, .products-table th {
    background: #0A0E27;
    color: #D4AF37;
    padding: 4mm 3mm;
    text-align: left;
    font-size: 10pt;
    text-transform: uppercase;
    letter-spacing: 1px;
}
.rooms-table td, .products-table td {
    padding: 4mm 3mm;
    border-bottom: 1px solid #E5E5E5;
    vertical-align: top;
}
.rooms-table tr:nth-child(even), .products-table tr:nth-child(even) {
    background: #FAF8F1;
}
.score-pill {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 100px;
    font-size: 9pt;
    font-weight: bold;
}
.score-pill.high { background: rgba(16, 185, 129, 0.15); color: #10B981; }
.score-pill.med { background: rgba(245, 158, 11, 0.15); color: #F59E0B; }
.score-pill.low { background: rgba(239, 68, 68, 0.15); color: #EF4444; }
.remedy-text { color: #B8941F; font-size: 9pt; }

/* ===== Remedies ===== */
.remedy-row {
    display: table;
    width: 100%;
    margin-bottom: 5mm;
    background: #FFF9E6;
    border-radius: 8px;
    padding: 5mm;
    border-left: 4px solid #D4AF37;
}
.remedy-row.priority-high { border-left-color: #EF4444; background: rgba(239, 68, 68, 0.05); }
.remedy-row.priority-medium { border-left-color: #F59E0B; background: rgba(245, 158, 11, 0.05); }
.remedy-row.priority-low { border-left-color: #3B82F6; background: rgba(59, 130, 246, 0.05); }

.remedy-num {
    display: table-cell;
    width: 12mm;
    font-size: 22pt;
    color: #D4AF37;
    font-weight: bold;
    vertical-align: middle;
}
.remedy-body {
    display: table-cell;
    vertical-align: top;
}
.remedy-head { margin-bottom: 2mm; font-size: 12pt; }
.priority-tag {
    display: inline-block;
    padding: 1px 8px;
    border-radius: 100px;
    font-size: 8pt;
    color: white;
    margin-left: 6px;
}
.priority-tag.high { background: #EF4444; }
.priority-tag.medium { background: #F59E0B; }
.priority-tag.low { background: #3B82F6; }
.remedy-desc { font-size: 10pt; color: #1F2937; line-height: 1.6; }

/* ===== Verdict ===== */
.verdict-box {
    background: linear-gradient(135deg, #FFF9E6 0%, #FAF8F1 100%);
    padding: 8mm;
    border-left: 6px solid #D4AF37;
    border-radius: 8px;
    margin-bottom: 10mm;
    font-size: 11pt;
    font-style: italic;
    line-height: 1.8;
    color: #1F2937;
}

.store-cta {
    text-align: center;
    margin-top: 8mm;
    padding: 4mm;
    background: #FFF9E6;
    border-radius: 6px;
    font-size: 10pt;
    color: #B8941F;
}

.report-footer {
    margin-top: 15mm;
    padding-top: 8mm;
    border-top: 1px solid #E5E5E5;
    text-align: center;
    font-size: 9pt;
    color: #4B5563;
}
.signature {
    margin-top: 4mm;
    color: #B8941F;
    font-weight: 600;
}
CSS;
    }
}
