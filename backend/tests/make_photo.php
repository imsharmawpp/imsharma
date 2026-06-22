<?php
// Generate a colourful "photo-like" image (simulates the honey box photo)
$dir = __DIR__ . '/fixtures';
if (!is_dir($dir)) mkdir($dir, 0755, true);

$W = 600; $H = 800;
$img = imagecreatetruecolor($W, $H);

// Gradient background (warm browns/golds - like a honey box photo)
for ($y = 0; $y < $H; $y++) {
    $r = intval(180 + 50 * sin($y / 80));
    $g = intval(120 + 60 * sin($y / 100));
    $b = intval(40 + 30 * cos($y / 90));
    $col = imagecolorallocate($img, min(255,$r), min(255,$g), min(255,$b));
    imageline($img, 0, $y, $W, $y, $col);
}
// A central "object" with rich colors and soft edges
for ($i = 0; $i < 200; $i++) {
    $r = rand(120, 220); $g = rand(80, 160); $b = rand(20, 90);
    $col = imagecolorallocate($img, $r, $g, $b);
    imagefilledellipse($img, rand(150,450), rand(250,550), rand(40,120), rand(40,120), $col);
}
imagepng($img, $dir . '/photo_honeybox.png');
imagedestroy($img);
echo "Created photo_honeybox.png\n";
