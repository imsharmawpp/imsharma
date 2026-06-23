<?php
/**
 * VastuImageAnalyzer
 * ==================
 * The geometric brain of the Vastu system. Pure GD (works on any PHP host).
 *
 * Responsibilities:
 *  1. Detect the actual drawing content (ignore white margins) -> bounding box.
 *  2. Compute the geometric centre = BRAHMASTHAN.
 *  3. Compute the chakra ROTATION from the user-marked ENTRY position + the
 *     user-selected facing direction. This works for a plan drawn in ANY
 *     orientation (we no longer assume "facing side = top").
 *  4. Map any marked element (room/cabin/etc.) to its true compass zone.
 *  5. Render the professional overlay: plan + rotated Vastu Chakra centred on
 *     the Brahmasthan (fully visible, clearly opaque) + diagonal energy lines
 *     + Brahmasthan marker + entry marker + small dots for marked elements.
 *
 * Coordinate convention:
 *   - Image pixels: x -> right, y -> down.
 *   - Image bearing: up=0, clockwise positive (right=90, down=180, left=270).
 *   - Compass facing degrees (clockwise from North):
 *       N=0, NE=45, E=90, SE=135, S=180, SW=225, W=270, NW=315.
 *
 * ROTATION MATH:
 *   entryBearing = image bearing of the entry marker relative to Brahmasthan.
 *   The entry faces compass direction `facingDeg`.
 *   We rotate the chakra clockwise by R so the chakra's `facingDeg` zone lands
 *   on the entry side:   R = (entryBearing - facingDeg) mod 360.
 *   A point at image bearing `b` is in compass direction (b - R) mod 360.
 *   (When no entry marker: entry is taken at the top, entryBearing=0, so
 *    R = (360 - facingDeg) % 360 — identical to the previous behaviour.)
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

    private static $dirOrder = ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW'];

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
     * @param string $path    Floor plan image path
     * @param string $facing  Facing direction code (N, NE, E, ...)
     * @param array  $markers Optional: [['type'=>'entrance','nx'=>0.5,'ny'=>0.1], ...]
     *                        nx/ny are normalised [0..1] coords on the full image.
     * @return array|null Geometry payload
     */
    public static function analyze($path, $facing, $markers = []) {
        $img = self::load($path);
        if (!$img) return null;

        $W = imagesx($img);
        $H = imagesy($img);

        // ---- 1. Detect the STRUCTURAL drawing (walls/ink) only ----
        // The Brahmasthan must be the centre of the ACTUAL building drawing, so:
        //   - ignore the white margins,
        //   - ignore coloured decorations (plants, flowers, furniture colour,
        //     logos) by excluding saturated pixels,
        //   - ignore sparse outliers (stray marks, a tree on one edge, a detached
        //     annotation) using a DENSITY-threshold bounding box instead of raw
        //     min/max. Dense wall columns/rows are kept; sparse decoration
        //     columns/rows are trimmed.
        $step = max(1, intval(min($W, $H) / 500));
        $colCount = [];   // structural-ink count per sampled x
        $rowCount = [];   // structural-ink count per sampled y
        $rawMinX = $W; $rawMinY = $H; $rawMaxX = 0; $rawMaxY = 0;
        $contentPixels = 0;
        for ($y = 0; $y < $H; $y += $step) {
            for ($x = 0; $x < $W; $x += $step) {
                $rgb = imagecolorat($img, $x, $y);
                $r = ($rgb >> 16) & 0xFF; $g = ($rgb >> 8) & 0xFF; $b = $rgb & 0xFF;
                $bright = ($r + $g + $b) / 3;
                $mx = max($r, $g, $b); $mn = min($r, $g, $b);
                $sat = $mx > 0 ? ($mx - $mn) / $mx : 0;
                // Structural ink = reasonably dark AND not strongly coloured.
                if ($bright < 210 && $sat < 0.30) {
                    $colCount[$x] = ($colCount[$x] ?? 0) + 1;
                    $rowCount[$y] = ($rowCount[$y] ?? 0) + 1;
                    $contentPixels++;
                    if ($x < $rawMinX) $rawMinX = $x;
                    if ($y < $rawMinY) $rawMinY = $y;
                    if ($x > $rawMaxX) $rawMaxX = $x;
                    if ($y > $rawMaxY) $rawMaxY = $y;
                }
            }
        }
        imagedestroy($img);

        if ($contentPixels < 20 || $rawMaxX <= $rawMinX || $rawMaxY <= $rawMinY) {
            // Nothing usable detected -> fall back to full frame.
            $minX = 0; $minY = 0; $maxX = $W - 1; $maxY = $H - 1;
        } else {
            // Density-trimmed bounds: keep columns/rows that carry a meaningful
            // share of the structural ink, drop sparse decoration/outliers.
            list($minX, $maxX) = self::densityBounds($colCount, 0.06);
            list($minY, $maxY) = self::densityBounds($rowCount, 0.06);
            if ($maxX <= $minX) { $minX = $rawMinX; $maxX = $rawMaxX; }
            if ($maxY <= $minY) { $minY = $rawMinY; $maxY = $rawMaxY; }
        }
        $boxW = $maxX - $minX;
        $boxH = $maxY - $minY;

        // ---- 2. Brahmasthan = centre of the trimmed STRUCTURAL bbox ----
        // (the 4 central squares of the 8x8 / 64-pad grid all share this centre)
        $brahmaX = intval($minX + $boxW / 2);
        $brahmaY = intval($minY + $boxH / 2);

        $facing = strtoupper($facing);
        $facingDeg = self::$directionDegrees[$facing] ?? 0;
        $diagonal = sqrt($boxW * $boxW + $boxH * $boxH);

        // ---- 3. Entry point + rotation ----
        $entryMarker = null;
        foreach ((array)$markers as $m) {
            if (($m['type'] ?? '') === 'entrance' && isset($m['nx'], $m['ny'])) {
                $entryMarker = $m;
                break;
            }
        }

        if ($entryMarker) {
            $ex = intval($entryMarker['nx'] * $W);
            $ey = intval($entryMarker['ny'] * $H);
            $entryBearing = self::bearing($ex - $brahmaX, $ey - $brahmaY);
            $rotation = self::norm360($entryBearing - $facingDeg);
            $entry = ['x' => $ex, 'y' => $ey, 'direction' => $facing, 'from_marker' => true];
        } else {
            // Fallback: entry on the top edge, rotation == old (360 - facingDeg)
            $entryBearing = 0;
            $rotation = self::norm360(360 - $facingDeg);
            $entry = ['x' => intval(($minX + $maxX) / 2), 'y' => $minY, 'direction' => $facing, 'from_marker' => false];
        }

        $geometry = [
            'image_width'  => $W,
            'image_height' => $H,
            'bbox' => ['minX' => $minX, 'minY' => $minY, 'maxX' => $maxX, 'maxY' => $maxY, 'w' => $boxW, 'h' => $boxH],
            'brahmasthan' => ['x' => $brahmaX, 'y' => $brahmaY],
            'entry' => $entry,
            'facing' => $facing,
            'facing_label' => self::$directionLabels[$facing] ?? $facing,
            'facing_deg' => $facingDeg,
            'rotation' => $rotation,        // clockwise degrees applied to chakra
            'diagonal' => $diagonal,
            'content_pixels' => $contentPixels,
        ];

        // ---- 4. Resolve each marker to pixel coords + compass zone ----
        $resolved = [];
        foreach ((array)$markers as $m) {
            if (!isset($m['nx'], $m['ny'])) continue;
            $px = intval($m['nx'] * $W);
            $py = intval($m['ny'] * $H);
            $resolved[] = [
                'type' => $m['type'] ?? 'unknown',
                'label' => $m['label'] ?? ($m['type'] ?? ''),
                'x' => $px,
                'y' => $py,
                'zone' => self::zoneFromPoint($px, $py, $geometry),
            ];
        }
        $geometry['markers'] = $resolved;

        return $geometry;
    }

    /** Image bearing (up=0, clockwise) of vector (dx,dy) where y is down. */
    private static function bearing($dx, $dy) {
        if ($dx == 0 && $dy == 0) return 0;
        $deg = rad2deg(atan2($dx, -$dy));
        return self::norm360($deg);
    }

    /**
     * Density-threshold bounds for a 1-D histogram [coord => ink_count].
     * Returns [min, max] of coordinates whose ink count is at least
     * $fraction of the peak count. This keeps dense structural lines (e.g. the
     * outer walls span the full height/width and therefore have high counts)
     * while discarding sparse decoration columns/rows (a plant on one edge, a
     * stray annotation), so the resulting centre tracks the real building.
     */
    private static function densityBounds($hist, $fraction) {
        if (empty($hist)) return [0, 0];
        $peak = max($hist);
        $thr = max(1, $peak * $fraction);
        $min = null; $max = null;
        foreach ($hist as $k => $v) {
            if ($v >= $thr) {
                if ($min === null || $k < $min) $min = $k;
                if ($max === null || $k > $max) $max = $k;
            }
        }
        if ($min === null) {
            $keys = array_keys($hist);
            return [min($keys), max($keys)];
        }
        return [$min, $max];
    }

    private static function norm360($d) {
        $d = fmod($d, 360);
        if ($d < 0) $d += 360;
        return $d;
    }

    /**
     * Map an image point to its true compass zone using the plan rotation.
     * Returns 'C' (central Brahmasthan) or one of the 8 directions.
     */
    public static function zoneFromPoint($px, $py, $geometry) {
        $brahma = $geometry['brahmasthan'];
        $dx = $px - $brahma['x'];
        $dy = $py - $brahma['y'];
        $dist = sqrt($dx * $dx + $dy * $dy);

        // Central Brahmasthan zone: within ~16% of the plan diagonal of centre
        if ($dist < ($geometry['diagonal'] * 0.16)) {
            return 'C';
        }

        $b = self::bearing($dx, $dy);
        $compass = self::norm360($b - $geometry['rotation']);
        $idx = intval(round($compass / 45)) % 8;
        return self::$dirOrder[$idx];
    }

    /** Back-compat alias. */
    public static function pixelToZone($px, $py, $geometry) {
        return self::zoneFromPoint($px, $py, $geometry);
    }

    /**
     * Render the professional overlay used in the report.
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
        $canvas = imagecreatetruecolor($W, $H);
        imagealphablending($canvas, true);
        imagecopy($canvas, $base, 0, 0, 0, 0, $W, $H);
        imagedestroy($base);

        $bbox = $geometry['bbox'];
        $brahma = $geometry['brahmasthan'];

        // ---- Chakra: rotate, scale to FIT FULLY inside the plan, composite ----
        $chakra = @imagecreatefrompng($chakraPath);
        if ($chakra) {
            $rotationDegrees = $geometry['rotation'] ?? 0;
            // GD imagerotate is counter-clockwise for positive angle -> negate for CW
            $rotated = imagerotate($chakra, -$rotationDegrees, imagecolorallocatealpha($chakra, 0, 0, 0, 127));
            imagesavealpha($rotated, true);
            imagedestroy($chakra);

            $cw = imagesx($rotated);
            $ch = imagesy($rotated);

            // Fit the WHOLE chakra within the smaller plan dimension so it is
            // always fully visible, then centre it on the Brahmasthan.
            $target = min($bbox['w'], $bbox['h']) * 0.98;
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

            // Strong, clearly-visible opacity (~80%). +27 alpha on opaque pixels.
            $semi = imagecreatetruecolor($nw, $nh);
            imagealphablending($semi, false);
            imagesavealpha($semi, true);
            imagefilledrectangle($semi, 0, 0, $nw, $nh, $tp);
            for ($x = 0; $x < $nw; $x++) {
                for ($y = 0; $y < $nh; $y++) {
                    $color = imagecolorat($scaled, $x, $y);
                    $a = ($color >> 24) & 0x7F;
                    if ($a >= 120) continue; // keep transparent background transparent
                    $r = ($color >> 16) & 0xFF;
                    $g = ($color >> 8) & 0xFF;
                    $b = $color & 0xFF;
                    $newA = min(127, intval($a + 27));
                    imagesetpixel($semi, $x, $y, imagecolorallocatealpha($semi, $r, $g, $b, $newA));
                }
            }
            imagedestroy($scaled);

            $posX = intval($brahma['x'] - $nw / 2);
            $posY = intval($brahma['y'] - $nh / 2);
            imagecopy($canvas, $semi, $posX, $posY, 0, 0, $nw, $nh);
            imagedestroy($semi);
        }

        // ---- Diagonal energy lines ----
        $lineCol = imagecolorallocatealpha($canvas, 200, 30, 30, 70);
        self::thickLine($canvas, $bbox['minX'], $bbox['minY'], $bbox['maxX'], $bbox['maxY'], $lineCol, 2);
        self::thickLine($canvas, $bbox['maxX'], $bbox['minY'], $bbox['minX'], $bbox['maxY'], $lineCol, 2);

        // ---- Marked elements (small amber dots + short code) ----
        if (!empty($geometry['markers'])) {
            $dotFill = imagecolorallocate($canvas, 212, 175, 55);
            $dotEdge = imagecolorallocate($canvas, 90, 70, 10);
            $txtCol  = imagecolorallocate($canvas, 30, 20, 0);
            foreach ($geometry['markers'] as $mk) {
                if (($mk['type'] ?? '') === 'entrance') continue; // entry drawn separately
                $mx = $mk['x']; $my = $mk['y'];
                imagefilledellipse($canvas, $mx, $my, 14, 14, $dotFill);
                imageellipse($canvas, $mx, $my, 14, 14, $dotEdge);
                $code = strtoupper(substr(str_replace(['_',' '], '', $mk['type']), 0, 3));
                imagestring($canvas, 1, $mx + 9, $my - 4, $code, $txtCol);
            }
        }

        // ---- Brahmasthan marker ----
        $bCol = imagecolorallocate($canvas, 200, 30, 30);
        $bFill = imagecolorallocatealpha($canvas, 212, 175, 55, 40);
        $rad = max(6, intval($geometry['diagonal'] * 0.02));
        imagefilledellipse($canvas, $brahma['x'], $brahma['y'], $rad * 2, $rad * 2, $bFill);
        imageellipse($canvas, $brahma['x'], $brahma['y'], $rad * 2, $rad * 2, $bCol);
        self::label($canvas, $brahma['x'], $brahma['y'] + $rad + 4, 'Brahmasthan', $bCol, true);

        // ---- Entry marker ----
        $entry = $geometry['entry'];
        $eCol = imagecolorallocate($canvas, 20, 130, 60);
        imagefilledellipse($canvas, $entry['x'], $entry['y'], 20, 20, $eCol);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagestring($canvas, 4, $entry['x'] - 4, $entry['y'] - 8, 'E', $white);

        // ---- Facing banner ----
        $label = $geometry['facing_label'] . ' Facing  -  Entry (E)  -  Brahmasthan at centre';
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

    private static function thickLine($img, $x1, $y1, $x2, $y2, $color, $thickness = 1) {
        for ($i = 0; $i < $thickness; $i++) {
            imageline($img, $x1, $y1 + $i, $x2, $y2 + $i, $color);
            imageline($img, $x1 + $i, $y1, $x2 + $i, $y2, $color);
        }
    }

    private static function label($img, $cx, $cy, $text, $color, $center = false) {
        $fs = 3;
        $tw = imagefontwidth($fs) * strlen($text);
        $tx = $center ? intval($cx - $tw / 2) : $cx;
        $bg = imagecolorallocatealpha($img, 255, 255, 255, 35);
        imagefilledrectangle($img, $tx - 3, $cy - 1, $tx + $tw + 3, $cy + imagefontheight($fs) + 1, $bg);
        imagestring($img, $fs, $tx, $cy, $text, $color);
    }
}
