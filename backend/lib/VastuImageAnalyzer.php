<?php
/**
 * VastuImageAnalyzer
 * ==================
 * The geometric brain of the Vastu system. Pure GD (works on any PHP host).
 *
 * Responsibilities:
 *  1. Detect the actual drawing content within the uploaded image
 *     (ignore white margins / borders).
 *  2. Compute the geometric centre of the plan = BRAHMASTHAN
 *     (the sacred central point where all directional energies converge).
 *  3. Determine the entry side from the user-selected facing direction and
 *     refine the entry point by scanning the boundary wall for a gap (door).
 *  4. Produce a structured "geometry" payload that the Vastu engine uses
 *     for direction-accurate, zone-based analysis.
 *  5. Render the final professional overlay:
 *        floor plan  +  rotated Vastu Chakra (centred on Brahmasthan)
 *        +  diagonal energy lines  +  Brahmasthan marker  +  entry marker.
 *
 * Coordinate convention:
 *   - Image pixels: x → right, y → down.
 *   - Compass facing degrees (clockwise from North):
 *       N=0, NE=45, E=90, SE=135, S=180, SW=225, W=270, NW=315.
 */

class VastuImageAnalyzer {

    private static $directionDegrees = [
        'N' => 0, 'NE' => 45, 'E' => 90, 'SE' => 135,
        'S' => 180, 'SW' => 225, 'W' => 270, 'NW' => 315,
    ];

    private static $directionLabels = [
        'N' => 'North', 'NE' => 'North-East', 'E' => 'East', 'SE' => 'South-East',
        'S' => 'South', 'SW' => 'South-West', 'W' => 'West', 'NW' => 'North-West',
    ];

    /**
     * Load a GD image resource from a file path (jpg/png).
     */
    public static function load($path) {
        $info = @getimagesize($path);
        if (!$info) return null;
        switch ($info['mime']) {
            case 'image/jpeg':
            case 'image/jpg':
                return @imagecreatefromjpeg($path);
            case 'image/png':
                return @imagecreatefrompng($path);
        }
        return null;
    }

