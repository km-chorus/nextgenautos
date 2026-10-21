<?php

declare(strict_types=1);

/**
 * Logic tests for the homepage hero (spec 10A): HomepageHeroService and the preload helper.
 * Builds its own throw-away project folder in the system temp directory (the real hero folder is
 * never touched). No database, no web server.
 * Run: php database/tests/homepage_hero_test.php
 */

require_once dirname(__DIR__, 2) . '/app/services/HomepageHeroService.php';
require_once dirname(__DIR__, 2) . '/app/helpers/public_ui.php';

$pass = 0;
$fail = 0;
$check = static function (string $name, bool $condition, string $detail = '') use (&$pass, &$fail): void {
    if ($condition) {
        $pass++;
        echo "  PASS  $name\n";
    } else {
        $fail++;
        echo "  FAIL  $name" . ($detail !== '' ? "  -> $detail" : '') . "\n";
    }
};

$root = sys_get_temp_dir() . '/hero_test_' . bin2hex(random_bytes(4));
$dir = $root . '/assets/images/hero';
mkdir($dir, 0777, true);
$webp = static function (string $file, int $w, int $h) use ($dir): void {
    $im = imagecreatetruecolor($w, $h);
    imagewebp($im, "$dir/$file", 60);
};
$config = static fn (array $slides): array => ['directory' => 'assets/images/hero', 'slides' => $slides];
$slide = static fn (int $id, string $focus = 'center', string $alt = ''): array => ['id' => $id, 'alt' => $alt, 'focus' => $focus];

echo "No approved images yet\n";
$service = new HomepageHeroService($root, $config([$slide(1), $slide(2), $slide(3), $slide(4)]));
$check('empty folder gives no slides (plain hero)', $service->slides() === []);
$realSlides = (new HomepageHeroService(dirname(__DIR__, 2), require dirname(__DIR__, 2) . '/app/config/homepage_hero.php'))->slides(0);
$check('the real project shows only images that exist (none is invented)', array_filter($realSlides, static fn (array $s): bool => !is_file(dirname(__DIR__, 2) . '/' . strtok($s['desktop']['url'], '?'))) === []);

echo "\nSlides\n";
$webp('hero-1-desktop.webp', 1600, 900);
$webp('hero-1-mobile.webp', 800, 1000);
$webp('hero-2-desktop.webp', 1600, 900);          // no mobile crop: desktop file is the fallback
$webp('hero-2-wide.webp', 2200, 1000);
$webp('hero-3-mobile.webp', 800, 1000);           // no desktop file: the slide is skipped
file_put_contents("$dir/hero-4-desktop.webp", "<?php echo 'x';");   // not an image: skipped
$service = new HomepageHeroService($root, $config([$slide(1, 'left', 'Alt one'), $slide(2, 'sideways'), $slide(3), $slide(4)]));
$slides = $service->slides(0);
$check('only valid slides are used (1 and 2)', count($slides) === 2 && array_column($slides, 'id') === [1, 2]);
$check('slide without desktop file skipped', !in_array(3, array_column($slides, 'id'), true));
$check('non-image file skipped', !in_array(4, array_column($slides, 'id'), true));
$check('dimensions read from the real file', $slides[0]['desktop']['width'] === 1600 && $slides[0]['desktop']['height'] === 900 && $slides[0]['mobile']['width'] === 800);
$check('missing mobile crop falls back to desktop', $slides[1]['mobile']['url'] === $slides[1]['desktop']['url']);
$check('wide version optional', $slides[0]['wide'] === null && $slides[1]['wide'] !== null && $slides[1]['wide']['width'] === 2200);
$check('alt text carried through', $slides[0]['alt'] === 'Alt one' && $slides[1]['alt'] === '');
$check('focus validated (unknown becomes center)', $slides[0]['focus'] === 'left' && $slides[1]['focus'] === 'center');
$check('urls are fixed-shape paths with a version', preg_match('#^/assets/images/hero/hero-[1-4]-(desktop|mobile|wide)\.webp\?v=\d+$#', $slides[0]['desktop']['url']) === 1);
$rot = $service->slides(1);
$check('start index rotates the order, nothing is dropped', array_column($rot, 'id') === [2, 1]);
$random = [];
for ($i = 0; $i < 40; $i++) {
    $random[(int) $service->slides()[0]['id']] = true;
}
$check('random start eventually shows each slide first', count($random) === 2);

