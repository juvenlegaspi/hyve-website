<?php

declare(strict_types=1);

$sourceDirectory = dirname(__DIR__).'/public/images';
$targetDirectory = $sourceDirectory.'/optimized';
$maxWidth = 1440;
$quality = 82;

if (! extension_loaded('gd') || ! function_exists('imagewebp')) {
    fwrite(STDERR, "GD with WebP support is required.\n");
    exit(1);
}

if (! is_dir($targetDirectory) && ! mkdir($targetDirectory, 0775, true) && ! is_dir($targetDirectory)) {
    fwrite(STDERR, "Unable to create {$targetDirectory}.\n");
    exit(1);
}

$sources = glob($sourceDirectory.'/*.png') ?: [];
$originalBytes = 0;
$optimizedBytes = 0;

foreach ($sources as $sourcePath) {
    $source = imagecreatefrompng($sourcePath);

    if ($source === false) {
        fwrite(STDERR, 'Skipped unreadable image: '.basename($sourcePath)."\n");
        continue;
    }

    $sourceWidth = imagesx($source);
    $sourceHeight = imagesy($source);
    $targetWidth = min($sourceWidth, $maxWidth);
    $targetHeight = (int) round($sourceHeight * ($targetWidth / $sourceWidth));
    $target = imagecreatetruecolor($targetWidth, $targetHeight);

    imagecopyresampled(
        $target,
        $source,
        0,
        0,
        0,
        0,
        $targetWidth,
        $targetHeight,
        $sourceWidth,
        $sourceHeight,
    );

    $targetPath = $targetDirectory.'/'.pathinfo($sourcePath, PATHINFO_FILENAME).'.webp';

    if (! imagewebp($target, $targetPath, $quality)) {
        fwrite(STDERR, 'Failed to optimize: '.basename($sourcePath)."\n");
        imagedestroy($target);
        imagedestroy($source);
        continue;
    }

    $originalBytes += filesize($sourcePath) ?: 0;
    $optimizedBytes += filesize($targetPath) ?: 0;
    imagedestroy($target);
    imagedestroy($source);
}

$savedPercent = $originalBytes > 0
    ? round((1 - ($optimizedBytes / $originalBytes)) * 100, 1)
    : 0;

printf(
    "Optimized %d images: %.2f MB -> %.2f MB (%s%% smaller).\n",
    count($sources),
    $originalBytes / 1048576,
    $optimizedBytes / 1048576,
    $savedPercent,
);
