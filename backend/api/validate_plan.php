<?php
/**
 * Floor Plan Validation API
 * 
 * STRICT validation - if this returns isValid=false, NO report should be generated.
 * 
 * Checks:
 * 1. File exists and is an image
 * 2. File has sufficient size (not blank/corrupt)
 * 3. Image has minimum resolution (300x300)
 * 4. Image has structural characteristics of a floor plan (not a random photo)
 * 5. Image is not predominantly blank
 * 
 * Error messages guide users to support team when validation fails.
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

// Check file size (too small = likely not a floor plan)
if ($file['size'] < 15000) { // Less than 15KB
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

// Analyze image with GD library
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

// GD-based image analysis
$result = analyzeFloorPlan($file['tmp_name'], $mime, $width, $height);

if (!$result['isValid']) {
    jsonResponse([
        'success' => false,
        'isValid' => false,
        'shouldGenerateReport' => false,
        'errorMessage' => $result['errorMessage'],
        'errorCode' => $result['errorCode']
    ]);
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
 * Analyze the image to determine if it's a valid floor plan.
 * Uses GD library for pixel analysis.
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

    // Sample pixels for analysis (scale down for performance)
    $sampleWidth = min($width, 200);
    $sampleHeight = min($height, 200);
    $sample = imagecreatetruecolor($sampleWidth, $sampleHeight);
    imagecopyresampled($sample, $img, 0, 0, 0, 0, $sampleWidth, $sampleHeight, $width, $height);

    $totalPixels = $sampleWidth * $sampleHeight;
    $colorCounts = [];
    $totalBrightness = 0;
    $edgeCount = 0;
    $highContrastCount = 0;

    // Analyze color distribution
    for ($y = 0; $y < $sampleHeight; $y++) {
        for ($x = 0; $x < $sampleWidth; $x++) {
            $rgb = imagecolorat($sample, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            
            $brightness = ($r + $g + $b) / 3;
            $totalBrightness += $brightness;
            
            // Quantize to detect dominant colors
            $qr = intval($r / 64);
            $qg = intval($g / 64);
            $qb = intval($b / 64);
            $key = "{$qr}-{$qg}-{$qb}";
            $colorCounts[$key] = ($colorCounts[$key] ?? 0) + 1;
        }
    }

    // Edge detection (simple horizontal gradient)
    for ($y = 1; $y < $sampleHeight - 1; $y++) {
        for ($x = 1; $x < $sampleWidth - 1; $x++) {
            $center = imagecolorat($sample, $x, $y);
            $right = imagecolorat($sample, $x + 1, $y);
            $below = imagecolorat($sample, $x, $y + 1);
            
            $cBright = (($center >> 16 & 0xFF) + ($center >> 8 & 0xFF) + ($center & 0xFF)) / 3;
            $rBright = (($right >> 16 & 0xFF) + ($right >> 8 & 0xFF) + ($right & 0xFF)) / 3;
            $bBright = (($below >> 16 & 0xFF) + ($below >> 8 & 0xFF) + ($below & 0xFF)) / 3;
            
            $gradH = abs($cBright - $rBright);
            $gradV = abs($cBright - $bBright);
            
            if ($gradH > 30 || $gradV > 30) $edgeCount++;
            if ($gradH > 80 || $gradV > 80) $highContrastCount++;
        }
    }

    imagedestroy($img);
    imagedestroy($sample);

    $avgBrightness = $totalBrightness / $totalPixels;
    $edgeRatio = $edgeCount / $totalPixels;
    $highContrastRatio = $highContrastCount / $totalPixels;
    $uniqueColors = count($colorCounts);
    $maxColorCount = max($colorCounts);
    $dominantRatio = $maxColorCount / $totalPixels;

    // CHECK: Is image blank? (>90% single color)
    if ($dominantRatio > 0.90) {
        return [
            'isValid' => false,
            'errorMessage' => 'The uploaded image was not recognised as a Floor Plan. The image appears to be blank or mostly a single colour. Please upload a valid architectural floor plan. If you need assistance, please connect with our support team.',
            'errorCode' => 'NOT_RECOGNIZED'
        ];
    }

    // CHECK: Too few unique color blocks (likely blank or very simple)
    if ($uniqueColors < 5) {
        return [
            'isValid' => false,
            'errorMessage' => 'The uploaded image was not recognised as a Floor Plan. Please upload a valid architectural floor plan. If you need assistance, please connect with our support team.',
            'errorCode' => 'NOT_RECOGNIZED'
        ];
    }

    // CHECK: Hand-drawn detection
    // Hand-drawn plans tend to have:
    // - Very high edge ratios (pencil/pen marks everywhere)
    // - Very few unique color blocks (black/white only)
    // - Irregular line patterns (high contrast count vs edge count)
    if ($edgeRatio > 0.35 && $uniqueColors < 12 && $highContrastRatio > 0.25) {
        return [
            'isValid' => false,
            'errorMessage' => 'Hand-drawn plans are not supported for automated analysis. Please upload a digitally created floor plan (CAD/architect drawing), or connect with our support team for manual analysis.',
            'errorCode' => 'HAND_DRAWN'
        ];
    }

    // CHECK: No structure (random noise or photo with no lines)
    if ($edgeRatio < 0.02) {
        return [
            'isValid' => false,
            'errorMessage' => 'The uploaded image was not recognised as a Floor Plan. A floor plan should show walls, rooms, and structural elements. Please upload a valid architectural floor plan. If you need assistance, please connect with our support team.',
            'errorCode' => 'NOT_RECOGNIZED'
        ];
    }

    // CHECK: Too many colors (likely a photograph, not a floor plan)
    // Floor plans typically have limited color palette (whites, grays, blacks, maybe some colors for annotations)
    if ($uniqueColors > 50 && $edgeRatio > 0.3 && $highContrastRatio < 0.05) {
        return [
            'isValid' => false,
            'errorMessage' => 'The uploaded image appears to be a photograph rather than a floor plan. Please upload a clear, digital architectural floor plan. If you need assistance, please connect with our support team.',
            'errorCode' => 'NOT_RECOGNIZED'
        ];
    }

    // Confidence scoring
    $confidence = 0.5;
    if ($edgeRatio > 0.05 && $edgeRatio < 0.3) $confidence += 0.15; // Good edge ratio for floor plans
    if ($uniqueColors > 5 && $uniqueColors < 40) $confidence += 0.15; // Reasonable palette
    if ($highContrastRatio > 0.03 && $highContrastRatio < 0.2) $confidence += 0.1; // Clear walls
    if ($width > 500 && $height > 500) $confidence += 0.1; // Good resolution

    return [
        'isValid' => true,
        'confidence' => min($confidence, 1.0),
        'errorMessage' => null,
        'errorCode' => null
    ];
}
