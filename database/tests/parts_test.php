<?php

declare(strict_types=1);

/**
 * Tests for the parts catalogue, phase 1 (spec 16): validation, schema rules, the admin service (create, edit,
 * compatible vehicles, photos, publish, stock, archive, categories, audit), controller authorization and error
 * containment, and the homepage data (only public parts, filters by type and by make, compatibility lines).
 *
 * Everything runs inside ONE transaction that is rolled back; photos are written to a temporary folder that is removed.
 * Run: php database/tests/parts_test.php
 */

require_once dirname(__DIR__, 2) . '/app/services/PartService.php';
require_once dirname(__DIR__, 2) . '/app/services/PublicPartService.php';
require_once dirname(__DIR__, 2) . '/app/controllers/PartController.php';
require_once __DIR__ . '/image_fixtures.php';

$passed = 0;
$failed = 0;
function check(string $label, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) { $passed++; echo "  PASS  $label\n"; }
    else { $failed++; echo "  FAIL  $label" . ($detail !== '' ? " -> $detail" : '') . "\n"; }
}

$work = sys_get_temp_dir() . '/vip_parts_test_' . bin2hex(random_bytes(4));
$store = $work . '/uploads/parts';
mkdir($store, 0777, true);
register_shutdown_function(static function () use ($work): void {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($work, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) {
        $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
    }
    @rmdir($work);
});
function upfile(string $bytes, string $name = 'part.jpg', int $error = UPLOAD_ERR_OK): array
{
    $path = $GLOBALS['work'] . '/' . bin2hex(random_bytes(4)) . '.bin';
    file_put_contents($path, $bytes);

    return ['name' => $name, 'tmp_name' => $path, 'error' => $error, 'size' => strlen($bytes)];
}
function part(array $over = []): array
{
    return $over + ['category_id' => (string) $GLOBALS['catBrakes'], 'name' => 'TEST Front brake pads', 'part_numbers' => ['04465-02220'], 'condition' => 'new',
        'price' => '450', 'currency' => 'GHS', 'stock_status' => 'in_stock', 'description' => 'TEST part', 'is_published' => '1',
        'fitments' => [['brand_id' => (string) $GLOBALS['toyota'], 'model' => 'Corolla', 'year_from' => '2014', 'year_to' => '2018']]];
}

// ------------------------------------------------------------------ validation
echo "Validation\n";
$GLOBALS['catBrakes'] = 1;
$GLOBALS['toyota'] = 1;
$v = PartValidator::validate(part());
check('a complete part is valid', $v['errors'] === [], json_encode($v['errors']));
check('price normalised to two decimals; "1,250.5" accepted', $v['values']['price'] === '450.00' && PartValidator::validate(part(['price' => '1,250.5']))['values']['price'] === '1250.50');
foreach (['abc', '-5', '1.234', '1e5', '99999999999'] as $bad) {
    check('price rejected: ' . json_encode($bad), isset(PartValidator::validate(part(['price' => $bad]))['errors']['price']));
}
check('name required, max 150, no < >', isset(PartValidator::validate(part(['name' => '  ']))['errors']['name']) && isset(PartValidator::validate(part(['name' => str_repeat('a', 151)]))['errors']['name'])
    && isset(PartValidator::validate(part(['name' => '<script>']))['errors']['name']) && isset(PartValidator::validate(part(['name' => ['x']]))['errors']['name']));
check('spec 27: an empty price means "price on request" (null, no error)', ($e = PartValidator::validate(part(['price' => ' '])))['values']['price'] === null && !isset($e['errors']['price']));
check('spec 27: condition and stock may be left unset (null)', ($e = PartValidator::validate(part(['condition' => '', 'stock_status' => ''])))['values']['condition'] === null && $e['values']['stock_status'] === null && $e['errors'] === []);
check('part numbers optional; odd characters rejected', PartValidator::validate(part(['part_numbers' => ['', ' ']]))['values']['part_numbers'] === []
    && isset(PartValidator::validate(part(['part_numbers' => ['AB"; DROP']]))['errors']['part_number_0']) && !isset(PartValidator::validate(part(['part_numbers' => ['90915-YZZE1 / A.2']]))['errors']['part_number_0']));
