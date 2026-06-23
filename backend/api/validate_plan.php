<?php
/**
 * Floor Plan Validation API - STRICT
 * 
 * If this returns isValid=false, NO report will be generated.
 * 
 * KEY PRINCIPLE:
 * Floor plans are characterized by:
 *   - Predominantly white/light background (>50% of pixels are very bright)
 *   - Dark lines on light background (walls, structure)
 *   - Very low color saturation (mostly grayscale/neutral)
 *   - Limited color palette (blacks, whites, grays, maybe some blue/red annotations)
 * 
 * Photos are characterized by:
 *   - Rich colors with high saturation
 *   - Smooth gradients and textures
 *   - No dominant white background
 *   - Wide color variety with natural hues
 * 
 * This validator REJECTS anything that looks like a photograph.
 */

require_once __DIR__ . '/../config/config.php';
handleCors();
requirePost();

if (empty($_FILES['plan'])) {
    jsonResponse([
        'success' => false,
        'isValid' => false,
        'shouldGenerateReport' => false,
        'errorMessage' => 'Floor Plan not uploaded. Please upload a valid floor plan image to generate your report.',
        'errorCode' => 'NOT_UPLOADED'
    ]);
}

$file = $_FILES['plan'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    jsonResponse([
        'success' => false,
        'isValid' => false,
        'shouldGenerateReport' => false,
        'errorMessage' => 'Upload failed. Please try again.',
        'errorCode' => 'UPLOAD_ERROR'
    ]);
}

// Check file type
$mime = mime_content_type($file['tmp_name']) ?: $file['type'];
$allowedMimes = ['image/jpeg', 'image/png', 'image/jpg'];

if (!in_array($mime, $allowedMimes)) {
    jsonResponse([
        'success' => false,
        'isValid' => false,
        'shouldGenerateReport' => false,
        'errorMessage' => 'The uploaded file is not a valid image. Please upload a JPG or PNG floor plan.',
        'errorCode' => 'INVALID_FORMAT'
    ]);
}

// Check file size (too small = likely empty/corrupt). Resolution + content
// analysis below are the real quality gates, so keep this floor low to avoid
// rejecting legitimately well-compressed floor plans.
if ($file['size'] < 3000) {
    jsonResponse([
        'success' => false,
        'isValid' => false,
        'shouldGenerateReport' => false,
        'errorMessage' => 'The uploaded image is not clear enough to be analysed. Please upload a higher resolution floor plan.',
        'errorCode' => 'NOT_CLEAR'
    ]);
}

if ($file['size'] > MAX_UPLOAD_SIZE) {
    jsonResponse([
        'success' => false,
        'isValid' => false,
        'shouldGenerateReport' => false,
        'errorMessage' => 'File size exceeds 10MB limit. Please compress or resize your image.',
        'errorCode' => 'TOO_LARGE'
    ]);
}

// Get image info
$imageInfo = @getimagesize($file['tmp_name']);
if (!$imageInfo) {
    jsonResponse([
        'success' => false,
        'isValid' => false,
        'shouldGenerateReport' => false,
        'errorMessage' => 'The uploaded image was not recognised as a Floor Plan. Please upload a valid architectural floor plan. If you need assistance, please connect with our support team.',
        'errorCode' => 'NOT_RECOGNIZED'
    ]);
}

$width = $imageInfo[0];
$height = $imageInfo[1];

// Minimum resolution check
if ($width < 300 || $height < 300) {
    jsonResponse([
        'success' => false,
        'isValid' => false,
        'shouldGenerateReport' => false,
        'errorMessage' => 'The uploaded image is not clear enough to be analysed. Minimum resolution required: 300x300 pixels. Please upload a higher quality floor plan.',
        'errorCode' => 'NOT_CLEAR'
    ]);
}

// Run strict floor plan analysis
$result = analyzeFloorPlan($file['tmp_name'], $mime, $width, $height);

