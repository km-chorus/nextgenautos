<?php

declare(strict_types=1);

/**
 * Brand Management logic tests (spec 05_spec_brand_crud.md, section 55).
 *
 * CLI only:  php database/tests/brand_test.php
 * Requires:  schema.sql + seed.sql loaded; DB credentials in .env.
 *
 * Covers validation, repository queries (search, filter, order, injection
 * strings), BrandService rules (duplicates, edit, status, deletion safety,
 * audit rows) and the database's own constraints. Everything runs inside one
 * transaction that is rolled back, so no brand, vehicle or audit row is left
 * behind. Test rows are labelled "TEST DATA". HTTP behaviour (authentication,
 * CSRF, GET refusal, XSS, redirects) is covered by the HTTP/browser suites.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__, 2) . '/app/services/BrandService.php';

$passed = 0;
$failed = 0;
function check(string $label, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) { $passed++; echo "  PASS  $label\n"; }
    else { $failed++; echo "  FAIL  $label" . ($detail !== '' ? " -> $detail" : '') . "\n"; }
}
function run(PDO $pdo, string $sql, array $params = []): PDOStatement
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}
function valid(array $over = []): array
{
    return array_merge(['name' => 'TEST DATA Brand', 'slug' => '', 'description' => '', 'display_order' => '50', 'status' => 'active'], $over);
}

$pdo = Database::connection();
$service = BrandService::create();
$repo = new BrandRepository($pdo);
$ip = '203.0.113.9';
$ua = 'brand_test';

// ------------------------------------------------------------------ validator
echo "Validator\n";
$r = BrandValidator::validate(valid(['name' => '  Mercedes-Benz  ']));
check('valid brand has no errors', $r['errors'] === []);
check('name is trimmed', $r['values']['name'] === 'Mercedes-Benz');
check('slug generated from name: Mercedes-Benz -> mercedes-benz', $r['values']['slug'] === 'mercedes-benz');
check('punctuation in name is allowed (Mercedes-Benz)', !isset($r['errors']['name']));
check('Land Rover -> land-rover', BrandValidator::validate(valid(['name' => 'Land   Rover']))['values']['slug'] === 'land-rover');
check('inner whitespace collapsed', BrandValidator::validate(valid(['name' => "Land \t Rover"]))['values']['name'] === 'Land Rover');
check('manual valid slug is kept, not regenerated', BrandValidator::validate(valid(['name' => 'Toyota', 'slug' => 'toyota-motors']))['values']['slug'] === 'toyota-motors');
check('manual slug is lowercased', BrandValidator::validate(valid(['slug' => 'My-Slug']))['values']['slug'] === 'my-slug');
check('empty name rejected', isset(BrandValidator::validate(valid(['name' => '   ']))['errors']['name']));
check('missing name rejected', isset(BrandValidator::validate(['slug' => '', 'display_order' => '1', 'status' => 'active'])['errors']['name']));
check('name of 101 characters rejected', isset(BrandValidator::validate(valid(['name' => str_repeat('a', 101)]))['errors']['name']));
check('name of 100 characters accepted', !isset(BrandValidator::validate(valid(['name' => str_repeat('a', 100)]))['errors']['name']));
check('multibyte name counted in characters (100 x e-acute ok)', !isset(BrandValidator::validate(valid(['name' => str_repeat("\u{e9}", 100), 'slug' => 'x']))['errors']['name']));
check('NUL byte in name rejected', isset(BrandValidator::validate(valid(['name' => "Toy\0ota"]))['errors']['name']));
check('control character in name rejected', isset(BrandValidator::validate(valid(['name' => "Toy\x07ota"]))['errors']['name']));
check('zero-width character in name rejected', isset(BrandValidator::validate(valid(['name' => "Toy\u{200B}ota"]))['errors']['name']));
check('invalid UTF-8 name rejected', isset(BrandValidator::validate(valid(['name' => "To\xC3\x28ota"]))['errors']['name']));
check('array name rejected (not a string)', isset(BrandValidator::validate(valid(['name' => ['x']]))['errors']['name']));
check('name with no letters/digits and no slug rejected', isset(BrandValidator::validate(valid(['name' => '---']))['errors']['name']));
foreach (['has space', 'UPPER_case', 'under_score', '-lead', 'trail-', 'dou--ble', 'caf' . "\u{e9}", 'a/b', '../x', 'a b', "x\0y", '<s>'] as $bad) {
    check('bad slug rejected: ' . json_encode($bad), isset(BrandValidator::validate(valid(['slug' => $bad]))['errors']['slug']));
}
check('slug over 100 chars rejected', isset(BrandValidator::validate(valid(['slug' => str_repeat('a', 101)]))['errors']['slug']));
check('description optional (empty -> null)', BrandValidator::validate(valid(['description' => '  ']))['values']['description'] === null);
check('description kept', BrandValidator::validate(valid(['description' => "Line1\r\nLine2"]))['values']['description'] === "Line1\nLine2");
check('description over limit rejected', isset(BrandValidator::validate(valid(['description' => str_repeat('a', 2001)]))['errors']['description']));
check('description with NUL rejected', isset(BrandValidator::validate(valid(['description' => "a\0b"]))['errors']['description']));
foreach (['0' => true, '1' => true, '9999' => true, '10000' => false, '-1' => false, '1.5' => false, 'abc' => false, '' => false, '1e3' => false, '99999999999' => false, ' 7 ' => true] as $order => $ok) {
    $order = (string) $order; // PHP turns numeric array keys into integers; the browser always sends text
    check('display order ' . json_encode($order) . ($ok ? ' accepted' : ' rejected'), $ok === !isset(BrandValidator::validate(valid(['display_order' => $order]))['errors']['display_order']));
}
check('display order array rejected', isset(BrandValidator::validate(valid(['display_order' => ['1']]))['errors']['display_order']));
check('status active accepted', !isset(BrandValidator::validate(valid(['status' => 'active']))['errors']['status']));
check('status inactive accepted', !isset(BrandValidator::validate(valid(['status' => 'inactive']))['errors']['status']));
foreach (['ACTIVE ', 'deleted', '', '1', "active' OR '1'='1"] as $bad) {
    $st = BrandValidator::validate(valid(['status' => $bad]));
    check('status ' . json_encode($bad) . ' handled', $bad === 'ACTIVE ' ? !isset($st['errors']['status']) : isset($st['errors']['status']));
}
check('multiple errors reported together', count(BrandValidator::validate(['name' => '', 'slug' => 'BAD SLUG', 'display_order' => 'x', 'status' => 'z'])['errors']) === 4);
foreach (['1', '12', '4294967295'] as $good) {
    check("id $good valid", BrandValidator::id($good) === (int) $good);
}
foreach (['0', '-1', '01', '1.0', 'abc', '', '1 OR 1=1', '4294967296', '1;DROP TABLE brands', ' 1', null, ['1'], 5] as $bad) {
    check('id rejected: ' . json_encode($bad), BrandValidator::id($bad) === null);
}
check('filters: defaults', BrandValidator::filters([]) === ['q' => '', 'status' => 'all']);
check('filters: unknown status falls back to all', BrandValidator::filters(['status' => 'x" OR 1'])['status'] === 'all');
check('filters: search trimmed + capped', mb_strlen(BrandValidator::filters(['q' => str_repeat('a', 500)])['q']) === 100);
check('filters: array input ignored', BrandValidator::filters(['q' => ['a'], 'status' => ['active']]) === ['q' => '', 'status' => 'all']);
check('target status: only active/inactive', BrandValidator::targetStatus('active') === 'active' && BrandValidator::targetStatus('inactive') === 'inactive'
    && BrandValidator::targetStatus('ACTIVE') === null && BrandValidator::targetStatus('x') === null && BrandValidator::targetStatus(['active']) === null);

// ------------------------------------------------------------- approved brands
echo "Approved brands (database)\n";
$approved = ['Hyundai', 'Honda', 'Toyota', 'Mazda', 'Ford', 'Chevrolet', 'Dodge', 'Nissan', 'Mercedes-Benz', 'BMW', 'Mitsubishi', 'GMC'];
$baseline = $repo->search('', 'all');
check('exactly the 12 approved brands exist, in approved order',
    array_column($baseline, 'name') === $approved, json_encode(array_column($baseline, 'name')));
$baseActive = count(array_filter($baseline, static fn ($b) => $b['status'] === 'active')); // owners may have deactivated some: only the split matters
check('every brand has a valid status', $baseActive + count(array_filter($baseline, static fn ($b) => $b['status'] === 'inactive')) === 12);
check('no descriptions or logos were invented', (int) $pdo->query('SELECT COUNT(*) FROM brands WHERE description IS NOT NULL OR logo_path IS NOT NULL')->fetchColumn() === 0);
check('slugs follow the format', count(array_filter($baseline, static fn ($b) => preg_match(BrandValidator::SLUG_PATTERN, $b['slug']) === 1)) === 12);

$pdo->beginTransaction();
try {
    // --------------------------------------------------------------- listing/search
    echo "Listing and search\n";
    $count = static fn (string $q, string $status = 'all'): array => array_column($repo->search($q, $status), 'name');
    check('search "Toyota" returns Toyota only', $count('Toyota') === ['Toyota']);
    check('search is case-insensitive', $count('toyota') === ['Toyota']);
    check('search by slug "mercedes-benz"', $count('mercedes-benz') === ['Mercedes-Benz']);
    check('partial search "mer" finds Mercedes-Benz', $count('mer') === ['Mercedes-Benz']);
    check('no match gives an empty list', $count('zzzzzz') === []);
    check('LIKE wildcard % is literal, not "match all"', $count('%') === []);
    check('LIKE wildcard _ is literal', $count('_') === []);
    check('SQL injection string in search returns nothing, no error', $count("' OR '1'='1") === [] && $count("x'; DROP TABLE brands; --") === []);
    check('brands table intact after injection strings', $repo->countAll() === 12);
    check('status filter: active matches the baseline', count($count('', 'active')) === $baseActive);
    check('status filter: inactive matches the baseline', count($count('', 'inactive')) === 12 - $baseActive);
    check('unknown status value in repository = no status filter', count($count('', 'bogus')) === 12);
    check('list rows carry a vehicle count', $baseline[0]['vehicle_count'] === 0 || $baseline[0]['vehicle_count'] === '0');

    // ------------------------------------------------------------------ ordering
    echo "Ordering\n";
    $adminId = (int) $pdo->query('SELECT id FROM admin_users ORDER BY id LIMIT 1')->fetchColumn();
    check('an administrator row exists for audit foreign keys', $adminId > 0);
    $a = $service->createBrand(valid(['name' => 'TEST DATA Zeta', 'display_order' => '3']), $adminId, $ip, $ua);
    $b = $service->createBrand(valid(['name' => 'TEST DATA Alpha', 'display_order' => '3']), $adminId, $ip, $ua);
    $c = $service->createBrand(valid(['name' => 'TEST DATA First', 'display_order' => '0']), $adminId, $ip, $ua);
    $names = array_column($repo->search('TEST DATA', 'all'), 'name');
    check('order: display_order ASC then name ASC', $names === ['TEST DATA First', 'TEST DATA Alpha', 'TEST DATA Zeta'], json_encode($names));
    check('full list starts with display_order 0 brand', ($repo->search('', 'all')[0]['name'] ?? '') === 'TEST DATA First');
    check('nextDisplayOrder is max + 1', $repo->nextDisplayOrder() === 13);

    // ------------------------------------------------------------------ creation
    echo "Creation\n";
    check('create returns ok + id', $a['status'] === 'ok' && $a['id'] > 0);
    $row = $service->find($a['id']);
    check('stored values correct', $row['name'] === 'TEST DATA Zeta' && $row['slug'] === 'test-data-zeta' && $row['status'] === 'active' && (int) $row['display_order'] === 3 && $row['description'] === null);
    check('BRAND_CREATED audit row written with admin + entity', (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type='BRAND_CREATED' AND admin_user_id=? AND entity_type='brand' AND entity_id=?", [$adminId, $a['id']])->fetchColumn() === 1);

    $dupName = $service->createBrand(valid(['name' => 'toyota']), $adminId, $ip, $ua);
    check('duplicate name (different case) rejected', $dupName['status'] === 'invalid' && ($dupName['errors']['name'] ?? '') === 'A brand with this name already exists.', json_encode($dupName['errors']));
    $dupName2 = $service->createBrand(valid(['name' => ' Toyota ']), $adminId, $ip, $ua);
    check('duplicate name (padded) rejected', isset($dupName2['errors']['name']));
    $dupSlug = $service->createBrand(valid(['name' => 'Something New', 'slug' => 'toyota']), $adminId, $ip, $ua);
    check('duplicate slug rejected with its own message', ($dupSlug['errors']['slug'] ?? '') === 'This slug is already in use.' && !isset($dupSlug['errors']['name']), json_encode($dupSlug['errors']));
    $dupGen = $service->createBrand(valid(['name' => 'TOYOTA!']), $adminId, $ip, $ua);
    check('generated slug that collides is rejected', isset($dupGen['errors']['slug']));
    check('rejected duplicates created nothing', $repo->countAll() === 15);
    $bad = $service->createBrand(valid(['name' => '', 'display_order' => 'x', 'status' => 'q']), $adminId, $ip, $ua);
    check('invalid data rejected server-side with field errors', $bad['status'] === 'invalid' && isset($bad['errors']['name'], $bad['errors']['display_order'], $bad['errors']['status']));
    check('invalid data inserted nothing', $repo->countAll() === 15);
    check('invalid submit does not write a BRAND_CREATED row', (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type='BRAND_CREATED' AND ip_address=?", [$ip])->fetchColumn() === 3);

    // ----------------------------------------------------------- database layer
    echo "Database constraints\n";
    $threw = static function (callable $fn): ?PDOException {
        $pdo = Database::connection();
        $pdo->exec('SAVEPOINT t');
        try { $fn(); $pdo->exec('ROLLBACK TO SAVEPOINT t'); return null; }
        catch (PDOException $e) { $pdo->exec('ROLLBACK TO SAVEPOINT t'); return $e; }
    };
    $e = $threw(fn () => $repo->insert(['name' => 'toyota', 'slug' => 'raw-unique-name', 'description' => null, 'display_order' => 1, 'status' => 'active']));
    check('UNIQUE(name) enforced by the database itself (case-insensitive)', $e !== null && (int) $e->errorInfo[1] === 1062 && str_contains($e->getMessage(), 'uq_brands_name'));
    $e = $threw(fn () => $repo->insert(['name' => 'Raw Unique Slug', 'slug' => 'toyota', 'description' => null, 'display_order' => 1, 'status' => 'active']));
    check('UNIQUE(slug) enforced by the database itself', $e !== null && (int) $e->errorInfo[1] === 1062 && str_contains($e->getMessage(), 'uq_brands_slug'));
    $e = $threw(fn () => $repo->insert(['name' => 'Raw Bad Slug', 'slug' => 'Bad Slug', 'description' => null, 'display_order' => 1, 'status' => 'active']));
    check('CHECK(slug format) enforced by the database', $e !== null);

    // -------------------------------------------------------------------- edit
    echo "Editing\n";
    $toyota = (int) run($pdo, "SELECT id FROM brands WHERE slug='toyota'")->fetchColumn();
    $before = $service->find($toyota);
    $same = $service->updateBrand($toyota, ['name' => 'Toyota', 'slug' => 'toyota', 'description' => '', 'display_order' => '3', 'status' => 'active'], $adminId, $ip, $ua);
    check('saving a brand with its own name and slug is not a duplicate', $same['status'] === 'unchanged', json_encode($same));
    $ed = $service->updateBrand($a['id'], ['name' => 'TEST DATA Zeta Renamed', 'slug' => 'test-data-zeta', 'description' => 'Test description', 'display_order' => '7', 'status' => 'active'], $adminId, $ip, $ua);
    $after = $service->find($a['id']);
    check('edit saves new values', $ed['status'] === 'ok' && $after['name'] === 'TEST DATA Zeta Renamed' && $after['description'] === 'Test description' && (int) $after['display_order'] === 7);
    check('edit kept the same slug without a false duplicate error', $after['slug'] === 'test-data-zeta');
    check('edit preserved created_at and id', $after['created_at'] === $row['created_at'] && (int) $after['id'] === $a['id']);
    check('BRAND_UPDATED audit row names the changed fields', (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type='BRAND_UPDATED' AND entity_id=? AND description LIKE '%changed: name, description, display_order%'", [$a['id']])->fetchColumn() === 1);
    $e1 = $service->updateBrand($a['id'], ['name' => 'Honda', 'slug' => 'test-data-zeta', 'description' => '', 'display_order' => '7', 'status' => 'active'], $adminId, $ip, $ua);
    check('edit to another brand\'s name rejected', ($e1['errors']['name'] ?? '') === 'A brand with this name already exists.');
    $e2 = $service->updateBrand($a['id'], ['name' => 'TEST DATA Zeta Renamed', 'slug' => 'honda', 'description' => '', 'display_order' => '7', 'status' => 'active'], $adminId, $ip, $ua);
    check('edit to another brand\'s slug rejected', ($e2['errors']['slug'] ?? '') === 'This slug is already in use.');
    check('rejected edits changed nothing', $service->find($a['id'])['name'] === 'TEST DATA Zeta Renamed' && $service->find($a['id'])['slug'] === 'test-data-zeta');
    check('edit of a missing id reports not_found', $service->updateBrand(99999, valid(), $adminId, $ip, $ua)['status'] === 'not_found');
    check('unrelated brand untouched by edits', $service->find($toyota) == $before);
    run($pdo, "UPDATE brands SET logo_path='uploads/brands/keep.webp' WHERE id=?", [$a['id']]);
    $service->updateBrand($a['id'], ['name' => 'TEST DATA Zeta Renamed', 'slug' => 'test-data-zeta', 'description' => 'Changed again', 'display_order' => '7', 'status' => 'active'], $adminId, $ip, $ua);
    check('edit does not touch the logo reference (out-of-scope field preserved)', run($pdo, 'SELECT logo_path FROM brands WHERE id=?', [$a['id']])->fetchColumn() === 'uploads/brands/keep.webp');
    $viaEdit = $service->updateBrand($a['id'], ['name' => 'TEST DATA Zeta Renamed', 'slug' => 'test-data-zeta', 'description' => 'Changed again', 'display_order' => '7', 'status' => 'inactive'], $adminId, $ip, $ua);
    check('status changed through the edit form is audited as a deactivation', $viaEdit['status'] === 'ok' && (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type='BRAND_DEACTIVATED' AND entity_id=?", [$a['id']])->fetchColumn() === 1);
    $service->updateBrand($a['id'], ['name' => 'TEST DATA Zeta Renamed', 'slug' => 'test-data-zeta', 'description' => 'Changed again', 'display_order' => '7', 'status' => 'active'], $adminId, $ip, $ua);

    // ------------------------------------------------------------------ status
    echo "Status\n";
    $d = $service->setStatus($b['id'], 'inactive', $adminId, $ip, $ua);
    check('active -> inactive', $d['status'] === 'ok' && $service->find($b['id'])['status'] === 'inactive');
    check('BRAND_DEACTIVATED audited', (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type='BRAND_DEACTIVATED' AND entity_id=? AND admin_user_id=?", [$b['id'], $adminId])->fetchColumn() === 1);
    check('inactive filter now lists it', in_array('TEST DATA Alpha', $count('', 'inactive'), true) && !in_array('TEST DATA Alpha', $count('', 'active'), true));
    $d2 = $service->setStatus($b['id'], 'inactive', $adminId, $ip, $ua);
    check('repeating the same target is a no-op (does not flip back)', $d2['status'] === 'unchanged' && $service->find($b['id'])['status'] === 'inactive');
    check('no audit row for a no-op', (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type='BRAND_DEACTIVATED' AND entity_id=?", [$b['id']])->fetchColumn() === 1);
    $u = $service->setStatus($b['id'], 'active', $adminId, $ip, $ua);
    check('inactive -> active', $u['status'] === 'ok' && $service->find($b['id'])['status'] === 'active');
    check('BRAND_ACTIVATED audited', (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type='BRAND_ACTIVATED' AND entity_id=?", [$b['id']])->fetchColumn() === 1);
    check('invalid target status refused', $service->setStatus($b['id'], 'deleted', $adminId, $ip, $ua)['status'] === 'invalid');
    check('status change of a missing brand = not_found', $service->setStatus(99999, 'active', $adminId, $ip, $ua)['status'] === 'not_found');

    // ---------------------------------------------------------------- deletion
    echo "Deletion\n";
    $before = $repo->countAll();
    $del = $service->deleteBrand($c['id'], $adminId, $ip, $ua);
    check('unreferenced brand deleted', $del['status'] === 'ok' && $service->find($c['id']) === null && $repo->countAll() === $before - 1);
    check('BRAND_DELETED audited (row survives the brand)', (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type='BRAND_DELETED' AND entity_id=? AND admin_user_id=?", [$c['id'], $adminId])->fetchColumn() === 1);
    check('deleting a missing brand = not_found', $service->deleteBrand(99999, $adminId, $ip, $ua)['status'] === 'not_found');
    check('deleting the same brand twice = not_found the second time', $service->deleteBrand($c['id'], $adminId, $ip, $ua)['status'] === 'not_found');

    run($pdo, 'INSERT INTO vehicles (brand_id, year, model, price, slug, deleted_at) VALUES (?, 2020, ?, 1.00, ?, ?)',
        [$b['id'], 'TEST DATA model', 'test-data-brand-vehicle', '2026-01-01 00:00:00']);
    $vehicleId = (int) $pdo->lastInsertId();
    check('vehicle count includes archived vehicles (the FK counts them too)', (int) $service->find($b['id'])['vehicle_count'] === 1);
    check('list shows the vehicle count', (int) (array_values(array_filter($repo->search('TEST DATA Alpha', 'all'), static fn ($x) => $x['id'] == $b['id']))[0]['vehicle_count']) === 1);
    $blocked = $service->deleteBrand($b['id'], $adminId, $ip, $ua);
    check('referenced brand cannot be deleted', $blocked['status'] === 'referenced' && $service->find($b['id']) !== null);
    check('the vehicle was not touched (no cascade, no null-out)', (int) run($pdo, 'SELECT brand_id FROM vehicles WHERE id=?', [$vehicleId])->fetchColumn() === $b['id']);
    check('BRAND_DELETION_ATTEMPTED audited', (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type='BRAND_DELETION_ATTEMPTED' AND entity_id=? AND admin_user_id=?", [$b['id'], $adminId])->fetchColumn() === 1);
    $e = $threw(fn () => $repo->delete($b['id']));
    check('database FK (RESTRICT) refuses the delete even if the service check were skipped', $e !== null && in_array((int) $e->errorInfo[1], [1451], true));
    check('foreign key checks are still on', (int) $pdo->query('SELECT @@foreign_key_checks')->fetchColumn() === 1);
    $svcDeactivate = $service->setStatus($b['id'], 'inactive', $adminId, $ip, $ua);
    check('a referenced brand can be deactivated instead', $svcDeactivate['status'] === 'ok');

    // ---------------------------------------------------------- injection/XSS data
    echo "Hostile input is stored as data\n";
    $xss = $service->createBrand(valid(['name' => '<script>alert(1)</script>', 'slug' => 'xss-test', 'description' => '<img src=x onerror=alert(1)>']), $adminId, $ip, $ua);
    check('HTML in name/description is accepted as plain text', $xss['status'] === 'ok');
    $stored = $service->find($xss['id']);
    check('stored byte-for-byte (escaping happens on output)', $stored['name'] === '<script>alert(1)</script>' && $stored['description'] === '<img src=x onerror=alert(1)>');
    check('output escaping neutralises it', !str_contains(htmlspecialchars($stored['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), '<script>'));
    $sqli = $service->createBrand(valid(['name' => "Robert'); DROP TABLE brands;--", 'slug' => 'sqli-test']), $adminId, $ip, $ua);
    check('SQL metacharacters in name are stored as data', $sqli['status'] === 'ok' && $service->find($sqli['id'])['name'] === "Robert'); DROP TABLE brands;--" && $repo->countAll() >= 12);
    check('audit descriptions are capped (no overflow)', (int) run($pdo, 'SELECT MAX(CHAR_LENGTH(description)) FROM audit_logs')->fetchColumn() <= 500);
    check('no secret-like data in brand audit rows', (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type LIKE 'BRAND_%' AND (description LIKE '%password%' OR description LIKE '%token%' OR description LIKE '%session%' OR description LIKE '%secret%')")->fetchColumn() === 0);
} finally {
    $pdo->rollBack();
}

// ------------------------------------------------------ controller: access + errors
echo "Controller authorization and error containment\n";
require_once dirname(__DIR__, 2) . '/app/controllers/BrandController.php';
$staff = ['id' => 1, 'name' => 'Staff', 'role' => 'staff'];
$unknown = ['id' => 1, 'name' => 'Nobody', 'role' => ''];
$controller = new BrandController();
$brandAuditBefore = (int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE event_type LIKE 'BRAND\_%'")->fetchColumn();
$refused = [
    'index'          => $controller->index($staff, []),
    'create (GET)'   => $controller->create($staff, 'GET', [], $ip, $ua),
    'edit'           => $controller->edit($staff, '1', 'GET', [], $ip, $ua),
    'show'           => $controller->show($staff, '1'),
    'changeStatus'   => $controller->changeStatus($staff, ['id' => '1', 'status' => 'inactive'], $ip, $ua),
    'confirmDelete'  => $controller->confirmDelete($staff, '1'),
    'delete'         => $controller->delete($staff, ['id' => '1', 'confirm' => 'yes'], $ip, $ua),
    'index (no role)' => $controller->index($unknown, []),
];
foreach ($refused as $action => $result) {
    check("role without the brands permission is refused: $action", $result['http'] === 403 && $result['problem'] === 'forbidden' && !isset($result['brands']));
}
check('the refused actions changed nothing', array_column($repo->search('', 'all'), 'name') === $approved && (int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE event_type LIKE 'BRAND\\_%'")->fetchColumn() === $brandAuditBefore);
check('administrator role holds the brands permission', AuthorizationService::can('administrator', BrandController::PERMISSION));

$failing = new class extends BrandService {
    public function __construct() {}
    public function listBrands(array $filters): array { throw new RuntimeException("SQLSTATE[HY000] secret detail in C:\\path\\file.php"); }
};
$admin = ['id' => 1, 'name' => 'Admin', 'role' => 'administrator'];
$prev = ini_set('error_log', sys_get_temp_dir() . '/brand_test_errors.log');
$err = (new BrandController($failing))->index($admin, []);
ini_set('error_log', (string) $prev);
check('a failing service gives a 500 with a generic message', $err['http'] === 500 && $err['problem'] === 'error' && $err['message'] === BrandController::MESSAGE_ERROR);
check('the message exposes no SQL, path or exception text', !preg_match('/SQLSTATE|secret|path|file\.php/i', json_encode($err)));
check('the technical detail went to the server log instead', str_contains((string) @file_get_contents(sys_get_temp_dir() . '/brand_test_errors.log'), 'SQLSTATE'));
@unlink(sys_get_temp_dir() . '/brand_test_errors.log');

echo "Cleanup\n";
check('rollback left exactly the 12 approved brands', array_column($repo->search('', 'all'), 'name') === $approved);
check('no test vehicles remain', (int) $pdo->query("SELECT COUNT(*) FROM vehicles WHERE model LIKE 'TEST DATA%'")->fetchColumn() === 0);
check('no brand audit rows remain from this run', (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type LIKE 'BRAND\\_%' AND user_agent = ?", [$ua])->fetchColumn() === 0);

// Rolled-back inserts still consume AUTO_INCREMENT values; put the counters back (MySQL never lowers one below max+1).
foreach (['brands', 'vehicles', 'audit_logs'] as $table) {
    $pdo->exec("ALTER TABLE `$table` AUTO_INCREMENT = 1");
}

echo "\n$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