check('condition, stock and currency only from their lists', isset(PartValidator::validate(part(['condition' => 'refurb']))['errors']['condition'])
    && isset(PartValidator::validate(part(['stock_status' => '5']))['errors']['stock_status']) && isset(PartValidator::validate(part(['currency' => 'USD']))['errors']['currency']));
check('published only when exactly "1"', PartValidator::validate(part(['is_published' => 'yes']))['values']['is_published'] === 0 && PartValidator::validate(part())['values']['is_published'] === 1);
check('category must be an id', isset(PartValidator::validate(part(['category_id' => 'x']))['errors']['category_id']) && isset(PartValidator::validate(part(['category_id' => '0']))['errors']['category_id']));
$f = static fn (mixed $rows): array => PartValidator::validate(part(['fitments' => $rows]))['errors'];
check('fitment: blank rows ignored; no rows allowed (universal accessory)', $f([['brand_id' => '', 'model' => '', 'year_from' => '', 'year_to' => '']]) === [] && $f([]) === []);
check('fitment: make required when a row has anything', isset($f([['brand_id' => '', 'model' => 'Corolla', 'year_from' => '', 'year_to' => '']])['fitment_0']));
check('fitment: years 1900-2100, four digits, first <= last', isset($f([['brand_id' => '1', 'model' => '', 'year_from' => '1800', 'year_to' => '']])['fitment_0'])
    && isset($f([['brand_id' => '1', 'model' => '', 'year_from' => '2019', 'year_to' => '2014']])['fitment_0']) && isset($f([['brand_id' => '1', 'model' => '', 'year_from' => '14', 'year_to' => '']])['fitment_0'])
    && !isset($f([['brand_id' => '1', 'model' => '', 'year_from' => '2014', 'year_to' => '']])['fitment_0']));
check('fitment: the error names the right row', array_keys($f([['brand_id' => '1', 'model' => '', 'year_from' => '', 'year_to' => ''], ['brand_id' => 'x', 'model' => '', 'year_from' => '', 'year_to' => '']])) === ['fitment_1']);
check('fitment: at most 20 rows; a non-array is refused', isset($f(array_fill(0, 21, ['brand_id' => '1', 'model' => '', 'year_from' => '', 'year_to' => '']))['fitments']) && isset($f('junk')['fitments']));
check('fitment records: empty model and years become NULL', PartValidator::fitmentRecords([['brand_id' => '3', 'model' => '', 'year_from' => '', 'year_to' => '2018']]) === [['brand_id' => 3, 'model' => null, 'year_from' => null, 'year_to' => 2018]]);
check('category form: name required and unique-safe slug; order digits only', isset(PartValidator::category(['name' => ''])['errors']['name']) && isset(PartValidator::category(['name' => '---'])['errors']['name'])
    && isset(PartValidator::category(['name' => 'Ok', 'display_order' => '-1'])['errors']['display_order']) && PartValidator::category(['name' => 'Ok', 'status' => 'inactive'])['values']['status'] === 'inactive');
check('list filters: junk falls back to no filter', PartValidator::filters(['q' => ['x'], 'category' => "1 OR 1", 'brand' => '-2', 'published' => 'maybe', 'stock' => 'x', 'page' => '0'])
    === ['q' => '', 'category' => null, 'brand' => null, 'published' => 'all', 'stock' => 'all', 'price' => 'all', 'fits' => 'all', 'page' => 1]);
check('fitment label reads naturally', PartService::fitmentLabel(['brand_name' => 'Toyota', 'model' => 'Corolla', 'year_from' => 2014, 'year_to' => 2018]) === 'Toyota Corolla 2014–2018'
    && PartService::fitmentLabel(['brand_name' => 'Honda', 'model' => null, 'year_from' => null, 'year_to' => null]) === 'Honda all models'
    && PartService::fitmentLabel(['brand_name' => 'Kia', 'model' => 'Rio', 'year_from' => 2015, 'year_to' => null]) === 'Kia Rio 2015 onwards'
    && PartService::fitmentLabel(['brand_name' => 'Kia', 'model' => 'Rio', 'year_from' => null, 'year_to' => 2012]) === 'Kia Rio up to 2012'
    && PartService::fitmentLabel(['brand_name' => 'Kia', 'model' => 'Rio', 'year_from' => 2015, 'year_to' => 2015]) === 'Kia Rio 2015');