if (!$result['isValid']) {
    jsonResponse([
        'success' => false,
        'isValid' => false,
        'shouldGenerateReport' => false,
        'errorMessage' => $result['errorMessage'],
        'errorCode' => $result['errorCode'],
        '_debug' => $result['_debug'] ?? null  // Remove in production
    ]);
}

// ===== CATEGORY SCREENING =====
// Verify the plan matches the property category the user selected.
// (e.g. reject a house plan uploaded as a factory).
$category = strtolower(trim($_POST['property_category'] ?? ''));
$subType = strtolower(trim($_POST['property_subtype'] ?? ''));

if ($category && $subType) {
    require_once BACKEND_PATH . '/lib/ClaudeAI.php';
    require_once BACKEND_PATH . '/lib/PlanClassifier.php';

    try {
        $classification = PlanClassifier::classify($file['tmp_name'], $category, $subType);
        logDebug('Plan classification', $classification);

        if (!$classification['match']) {
            jsonResponse([
                'success' => false,
                'isValid' => false,
                'shouldGenerateReport' => false,
                'errorMessage' => $classification['message'] ?? 'The uploaded plan does not match the selected property category. Please upload the correct plan or change your selection.',
                'errorCode' => strtoupper($classification['verdict'] ?? 'CATEGORY_MISMATCH'),
                'detected' => [
                    'category' => $classification['detected_category'] ?? null,
                    'subtype' => $classification['detected_subtype'] ?? null,
                ],
            ]);
        }
    } catch (Exception $e) {
        // Don't block on classifier errors; structural checks already passed
        logDebug('Plan classification error', ['error' => $e->getMessage()]);
    }
}

// All checks passed
jsonResponse([
    'success' => true,
    'isValid' => true,
    'shouldGenerateReport' => true,
    'confidence' => $result['confidence'] ?? 0.75,
    'metadata' => [
        'width' => $width,
        'height' => $height,
        'size' => $file['size']
    ]
]);

/**
 * STRICT floor plan analysis.
 * 
 * A floor plan MUST have:
 * 1. High white/light pixel ratio (>40% of pixels with brightness > 200)
 * 2. Low color saturation (floor plans are mostly grayscale)
 * 3. Clear line structure (high-contrast edges representing walls)
 * 4. Limited unique color palette
 * 
 * A PHOTO will be rejected because:
 * - Photos have high saturation (colorful)
 * - Photos don't have dominant white backgrounds
 * - Photos have smooth gradients (not sharp wall-like edges)
 */
