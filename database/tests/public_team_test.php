<?php

declare(strict_types=1);

/**
 * Logic tests for the homepage team (spec 11C): TeamRepository::publicActive and PublicTeamService. CLI only:
 *   php database/tests/public_team_test.php
 * Database rows are created inside one transaction that is rolled back (the real team is never changed); photo
 * files live in a temporary folder.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__, 2) . '/app/services/PublicTeamService.php';

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

$pdo = Database::connection();
$repo = new TeamRepository($pdo);
$root = sys_get_temp_dir() . '/pt_test_' . bin2hex(random_bytes(4));
mkdir($root . '/uploads/team', 0777, true);
$service = new PublicTeamService($repo, $root);
$mine = static fn (array $members): array => array_values(array_filter($members, static fn (array $m): bool => str_starts_with($m['name'], 'TESTDATA')));
$webp = static function (string $file, int $w, int $h) use ($root): void {
    $im = imagecreatetruecolor($w, $h);
    imagewebp($im, $root . '/' . $file, 60);
};
$add = static function (string $name, array $o = []) use ($pdo): void {
    $o += ['position' => 'Role', 'category' => 'sales', 'bio' => null, 'photo' => null, 'order' => 50, 'status' => 'active'];
    $pdo->prepare('INSERT INTO team_members (name, position, category, biography, photo_path, display_order, status) VALUES (?,?,?,?,?,?,?)')
        ->execute([$name, $o['position'], $o['category'], $o['bio'], $o['photo'], $o['order'], $o['status']]);
};

$pdo->beginTransaction();
$pdo->exec("UPDATE team_members SET status = 'inactive' WHERE name NOT LIKE 'TESTDATA%'");   // inside the rolled-back transaction: real members never push test members past the 12-member limit
try {
    echo "Who is shown\n";
    $add('TESTDATA Active B', ['order' => 30]);
    $add('TESTDATA Active A', ['order' => 30]);
    $add('TESTDATA First', ['order' => 0]);
    $add('TESTDATA Inactive', ['status' => 'inactive', 'order' => 1]);
    $all = $mine($service->forHomepage(12));
    $check('only active members are shown (the inactive one is not)', !in_array('TESTDATA Inactive', array_column($all, 'name'), true) && count($all) === 3);
    $check("the administrator's display order is respected (order 0 first; ties by name)", array_column($all, 'name') === ['TESTDATA First', 'TESTDATA Active A', 'TESTDATA Active B'], implode('|', array_column($all, 'name')));
    $check('the homepage limit is respected (default 4, never more); 0 and 99 are clamped', count($service->forHomepage()) <= 4 && count($service->forHomepage(0)) === 1 && count($service->forHomepage(99)) <= 12);
    $activeTotal = (int) $pdo->query("SELECT COUNT(*) FROM team_members WHERE status = 'active'")->fetchColumn();
    $check('fewer members than the limit shows just those: nothing is padded or invented', count($service->forHomepage(12)) === min(12, $activeTotal));
    $check('only customer-facing fields leave the service (name, position, category name, bio, photo; no id, status, timestamps)', array_keys($all[0]) === ['name', 'position', 'category', 'bio', 'photo', 'alt']);

    echo "\nText\n";
    $add('TESTDATA Long Bio', ['bio' => str_repeat('Word ', 80), 'order' => 5, 'position' => 'Director']);
    $add('TESTDATA Short Bio', ['bio' => "  Short  \n  text  ", 'order' => 6]);
    $add('TESTDATA No Bio', ['bio' => '   ', 'order' => 7]);
    $add('TESTDATA <b>Markup</b>', ['bio' => '<script>alert(1)</script>', 'position' => '"><img src=x>', 'order' => 8]);
    $m = array_column($mine($service->forHomepage(12)), null, 'name');
    $long = $m['TESTDATA Long Bio']['bio'];
    $check('a long biography is shortened at a word boundary with an ellipsis (about 150 characters)', mb_strlen($long) <= PublicTeamService::BIO_LENGTH + 1 && str_ends_with($long, '…') && !str_contains($long, 'Wor…'), $long);
    $check('the stored biography is not changed by shortening', mb_strlen((string) $pdo->query("SELECT biography FROM team_members WHERE name = 'TESTDATA Long Bio'")->fetchColumn()) === 400);
    $check('whitespace is tidied; a blank biography becomes null (no empty line)', $m['TESTDATA Short Bio']['bio'] === 'Short text' && $m['TESTDATA No Bio']['bio'] === null);
    $check('markup in name, position and biography is returned as plain text for the view to escape', $m['TESTDATA <b>Markup</b>']['bio'] === '<script>alert(1)</script>' && $m['TESTDATA <b>Markup</b>']['position'] === '"><img src=x>');
    $check('the photo alt text is "Name — Position"', $m['TESTDATA Long Bio']['alt'] === 'TESTDATA Long Bio — Director');

    echo "\nCategory\n";
    $add('TESTDATA Cat Leadership', ['category' => 'leadership', 'order' => 2]);
    $add('TESTDATA Cat Other', ['category' => 'other', 'order' => 3]);
    $c = array_column($mine($service->forHomepage(12)), null, 'name');
    $check('the category shows its Team Management name (leadership is "CEO / Leadership", sales is "Sales")', $c['TESTDATA Cat Leadership']['category'] === 'CEO / Leadership' && $c['TESTDATA Active A']['category'] === 'Sales', (string) $c['TESTDATA Cat Leadership']['category']);
    $check('the category "other" is not shown to visitors (null)', $c['TESTDATA Cat Other']['category'] === null);
    $pdo->exec("DELETE FROM team_members WHERE name LIKE 'TESTDATA Cat%'");   // keep the 12-row window free for the photo cases

    echo "\nPhotos\n";
    $hex = bin2hex(random_bytes(16));
    $webp("uploads/team/$hex-t.webp", 400, 533);
    $webp("uploads/team/$hex.webp", 1200, 1600);
    $add('TESTDATA Photo OK', ['photo' => "uploads/team/$hex.webp", 'order' => 10]);
    $hexMissing = bin2hex(random_bytes(16));
    $add('TESTDATA Photo Missing', ['photo' => "uploads/team/$hexMissing.webp", 'order' => 11]);
    $add('TESTDATA Photo Traversal', ['photo' => 'uploads/team/../../.env', 'order' => 12]);
    $add('TESTDATA Photo Remote', ['photo' => 'https://evil.example/x.webp', 'order' => 13]);
    $add('TESTDATA Photo Vehicles Folder', ['photo' => 'uploads/vehicles/' . $hex . '.webp', 'order' => 14]);
    $fake = bin2hex(random_bytes(16));
    file_put_contents($root . "/uploads/team/$fake-t.webp", '<?php echo 1;');
    $add('TESTDATA Photo Fake', ['photo' => "uploads/team/$fake.webp", 'order' => 15]);
    $m = array_column($mine($service->forHomepage(12)), null, 'name');
    $check('a real photo: the stored 400 px thumbnail with its real dimensions (not the large original)', $m['TESTDATA Photo OK']['photo'] === ['url' => "/uploads/team/$hex-t.webp", 'width' => 400, 'height' => 533]);
    $check('a missing file gives no photo (neutral panel)', $m['TESTDATA Photo Missing']['photo'] === null);
    $check('a path with ".." gives no photo', $m['TESTDATA Photo Traversal']['photo'] === null);
    $check('a remote URL in the database gives no photo', $m['TESTDATA Photo Remote']['photo'] === null);
    $check('a path outside uploads/team gives no photo', $m['TESTDATA Photo Vehicles Folder']['photo'] === null);
    $check('a file that is not a real WebP gives no photo', $m['TESTDATA Photo Fake']['photo'] === null);
    $check('a member with no photo at all gets none (nothing invented)', $m['TESTDATA Short Bio']['photo'] === null);

    echo "\nSafety\n";
    $selects = static fn (): int => (int) $pdo->query("SHOW SESSION STATUS LIKE 'Com_select'")->fetch(PDO::FETCH_NUM)[1];
    $before = $selects();
    $service->forHomepage(4);
    $check('exactly one SELECT query for the whole section', $selects() - $before === 1, (string) ($selects() - $before));
    $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/repositories/TeamRepository.php');
    $body = substr($src, (int) strpos($src, 'function publicActive'), 420);
    $check('publicActive: prepared statement, bound LIMIT, active only, no SELECT *', str_contains($body, ':limit') && str_contains($body, 'bindValue') && str_contains($body, "status = 'active'") && !preg_match('/SELECT\s+\*/i', $body));
    $check('publicActive selects only name, position, category, biography and photo', str_contains($body, 'SELECT name, position, category, biography, photo_path FROM team_members'));
    $view = (string) file_get_contents(dirname(__DIR__, 2) . '/includes/about.php') . (string) file_get_contents(dirname(__DIR__, 2) . '/public/views/home.php');
    $check('the templates contain no SQL, database calls or hardcoded people', !preg_match('/SELECT |PDO|Database::|GODFRED|ANTWI/i', $view));
} finally {
    $pdo->rollBack();
    foreach (glob($root . '/uploads/team/*') ?: [] as $f) {
        unlink($f);
    }
    rmdir($root . '/uploads/team');
    rmdir($root . '/uploads');
    rmdir($root);
}
$check('rolled back: no test member remains', (int) $pdo->query("SELECT COUNT(*) FROM team_members WHERE name LIKE 'TESTDATA%'")->fetchColumn() === 0);

