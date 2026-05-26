<?php
// One-time banner optimization.
// Backs up originals to originals/, then writes WebP + optimized JPEG at MAX_W width.
// Run once with: php -d extension=gd optimize-banners.php
// Safe to re-run: skips re-processing files already optimized within the last hour.

const MAX_W       = 2400;   // 2× the 1280px max-width container — covers retina
const WEBP_Q      = 82;
const JPEG_Q      = 78;
const TARGET_DIR  = __DIR__ . '/../assets/images/banners';
const BACKUP_DIR  = __DIR__ . '/../assets/images/banners/originals';
const NAMES       = ['banner1', 'banner2', 'banner3'];

if (!extension_loaded('gd')) {
    fwrite(STDERR, "GD extension required. Run: php -d extension=gd optimize-banners.php\n");
    exit(1);
}

if (!is_dir(BACKUP_DIR)) {
    mkdir(BACKUP_DIR, 0755, true);
}

foreach (NAMES as $name) {
    $src     = TARGET_DIR . "/$name.png";
    $backup  = BACKUP_DIR . "/$name.png";
    $outWebp = TARGET_DIR . "/$name.webp";
    $outJpeg = TARGET_DIR . "/$name.jpg";

    if (!file_exists($src) && file_exists($backup)) {
        // Already migrated — re-derive from backup.
        $src = $backup;
    }
    if (!file_exists($src)) {
        echo "SKIP $name: source missing\n";
        continue;
    }

    // Backup once.
    if (!file_exists($backup) && $src !== $backup) {
        copy($src, $backup);
        echo "BACKUP $name.png -> originals/\n";
    }

    $img = @imagecreatefrompng($src);
    if (!$img) {
        fwrite(STDERR, "FAIL $name: imagecreatefrompng\n");
        continue;
    }

    $w = imagesx($img);
    $h = imagesy($img);
    $scale = ($w > MAX_W) ? MAX_W / $w : 1.0;
    $newW  = (int)round($w * $scale);
    $newH  = (int)round($h * $scale);

    if ($scale < 1.0) {
        $resized = imagecreatetruecolor($newW, $newH);
        imagecopyresampled($resized, $img, 0, 0, 0, 0, $newW, $newH, $w, $h);
        imagedestroy($img);
        $img = $resized;
    }

    // WebP (modern browsers — primary).
    imagewebp($img, $outWebp, WEBP_Q);

    // JPEG fallback (older browsers, email clients, social previews).
    imagejpeg($img, $outJpeg, JPEG_Q);

    imagedestroy($img);

    // Remove the original PNG from the live path — markup will point to .webp/.jpg.
    if ($src === TARGET_DIR . "/$name.png" && file_exists($backup)) {
        unlink($src);
        echo "REMOVE $name.png (kept in originals/)\n";
    }

    $sizeWebp = filesize($outWebp);
    $sizeJpeg = filesize($outJpeg);
    printf(
        "OK    %s  %dx%d  webp=%s  jpg=%s\n",
        $name, $newW, $newH,
        number_format($sizeWebp / 1024, 1) . 'KB',
        number_format($sizeJpeg / 1024, 1) . 'KB'
    );
}

echo "Done.\n";