// ------------------------------------------------------------------ schema and service
$pdo = Database::connection();
echo "Schema\n";
$tables = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE 'part%' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN);
check('the five parts tables exist (part_numbers: spec 27)', $tables === ['part_categories', 'part_fitments', 'part_images', 'part_numbers', 'parts'], implode(',', $tables));
check('the eleven starting categories exist, Accessories included', (int) $pdo->query("SELECT COUNT(*) FROM part_categories WHERE slug IN ('engine','transmission','brakes','suspension-steering','electrical','cooling','body-lights','interior','filters-service','wheels-tyres','accessories')")->fetchColumn() === 11);
$realParts = (int) $pdo->query('SELECT COUNT(*) FROM parts')->fetchColumn();
$realFiles = array_values(array_diff(scandir(dirname(__DIR__, 2) . '/uploads/parts') ?: [], ['.', '..', '.gitkeep']));
$adminId = (int) $pdo->query('SELECT id FROM admin_users ORDER BY id LIMIT 1')->fetchColumn();
check('an administrator row exists for audit foreign keys', $adminId > 0);
check('the environment can process images', ImageService::missingRequirements() === []);
$GLOBALS['catBrakes'] = (int) $pdo->query("SELECT id FROM part_categories WHERE slug = 'brakes'")->fetchColumn();
$catAcc = (int) $pdo->query("SELECT id FROM part_categories WHERE slug = 'accessories'")->fetchColumn();
$GLOBALS['toyota'] = (int) $pdo->query("SELECT id FROM brands WHERE slug = 'toyota'")->fetchColumn();
$honda = (int) $pdo->query("SELECT id FROM brands WHERE slug = 'honda'")->fetchColumn();
$ip = '203.0.113.7';
$ua = 'parts_test';

