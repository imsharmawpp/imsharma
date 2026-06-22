<?php
/**
 * Integration test for the floor-plan validator's image analysis +
 * the PlanClassifier fallback. Runs standalone (no DB needed).
 */

if (!defined('MAX_UPLOAD_SIZE')) define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024);
if (!defined('BACKEND_PATH')) define('BACKEND_PATH', __DIR__ . '/..');
if (!function_exists('logDebug')) { function logDebug($m, $c = []) {} }
if (!function_exists('getSetting')) { function getSetting($k, $d = '') { return $d; } }

// Extract analyzeFloorPlan() from validate_plan.php without running its top code.
$src = file_get_contents(__DIR__ . '/../api/validate_plan.php');
$start = strpos($src, 'function analyzeFloorPlan');
$fnCode = substr($src, $start);
eval($fnCode); // defines analyzeFloorPlan + helpers in this scope

function check($file, $expectValid) {
    $mime = mime_content_type($file);
    $info = getimagesize($file);
    $res = analyzeFloorPlan($file, $mime, $info[0], $info[1]);
    $ok = ($res['isValid'] === $expectValid);
    printf("%-28s expect=%-5s got=%-5s %s\n",
        basename($file),
        $expectValid ? 'VALID' : 'REJECT',
        $res['isValid'] ? 'VALID' : 'REJECT',
        $ok ? 'PASS' : 'FAIL ***');
    if (!$res['isValid']) {
        echo "      reason: [" . $res['errorCode'] . "] " . substr($res['errorMessage'], 0, 70) . "...\n";
    }
    if (isset($res['_debug'])) {
        echo "      debug: " . json_encode($res['_debug']) . "\n";
    }
    return $ok;
}

echo "===== FLOOR PLAN VALIDATION TESTS =====\n";
$fx = __DIR__ . '/fixtures/';
$allPass = true;
$allPass &= check($fx . 'plan_square_south.png', true);
$allPass &= check($fx . 'plan_wide_east.png', true);
$allPass &= check($fx . 'plan_tall_north.png', true);
$allPass &= check($fx . 'plan_offset.png', true);
$allPass &= check($fx . 'photo_honeybox.png', false);  // photo must be REJECTED

echo "\n===== PLAN CLASSIFIER FALLBACK TEST (no AI) =====\n";
require_once __DIR__ . '/../lib/PlanClassifier.php';
$c = PlanClassifier::classify($fx . 'plan_square_south.png', 'residential', 'villa');
printf("No-AI classify: verdict=%s match=%s ai_used=%s -> %s\n",
    $c['verdict'], $c['match'] ? 'true' : 'false', $c['ai_used'] ? 'true' : 'false',
    ($c['verdict'] === 'needs_manual_review' && $c['match'] === true && $c['ai_used'] === false) ? 'PASS' : 'FAIL ***');

echo "\n" . ($allPass ? "ALL VALIDATION TESTS PASSED" : "SOME TESTS FAILED") . "\n";