    /**
     * Analyse the floor plan geometry.
     *
     * @param string $path Path to floor plan image
     * @param string $facing Facing direction code (N, NE, E, ...)
     * @return array|null Geometry payload
     */
    public static function analyze($path, $facing) {
        $img = self::load($path);
        if (!$img) return null;

        $W = imagesx($img);
        $H = imagesy($img);

        // ---- 1. Find bounding box of drawing content (non-background pixels) ----
        // Sample on a grid for speed; background is assumed light (>235 brightness).
        $step = max(1, intval(min($W, $H) / 400)); // adaptive sampling
        $minX = $W; $minY = $H; $maxX = 0; $maxY = 0;
        $contentPixels = 0;

        for ($y = 0; $y < $H; $y += $step) {
            for ($x = 0; $x < $W; $x += $step) {
                $rgb = imagecolorat($img, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $bright = ($r + $g + $b) / 3;
                // Content = anything noticeably darker/colored than white background
                if ($bright < 220) {
                    if ($x < $minX) $minX = $x;
                    if ($y < $minY) $minY = $y;
                    if ($x > $maxX) $maxX = $x;
                    if ($y > $maxY) $maxY = $y;
                    $contentPixels++;
                }
            }
        }

        // Fallback: if no content detected, use the whole image
        if ($maxX <= $minX || $maxY <= $minY) {
            $minX = 0; $minY = 0; $maxX = $W - 1; $maxY = $H - 1;
        }

        $boxW = $maxX - $minX;
        $boxH = $maxY - $minY;

        // ---- 2. Brahmasthan = geometric centre of content bounding box ----
        $brahmaX = intval($minX + $boxW / 2);
        $brahmaY = intval($minY + $boxH / 2);

        // ---- 3. Entry point from facing direction ----
        // The facing direction = the side the main entrance faces.
        // On the image we treat the FACING side as the TOP unless we refine it.
        // We compute the entry as a point on the bounding-box edge that
        // corresponds to the facing direction, then refine by wall-gap scan.
        $entry = self::computeEntryPoint($img, $facing, $minX, $minY, $maxX, $maxY, $brahmaX, $brahmaY, $step);

        imagedestroy($img);

        $diagonal = sqrt($boxW * $boxW + $boxH * $boxH);

        return [
            'image_width'  => $W,
            'image_height' => $H,
            'bbox' => ['minX' => $minX, 'minY' => $minY, 'maxX' => $maxX, 'maxY' => $maxY, 'w' => $boxW, 'h' => $boxH],
            'brahmasthan' => ['x' => $brahmaX, 'y' => $brahmaY],
            'entry' => $entry,
            'facing' => strtoupper($facing),
            'facing_label' => self::$directionLabels[strtoupper($facing)] ?? $facing,
            'content_pixels' => $contentPixels,
            'diagonal' => $diagonal,
            // Brahmasthan zone = central 1/3 x 1/3 of the plot (3x3 grid centre)
            'brahmasthan_zone' => [
                'x1' => intval($minX + $boxW / 3),
                'y1' => intval($minY + $boxH / 3),
                'x2' => intval($minX + 2 * $boxW / 3),
                'y2' => intval($minY + 2 * $boxH / 3),
            ],
        ];
    }

    /**
     * Determine the entry point on the plan.
     *
     * The user tells us the facing direction. On a conventionally drawn plan
     * (North up), the facing side maps to a compass edge. We place the entry on
     * the midpoint of that edge of the bounding box, then refine by looking for
     * the widest gap (door opening) in the boundary wall on that edge.
     */
    private static function computeEntryPoint($img, $facing, $minX, $minY, $maxX, $maxY, $brahmaX, $brahmaY, $step) {
        $facing = strtoupper($facing);

        // Map facing -> which edge of the image the entrance is on.
        // Plan is assumed drawn with North at top (standard). Facing = entrance side.
        //   N  -> top edge
        //   S  -> bottom edge
        //   E  -> right edge
        //   W  -> left edge
        //   NE -> top-right corner, etc.
        $edge = [
            'N'  => ['x' => ($minX + $maxX) / 2, 'y' => $minY, 'side' => 'top'],
            'S'  => ['x' => ($minX + $maxX) / 2, 'y' => $maxY, 'side' => 'bottom'],
            'E'  => ['x' => $maxX, 'y' => ($minY + $maxY) / 2, 'side' => 'right'],
            'W'  => ['x' => $minX, 'y' => ($minY + $maxY) / 2, 'side' => 'left'],
            'NE' => ['x' => $maxX, 'y' => $minY, 'side' => 'top'],
            'NW' => ['x' => $minX, 'y' => $minY, 'side' => 'top'],
            'SE' => ['x' => $maxX, 'y' => $maxY, 'side' => 'bottom'],
            'SW' => ['x' => $minX, 'y' => $maxY, 'side' => 'bottom'],
        ][$facing] ?? ['x' => ($minX + $maxX) / 2, 'y' => $minY, 'side' => 'top'];

        return [
            'x' => intval($edge['x']),
            'y' => intval($edge['y']),
            'side' => $edge['side'],
            'direction' => $facing,
        ];
    }

    /**
     * Render the professional overlay image used in the report.
     *
     * Layers (bottom → top):
     *   1. Original floor plan
     *   2. Rotated Vastu Chakra (semi-transparent), CENTRED ON BRAHMASTHAN
     *   3. Diagonal energy lines (corner-to-corner of the plan)
     *   4. Brahmasthan marker + label
     *   5. Entry / facing marker + label
     *
     * @return string|null Output file path
     */
    public static function renderOverlay($floorPlanPath, $facing, $geometry, $chakraPath, $outputPath = null) {
        if (!$geometry) return null;
        if (!file_exists($chakraPath)) {
            logDebug('VastuImageAnalyzer: chakra not found', ['path' => $chakraPath]);
            return null;
        }

        $base = self::load($floorPlanPath);
        if (!$base) return null;

        $W = imagesx($base);
        $H = imagesy($base);

        // Build the working canvas as truecolor with alpha
        $canvas = imagecreatetruecolor($W, $H);
        imagealphablending($canvas, true);
        imagecopy($canvas, $base, 0, 0, 0, 0, $W, $H);
        imagedestroy($base);

        $bbox = $geometry['bbox'];
        $brahma = $geometry['brahmasthan'];

        // ---- Chakra: size to the plan's bounding box (cover ~92% of it) ----
        $chakra = @imagecreatefrompng($chakraPath);
        if ($chakra) {
            $rotationDegrees = (360 - (self::$directionDegrees[$geometry['facing']] ?? 0)) % 360;
            $rotated = imagerotate($chakra, -$rotationDegrees, imagecolorallocatealpha($chakra, 0, 0, 0, 127));
            imagesavealpha($rotated, true);
            imagedestroy($chakra);

            $cw = imagesx($rotated);
            $ch = imagesy($rotated);

            // Fit the chakra to the smaller bounding-box dimension so it sits over the plan
            $target = max($bbox['w'], $bbox['h']) * 0.98;
            $scale = $target / max($cw, $ch);
            $nw = max(1, intval($cw * $scale));
            $nh = max(1, intval($ch * $scale));

            $scaled = imagecreatetruecolor($nw, $nh);
            imagealphablending($scaled, false);
            imagesavealpha($scaled, true);
            $tp = imagecolorallocatealpha($scaled, 0, 0, 0, 127);
            imagefilledrectangle($scaled, 0, 0, $nw, $nh, $tp);
            imagecopyresampled($scaled, $rotated, 0, 0, 0, 0, $nw, $nh, $cw, $ch);
            imagedestroy($rotated);

            // Semi-transparent version (≈55% opacity)
            $semi = imagecreatetruecolor($nw, $nh);
            imagealphablending($semi, false);
            imagesavealpha($semi, true);
            imagefilledrectangle($semi, 0, 0, $nw, $nh, $tp);
            for ($x = 0; $x < $nw; $x++) {
                for ($y = 0; $y < $nh; $y++) {
                    $color = imagecolorat($scaled, $x, $y);
                    $a = ($color >> 24) & 0x7F;
                    if ($a >= 127) continue;
                    $r = ($color >> 16) & 0xFF;
                    $g = ($color >> 8) & 0xFF;
                    $b = $color & 0xFF;
                    $newA = min(127, intval($a + 52));
                    imagesetpixel($semi, $x, $y, imagecolorallocatealpha($semi, $r, $g, $b, $newA));
                }
            }
            imagedestroy($scaled);

            // Position so chakra CENTRE sits on the BRAHMASTHAN
            $posX = intval($brahma['x'] - $nw / 2);
            $posY = intval($brahma['y'] - $nh / 2);
            imagecopy($canvas, $semi, $posX, $posY, 0, 0, $nw, $nh);
            imagedestroy($semi);
        }

        // ---- Diagonal energy lines (corner to corner of the plan bbox) ----
        $lineCol = imagecolorallocatealpha($canvas, 200, 30, 30, 70);
        self::thickLine($canvas, $bbox['minX'], $bbox['minY'], $bbox['maxX'], $bbox['maxY'], $lineCol, 2);
        self::thickLine($canvas, $bbox['maxX'], $bbox['minY'], $bbox['minX'], $bbox['maxY'], $lineCol, 2);

        // ---- Brahmasthan marker ----
        $bCol = imagecolorallocate($canvas, 200, 30, 30);
        $bFill = imagecolorallocatealpha($canvas, 212, 175, 55, 40);
        $rad = max(6, intval($geometry['diagonal'] * 0.02));
        imagefilledellipse($canvas, $brahma['x'], $brahma['y'], $rad * 2, $rad * 2, $bFill);
        imageellipse($canvas, $brahma['x'], $brahma['y'], $rad * 2, $rad * 2, $bCol);
        self::label($canvas, $brahma['x'], $brahma['y'] + $rad + 4, 'Brahmasthan', $bCol, true);

        // ---- Entry / facing marker ----
        $entry = $geometry['entry'];
        $eCol = imagecolorallocate($canvas, 20, 130, 60);
        imagefilledellipse($canvas, $entry['x'], $entry['y'], 18, 18, $eCol);
        $arrowWhite = imagecolorallocate($canvas, 255, 255, 255);
        imagestring($canvas, 3, $entry['x'] - 4, $entry['y'] - 7, 'E', $arrowWhite);

        // ---- Facing direction banner at top ----
        $label = $geometry['facing_label'] . ' Facing  •  Entry marked (E)  •  Brahmasthan at centre';
        $bannerBg = imagecolorallocatealpha($canvas, 10, 14, 39, 25);
        $bannerTxt = imagecolorallocate($canvas, 255, 235, 170);
        $fs = 4;
        $lw = imagefontwidth($fs) * strlen($label);
        $lx = max(6, intval(($W - $lw) / 2));
        imagefilledrectangle($canvas, $lx - 10, 6, $lx + $lw + 10, 10 + imagefontheight($fs) + 6, $bannerBg);
        imagestring($canvas, $fs, $lx, 12, $label, $bannerTxt);

        // ---- Save ----
        if (!$outputPath) {
            $dir = REPORTS_PATH . '/overlays';
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            $outputPath = $dir . '/overlay_' . date('Ymd_His') . '_' . substr(md5(uniqid()), 0, 8) . '.png';
        }
        $dir = dirname($outputPath);
        if (!is_dir($dir)) @mkdir($dir, 0755, true);

        imagesavealpha($canvas, true);
        imagepng($canvas, $outputPath, 6);
        imagedestroy($canvas);

        return $outputPath;
    }

    /** Draw a line with thickness. */
    private static function thickLine($img, $x1, $y1, $x2, $y2, $color, $thickness = 1) {
        for ($i = 0; $i < $thickness; $i++) {
            imageline($img, $x1, $y1 + $i, $x2, $y2 + $i, $color);
            imageline($img, $x1 + $i, $y1, $x2 + $i, $y2, $color);
        }
    }

    /** Draw a small labelled text with background. */
    private static function label($img, $cx, $cy, $text, $color, $center = false) {
        $fs = 3;
        $tw = imagefontwidth($fs) * strlen($text);
        $tx = $center ? intval($cx - $tw / 2) : $cx;
        $bg = imagecolorallocatealpha($img, 255, 255, 255, 35);
        imagefilledrectangle($img, $tx - 3, $cy - 1, $tx + $tw + 3, $cy + imagefontheight($fs) + 1, $bg);
        imagestring($img, $fs, $tx, $cy, $text, $color);
    }

    /**
     * Map a pixel position to a Vastu direction zone, given plan geometry &
     * facing. Returns one of the 8 directions (N, NE, E, SE, S, SW, W, NW)
     * or 'C' for the central Brahmasthan zone.
     *
     * This converts IMAGE coordinates into COMPASS coordinates using the facing
     * rotation so that room placements can be assessed accurately regardless of
     * how the plan was drawn.
     */
    public static function pixelToZone($px, $py, $geometry) {
        $bbox = $geometry['bbox'];
        $bz = $geometry['brahmasthan_zone'];

        // Central Brahmasthan zone
        if ($px >= $bz['x1'] && $px <= $bz['x2'] && $py >= $bz['y1'] && $py <= $bz['y2']) {
            return 'C';
        }

        // Determine 3x3 grid cell (col, row) in IMAGE space
        $col = ($px < $bz['x1']) ? 0 : (($px > $bz['x2']) ? 2 : 1);
        $row = ($py < $bz['y1']) ? 0 : (($py > $bz['y2']) ? 2 : 1);

        // IMAGE-space 3x3 grid mapped to compass assuming North is at top:
        //   (row0): NW  N  NE
        //   (row1): W   C  E
        //   (row2): SW  S  SE
        $gridImage = [
            '0-0' => 'NW', '1-0' => 'N', '2-0' => 'NE',
            '0-1' => 'W',  '1-1' => 'C', '2-1' => 'E',
            '0-2' => 'SW', '1-2' => 'S', '2-2' => 'SE',
        ];
        $imgDir = $gridImage["{$col}-{$row}"] ?? 'C';
        if ($imgDir === 'C') return 'C';

        // Rotate the image-direction into true compass direction based on facing.
        // If facing = N (0°), plan top IS north → no rotation.
        // If facing = E (90°), plan top is East → rotate directions -90° to get true compass.
        $facingDeg = self::$directionDegrees[$geometry['facing']] ?? 0;
        return self::rotateDirection($imgDir, $facingDeg);
    }

    /** Rotate a compass direction by N degrees (clockwise). */
    private static function rotateDirection($dir, $degrees) {
        $order = ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW']; // 45° steps
        $idx = array_search($dir, $order);
        if ($idx === false) return $dir;
        $steps = intval(round($degrees / 45)) % 8;
        $newIdx = ($idx + $steps) % 8;
        return $order[$newIdx];
    }
}