echo "\nAbout page and sections fed by the admin (owner rule 2026-10-26: hidden when nothing is uploaded)\n";
require_once dirname(__DIR__, 2) . '/app/helpers/public_ui.php';
$aboutMembers = PublicTeamService::create()->forAboutPage();
$check('About page team: only members with an uploaded photo (the card is the photo)', array_filter($aboutMembers, static fn (array $m): bool => $m['photo'] === null) === []);
$renderView = static function (string $file, array $vars): string {
    extract($vars, EXTR_SKIP);
    ob_start();
    require $file;

    return (string) ob_get_clean();
};
$root = dirname(__DIR__, 2);
$aboutHtml = $renderView($root . '/public/views/about.php', ['data' => ['team' => [], 'steps' => [], 'address' => null], 'page' => []]);
$check('About page without team members: no team section at all', !str_contains($aboutHtml, 'id="team"') && !str_contains($aboutHtml, 'Meet the team'));
$check('About page without an office address: no "Our office" card', !str_contains($aboutHtml, 'Our office'));
$check('Browse by Brand without brands: nothing at all (no "unavailable" box)', trim($renderView($root . '/includes/brand_strip.php', ['brands' => []])) === ''
    && trim($renderView($root . '/includes/brand_strip.php', ['brands' => null])) === '');
$check('Latest Vehicles without vehicles: nothing at all', trim($renderView($root . '/includes/latest_vehicles.php', ['latest' => [], 'vehiclesPath' => '/vehicles/'])) === '');
$check('Latest Parts without parts: nothing at all', trim($renderView($root . '/includes/latest_parts.php', ['parts' => ['cards' => [], 'types' => [], 'makes' => [], 'type' => null, 'make' => null], 'whatsappDigits' => null])) === '');

echo "\nResult: $passed passed, $failed failed\n";
exit($failed > 0 ? 1 : 0);
