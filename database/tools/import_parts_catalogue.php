<?php

declare(strict_types=1);

/**
 * One-off import (spec 27): the 50 part types from the client's picture become parts in Admin > Parts. CLI only:
 *   php database/tools/import_parts_catalogue.php            (import)
 *   php database/tools/import_parts_catalogue.php --dry-run  (only report what would happen)
 *
 * Run once per environment, after migration 012. Each part is created published, with no price ("Price on request"),
 * condition, stock, part number or compatible vehicles; its picture goes through the normal image pipeline (validation,
 * WebP, hard size limits, read-back check) and becomes the main photo in uploads/parts/. A part whose web address (slug)
 * already exists, archived parts included, is skipped: running the import again changes nothing. Every created part is
 * written to the audit log (PART_IMPORTED).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__, 2);
require_once $root . '/app/services/PartService.php';

$dryRun = in_array('--dry-run', $argv, true);
$folder = $root . '/database/import/parts-catalogue';
$parts = require $folder . '/parts.php';

if (!ImageService::isAvailable()) {
    fwrite(STDERR, 'Image processing is not available: ' . implode(', ', ImageService::missingRequirements()) . "\n");
    exit(1);
}

$pdo = Database::connection();
$service = PartService::create();
$repo = new PartRepository($pdo);
$created = 0;
$skipped = 0;
$failed = 0;
foreach ($parts as [$slug, $name, $category, $image]) {
    $picture = $image === null ? null : $folder . '/' . basename($image);
    if ($picture !== null && !is_file($picture)) {
        echo "  FAILED   $slug: picture missing\n";
        $failed++;
        continue;
    }
    if ($dryRun) {
        $exists = $repo->slugTaken($slug);
        echo '  ' . ($exists ? 'SKIP     ' : 'CREATE   ') . $slug . "\n";
        $exists ? $skipped++ : $created++;
        continue;
    }
    try {
        $outcome = $service->importCatalogPart($slug, $name, $category, $picture, '127.0.0.1', 'cli import_parts_catalogue');
    } catch (Throwable $e) {
        echo "  FAILED   $slug: " . $e->getMessage() . "\n";
        $failed++;
        continue;
    }
    echo '  ' . ($outcome === 'created' ? 'CREATED  ' : 'SKIPPED  ') . $slug . "\n";
    $outcome === 'created' ? $created++ : $skipped++;
}

echo "\n" . ($dryRun ? 'Dry run: ' : '') . "$created created, $skipped skipped (already there), $failed failed\n";
exit($failed > 0 ? 1 : 0);
