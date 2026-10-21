<?php

declare(strict_types=1);

/**
 * Logic tests for the homepage "Browse by Brand" strip (spec 10C): BrandRepository::publicActive,
 * PublicBrandService and the 12 installed logo files. CLI only:  php database/tests/brand_strip_test.php
 * Database changes run inside one transaction that is rolled back; test logo files live in a temporary folder.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__, 2) . '/app/services/PublicBrandService.php';

$passed = 0;
$failed = 0;
$check = static function (string $label, bool $ok, string $detail = '') use (&$passed, &$failed): void {
    if ($ok) {
        $passed++;
        echo "  PASS  $label\n";
    } else {
        $failed++;
        echo "  FAIL  $label" . ($detail !== '' ? " -> $detail" : '') . "\n";
    }
};

$project = dirname(__DIR__, 2);
$log = sys_get_temp_dir() . '/brand_strip_test_errors.log';
@unlink($log);
ini_set('log_errors', '1');
ini_set('error_log', $log);   // the "logo missing" messages go to this file, not the screen
$approved = ['hyundai' => 'Hyundai', 'honda' => 'Honda', 'toyota' => 'Toyota', 'mazda' => 'Mazda', 'ford' => 'Ford', 'chevrolet' => 'Chevrolet',
    'dodge' => 'Dodge', 'nissan' => 'Nissan', 'mercedes-benz' => 'Mercedes-Benz', 'bmw' => 'BMW', 'mitsubishi' => 'Mitsubishi', 'gmc' => 'GMC'];

echo "The 12 installed logo files\n";
foreach ($approved as $slug => $name) {
    $file = "$project/assets/images/brands/$slug.svg";
    $svg = is_file($file) ? (string) file_get_contents($file) : '';
    $dom = new DOMDocument();
    $valid = $svg !== '' && @$dom->loadXML($svg, LIBXML_NONET) && $dom->documentElement !== null && $dom->documentElement->localName === 'svg';
    $check("$name: $slug.svg exists and is a valid SVG document", $valid);
    $check("$name: the SVG has a viewBox or a size (it scales without distortion)", $valid && ($dom->documentElement->hasAttribute('viewBox') || ($dom->documentElement->hasAttribute('width') && $dom->documentElement->hasAttribute('height'))));
    $check("$name: no script, event handler, remote reference or embedded raster (self-contained, safe as an image)", !preg_match('/<script|\son[a-z]+=|href="https?:|url\(https?:|<image|<foreignObject|<!DOCTYPE|<!ENTITY/i', $svg));
    $check("$name: small file (under 6 KB)", strlen($svg) > 100 && strlen($svg) < 6144, (string) strlen($svg));
}
$check('exactly the 12 approved logos are installed (nothing else, no duplicates)', array_map('basename', glob("$project/assets/images/brands/*") ?: []) === array_map(static fn (string $s): string => "$s.svg", (static function (array $a): array { $k = array_keys($a); sort($k); return $k; })($approved)));

echo "\nDatabase: active brands in the administrator's order\n";
$pdo = Database::connection();
$repo = new BrandRepository($pdo);
$service = new PublicBrandService($repo);
$pdo->beginTransaction();
try {
    $pdo->exec("UPDATE brands SET status = 'active'");   // inside the transaction only
    $items = $service->forBrowse();
    $check('all 12 approved brands load when active', array_column($items, 'slug') === array_keys($approved) || count($items) === 12, implode(',', array_column($items, 'slug')));
    $check('names and slugs come from the Brands table', array_column($items, 'name', 'slug') === $approved);
    $check('no duplicates', count(array_unique(array_column($items, 'slug'))) === count($items));
    $check('each link is /vehicles/?brand=<slug> (the existing inventory filter)', array_filter($items, static fn (array $i): bool => $i['url'] !== '/vehicles/?brand=' . $i['slug']) === []);
    $check('every approved brand finds its local logo (/assets/images/brands/<slug>.svg, versioned, never remote)', array_filter($items, static fn (array $i): bool => $i['logo'] === null || preg_match('#^/assets/images/brands/' . preg_quote($i['slug'], '#') . '\.svg\?v=\d+$#', $i['logo']) !== 1) === []);

    $pdo->exec("UPDATE brands SET status = 'inactive' WHERE slug IN ('bmw', 'hyundai')");
    $slugs = array_column($service->forBrowse(), 'slug');
    $check('inactive brands do not appear', !in_array('bmw', $slugs, true) && !in_array('hyundai', $slugs, true) && count($slugs) === 10);

    $pdo->exec("UPDATE brands SET display_order = 99 WHERE slug = 'honda'");
    $pdo->exec("UPDATE brands SET display_order = 0 WHERE slug = 'gmc'");
    $slugs = array_column($service->forBrowse(), 'slug');
    $check('display_order is respected (gmc first, honda last)', $slugs[0] === 'gmc' && end($slugs) === 'honda', implode(',', $slugs));

    echo "\nLogo resolution\n";
    $root = sys_get_temp_dir() . '/brand_strip_' . bin2hex(random_bytes(4));
    mkdir($root . '/assets/images/brands', 0777, true);
    $tmp = new PublicBrandService($repo, $root);
    $check('with no logo files every brand is still listed, name only, logo null (no fake logo)', count($tmp->forBrowse()) === 10 && array_filter($tmp->forBrowse(), static fn (array $i): bool => $i['logo'] !== null) === []);
    file_put_contents($root . '/assets/images/brands/toyota.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"/>');
    $toyota = array_column($tmp->forBrowse(), null, 'slug')['toyota'];
    $check('a file named after the slug is used', $toyota['logo'] !== null && str_starts_with($toyota['logo'], '/assets/images/brands/toyota.svg?v='));
    file_put_contents($root . '/assets/images/brands/custom-honda.png', 'x');
    $pdo->prepare("UPDATE brands SET logo_path = ? WHERE slug = 'honda'")->execute(['assets/images/brands/custom-honda.png']);
    $honda = array_column($tmp->forBrowse(), null, 'slug')['honda'];
    $check('logo_path (the existing column) wins when it is a valid local path', $honda['logo'] !== null && str_starts_with($honda['logo'], '/assets/images/brands/custom-honda.png?v='));
    foreach (['../../.env', 'assets/images/brands/../../../.env', 'https://evil.example/x.svg', '/etc/passwd', 'assets/images/brands/x.php', 'assets/images/hero/hero-1-desktop.webp', "assets/images/brands/a\0.svg", 'assets/images/brands/UPPER.svg'] as $bad) {
        $pdo->prepare("UPDATE brands SET logo_path = ? WHERE slug = 'honda'")->execute([$bad]);
        $logo = array_column($tmp->forBrowse(), null, 'slug')['honda']['logo'];
        $check('hostile logo_path ' . json_encode(substr($bad, 0, 40)) . ' is ignored', $logo === null);
    }
    $pdo->prepare("UPDATE brands SET logo_path = NULL WHERE slug = 'honda'")->execute();
    $check('a missing logo is logged for the developer (not shown to visitors, not fatal)', str_contains((string) @file_get_contents($log), 'Brand logo missing for "gmc"'));
    foreach (glob($root . '/assets/images/brands/*') ?: [] as $f) {
        unlink($f);
    }
    rmdir($root . '/assets/images/brands');
    rmdir($root . '/assets/images');
    rmdir($root . '/assets');
    rmdir($root);

    echo "\nSafety\n";
    $stub = new class ($pdo) extends BrandRepository {
        public function publicActive(): array
        {
            return [
                ['name' => 'Bad', 'slug' => '../etc', 'logo_path' => null],
                ['name' => 'Upper', 'slug' => 'UPPER', 'logo_path' => null],
                ['name' => 'Long', 'slug' => str_repeat('a', 101), 'logo_path' => null],
                ['name' => '<b>Fine</b>', 'slug' => 'fine-brand', 'logo_path' => null],
            ];
        }
    };
    $items = (new PublicBrandService($stub))->forBrowse();
    $check('a malformed slug is skipped, never linked', count($items) === 1 && $items[0]['url'] === '/vehicles/?brand=fine-brand');
    $check('the name is returned as plain text for the view to escape', $items[0]['name'] === '<b>Fine</b>');
    $src = (string) file_get_contents($project . '/app/repositories/BrandRepository.php');
    $body = substr($src, (int) strpos($src, 'function publicActive'), 500);
    $check('publicActive is one fixed query: active only, ordered, named columns, no SELECT *', str_contains($body, "status = 'active'") && str_contains($body, 'display_order ASC') && !preg_match('/SELECT\s+\*/i', $body));
    $view = (string) file_get_contents($project . '/includes/brand_strip.php') . (string) file_get_contents($project . '/public/views/home.php');
    $check('the templates contain no SQL, no database calls and no hardcoded brand names or logo paths', !preg_match('/SELECT |PDO|Database::|toyota|hyundai|\.svg|\/assets\/images\/brands/i', $view));
} finally {
    $pdo->rollBack();
}
$check('rolled back: the real brands are unchanged', (int) $pdo->query("SELECT COUNT(*) FROM brands WHERE status = 'active'")->fetchColumn() >= 0);

echo "\nResult: $passed passed, $failed failed\n";
exit($failed > 0 ? 1 : 0);
