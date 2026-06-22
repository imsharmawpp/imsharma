<?php
/**
 * Generate synthetic floor-plan test images to validate the analyzer.
 * Produces clean, digital-style plans (white bg, black walls, door gaps).
 */

$dir = __DIR__ . '/fixtures';
if (!is_dir($dir)) mkdir($dir, 0755, true);

/** Helper: thick rectangle outline (walls). */
function wall($img, $x1, $y1, $x2, $y2, $color, $t = 4) {
    for ($i = 0; $i < $t; $i++) {
        imagerectangle($img, $x1 + $i, $y1 + $i, $x2 - $i, $y2 - $i, $color);
    }
}

/**
 * Build a rectangular house plan with rooms and a door gap.
 * $doorSide: 'top'|'bottom'|'left'|'right' — where the entrance gap is.
 */
function makeHousePlan($file, $W, $H, $doorSide = 'bottom') {
    $img = imagecreatetruecolor($W, $H);
    $white = imagecolorallocate($img, 255, 255, 255);
    $black = imagecolorallocate($img, 20, 20, 20);
    $gray  = imagecolorallocate($img, 90, 90, 90);
    imagefill($img, 0, 0, $white);

    // Outer walls with margin (thick walls like real CAD plans)
    $m = intval($W * 0.12);
    $x1 = $m; $y1 = $m; $x2 = $W - $m; $y2 = $H - $m;
    wall($img, $x1, $y1, $x2, $y2, $black, 10);

    // Internal partitions (rooms) - thick walls
    $midX = intval(($x1 + $x2) / 2);
    $midY = intval(($y1 + $y2) / 2);
    for ($t = 0; $t < 6; $t++) {
        imageline($img, $midX + $t, $y1, $midX + $t, $midY, $black);
        imageline($img, $x1, $midY + $t, $x2, $midY + $t, $black);
        imageline($img, $midX + $t, $midY, $midX + $t, $y2, $black);
    }

    // Extra sub-divisions for realistic room density
    $qX = intval(($x1 + $midX) / 2);
    $qY = intval(($midY + $y2) / 2);
    for ($t = 0; $t < 4; $t++) {
        imageline($img, $x1, $qY + $t, $midX, $qY + $t, $gray);
        imageline($img, $qX + $t, $y1, $qX + $t, $midY, $gray);
    }

    // Dimension lines around the perimeter (common in plans)
    imageline($img, $x1, $y1 - 20, $x2, $y1 - 20, $gray);
    imageline($img, $x1 - 20, $y1, $x1 - 20, $y2, $gray);

    // Room labels
    imagestring($img, 3, $x1 + 15, $y1 + 15, 'BEDROOM', $black);
    imagestring($img, 3, $midX + 15, $y1 + 15, 'KITCHEN', $black);
    imagestring($img, 3, $x1 + 15, $midY + 15, 'LIVING', $black);
    imagestring($img, 3, $midX + 15, $midY + 15, 'POOJA', $black);

    // Door gap (white over the wall) + arc
    $gap = intval($W * 0.10);
    switch ($doorSide) {
        case 'top':
            imagefilledrectangle($img, $midX - $gap/2, $y1 - 2, $midX + $gap/2, $y1 + 6, $white);
            imagearc($img, $midX, $y1, $gap, $gap, 0, 90, $gray);
            break;
        case 'bottom':
            imagefilledrectangle($img, $midX - $gap/2, $y2 - 6, $midX + $gap/2, $y2 + 2, $white);
            imagearc($img, $midX, $y2, $gap, $gap, 180, 270, $gray);
            break;
        case 'left':
            imagefilledrectangle($img, $x1 - 2, $midY - $gap/2, $x1 + 6, $midY + $gap/2, $white);
            imagearc($img, $x1, $midY, $gap, $gap, 270, 360, $gray);
            break;
        case 'right':
            imagefilledrectangle($img, $x2 - 6, $midY - $gap/2, $x2 + 2, $midY + $gap/2, $white);
            imagearc($img, $x2, $midY, $gap, $gap, 90, 180, $gray);
            break;
    }

    imagepng($img, $file);
    imagedestroy($img);
    echo "Created: $file ({$W}x{$H}, door=$doorSide)\n";
}

// Square plan, door at bottom (South entrance when North up)
makeHousePlan($dir . '/plan_square_south.png', 800, 800, 'bottom');
// Wide plan, door at right (East entrance)
makeHousePlan($dir . '/plan_wide_east.png', 1000, 700, 'right');
// Tall plan with offset content (margins uneven) door top (North)
makeHousePlan($dir . '/plan_tall_north.png', 700, 1000, 'top');

// A plan that is small/offset within a larger white canvas (tests bbox detection)
$img = imagecreatetruecolor(1200, 1200);
$white = imagecolorallocate($img, 255, 255, 255);
$black = imagecolorallocate($img, 20, 20, 20);
imagefill($img, 0, 0, $white);
// content only in top-left quadrant
wall($img, 150, 150, 550, 500, $black, 5);
imagestring($img, 3, 170, 170, 'OFFICE PLAN', $black);
imageline($img, 350, 150, 350, 500, $black);
imagepng($img, $dir . '/plan_offset.png');
imagedestroy($img);
echo "Created: {$dir}/plan_offset.png (offset content)\n";

echo "\nAll fixtures generated in: $dir\n";
