<?php
/**
 * ChakraOverlay - Generates Vastu Chakra overlay on floor plan
 * 
 * The Vastu Chakra PNG (chakra-overlay.png) always has NORTH at the top.
 * 
 * ROTATION LOGIC:
 * ===============
 * The user's floor plan is oriented with the FACING DIRECTION at the top.
 * We need to rotate the chakra so its compass zones align correctly.
 * 
 * If user says "facing East" (East at top of plan):
 *   - North is to the LEFT in real space
 *   - Rotate chakra: (360 - 90) % 360 = 270° clockwise
 *   - This puts North pointing LEFT, matching the plan
 * 
 * Formula: rotation = (360 - facingDegrees) % 360
 * 
 * Facing degrees (clockwise from North):
 *   N=0, NE=45, E=90, SE=135, S=180, SW=225, W=270, NW=315
 * 
 * Verification:
 *   North facing  → rotation = 0°   → N stays on top ✓
 *   East facing   → rotation = 270° → N points left  ✓
 *   South facing  → rotation = 180° → N points down  ✓
 *   West facing   → rotation = 90°  → N points right ✓
 */

class ChakraOverlay {
    
    private static $directionDegrees = [
        'N'  => 0,
        'NE' => 45,
        'E'  => 90,
        'SE' => 135,
        'S'  => 180,
        'SW' => 225,
        'W'  => 270,
        'NW' => 315,
    ];

    /**
     * Get rotation degrees for a facing direction.
     * Returns the clockwise rotation to apply to the chakra image.
     */
    public static function getRotationDegrees($facingDirection) {
        $degrees = self::$directionDegrees[strtoupper($facingDirection)] ?? 0;
        return (360 - $degrees) % 360;
    }