function analyzeFloorPlan($filepath, $mime, $width, $height) {
    // Load image
    switch ($mime) {
        case 'image/jpeg':
        case 'image/jpg':
            $img = @imagecreatefromjpeg($filepath);
            break;
        case 'image/png':
            $img = @imagecreatefrompng($filepath);
            break;
        default:
            return ['isValid' => false, 'errorMessage' => 'Unsupported image format.', 'errorCode' => 'INVALID_FORMAT'];
    }

    if (!$img) {
        return [
            'isValid' => false,
            'errorMessage' => 'The uploaded image could not be processed. Please upload a valid JPG or PNG floor plan.',
            'errorCode' => 'NOT_RECOGNIZED'
        ];
    }

    // Scale down to 250x250 max for analysis
    $sampleWidth = min($width, 250);
    $sampleHeight = min($height, 250);
    $sample = imagecreatetruecolor($sampleWidth, $sampleHeight);
    imagecopyresampled($sample, $img, 0, 0, 0, 0, $sampleWidth, $sampleHeight, $width, $height);
    imagedestroy($img);

    // ---- Crop to content bounding box (ignore white margins) ----
    // Real floor plans often have large white borders; analysing the whole
    // canvas skews ratios. We find the drawing content and analyse only that.
    $bMinX = $sampleWidth; $bMinY = $sampleHeight; $bMaxX = 0; $bMaxY = 0;
    for ($y = 0; $y < $sampleHeight; $y++) {
        for ($x = 0; $x < $sampleWidth; $x++) {
            $rgb = imagecolorat($sample, $x, $y);
            $r = ($rgb >> 16) & 0xFF; $g = ($rgb >> 8) & 0xFF; $b = $rgb & 0xFF;
            if (($r + $g + $b) / 3 < 220) {
                if ($x < $bMinX) $bMinX = $x;
                if ($y < $bMinY) $bMinY = $y;
                if ($x > $bMaxX) $bMaxX = $x;
                if ($y > $bMaxY) $bMaxY = $y;
            }
        }
    }
    // Fall back to full frame if no content found
    if ($bMaxX <= $bMinX || $bMaxY <= $bMinY) {
        $bMinX = 0; $bMinY = 0; $bMaxX = $sampleWidth - 1; $bMaxY = $sampleHeight - 1;
    }
    // Add small padding
    $bMinX = max(0, $bMinX - 2); $bMinY = max(0, $bMinY - 2);
    $bMaxX = min($sampleWidth - 1, $bMaxX + 2); $bMaxY = min($sampleHeight - 1, $bMaxY + 2);

    $totalPixels = 0;
    
    // Metrics to calculate
    $whitePixels = 0;        // Brightness > 200 (very light)
    $brightPixels = 0;       // Brightness > 150 (light)
    $darkPixels = 0;         // Brightness < 60 (dark lines/walls)
    $saturatedPixels = 0;    // High color saturation (colorful = photo)
    $grayscalePixels = 0;    // Low saturation (neutral = floor plan)
    $totalSaturation = 0;
    $totalBrightness = 0;
    $colorBuckets = [];      // Quantized color distribution
    $edgeCount = 0;
    $sharpEdgeCount = 0;     // Very high contrast edges (wall lines)

    // Pass 1: Analyze every pixel for color/brightness/saturation (within bbox)
    for ($y = $bMinY; $y <= $bMaxY; $y++) {
        for ($x = $bMinX; $x <= $bMaxX; $x++) {
            $totalPixels++;
            $rgb = imagecolorat($sample, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            
            // Brightness (0-255)
            $brightness = ($r + $g + $b) / 3;
            $totalBrightness += $brightness;
            
            if ($brightness > 200) $whitePixels++;
            if ($brightness > 150) $brightPixels++;
            if ($brightness < 60) $darkPixels++;
            
            // Saturation calculation (HSL saturation)
            $max = max($r, $g, $b);
            $min = min($r, $g, $b);
            $diff = $max - $min;
            
            if ($max == 0) {
                $saturation = 0;
            } else {
                $saturation = $diff / $max; // 0 to 1
            }
            
            $totalSaturation += $saturation;
            
            // A pixel is "saturated" (colorful) if saturation > 0.25 AND brightness is in mid range
            if ($saturation > 0.25 && $brightness > 40 && $brightness < 220) {
                $saturatedPixels++;
            }
            
            // A pixel is "grayscale" if saturation is very low
            if ($saturation < 0.15) {
                $grayscalePixels++;
            }
            
            // Color bucket (coarse quantization)
            $qr = intval($r / 51); // 0-5 per channel = 216 max buckets
            $qg = intval($g / 51);
            $qb = intval($b / 51);
            $key = "{$qr}-{$qg}-{$qb}";
            $colorBuckets[$key] = ($colorBuckets[$key] ?? 0) + 1;
        }
    }

    // Pass 2: Edge detection (sharp transitions = walls in floor plans), within bbox
    for ($y = $bMinY + 1; $y < $bMaxY; $y++) {
        for ($x = $bMinX + 1; $x < $bMaxX; $x++) {
            $center = imagecolorat($sample, $x, $y);
            $right = imagecolorat($sample, $x + 1, $y);
            $below = imagecolorat($sample, $x, $y + 1);
            
            $cB = (($center >> 16 & 0xFF) + ($center >> 8 & 0xFF) + ($center & 0xFF)) / 3;
            $rB = (($right >> 16 & 0xFF) + ($right >> 8 & 0xFF) + ($right & 0xFF)) / 3;
            $bB = (($below >> 16 & 0xFF) + ($below >> 8 & 0xFF) + ($below & 0xFF)) / 3;
            
            $gradH = abs($cB - $rB);
            $gradV = abs($cB - $bB);
            
            if ($gradH > 40 || $gradV > 40) $edgeCount++;
            if ($gradH > 100 || $gradV > 100) $sharpEdgeCount++;  // Very sharp = wall lines
        }
    }

    // Pass 3: STRAIGHT axis-aligned line detection (the signature of digital plans).
    // Digital/CAD floor plans are dominated by long, perfectly straight
    // horizontal & vertical wall lines. Hand-drawn sketches use wavy freehand
    // strokes plus handwritten labels, so very little of their ink forms long
    // straight runs. We measure how much of the dark ink lies in long
    // axis-aligned runs (straightRatio) and how many full-length wall lines exist.
    $darkInk = 0;
    $hLineInk = 0;
    $vLineInk = 0;
    $longHLines = 0;
    $longVLines = 0;
    $bw = $bMaxX - $bMinX + 1;
    $bh = $bMaxY - $bMinY + 1;
    $minRunH = max(12, intval($bw * 0.22));
    $minRunV = max(12, intval($bh * 0.22));
    $darkThresh = 120;

    // Horizontal runs (also count total dark ink once, here)
    for ($y = $bMinY; $y <= $bMaxY; $y++) {
        $run = 0; $rowHasLong = false;
        for ($x = $bMinX; $x <= $bMaxX; $x++) {
            $rgb = imagecolorat($sample, $x, $y);
            $bb = (($rgb >> 16 & 0xFF) + ($rgb >> 8 & 0xFF) + ($rgb & 0xFF)) / 3;
            if ($bb < $darkThresh) {
                $run++;
                $darkInk++;
            } else {
                if ($run >= $minRunH) { $hLineInk += $run; $rowHasLong = true; }
                $run = 0;
            }
        }
        if ($run >= $minRunH) { $hLineInk += $run; $rowHasLong = true; }
        if ($rowHasLong) $longHLines++;
    }
    // Vertical runs (do NOT recount dark ink)
    for ($x = $bMinX; $x <= $bMaxX; $x++) {
        $run = 0; $colHasLong = false;
        for ($y = $bMinY; $y <= $bMaxY; $y++) {
            $rgb = imagecolorat($sample, $x, $y);
            $bb = (($rgb >> 16 & 0xFF) + ($rgb >> 8 & 0xFF) + ($rgb & 0xFF)) / 3;
            if ($bb < $darkThresh) {
                $run++;
            } else {
                if ($run >= $minRunV) { $vLineInk += $run; $colHasLong = true; }
                $run = 0;
            }
        }
        if ($run >= $minRunV) { $vLineInk += $run; $colHasLong = true; }
        if ($colHasLong) $longVLines++;
    }
    $straightRatio = $darkInk > 0 ? ($hLineInk + $vLineInk) / $darkInk : 0;

    imagedestroy($sample);

    // Calculate ratios
    $whiteRatio = $whitePixels / $totalPixels;          // % of very white pixels
    $brightRatio = $brightPixels / $totalPixels;        // % of bright pixels  
    $darkRatio = $darkPixels / $totalPixels;            // % of dark pixels
    $saturatedRatio = $saturatedPixels / $totalPixels;  // % of colorful pixels
    $grayscaleRatio = $grayscalePixels / $totalPixels;  // % of neutral pixels
    $avgSaturation = $totalSaturation / $totalPixels;   // Average saturation (0-1)
    $avgBrightness = $totalBrightness / $totalPixels;   // Average brightness (0-255)
    $edgeRatio = $edgeCount / $totalPixels;
    $sharpEdgeRatio = $sharpEdgeCount / $totalPixels;
    $uniqueColors = count($colorBuckets);

    // Debug info (for troubleshooting)
    $debug = [
        'whiteRatio' => round($whiteRatio, 3),
        'brightRatio' => round($brightRatio, 3),
        'darkRatio' => round($darkRatio, 3),
        'saturatedRatio' => round($saturatedRatio, 3),
        'grayscaleRatio' => round($grayscaleRatio, 3),
        'avgSaturation' => round($avgSaturation, 3),
        'avgBrightness' => round($avgBrightness, 1),
        'edgeRatio' => round($edgeRatio, 3),
        'sharpEdgeRatio' => round($sharpEdgeRatio, 3),
        'uniqueColors' => $uniqueColors,
        'straightRatio' => round($straightRatio, 3),
        'longHLines' => $longHLines,
        'longVLines' => $longVLines,
    ];

    logDebug('Floor plan validation', $debug);

    // ========== REJECTION RULES ==========

    // RULE 1: PHOTO DETECTION - High saturation = photograph
    // Floor plans are almost always grayscale/neutral. Photos have vivid colors.
    if ($saturatedRatio > 0.20) {
        return [
            'isValid' => false,
            'errorMessage' => 'The uploaded image appears to be a photograph, not a floor plan. Floor plans are typically black/white or grayscale architectural drawings. Please upload a clear, digital floor plan. If you need assistance, please connect with our support team.',
            'errorCode' => 'NOT_RECOGNIZED',
            '_debug' => $debug
        ];
    }

    // RULE 2: PHOTO DETECTION - Average saturation too high
    if ($avgSaturation > 0.15) {
        return [
            'isValid' => false,
            'errorMessage' => 'The uploaded image appears to be a photograph or colourful image, not a floor plan. Please upload a digital architectural floor plan (typically black lines on white background). If you need assistance, please connect with our support team.',
            'errorCode' => 'NOT_RECOGNIZED',
            '_debug' => $debug
        ];
    }

    // RULE 3: Floor plans MUST have a significant white/bright background
    // At least 40% of the image should be bright (background)
    if ($brightRatio < 0.35) {
        return [
            'isValid' => false,
            'errorMessage' => 'The uploaded image was not recognised as a Floor Plan. Floor plans typically have a white or light background with dark lines showing walls and structure. Please upload a valid architectural floor plan. If you need assistance, please connect with our support team.',
            'errorCode' => 'NOT_RECOGNIZED',
            '_debug' => $debug
        ];
    }

    // RULE 4: Must have SOME structure (lines/walls).
    // A truly blank/featureless image has almost no edges.
    if ($edgeRatio < 0.015) {
        return [
            'isValid' => false,
            'errorMessage' => 'The uploaded image appears to be blank or does not contain visible floor plan structure. Please upload a floor plan with clear walls and room layouts.',
            'errorCode' => 'NOT_RECOGNIZED',
            '_debug' => $debug
        ];
    }

    // RULE 5: Must be predominantly grayscale (>60% neutral pixels)
    if ($grayscaleRatio < 0.55) {
        return [
            'isValid' => false,
            'errorMessage' => 'The uploaded image contains too many colours to be a floor plan. Architectural floor plans are typically in black, white, and grey tones. Please upload a proper digital floor plan. If you need assistance, please connect with our support team.',
            'errorCode' => 'NOT_RECOGNIZED',
            '_debug' => $debug
        ];
    }

    // RULE 6: Too many unique colors = likely a photo
    // Floor plans with limited palette: typically <80 color buckets
    // Photos with rich gradients: typically >100 color buckets
    if ($uniqueColors > 100 && $saturatedRatio > 0.10) {
        return [
            'isValid' => false,
            'errorMessage' => 'The uploaded image appears to be a photograph rather than a floor plan. Please upload a clear, digital architectural floor plan. If you need assistance, please connect with our support team.',
            'errorCode' => 'NOT_RECOGNIZED',
            '_debug' => $debug
        ];
    }

    // RULE 7: Must have some line structure (edges)
    // Floor plans have sharp lines. No edges = solid color or blurred photo
    if ($edgeRatio < 0.03) {
        return [
            'isValid' => false,
            'errorMessage' => 'The uploaded image does not appear to contain floor plan structure (walls, rooms). Please upload a valid architectural floor plan with visible room layouts. If you need assistance, please connect with our support team.',
            'errorCode' => 'NOT_RECOGNIZED',
            '_debug' => $debug
        ];
    }

    // RULE 8: Should have SHARP edges (wall lines are very high contrast)
    // Photos have soft gradients; floor plans have hard black-white transitions
    if ($sharpEdgeRatio < 0.01 && $edgeRatio > 0.1) {
        // Lots of soft edges but no sharp ones = photo with textures
        return [
            'isValid' => false,
            'errorMessage' => 'The uploaded image appears to be a photograph with textures rather than a floor plan with clean lines. Please upload a digital architectural floor plan. If you need assistance, please connect with our support team.',
            'errorCode' => 'NOT_RECOGNIZED',
            '_debug' => $debug
        ];
    }

    // RULE 9: Entirely blank image (almost all white AND no real line structure)
    if ($whiteRatio > 0.97 && $edgeRatio < 0.025) {
        return [
            'isValid' => false,
            'errorMessage' => 'The uploaded image appears to be blank. Please upload a floor plan with visible content.',
            'errorCode' => 'NOT_RECOGNIZED',
            '_debug' => $debug
        ];
    }

    // RULE 10: HAND-DRAWN / SKETCH detection (CONSERVATIVE fallback only).
    //
    // IMPORTANT: The AI vision classifier (PlanClassifier / Bedrock) is the
    // AUTHORITY on hand-drawn detection (it returns `is_hand_drawn`). This GD
    // heuristic is only a crude safety net for blatant freehand sketches when
    // the AI is unavailable. It must NOT false-reject legitimate digital/CAD
    // plans, including DETAILED ones with lots of furniture symbols, text
    // labels, dimension lines and hatching.
    //
    // The reliable signal for "this is a real CAD plan" is the COUNT of long,
    // perfectly straight, axis-aligned wall lines. Even a busy CAD drawing has
    // many full-length walls; a freehand sketch has essentially none. The
    // `straightRatio` metric is NOT reliable for detailed plans (furniture/text
    // dilute it), so we no longer reject on it alone — it is kept in `_debug`
    // for tuning only. We reject ONLY when there are almost no long straight
    // wall lines in either axis.
    if (($longHLines + $longVLines) < 3) {
        return [
            'isValid' => false,
            'errorMessage' => 'This looks like a hand-drawn or sketched plan. We can only analyse digitally created floor plans (CAD / architect drawings with clean, straight walls). Please upload a digital floor plan, or connect with our support team for a manual consultation.',
            'errorCode' => 'HAND_DRAWN',
            '_debug' => $debug
        ];
    }

    // ========== PASSED ALL CHECKS ==========
    // Calculate confidence
    $confidence = 0.6;
    if ($whiteRatio > 0.50) $confidence += 0.1;   // Strong white background
    if ($grayscaleRatio > 0.75) $confidence += 0.1; // Very neutral
    if ($sharpEdgeRatio > 0.03) $confidence += 0.1; // Clear wall lines
    if ($avgSaturation < 0.08) $confidence += 0.1;  // Very low color

    return [
        'isValid' => true,
        'confidence' => min($confidence, 1.0),
        'errorMessage' => null,
        'errorCode' => null,
        '_debug' => $debug
    ];
}
