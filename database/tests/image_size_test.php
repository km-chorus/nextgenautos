<?php

declare(strict_types=1);

/**
 * Image size limits are enforced on the server (owner requirement 2026-10-26: images must be compressed before they reach
 * the server's storage, and the backend must verify it; the browser is never trusted). CLI only:
 *   php -d memory_limit=512M database/tests/image_size_test.php
 *
 * 1. Worst-case pictures (large camera photos, noise that barely compresses) go straight into ImageService for every kind
 *    with NO browser compression: every stored file must be a real WebP within its byte and dimension limits.
 * 2. The read-back verification refuses an oversized or fake file.
 * 3. The upload validator refuses files over 10 MB before any decoding.
 * 4. Every image already stored in uploads/ is within the limits (read-only report).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__, 2);
require_once $root . '/app/services/ImageService.php';
require_once $root . '/app/validation/ImageValidator.php';

$passed = 0;
$failed = 0;
$check = static function (string $label, bool $ok, string $detail = '') use (&$passed, &$failed): void {
    $ok ? $passed++ : $failed++;
    echo '  ' . ($ok ? 'PASS' : 'FAIL') . "  $label" . (!$ok && $detail !== '' ? " -> $detail" : '') . "\n";
};

$work = sys_get_temp_dir() . '/img_size_test_' . bin2hex(random_bytes(4));
mkdir($work);
$make = static function (int $w, int $h, string $style) use ($work): string {
    $im = imagecreatetruecolor($w, $h);
    mt_srand(7);
    if ($style === 'noise') {
        for ($y = 0; $y < $h; $y += 2) {
            for ($x = 0; $x < $w; $x += 2) {
                imagefilledrectangle($im, $x, $y, $x + 1, $y + 1, mt_rand(0, 0xFFFFFF));
            }
        }
    } else {
        for ($i = 0; $i < 3000; $i++) {
            imageline($im, mt_rand(0, $w), mt_rand(0, $h), mt_rand(0, $w), mt_rand(0, $h), mt_rand(0, 0xFFFFFF));
        }
    }
    $path = "$work/src-$style-{$w}x$h.jpg";
    imagejpeg($im, $path, 95);

    return $path;
};
$sources = ['noise 1600x1200' => $make(1600, 1200, 'noise'), 'detail 4000x3000' => $make(4000, 3000, 'detail'), 'noise 1200x1600' => $make(1200, 1600, 'noise')];

echo "Worst-case uploads, no browser compression\n";
foreach ([ImageService::KIND_VEHICLE, ImageService::KIND_TEAM, ImageService::KIND_PART] as $kind) {
    $limits = ImageService::limits($kind);
    $dir = "$work/$kind";
    mkdir($dir);
    foreach ($sources as $label => $src) {
        try {
            $r = (new ImageService($dir, $kind))->process($src);
        } catch (Throwable $e) {
            $check("$kind, $label: processed", false, $e->getMessage());
            continue;
        }
        $main = $dir . '/' . basename($r['path']);
        $thumb = $dir . '/' . basename($r['thumb']);
        $mi = getimagesize($main);
        $ti = getimagesize($thumb);
        $ok = filesize($main) <= $limits['target'] && filesize($thumb) <= $limits['thumb_target']
            && $mi[2] === IMAGETYPE_WEBP && $ti[2] === IMAGETYPE_WEBP
            && $mi[0] <= $limits['box'][0] && $mi[1] <= $limits['box'][1] && $ti[0] <= $limits['thumb'][0] && $ti[1] <= $limits['thumb'][1]
            && $r['bytes'] === filesize($main);
        $check(sprintf('%s, %s (%d KB source): main %d KB <= %d KB, thumb %d KB <= %d KB, WebP, within the box', $kind, $label, filesize($src) / 1024,
            filesize($main) / 1024, $limits['target'] / 1024, filesize($thumb) / 1024, $limits['thumb_target'] / 1024), $ok);
    }
    $check("$kind: nothing but the stored WebP pairs is left in the folder (the original is never kept)",
        count(glob("$dir/*")) === 2 * count($sources) && glob("$dir/*.jpg") === []);
}

echo "\nRead-back verification\n";
$verify = new ReflectionMethod(ImageService::class, 'verifyStored');
$big = "$work/big.webp";
copy("$work/" . ImageService::KIND_VEHICLE . '/' . basename(glob("$work/vehicle/*[0-9a-f].webp")[0]), $big);
$throws = static function (callable $fn): bool {
    try {
        $fn();
    } catch (RuntimeException) {
        return true;
    }

    return false;
};
$check('a file over the byte ceiling is refused', $throws(static fn () => $verify->invoke(null, $big, 1000, [1600, 1600])));
$check('a file larger than its box is refused', $throws(static fn () => $verify->invoke(null, $big, 10000000, [100, 100])));
file_put_contents("$work/fake.webp", 'RIFF0000WEBP<?php echo 1;');
$check('a fake WebP is refused', $throws(static fn () => $verify->invoke(null, "$work/fake.webp", 10000000, [1600, 1600])));
$check('a good file passes', !$throws(static fn () => $verify->invoke(null, $big, 716800, [1600, 1600])));

echo "\nUpload limit before decoding\n";
$huge = "$work/huge.jpg";
copy($sources['noise 1600x1200'], $huge);
$fh = fopen($huge, 'ab');
fwrite($fh, str_repeat("\0", ImageValidator::MAX_BYTES));
fclose($fh);
$v = ImageValidator::inspect($huge);
$check('a file over 10 MB is refused by the validator (never decoded)', $v['ok'] === false && $v['reason'] === 'too_large');

echo "\nImages already stored in uploads/ (read-only)\n";
foreach ([ImageService::KIND_VEHICLE => 'vehicles', ImageService::KIND_TEAM => 'team', ImageService::KIND_PART => 'parts'] as $kind => $folder) {
    $limits = ImageService::limits($kind);
    $over = [];
    $files = glob("$root/uploads/$folder/*.webp") ?: [];
    foreach ($files as $file) {
        $isThumb = str_ends_with($file, '-t.webp');
        $max = $isThumb ? $limits['thumb_target'] : $limits['target'];
        $info = @getimagesize($file);
        if (filesize($file) > $max || $info === false || $info[2] !== IMAGETYPE_WEBP) {
            $over[] = basename($file) . ' (' . (int) (filesize($file) / 1024) . ' KB)';
        }
    }
    $total = array_sum(array_map('filesize', $files));
    $check(sprintf('uploads/%s: %d files, %d KB in total, all WebP within limits', $folder, count($files), $total / 1024), $over === [], implode(', ', $over));
}

foreach (array_merge(glob("$work/*/*") ?: [], glob("$work/*") ?: []) as $f) {
    is_dir($f) ? null : unlink($f);
}
foreach (glob("$work/*", GLOB_ONLYDIR) ?: [] as $d) {
    rmdir($d);
}
rmdir($work);

echo "\nResult: $passed passed, $failed failed\n";
exit($failed > 0 ? 1 : 0);