$pdo->beginTransaction();
try {
    $repo = new PartRepository($pdo);
    $service = new PartService($pdo, $repo, new AuditLogRepository($pdo), new ImageService($store, ImageService::KIND_PART));
    $audit = static fn (string $event): int => (int) $GLOBALS['pdo']->query("SELECT COUNT(*) FROM audit_logs WHERE user_agent = 'parts_test' AND event_type = " . $GLOBALS['pdo']->quote($event))->fetchColumn();
    $files = static fn (): array => array_values(array_diff(scandir($GLOBALS['store']) ?: [], ['.', '..']));

    echo "Schema rules\n";
    $raw = static function (string $sql) use ($pdo): bool {
        $pdo->exec('SAVEPOINT s');
        try { $pdo->exec($sql); $pdo->exec('ROLLBACK TO SAVEPOINT s'); return true; } catch (PDOException $e) { $pdo->exec('ROLLBACK TO SAVEPOINT s'); return false; }
    };
    check('database refuses a negative price', !$raw("INSERT INTO parts (category_id, name, price, slug) VALUES ({$GLOBALS['catBrakes']}, 'x', -1, 'neg-price')"));
    check('database refuses a bad slug', !$raw("INSERT INTO parts (category_id, name, price, slug) VALUES ({$GLOBALS['catBrakes']}, 'x', 1, 'Bad Slug')"));
    check('database refuses an unknown category', !$raw("INSERT INTO parts (category_id, name, price, slug) VALUES (999999, 'x', 1, 'no-cat')"));

    echo "Create\n";
    $jpeg = fx_photo(1200, 900);
    $r = $service->createPart(part(), [upfile($jpeg, 'pads-front.jpg'), upfile(fx_photo(900, 700, 'png'), 'pads-side.png')], $adminId, $ip, $ua, false);
    check('a part with fitment and two photos is created', $r['status'] === 'ok' && is_int($r['id']), json_encode($r['errors']));
    $id = (int) $r['id'];
    $saved = $service->find($id);
    check('stored values and generated slug', $saved['name'] === 'TEST Front brake pads' && $saved['slug'] === 'test-front-brake-pads-04465-02220' && $saved['price'] === '450.00' && (int) $saved['is_published'] === 1);
    check('fitment stored with make name', count($saved['fitments']) === 1 && $saved['fitments'][0]['brand_name'] === 'Toyota' && $saved['fitments'][0]['model'] === 'Corolla' && $saved['fitments'][0]['year_to'] === 2018);
    check('two photos, the first is the main photo, both re-encoded to WebP with thumbnails', count($saved['images']) === 2 && $saved['images'][0]['is_primary'] === 1 && count($files()) === 4
        && str_starts_with((string) file_get_contents($store . '/' . basename($saved['images'][0]['file_path'])), 'RIFF'));
    check('photo alt text is the part name; the original file name is kept only as metadata', $saved['images'][0]['alt_text'] === 'TEST Front brake pads' && !str_contains($saved['images'][0]['file_path'], 'pads'));
    check('creation audited once, no file names in the audit text', $audit('PART_CREATED') === 1 && (int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE user_agent = 'parts_test' AND description LIKE '%.jpg%'")->fetchColumn() === 0);
    $r2 = $service->createPart(part(), [], $adminId, $ip, $ua, false);
    check('same name and number again gets a -2 slug (never reused)', $r2['status'] === 'ok' && $service->find((int) $r2['id'])['slug'] === 'test-front-brake-pads-04465-02220-2');

    echo "Rejections\n";
    $before = $files();
    $bad = $service->createPart(part(), [upfile('<?php echo 1; ?>', 'shell.jpg')], $adminId, $ip, $ua, false);
    check('a script renamed .jpg is refused and nothing is stored', $bad['status'] === 'invalid' && isset($bad['errors']['photos']) && $files() === $before && $audit('PART_PHOTO_REJECTED') === 1);
    $small = $service->createPart(part(), [upfile(fx_photo(300, 200))], $adminId, $ip, $ua, false);
    check('a photo below 600 x 450 is refused with the minimum in the message', $small['status'] === 'invalid' && str_contains((string) $small['errors']['photos'], '600 x 450'));
    $notUploaded = $service->createPart(part(), [upfile($jpeg)], $adminId, $ip, $ua, true);
    check('a file that did not arrive by HTTP upload is refused', $notUploaded['status'] === 'invalid' && isset($notUploaded['errors']['photos']));
    $tooMany = $service->createPart(part(), array_fill(0, 9, upfile($jpeg)), $adminId, $ip, $ua, false);
    check('more than 8 photos refused before anything is processed', $tooMany['status'] === 'invalid' && isset($tooMany['errors']['photos']) && $files() === $before);
    check('an unknown category is refused', isset($service->createPart(part(['category_id' => '999999']), [], $adminId, $ip, $ua, false)['errors']['category_id']));
    check('an unknown make is refused on its row', isset($service->createPart(part(['fitments' => [['brand_id' => '999999', 'model' => '', 'year_from' => '', 'year_to' => '']]]), [], $adminId, $ip, $ua, false)['errors']['fitment_0']));
    $pdo->exec("UPDATE part_categories SET status = 'inactive' WHERE id = $catAcc");
    check('an inactive category cannot be chosen for a new part', isset($service->createPart(part(['category_id' => (string) $catAcc]), [], $adminId, $ip, $ua, false)['errors']['category_id']));
    $pdo->exec("UPDATE part_categories SET status = 'active' WHERE id = $catAcc");

    echo "Edit\n";
    check('saving without changes reports unchanged', $service->updatePart($id, part(), [], $adminId, $ip, $ua, false)['status'] === 'unchanged');
    $u = $service->updatePart($id, part(['price' => '475.5', 'fitments' => [
        ['brand_id' => (string) $GLOBALS['toyota'], 'model' => 'Corolla', 'year_from' => '2014', 'year_to' => '2018'],
        ['brand_id' => (string) $honda, 'model' => '', 'year_from' => '2016', 'year_to' => ''],
    ]]), [upfile($jpeg)], $adminId, $ip, $ua, false);
    $saved = $service->find($id);
    check('price, fitments and a third photo saved', $u['status'] === 'ok' && $saved['price'] === '475.50' && count($saved['fitments']) === 2 && count($saved['images']) === 3 && $saved['images'][0]['is_primary'] === 1);
    check('the audit names what changed', (int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE user_agent = 'parts_test' AND event_type = 'PART_UPDATED' AND description LIKE '%price%' AND description LIKE '%compatible vehicles%'")->fetchColumn() === 1);
    check('an inactive make already on the part may stay', (function () use ($service, $pdo, $id, $honda, $adminId, $ip, $ua): bool {
        $pdo->exec("UPDATE brands SET status = 'inactive' WHERE id = $honda");
        $ok = $service->updatePart($id, part(['name' => 'TEST Front brake pads set', 'fitments' => [['brand_id' => (string) $honda, 'model' => '', 'year_from' => '2016', 'year_to' => '']]]), [], $adminId, $ip, $ua, false)['status'] === 'ok';
        $pdo->exec("UPDATE brands SET status = 'active' WHERE id = $honda");
        return $ok;
    })());

    echo "Photos\n";
    $imgs = $service->find($id)['images'];
    $second = $imgs[1];
    check('make main: the chosen photo becomes the only main photo', $service->changePhoto($id, $second['id'], 'primary', $adminId, $ip, $ua) === 'ok'
        && array_values(array_filter($service->find($id)['images'], static fn (array $i): bool => $i['is_primary'] === 1))[0]['id'] === $second['id']);
    $fileCount = count($files());
    check('remove the main photo: record gone, files gone, the next photo is promoted', $service->changePhoto($id, $second['id'], 'remove', $adminId, $ip, $ua) === 'ok'
        && count($files()) === $fileCount - 2 && count($service->find($id)['images']) === 2 && $service->find($id)['images'][0]['is_primary'] === 1);
    $otherPart = (int) $r2['id'];
    check('a photo of another part cannot be changed through this part (IDOR)', $service->changePhoto($otherPart, $service->find($id)['images'][0]['id'], 'remove', $adminId, $ip, $ua) === 'not_found' && count($service->find($id)['images']) === 2);

    echo "Publish, stock, archive\n";
    check('unpublish and publish are audited', $service->changeState($id, 'unpublish', $adminId, $ip, $ua) === 'ok' && (int) $service->find($id)['is_published'] === 0
        && $service->changeState($id, 'publish', $adminId, $ip, $ua) === 'ok' && $audit('PART_PUBLISHED') === 1 && $audit('PART_UNPUBLISHED') === 1);
    check('stock changes', $service->changeState($id, 'out_of_stock', $adminId, $ip, $ua) === 'ok' && $service->find($id)['stock_status'] === 'out_of_stock'
        && $service->changeState($id, 'in_stock', $adminId, $ip, $ua) === 'ok');

    echo "Homepage data\n";
    $acc = $service->createPart(part(['name' => 'TEST Phone holder', 'part_numbers' => [], 'category_id' => (string) $catAcc, 'fitments' => []]), [], $adminId, $ip, $ua, false);
    $hidden = $service->createPart(part(['name' => 'TEST Hidden part', 'is_published' => '0']), [], $adminId, $ip, $ua, false);
    $public = new PublicPartService($repo, $work);
    $home = $public->homepage();
    $names = array_column($home['cards'], 'name');
    check('only published parts are shown, newest first', !in_array('TEST Hidden part', $names, true) && in_array('TEST Phone holder', $names, true) && $names[0] === 'TEST Phone holder');
    $card = $home['cards'][array_search('TEST Front brake pads set', $names, true)];
    check('a card lists its compatible vehicles and makes', $card['fits'] === ['Honda all models 2016 onwards'] && $card['makes'] === ['honda'] && $card['price'] === 'GHS 450' && $card['in_stock'] === true);
    check('a card has its thumbnail', is_array($card['image']) && str_starts_with($card['image']['url'], '/uploads/parts/') && str_ends_with($card['image']['url'], '-t.webp'));
    $holder = $home['cards'][0];
    check('a universal accessory claims no vehicles', $holder['fits'] === [] && $holder['makes'] === []);
    check('types and brands offered are only those with public parts', in_array('accessories', array_column($home['types'], 'slug'), true) && in_array('honda', array_column($home['makes'], 'slug'), true)
        && !in_array('cooling', array_column($home['types'], 'slug'), true));
    $byType = $public->homepage('accessories');
    check('filter by type', $byType['type'] === 'accessories' && array_column($byType['cards'], 'name') === ['TEST Phone holder']);
    $byMake = $public->homepage(null, 'honda');
    check('filter by make (via compatible vehicles)', $byMake['make'] === 'honda' && in_array('TEST Front brake pads set', array_column($byMake['cards'], 'name'), true) && !in_array('TEST Phone holder', array_column($byMake['cards'], 'name'), true));
    check('unknown or hostile filters are ignored', $public->homepage("x' OR 1=1")['type'] === null && $public->homepage(['a'], '../../etc')['make'] === null && $public->homepage('cooling')['type'] === null);
    check('contact pre-fill: a public part by slug; hidden or malformed slugs give nothing', $public->enquiryLabel('test-phone-holder') === 'TEST Phone holder'
        && $public->enquiryLabel($service->find((int) $hidden['id'])['slug']) === null && $public->enquiryLabel('<x>') === null && $public->enquiryLabel(['a']) === null);
    check('archive hides the part everywhere and unpublishes it', $service->changeState((int) $acc['id'], 'archive', $adminId, $ip, $ua) === 'ok' && $service->find((int) $acc['id']) === null
        && !in_array('TEST Phone holder', array_column($public->homepage()['cards'], 'name'), true) && (int) $pdo->query('SELECT is_published FROM parts WHERE id = ' . (int) $acc['id'])->fetchColumn() === 0);
    check('an archived part cannot be edited or changed', $service->updatePart((int) $acc['id'], part(), [], $adminId, $ip, $ua, false)['status'] === 'not_found' && $service->changeState((int) $acc['id'], 'publish', $adminId, $ip, $ua) === 'not_found');

    echo "Categories\n";
    $c = $service->saveCategory(null, ['name' => 'TEST Batteries', 'display_order' => '12'], $adminId, $ip, $ua);
    check('a category is added with a slug from its name', $c['status'] === 'ok' && $repo->findCategory((int) $c['id'])['slug'] === 'test-batteries');
    check('duplicate names are refused (case-insensitive)', isset($service->saveCategory(null, ['name' => 'test batteries'], $adminId, $ip, $ua)['errors']['name']));
    check('a category can be renamed and deactivated', $service->saveCategory((int) $c['id'], ['name' => 'TEST Car batteries', 'display_order' => '12', 'status' => 'inactive'], $adminId, $ip, $ua)['status'] === 'ok'
        && $repo->findCategory((int) $c['id'])['status'] === 'inactive' && $audit('PART_CATEGORY_UPDATED') === 1);
    check('editing an unknown category reports not found', $service->saveCategory(999999, ['name' => 'X'], $adminId, $ip, $ua)['status'] === 'not_found');

    echo "List\n";
    $list = $service->listParts(PartValidator::filters(['q' => 'TEST Front']));
    check('search by name finds the parts with their compatibility summary', $list['total'] === 2 && str_contains($list['parts'][0]['fits'] . $list['parts'][1]['fits'], 'Honda all models'));
    check('LIKE wildcards in the search are literal', $service->listParts(PartValidator::filters(['q' => '%']))['total'] === 0);
    check('filter by make and by unpublished', $service->listParts(PartValidator::filters(['brand' => (string) $honda]))['total'] >= 1
        && in_array('TEST Hidden part', array_column($service->listParts(PartValidator::filters(['published' => 'no']))['parts'], 'name'), true));
    check('audit rows hold no secrets', (int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE user_agent = 'parts_test' AND event_type LIKE 'PART%' AND (description LIKE '%password%' OR description LIKE '%token%')")->fetchColumn() === 0);
} finally {
    $pdo->rollBack();
}

// ------------------------------------------------------------------ controller
echo "Controller\n";
$staff = ['id' => 1, 'name' => 'Staff', 'role' => 'staff'];
$controller = new PartController();
foreach ([
    'index' => $controller->index($staff, []), 'create' => $controller->create($staff, 'POST', part(), [], [], $ip, $ua),
    'edit' => $controller->edit($staff, '1', 'GET', [], [], [], $ip, $ua), 'state' => $controller->changeState($staff, ['id' => '1', 'action' => 'archive'], $ip, $ua),
    'photo' => $controller->changePhoto($staff, ['id' => '1', 'image' => '1', 'action' => 'remove'], $ip, $ua), 'categories' => $controller->categories($staff, 'POST', ['name' => 'x'], $ip, $ua),
] as $action => $result) {
    check("a role without the parts permission is refused: $action", $result['http'] === 403 && $result['problem'] === 'forbidden');
}
check('administrator holds the parts permission', AuthorizationService::can('administrator', PartController::PERMISSION));
$admin = ['id' => $adminId, 'name' => 'Admin', 'role' => 'administrator'];
$_SESSION = [];
$pdo->beginTransaction();   // the controller audits each CSRF failure: keep those rows out of the real table
$auditStart = (int) $pdo->query('SELECT COALESCE(MAX(id), 0) FROM audit_logs')->fetchColumn();   // QA 2026-10-27: was a fixed id (2052) that only fit one database
foreach ([
    'create' => $controller->create($admin, 'POST', part(), [], [], $ip, $ua),
    'state' => $controller->changeState($admin, ['id' => '1', 'action' => 'archive'], $ip, $ua),
    'photo' => $controller->changePhoto($admin, ['id' => '1', 'image' => '1', 'action' => 'remove'], $ip, $ua),
    'categories' => $controller->categories($admin, 'POST', ['name' => 'x'], $ip, $ua),
] as $action => $result) {
    check("a POST without a valid CSRF token is refused: $action", $result['http'] === 400 && $result['problem'] === 'csrf');
}
check('each CSRF failure is audited', (int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE user_agent = 'parts_test' AND event_type = 'CSRF_FAILURE' AND id > $auditStart")->fetchColumn() === 4);
$pdo->rollBack();
check('photos(): empty slots dropped, odd shapes become an invalid upload', PartController::photos([]) === []
    && PartController::photos(['photos' => ['name' => [''], 'tmp_name' => [''], 'error' => [UPLOAD_ERR_NO_FILE], 'size' => [0]]]) === []
    && PartController::photos(['photos' => ['name' => 'a.jpg', 'tmp_name' => '/x', 'error' => 0, 'size' => 1]])[0]['error'] !== UPLOAD_ERR_OK
    && count(PartController::photos(['photos' => ['name' => ['a.jpg', 'b.jpg'], 'tmp_name' => ['/x', '/y'], 'error' => [0, 0], 'size' => [1, 1]]])) === 2);
$failing = new class extends PartService {
    public function __construct() {}
    public function listParts(array $filters): array { throw new RuntimeException('SQLSTATE[HY000] secret detail in C:\\path\\file.php'); }
};
$prevLog = ini_set('error_log', $work . '/err.log');
putenv('APP_ENV=production'); $_ENV['APP_ENV'] = 'production';
$err = (new PartController($failing))->index($admin, []);
putenv('APP_ENV=development'); $_ENV['APP_ENV'] = 'development';
ini_set('error_log', (string) $prevLog);
check('a failing service gives a 500 with a generic message; the detail is only in the log', $err['http'] === 500 && $err['detail'] === null && !preg_match('/SQLSTATE|secret|path/i', json_encode($err)) && str_contains((string) @file_get_contents($work . '/err.log'), 'SQLSTATE'));

echo "Cleanup\n";
check('the real parts table is unchanged', (int) $pdo->query('SELECT COUNT(*) FROM parts')->fetchColumn() === $realParts);
check('no audit rows remain from this run', (int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE user_agent = 'parts_test' AND id > 2052")->fetchColumn() === 0);   // ids <= 2052: four rows left by the first version of this test (2026-10-16), kept on purpose: audit rows are never deleted
check('the real uploads/parts folder was not touched', array_values(array_diff(scandir(dirname(__DIR__, 2) . '/uploads/parts') ?: [], ['.', '..', '.gitkeep'])) === $realFiles);
foreach (['parts', 'part_fitments', 'part_images', 'part_categories', 'audit_logs'] as $table) {
    $pdo->exec("ALTER TABLE `$table` AUTO_INCREMENT = 1");
}

echo "\n$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