    /**
     * Generate overlay image: floor plan + rotated chakra.
     * Uses GD library (available on virtually all PHP hosts).
     * 
     * @param string $floorPlanPath Path to the uploaded floor plan image
     * @param string $facingDirection Direction code (N, NE, E, SE, S, SW, W, NW)
     * @param string $outputPath Where to save the overlay image
     * @return string|null Path to generated overlay, or null on failure
     */
    public static function generate($floorPlanPath, $facingDirection, $outputPath = null) {
        $chakraPath = UPLOADS_PATH . '/chakra-overlay.png';
        
        if (!file_exists($floorPlanPath)) {
            logDebug('ChakraOverlay: Floor plan not found', ['path' => $floorPlanPath]);
            return null;
        }
        if (!file_exists($chakraPath)) {
            logDebug('ChakraOverlay: Chakra image not found', ['path' => $chakraPath]);
            return null;
        }

        // Determine floor plan image type
        $info = @getimagesize($floorPlanPath);
        if (!$info) return null;

        $mime = $info['mime'];
        $planWidth = $info[0];
        $planHeight = $info[1];

        // Load floor plan
        switch ($mime) {
            case 'image/jpeg':
            case 'image/jpg':
                $floorPlan = @imagecreatefromjpeg($floorPlanPath);
                break;
            case 'image/png':
                $floorPlan = @imagecreatefrompng($floorPlanPath);
                break;
            default:
                logDebug('ChakraOverlay: Unsupported format', ['mime' => $mime]);
                return null;
        }

        if (!$floorPlan) return null;

        // Load chakra image (PNG with transparency)
        $chakra = @imagecreatefrompng($chakraPath);
        if (!$chakra) {
            imagedestroy($floorPlan);
            return null;
        }

        // Calculate rotation
        $rotationDegrees = self::getRotationDegrees($facingDirection);

        // Rotate chakra (GD rotates counter-clockwise, so negate for clockwise)
        // imagerotate() rotates counter-clockwise, so pass negative for CW rotation
        $rotatedChakra = imagerotate($chakra, -$rotationDegrees, imagecolorallocatealpha($chakra, 0, 0, 0, 127));
        imagesavealpha($rotatedChakra, true);
        imagedestroy($chakra);

        // Get rotated dimensions
        $chakraW = imagesx($rotatedChakra);
        $chakraH = imagesy($rotatedChakra);

        // Calculate target size: 85% of smallest floor plan dimension
        $targetSize = min($planWidth, $planHeight) * 0.85;
        
        // Scale chakra to target size
        $scaleX = $targetSize / $chakraW;
        $scaleY = $targetSize / $chakraH;
        $scale = min($scaleX, $scaleY);
        
        $newChakraW = intval($chakraW * $scale);
        $newChakraH = intval($chakraH * $scale);

        // Create resized chakra
        $scaledChakra = imagecreatetruecolor($newChakraW, $newChakraH);
        imagealphablending($scaledChakra, false);
        imagesavealpha($scaledChakra, true);
        $transparent = imagecolorallocatealpha($scaledChakra, 0, 0, 0, 127);
        imagefilledrectangle($scaledChakra, 0, 0, $newChakraW, $newChakraH, $transparent);
        imagecopyresampled($scaledChakra, $rotatedChakra, 0, 0, 0, 0, $newChakraW, $newChakraH, $chakraW, $chakraH);
        imagedestroy($rotatedChakra);

        // Create output canvas (same size as floor plan)
        $output = imagecreatetruecolor($planWidth, $planHeight);
        imagealphablending($output, true);
        
        // Draw floor plan
        imagecopy($output, $floorPlan, 0, 0, 0, 0, $planWidth, $planHeight);
        imagedestroy($floorPlan);

        // Apply semi-transparency to chakra (55% opacity)
        // We'll composite with alpha blending
        imagealphablending($output, true);
        
        // Calculate position to center chakra on floor plan
        $posX = intval(($planWidth - $newChakraW) / 2);
        $posY = intval(($planHeight - $newChakraH) / 2);

        // Create a semi-transparent version of the chakra
        $semiTransparent = imagecreatetruecolor($newChakraW, $newChakraH);
        imagealphablending($semiTransparent, false);
        imagesavealpha($semiTransparent, true);
        imagefilledrectangle($semiTransparent, 0, 0, $newChakraW, $newChakraH, $transparent);
        
        // Copy chakra with reduced opacity
        for ($x = 0; $x < $newChakraW; $x++) {
            for ($y = 0; $y < $newChakraH; $y++) {
                $color = imagecolorat($scaledChakra, $x, $y);
                $a = ($color >> 24) & 0x7F;
                if ($a >= 127) continue; // Fully transparent, skip
                
                $r = ($color >> 16) & 0xFF;
                $g = ($color >> 8) & 0xFF;
                $b = $color & 0xFF;
                
                // Increase alpha for semi-transparency (55% opacity = ~57 alpha in GD's 0-127 scale)
                $newAlpha = min(127, intval($a + 57)); // Add transparency
                
                $newColor = imagecolorallocatealpha($semiTransparent, $r, $g, $b, $newAlpha);
                imagesetpixel($semiTransparent, $x, $y, $newColor);
            }
        }
        imagedestroy($scaledChakra);

        // Composite semi-transparent chakra onto floor plan
        imagecopy($output, $semiTransparent, $posX, $posY, 0, 0, $newChakraW, $newChakraH);
        imagedestroy($semiTransparent);

        // Add direction label at top
        $dirMap = ['N' => 'North', 'S' => 'South', 'E' => 'East', 'W' => 'West', 
                   'NE' => 'North-East', 'NW' => 'North-West', 'SE' => 'South-East', 'SW' => 'South-West'];
        $label = ($dirMap[$facingDirection] ?? $facingDirection) . ' Facing ↑';
        
        $labelColor = imagecolorallocate($output, 220, 40, 40);
        $bgColor = imagecolorallocatealpha($output, 255, 255, 255, 30);
        
        // Draw label background
        $fontSize = max(3, min(5, intval($planWidth / 200)));
        $labelWidth = imagefontwidth($fontSize) * strlen($label);
        $labelX = intval(($planWidth - $labelWidth) / 2);
        imagefilledrectangle($output, $labelX - 8, 4, $labelX + $labelWidth + 8, 8 + imagefontheight($fontSize) + 4, $bgColor);
        imagestring($output, $fontSize, $labelX, 8, $label, $labelColor);

        // Determine output path
        if (!$outputPath) {
            $outputPath = REPORTS_PATH . '/overlays/';
            if (!is_dir($outputPath)) @mkdir($outputPath, 0755, true);
            $outputPath .= 'overlay_' . date('Ymd_His') . '_' . substr(md5(uniqid()), 0, 8) . '.png';
        }

        // Ensure directory exists
        $dir = dirname($outputPath);
        if (!is_dir($dir)) @mkdir($dir, 0755, true);

        // Save as PNG
        imagesavealpha($output, true);
        imagepng($output, $outputPath, 6); // compression level 6
        imagedestroy($output);

        return $outputPath;
    }
}
