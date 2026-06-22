<?php
/**
 * Test the VastuImageAnalyzer in isolation (no DB / config needed).
 */

// --- Stubs so the analyzer can run standalone ---
if (!defined('REPORTS_PATH')) define('REPORTS_PATH', __DIR__ . '/output');
if (!function_exists('logDebug')) { function logDebug($m, $c = []) { echo "[debug] $m " . json_encode($c) . "\n"; } }

if (!is_dir(REPORTS_PATH)) mkdir(REPORTS_PATH, 0755, true);

require_once __DIR__ . '/../lib/VastuImageAnalyzer.php';

$chakra = __DIR__ . '/../uploads/chakra-overlay.png';
echo "Chakra exists: " . (file_exists($chakra) ? 'YES' : 'NO') . "\n\n";

$cases = [
    ['file' => 'fixtures/plan_square_south.png', 'facing' => 'S'],
    ['file' => 'fixtures/plan_wide_east.png',    'facing' => 'E'],
    ['file' => 'fixtures/plan_tall_north.png',   'facing' => 'N'],
    ['file' => 'fixtures/plan_offset.png',       'facing' => 'W'],
];

foreach ($cases as $c) {
    $path = __DIR__ . '/' . $c['file'];
    echo "===== " . basename($path) . " (facing {$c['facing']}) =====\n";

    $geo = VastuImageAnalyzer::analyze($path, $c['facing']);
    if (!$geo) { echo "  ANALYZE FAILED\n\n"; continue; }

    printf("  Image: %dx%d\n", $geo['image_width'], $geo['image_height']);
    printf("  BBox:  (%d,%d) -> (%d,%d)  [%dx%d]\n",
        $geo['bbox']['minX'], $geo['bbox']['minY'], $geo['bbox']['maxX'], $geo['bbox']['maxY'],
        $geo['bbox']['w'], $geo['bbox']['h']);
    printf("  Brahmasthan: (%d,%d)\n", $geo['brahmasthan']['x'], $geo['brahmasthan']['y']);
    printf("  Entry: (%d,%d) side=%s dir=%s\n",
        $geo['entry']['x'], $geo['entry']['y'], $geo['entry']['side'], $geo['entry']['direction']);

    // Verify Brahmasthan is roughly at the centre of the bbox
    $cx = $geo['bbox']['minX'] + $geo['bbox']['w'] / 2;
    $cy = $geo['bbox']['minY'] + $geo['bbox']['h'] / 2;
    $okCenter = (abs($geo['brahmasthan']['x'] - $cx) < 3 && abs($geo['brahmasthan']['y'] - $cy) < 3);
    echo "  Brahmasthan centred correctly: " . ($okCenter ? 'PASS' : 'FAIL') . "\n";

    // Zone mapping sanity: test a point in each grid cell
    $samples = [
        'top-left'     => [$geo['bbox']['minX'] + 5, $geo['bbox']['minY'] + 5],
        'top-mid'      => [$geo['brahmasthan']['x'], $geo['bbox']['minY'] + 5],
        'centre'       => [$geo['brahmasthan']['x'], $geo['brahmasthan']['y']],
        'bottom-right' => [$geo['bbox']['maxX'] - 5, $geo['bbox']['maxY'] - 5],
    ];
    foreach ($samples as $name => $pt) {
        $zone = VastuImageAnalyzer::pixelToZone($pt[0], $pt[1], $geo);
        printf("    zone[%-12s] = %s\n", $name, $zone);
    }

    // Render overlay
    $out = REPORTS_PATH . '/overlay_' . basename($path);
    $res = VastuImageAnalyzer::renderOverlay($path, $c['facing'], $geo, $chakra, $out);
    echo "  Overlay: " . ($res && file_exists($res) ? "OK -> " . basename($res) . " (" . filesize($res) . " bytes)" : "FAILED") . "\n\n";
}

echo "Done.\n";