echo "\nJPEG and PNG files\n";
$jpg = static function (string $file, int $w, int $h) use ($dir): void {
    $im = imagecreatetruecolor($w, $h);
    imagejpeg($im, "$dir/$file", 70);
};
$jpg('hero-3-desktop.jpg', 1200, 800);                                          // slide 3 now has a JPEG desktop file
file_put_contents("$dir/hero-4-desktop.jpg", "<?php echo 'x';");              // a script named .jpg
$jpgService = new HomepageHeroService($root, $config([$slide(1), $slide(2), $slide(3), $slide(4)]));
$byId = array_column($jpgService->slides(0), null, 'id');
$check('a JPEG desktop file makes a slide (real type checked)', isset($byId[3]) && str_ends_with(strtok($byId[3]['desktop']['url'], '?'), 'hero-3-desktop.jpg') && $byId[3]['desktop']['width'] === 1200);
$check('a script or any other file named .jpg is not an image and is skipped', !isset($byId[4]));
$jpg('hero-1-desktop.jpg', 800, 500);
$check('WebP is preferred when both a WebP and a JPEG exist', str_ends_with(strtok(array_column($jpgService->slides(0), null, 'id')[1]['desktop']['url'], '?'), 'hero-1-desktop.webp'));
$png = imagecreatetruecolor(300, 200);
imagepng($png, "$dir/hero-2-mobile.png");
$check('PNG is accepted for the optional mobile crop', str_ends_with(strtok(array_column($jpgService->slides(0), null, 'id')[2]['mobile']['url'], '?'), 'hero-2-mobile.png'));
rename("$dir/hero-1-desktop.webp", "$dir/hero-1-desktop.moved");
$check('without the WebP the JPEG is used', str_ends_with(strtok(array_column($jpgService->slides(0), null, 'id')[1]['desktop']['url'], '?'), 'hero-1-desktop.jpg'));
rename("$dir/hero-1-desktop.moved", "$dir/hero-1-desktop.webp");

echo "\nHostile configuration\n";
$bad = new HomepageHeroService($root, $config([['id' => 0], ['id' => 5], ['id' => '1/../../x'], ['id' => -1], ['alt' => 'no id']]));
$check('out-of-range or malformed ids are ignored', $bad->slides() === [] || array_column($bad->slides(), 'id') === [1]);
$many = new HomepageHeroService($root, $config([$slide(1), $slide(1), $slide(1), $slide(1), $slide(1), $slide(1)]));
$check('never more than 4 slides', count($many->slides(0)) <= 4);
$missing = new HomepageHeroService($root . '/nope', $config([$slide(1)]));
$check('missing directory is harmless', $missing->slides() === []);

echo "\nPreloads\n";
$p = public_hero_preloads($slides[1]);
$check('one preload per layout with media queries', count($p) === 2 && str_contains($p[0]['media'], 'max-width') && str_contains($p[1]['media'], 'min-width'));
$check('desktop preload carries a srcset when a wide image exists', $p[1]['srcset'] !== null && str_contains($p[1]['srcset'], '2200w'));
$check('no srcset without wide image', public_hero_preloads($slides[0])[1]['srcset'] === null);

// clean up the temporary folder
foreach (glob("$dir/*") ?: [] as $f) {
    unlink($f);
}
rmdir($dir);
rmdir($root . '/assets/images');
rmdir($root . '/assets');
rmdir($root);

echo "\nResult: $pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
