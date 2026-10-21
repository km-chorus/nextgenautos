<?php

declare(strict_types=1);

/**
 * Team Management logic tests (spec 08_spec_team.md, sections 48 and 51).
 *
 * CLI only:  php database/tests/team_test.php
 * Requires:  schema.sql (with the category column, migration 002) + seed.sql loaded; DB
 *            credentials in .env; one administrator; GD with WebP, fileinfo, exif; composer install.
 *
 * Covers validation, the schema (category column, constraints), repository queries (search,
 * filters, order, injection strings), TeamService rules (create/edit/status/reorder/delete,
 * photo upload, replacement order of operations, removal, cleanup after failures, audit rows,
 * the public-page query) and controller authorization. Image FILES go to a temporary folder
 * (never to uploads/team); database changes run inside one transaction that is rolled back.
 * Images are generated with GD (see image_fixtures.php). HTTP behaviour (authentication, CSRF,
 * GET refusal, real multipart uploads, XSS) is covered by the HTTP/browser suites.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__, 2) . '/app/services/TeamService.php';
require_once dirname(__DIR__, 2) . '/app/controllers/TeamController.php';
require_once __DIR__ . '/image_fixtures.php';

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

$work = sys_get_temp_dir() . '/vip_team_test_' . bin2hex(random_bytes(4));
$store = $work . '/team';
mkdir($store, 0777, true);
register_shutdown_function(static function () use ($work): void {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($work, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) {
        $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
    }
    @rmdir($work);
});
function upfile(string $bytes, string $name = 'photo.jpg', int $error = UPLOAD_ERR_OK): array
{
    $path = $GLOBALS['work'] . '/' . bin2hex(random_bytes(4)) . '.bin';
    file_put_contents($path, $bytes);
    return ['name' => $name, 'tmp_name' => $path, 'error' => $error, 'size' => strlen($bytes)];
}
function valid(array $over = []): array
{
    return array_merge(['name' => 'TEST DATA Ama Boateng', 'position' => 'Sales Manager', 'category' => 'sales', 'biography' => '', 'display_order' => '50', 'status' => 'active'], $over);
}
function errs(array $over): array
{
    return TeamValidator::validate(valid($over))['errors'];
}

// ------------------------------------------------------------------ validator
echo "Validator\n";
$ok = TeamValidator::validate(valid());
check('a valid member has no errors', $ok['errors'] === [], json_encode($ok['errors']));
foreach (['John Mensah', "Ama O'Boateng", 'Jean-Luc Mbeki', "Zo\u{eb} N\u{2019}Diaye", "\u{4f60}\u{597d} Li", 'Michael Antwi Jr.'] as $n) {
    check('normal names are accepted: ' . $n, !isset(errs(['name' => $n])['name']));
}
check('name is trimmed and inner spaces collapsed', TeamValidator::validate(valid(['name' => "  John   Mensah\t "]))['values']['name'] === 'John Mensah');
check('missing name rejected', isset(errs(['name' => '   '])['name']) && isset(TeamValidator::validate(['position' => 'x', 'category' => 'sales', 'display_order' => '1', 'status' => 'active'])['errors']['name']));
check('name of 151 characters rejected, 150 accepted', isset(errs(['name' => str_repeat('a', 151)])['name']) && !isset(errs(['name' => str_repeat('a', 150)])['name']));
check('a name with no letter or digit is rejected', isset(errs(['name' => '---'])['name']) && isset(errs(['name' => '...'])['name']));
check('control, NUL and zero-width characters rejected in name and position', isset(errs(['name' => "Jo\0hn"])['name']) && isset(errs(['name' => "Jo\u{200B}hn"])['name']) && isset(errs(['position' => "CE\x07O"])['position']));
check('invalid UTF-8 and arrays rejected', isset(errs(['name' => "Jo\xC3\x28hn"])['name']) && isset(errs(['name' => ['x']])['name']) && isset(errs(['position' => ['x']])['position']));
check('missing position rejected; 151 chars rejected', isset(errs(['position' => ''])['position']) && isset(errs(['position' => str_repeat('a', 151)])['position']));
check('any title is accepted (nothing hardcoded)', !isset(errs(['position' => 'Chief Executive Officer'])['position']) && !isset(errs(['position' => 'Head of Vehicle Sourcing, Canada'])['position']));
foreach (array_keys(TeamValidator::CATEGORIES) as $c) { check("category $c accepted", !isset(errs(['category' => $c])['category'])); }
foreach (['', 'ceo', 'LEADERSHIP', "sales' OR '1'='1", 'unknown', ['sales']] as $bad) {
    check('category rejected: ' . json_encode($bad), isset(errs(['category' => $bad])['category']));
}
check('six categories exist with readable labels', count(TeamValidator::CATEGORIES) === 6 && TeamValidator::categoryLabel('leadership') === 'CEO / Leadership' && TeamValidator::categoryLabel('investor_partner') === 'Investor / Partner' && TeamValidator::categoryLabel('nonsense') === 'Other');
check('biography optional: empty is null; multiline kept; CRLF normalised', TeamValidator::validate(valid(['biography' => "  \n "]))['values']['biography'] === null && TeamValidator::validate(valid(['biography' => "Line 1\r\nLine 2"]))['values']['biography'] === "Line 1\nLine 2");
check('biography limit: 1500 accepted, 1501 rejected; NUL rejected', !isset(errs(['biography' => str_repeat('a', 1500)])['biography']) && isset(errs(['biography' => str_repeat('a', 1501)])['biography']) && isset(errs(['biography' => "a\0b"])['biography']));
check('HTML in the biography is accepted as plain text (escaped on output)', TeamValidator::validate(valid(['biography' => '<b>x</b>']))['values']['biography'] === '<b>x</b>');
foreach (['0' => true, '1' => true, '9999' => true, '10000' => false, '-1' => false, '1.5' => false, 'abc' => false, '' => false, '1e3' => false, ' 7 ' => true, '99999999999' => false] as $order => $good) {
    $order = (string) $order;
    check('display order ' . json_encode($order) . ($good ? ' accepted' : ' rejected'), $good === !isset(errs(['display_order' => $order])['display_order']));
}
check('status: active/inactive accepted; anything else rejected', !isset(errs(['status' => 'active'])['status']) && !isset(errs(['status' => 'inactive'])['status']) && isset(errs(['status' => 'deleted'])['status']) && isset(errs(['status' => ''])['status']) && isset(errs(['status' => ["active"]])['status']) && isset(errs(['status' => "active' OR 1"])['status']));
check('every field reports at once', count(errs(['name' => '', 'position' => '', 'category' => 'x', 'display_order' => 'x', 'status' => 'x'])) === 5);
foreach (['1', '12', '4294967295'] as $good) { check("id $good valid", TeamValidator::id($good) === (int) $good); }
foreach (['0', '-1', '01', '1.0', 'abc', '', '1 OR 1=1', '4294967296', ' 1', null, ['1'], 5] as $bad) { check('id rejected: ' . json_encode($bad), TeamValidator::id($bad) === null); }
check('filters: defaults, junk and arrays fall back safely', TeamValidator::filters([]) === ['q' => '', 'category' => '', 'status' => 'all']
    && TeamValidator::filters(['q' => ['a'], 'category' => ['x'], 'status' => ['active']]) === ['q' => '', 'category' => '', 'status' => 'all']
    && TeamValidator::filters(['category' => "x' OR 1", 'status' => "' OR 1"]) === ['q' => '', 'category' => '', 'status' => 'all']);
check('filters: valid values kept; search capped at 100', TeamValidator::filters(['q' => 'sales', 'category' => 'management', 'status' => 'inactive']) === ['q' => 'sales', 'category' => 'management', 'status' => 'inactive'] && mb_strlen(TeamValidator::filters(['q' => str_repeat('a', 500)])['q']) === 100);
check('id lists: valid list parsed; malformed ones rejected', TeamValidator::parseIdList(['3', '1', '2']) === [3, 1, 2]
    && TeamValidator::parseIdList([3, 1]) === null && TeamValidator::parseIdList([]) === null && TeamValidator::parseIdList('1') === null && TeamValidator::parseIdList(['a' => '1']) === null
    && TeamValidator::parseIdList(['1', 'abc']) === null && TeamValidator::parseIdList(['1', '0']) === null && TeamValidator::parseIdList([['1']]) === null && TeamValidator::parseIdList(['1 OR 1=1']) === null);

// -------------------------------------------------------------------- schema
echo "Schema\n";
$pdo = Database::connection();
$col = $pdo->query("SELECT COLUMN_TYPE, COLUMN_DEFAULT, IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'team_members' AND COLUMN_NAME = 'category'")->fetch();
check('team_members has the category column: ENUM of the six values, NOT NULL, default other', $col !== false && $col['IS_NULLABLE'] === 'NO' && $col['COLUMN_DEFAULT'] === 'other'
    && $col['COLUMN_TYPE'] === "enum('leadership','investor_partner','management','sales','operations','other')");
check('the category list in code matches the database enum exactly', array_keys(TeamValidator::CATEGORIES) === ['leadership', 'investor_partner', 'management', 'sales', 'operations', 'other']);
$realTeam = (int) $pdo->query('SELECT COUNT(*) FROM team_members')->fetchColumn();
$realPhotos = array_values(array_diff(scandir(dirname(__DIR__, 2) . '/uploads/team') ?: [], ['.', '..', '.gitkeep']));
echo "  (baseline: $realTeam real team member row(s); " . count($realPhotos) . " photo file(s) in uploads/team: untouched by this run)\n";
$adminId = (int) $pdo->query('SELECT id FROM admin_users ORDER BY id LIMIT 1')->fetchColumn();
check('an administrator row exists for audit foreign keys', $adminId > 0);
check('the environment can process images', ImageService::missingRequirements() === []);

$ip = '203.0.113.9';
$ua = 'team_test';
$jpeg = fx_photo(1200, 1600, 'jpeg');
$pdo->beginTransaction();
try {
    $images = new ImageService($store, ImageService::KIND_TEAM);
    $repo = new TeamRepository($pdo);
    $service = new TeamService($pdo, $repo, new AuditLogRepository($pdo), $images);
    $make = static fn (array $over = [], ?array $photo = null) => $GLOBALS['service']->createMember(valid($over), $photo, $GLOBALS['adminId'], $GLOBALS['ip'], $GLOBALS['ua'], false);
    $files = static fn (): array => array_values(array_diff(scandir($GLOBALS['store']) ?: [], ['.', '..']));
    $row = static fn (int $id): ?array => $GLOBALS['repo']->find($id);

    // --------------------------------------------------------------- create
    echo "Create\n";
    $a = $make(['name' => 'TEST DATA Ceo Person', 'position' => 'Chief Executive Officer', 'category' => 'leadership', 'biography' => "Leads the company.\nSecond line.", 'display_order' => '1']);
    check('valid member created (no photo)', $a['status'] === 'ok' && $a['id'] > 0, json_encode($a['errors']));
    $ra = $row($a['id']);
    check('stored values are exact', $ra['name'] === 'TEST DATA Ceo Person' && $ra['position'] === 'Chief Executive Officer' && $ra['category'] === 'leadership' && $ra['biography'] === "Leads the company.\nSecond line." && (int) $ra['display_order'] === 1 && $ra['status'] === 'active' && $ra['photo_path'] === null);
    check('timestamps are set', $ra['created_at'] !== null && $ra['updated_at'] !== null);
    check('TEAM_MEMBER_CREATED audited with admin and entity', (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type='TEAM_MEMBER_CREATED' AND admin_user_id=? AND entity_type='team_member' AND entity_id=?", [$adminId, $a['id']])->fetchColumn() === 1);
    $before = $repo->countAll();
    foreach ([['missing name', ['name' => '']], ['missing position', ['position' => '']], ['invalid category', ['category' => 'boss']], ['invalid status', ['status' => 'gone']], ['invalid display order', ['display_order' => '-3']]] as [$label, $over]) {
        $r = $make($over);
        check("create rejected: $label", $r['status'] === 'invalid' && $r['errors'] !== [] && $r['id'] === null);
    }
    check('rejected submissions created nothing and logged no creation', $repo->countAll() === $before && (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type='TEAM_MEMBER_CREATED' AND user_agent=?", [$ua])->fetchColumn() === 1);

    // ----------------------------------------------------------- photo upload
    echo "Photo upload\n";
    $b = $make(['name' => 'TEST DATA Photo Person', 'category' => 'management', 'display_order' => '2'], upfile($jpeg, 'portrait.jpg'));
    $rb = $row($b['id']);
    check('create with a valid photo succeeds and stores a generated path', $b['status'] === 'ok' && ImageService::isStoredPath((string) $rb['photo_path'], ImageService::KIND_TEAM) && str_starts_with((string) $rb['photo_path'], 'uploads/team/'), (string) $rb['photo_path']);
    $mainFile = $store . '/' . basename((string) $rb['photo_path']);
    $thumbFile = $store . '/' . basename(ImageService::thumbPath((string) $rb['photo_path']));
    $mi = getimagesize($mainFile); $ti = getimagesize($thumbFile);
    check('stored photo is WebP, 1200x1600 portrait kept (ratio preserved, not upscaled or cropped)', $mi[2] === IMAGETYPE_WEBP && $mi[0] === 1200 && $mi[1] === 1600, json_encode($mi));
    check('thumbnail is WebP and fits 400x533 (ratio kept)', $ti[2] === IMAGETYPE_WEBP && $ti[0] <= 400 && $ti[1] <= 533 && abs($ti[0] / $ti[1] - 0.75) < 0.02, json_encode($ti));
    check('file names are random; the original name is not used; only the two processed files exist', count($files()) === 2 && preg_match('/^[a-f0-9]{32}(-t)?\.webp$/', $files()[0]) === 1);
    check('TEAM_MEMBER_PHOTO_UPLOADED audited', (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type='TEAM_MEMBER_PHOTO_UPLOADED' AND entity_id=?", [$b['id']])->fetchColumn() === 1);
    $bigPhoto = $make(['name' => 'TEST DATA Big Source', 'display_order' => '3'], upfile(fx_photo(3000, 2400, 'jpeg'), 'big.jpg'));
    $bi = getimagesize($store . '/' . basename((string) $row($bigPhoto['id'])['photo_path']));
    check('a large source is scaled down to fit 1200 x 1600 (3000x2400 -> 1200x960)', $bi[0] === 1200 && $bi[1] === 960, json_encode($bi));
    $smallPhoto = $make(['name' => 'TEST DATA Small Source', 'display_order' => '4'], upfile(fx_photo(500, 500, 'png'), 'small.png'));
    $si = getimagesize($store . '/' . basename((string) $row($smallPhoto['id'])['photo_path']));
    check('a small 500x500 PNG is accepted (minimum is 400x400) and NOT upscaled', $smallPhoto['status'] === 'ok' && $si[0] === 500 && $si[1] === 500 && $si[2] === IMAGETYPE_WEBP);
    check('a 300x300 photo is refused as too small, with the minimum in the message', ($r = $make(['name' => 'TEST DATA Tiny'], upfile(fx_photo(300, 300, 'jpeg'), 't.jpg'))) && $r['status'] === 'invalid' && str_contains((string) $r['errors']['photo'], '400 x 400'));
    $webpPhoto = $make(['name' => 'TEST DATA Webp Source', 'display_order' => '5'], upfile(fx_photo(900, 1200, 'webp'), 'w.webp'));
    check('a WebP source works too', $webpPhoto['status'] === 'ok' && getimagesize($store . '/' . basename((string) $row($webpPhoto['id'])['photo_path']))[2] === IMAGETYPE_WEBP);
    $countBeforeBad = $repo->countAll();
    $filesBeforeBad = $files();
    $badPhotos = [
        'PHP script named .jpg' => fx_php_script(), 'HTML' => fx_html(), 'SVG with script' => fx_svg(), 'GIF' => fx_gif(), 'PDF' => fx_pdf(), 'EXE' => fx_exe(),
        'corrupt JPEG' => fx_truncated_jpeg(), 'GIF/PHP polyglot' => fx_gif_php_polyglot(), 'oversized (over 10 MB)' => fx_oversized_jpeg(), '20000x20000 header' => fx_png_claiming(20000, 20000), 'empty file' => '',
    ];
    foreach ($badPhotos as $label => $bytes) {
        $r = $make(['name' => 'TEST DATA Bad ' . substr(md5($label), 0, 6)], upfile($bytes, 'evil.jpg'));
        check("invalid photo rejected: $label", $r['status'] === 'invalid' && isset($r['errors']['photo']) && $r['id'] === null, json_encode($r['errors']));
    }
    check('...and the unsupported-format message is the specified sentence', ($r = $make([], upfile(fx_php_script(), 'x.jpg'))) && $r['errors']['photo'] === 'The selected photo is not supported.');
    check('...a rejected photo creates no member and writes no file', $repo->countAll() === $countBeforeBad && $files() === $filesBeforeBad);
    check('the member text is not saved when the photo is bad (nothing half-saved)', (int) run($pdo, "SELECT COUNT(*) FROM team_members WHERE name LIKE 'TEST DATA Bad %'")->fetchColumn() === 0);
    check('a PHP upload error code (too big for the server) is reported clearly', ($r = $make([], upfile('', 'x.jpg', UPLOAD_ERR_FORM_SIZE))) && $r['errors']['photo'] === ImageValidator::ERROR_SIZE);
    check('photo rejections are audited with a reason code only', (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type='TEAM_MEMBER_PHOTO_REJECTED' AND user_agent=? AND description REGEXP '^Photo rejected: [a-z0-9_]+$'", [$ua])->fetchColumn() >= 11);
    $polyMember = $make(['name' => 'TEST DATA Polyglot Real Image', 'display_order' => '6'], upfile(fx_jpeg_with_php_payload(), 'poly.jpg'));
    $polyBytes = (string) file_get_contents($store . '/' . basename((string) $row($polyMember['id'])['photo_path']));
    check('a real image carrying a PHP payload is re-encoded: the stored file contains no PHP', $polyMember['status'] === 'ok' && !str_contains($polyBytes, '<?php') && !str_contains($polyBytes, 'PWNED'));
    check('with HTTP-upload enforcement on, a path that is not a real upload is rejected', ($r = $service->createMember(valid(['name' => 'TEST DATA Forged Path']), upfile($jpeg), $adminId, $ip, $ua, true)) && $r['status'] === 'invalid');
    run($pdo, "DELETE FROM team_members WHERE name LIKE 'TEST DATA Polyglot%' OR name LIKE 'TEST DATA Webp%' OR name LIKE 'TEST DATA Small%' OR name LIKE 'TEST DATA Big%'");

    // ---------------------------------------------------------------- edit
    echo "Edit\n";
    $newName = $service->updateMember($a['id'], valid(['name' => 'TEST DATA Ceo Renamed', 'position' => 'Chief Executive Officer', 'category' => 'leadership', 'biography' => "Leads the company.\nSecond line.", 'display_order' => '1']), null, $adminId, $ip, $ua, false);
    check('change name', $newName['status'] === 'ok' && $row($a['id'])['name'] === 'TEST DATA Ceo Renamed');
    check('change position, category, biography, order and status in one save', ($r = $service->updateMember($a['id'], valid(['name' => 'TEST DATA Ceo Renamed', 'position' => 'Managing Director', 'category' => 'management', 'biography' => 'New bio', 'display_order' => '7', 'status' => 'inactive']), null, $adminId, $ip, $ua, false))['status'] === 'ok'
        && ($x = $row($a['id'])) && $x['position'] === 'Managing Director' && $x['category'] === 'management' && $x['biography'] === 'New bio' && (int) $x['display_order'] === 7 && $x['status'] === 'inactive');
    check('TEAM_MEMBER_UPDATED audit names the changed fields', (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type='TEAM_MEMBER_UPDATED' AND entity_id=? AND description LIKE '%position, category, biography, display_order, status%'", [$a['id']])->fetchColumn() === 1);
    check('a status change through the edit form is also audited as a deactivation', (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type='TEAM_MEMBER_DEACTIVATED' AND entity_id=?", [$a['id']])->fetchColumn() === 1);
    check('saving with no change reports "unchanged" and writes no audit row', ($r = $service->updateMember($a['id'], valid(['name' => 'TEST DATA Ceo Renamed', 'position' => 'Managing Director', 'category' => 'management', 'biography' => 'New bio', 'display_order' => '7', 'status' => 'inactive']), null, $adminId, $ip, $ua, false))['status'] === 'unchanged');
    check('invalid edits are rejected with field errors and change nothing', ($r = $service->updateMember($a['id'], valid(['name' => '', 'category' => 'x']), null, $adminId, $ip, $ua, false))['status'] === 'invalid' && isset($r['errors']['name'], $r['errors']['category']) && $row($a['id'])['name'] === 'TEST DATA Ceo Renamed');
    check('edit of a missing id = not_found', $service->updateMember(99999, valid(), null, $adminId, $ip, $ua, false)['status'] === 'not_found');

    echo "Photo replacement, keeping and removal\n";
    $oldPath = (string) $row($b['id'])['photo_path'];
    $keep = $service->updateMember($b['id'], valid(['name' => 'TEST DATA Photo Person', 'category' => 'management', 'display_order' => '2', 'position' => 'Edited position']), null, $adminId, $ip, $ua, false);
    check('editing the text WITHOUT a new photo keeps the existing photo reference and files', $keep['status'] === 'ok' && $row($b['id'])['photo_path'] === $oldPath && is_file($store . '/' . basename($oldPath)) && !$keep['photo_replaced']);
    $rep = $service->updateMember($b['id'], valid(['name' => 'TEST DATA Photo Person', 'category' => 'management', 'display_order' => '2', 'position' => 'Edited position']), upfile(fx_photo(1000, 1300, 'png'), 'new.png'), $adminId, $ip, $ua, false);
    $newPath = (string) $row($b['id'])['photo_path'];
    check('replacing the photo: the record now points at a NEW generated file', $rep['status'] === 'ok' && $rep['photo_replaced'] === true && $newPath !== $oldPath && ImageService::isStoredPath($newPath, ImageService::KIND_TEAM));
    check('...the new file exists and the OLD main file and thumbnail are gone', is_file($store . '/' . basename($newPath)) && !is_file($store . '/' . basename($oldPath)) && !is_file($store . '/' . basename(ImageService::thumbPath($oldPath))));
    check('...only the new pair remains for this member (no leftovers)', count(array_filter($files(), static fn ($f) => str_starts_with($f, substr(basename($newPath), 0, 32)))) === 2);
    check('TEAM_MEMBER_PHOTO_REPLACED audited', (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type='TEAM_MEMBER_PHOTO_REPLACED' AND entity_id=?", [$b['id']])->fetchColumn() === 1);
    $keepPath = (string) $row($b['id'])['photo_path'];
    $filesNow = $files();
    $badRep = $service->updateMember($b['id'], valid(['name' => 'TEST DATA Photo Person', 'category' => 'management', 'display_order' => '2', 'position' => 'Should not be saved']), upfile(fx_php_script(), 'x.jpg'), $adminId, $ip, $ua, false);
    check('a replacement that fails validation leaves the old photo AND the text untouched', $badRep['status'] === 'invalid' && $row($b['id'])['photo_path'] === $keepPath && $row($b['id'])['position'] === 'Edited position' && is_file($store . '/' . basename($keepPath)) && $files() === $filesNow);
    // photo processing fails (storage folder gone) -> old photo intact
    $brokenService = new TeamService($pdo, $repo, new AuditLogRepository($pdo), new ImageService($work . '/nowhere', ImageService::KIND_TEAM));
    $prevLog = ini_set('error_log', $work . '/e1.log');
    $procFail = $brokenService->updateMember($b['id'], valid(['name' => 'TEST DATA Photo Person', 'category' => 'management', 'display_order' => '2', 'position' => 'Edited position']), upfile($jpeg, 'x.jpg'), $adminId, $ip, $ua, false);
    ini_set('error_log', (string) $prevLog);
    check('if processing the new photo fails, the old photo remains intact ("The photo could not be processed.")', $procFail['status'] === 'invalid' && $procFail['errors']['photo'] === 'The photo could not be processed.' && $row($b['id'])['photo_path'] === $keepPath && is_file($store . '/' . basename($keepPath)));
    // database fails after the new file was written -> new file removed, old photo intact
    $filesBeforeDbFail = $files();
    run($pdo, 'SAVEPOINT dbfail');
    $prevLog = ini_set('error_log', $work . '/e2.log');
    $threw = false;
    try { $service->updateMember($b['id'], valid(['name' => 'TEST DATA Photo Person', 'category' => 'management', 'display_order' => '2', 'position' => 'Another change']), upfile($jpeg, 'y.jpg'), 999999999, $ip, $ua, false); }
    catch (Throwable $e) { $threw = true; }      // the audit insert violates its foreign key: the save fails after the file was written
    ini_set('error_log', (string) $prevLog);
    run($pdo, 'ROLLBACK TO SAVEPOINT dbfail');
    check('if the database update fails, the NEW file is cleaned up and the old photo stays', $threw && $files() === $filesBeforeDbFail && $row($b['id'])['photo_path'] === $keepPath && is_file($store . '/' . basename($keepPath)));
    $rm = $service->removePhoto($b['id'], $adminId, $ip, $ua);
    check('removing the photo clears the reference and deletes both files; the member remains', $rm['status'] === 'ok' && $row($b['id'])['photo_path'] === null && $row($b['id']) !== null && !is_file($store . '/' . basename($keepPath)) && !is_file($store . '/' . basename(ImageService::thumbPath($keepPath))));
    check('TEAM_MEMBER_PHOTO_REMOVED audited; removing again is "unchanged"; a missing member is not found', (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type='TEAM_MEMBER_PHOTO_REMOVED' AND entity_id=?", [$b['id']])->fetchColumn() === 1
        && $service->removePhoto($b['id'], $adminId, $ip, $ua)['status'] === 'unchanged' && $service->removePhoto(99999, $adminId, $ip, $ua)['status'] === 'not_found');
    $add = $service->updateMember($b['id'], valid(['name' => 'TEST DATA Photo Person', 'category' => 'management', 'display_order' => '2', 'position' => 'Edited position']), upfile($jpeg, 'again.jpg'), $adminId, $ip, $ua, false);
    check('a member without a photo can get one through the edit form (audited as an upload, not a replacement)', $add['status'] === 'ok' && $row($b['id'])['photo_path'] !== null && (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type='TEAM_MEMBER_PHOTO_UPLOADED' AND entity_id=?", [$b['id']])->fetchColumn() === 2);

    // ---------------------------------------------------------------- status
    echo "Status\n";
    $st = $service->setStatus($a['id'], 'active', $adminId, $ip, $ua);
    check('activate', $st['status'] === 'ok' && $row($a['id'])['status'] === 'active' && (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type='TEAM_MEMBER_ACTIVATED' AND entity_id=?", [$a['id']])->fetchColumn() === 1);
    check('deactivate: the member is kept, not deleted', $service->setStatus($a['id'], 'inactive', $adminId, $ip, $ua)['status'] === 'ok' && $row($a['id']) !== null && $row($a['id'])['status'] === 'inactive');
    check('repeating a status is a no-op, not audited again', $service->setStatus($a['id'], 'inactive', $adminId, $ip, $ua)['status'] === 'unchanged' && (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type='TEAM_MEMBER_DEACTIVATED' AND entity_id=?", [$a['id']])->fetchColumn() === 2);
    check('invalid target and missing member', $service->setStatus($a['id'], 'deleted', $adminId, $ip, $ua)['status'] === 'invalid' && $service->setStatus(99999, 'active', $adminId, $ip, $ua)['status'] === 'not_found');
    $service->setStatus($a['id'], 'active', $adminId, $ip, $ua);

    // ---------------------------------------------------------- list / search
    echo "Search, filters, order\n";
    $c = $make(['name' => 'TEST DATA Kofi Sales', 'position' => 'Sales Consultant', 'category' => 'sales', 'display_order' => '3', 'status' => 'active']);
    $d = $make(['name' => 'TEST DATA Yaw Ops', 'position' => 'Operations Manager', 'category' => 'operations', 'display_order' => '3', 'status' => 'inactive']);
    $list = static fn (array $f = []): array => array_column($GLOBALS['service']->listMembers(TeamValidator::filters($f))['members'], 'name');
    $mine = static fn (array $names): array => array_values(array_filter($names, static fn ($n) => str_starts_with($n, 'TEST DATA')));
    check('search by name', $mine($list(['q' => 'kofi'])) === ['TEST DATA Kofi Sales']);
    check('search by position', $mine($list(['q' => 'operations manager'])) === ['TEST DATA Yaw Ops']);
    check('empty search lists everyone', count($mine($list())) === 4);
    check('search is case-insensitive and partial', $mine($list(['q' => 'SALES'])) === ['TEST DATA Kofi Sales'] && $mine($list(['q' => 'ofi sal'])) === ['TEST DATA Kofi Sales']);
    check('wildcards % and _ are literal; special characters are safe', $mine($list(['q' => '%'])) === [] && $mine($list(['q' => '_'])) === [] && $mine($list(['q' => "O'Brien"])) === [] && $mine($list(['q' => '\\'])) === []);
    foreach (["' OR '1'='1", "x'; DROP TABLE team_members; --", '" OR ""="', "1' UNION SELECT password_hash FROM admin_users-- "] as $evil) {
        check('injection string in search: no rows, no error: ' . substr($evil, 0, 24), $mine($list(['q' => $evil])) === []);
    }
    check('the table survived the injection attempts', $repo->countAll() >= 4);
    check('category filter', $mine($list(['category' => 'operations'])) === ['TEST DATA Yaw Ops'] && $mine($list(['category' => 'leadership'])) === []);
    check('status filter', $mine($list(['status' => 'inactive'])) === ['TEST DATA Yaw Ops'] && !in_array('TEST DATA Yaw Ops', $list(['status' => 'active']), true));
    check('filters combine', $mine($list(['category' => 'sales', 'status' => 'active', 'q' => 'kofi'])) === ['TEST DATA Kofi Sales'] && $mine($list(['category' => 'sales', 'status' => 'inactive'])) === []);
    $ordered = $mine($list());
    check('order is display_order ASC, then name ASC (deterministic)', $ordered === ['TEST DATA Photo Person', 'TEST DATA Kofi Sales', 'TEST DATA Yaw Ops', 'TEST DATA Ceo Renamed'], json_encode($ordered));
    check('equal display_order values are ordered by name', array_search('TEST DATA Kofi Sales', $ordered, true) < array_search('TEST DATA Yaw Ops', $ordered, true));
    $pub = array_column($service->publicMembers(), 'name');
    check('the public-page query returns ACTIVE members only, in display order, with every field the public page needs', !in_array('TEST DATA Yaw Ops', $pub, true) && in_array('TEST DATA Kofi Sales', $pub, true)
        && array_keys($service->publicMembers()[0]) === ['id', 'name', 'position', 'category', 'biography', 'photo_path', 'display_order', 'status']);

    // -------------------------------------------------------------- reorder
    echo "Reorder\n";
    $allIds = $repo->allIdsInOrder();
    $new = array_reverse($allIds);
    $ro = $service->reorder(array_map('strval', $new), $adminId, $ip, $ua);
    check('a valid reorder succeeds: the list now follows the submitted order, display_order is 1..n', $ro['status'] === 'ok' && $repo->allIdsInOrder() === $new
        && array_map(static fn ($r) => (int) $r['display_order'], $repo->search(['q' => '', 'category' => '', 'status' => 'all'])) === range(1, count($new)));
    check('TEAM_MEMBER_REORDERED audited', (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type='TEAM_MEMBER_REORDERED' AND admin_user_id=?", [$adminId])->fetchColumn() === 1);
    check('submitting the same order again is unchanged', $service->reorder(array_map('strval', $new), $adminId, $ip, $ua)['status'] === 'unchanged');
    $snapshot = $repo->allIdsInOrder();
    $bad = [
        'a missing member' => array_map('strval', array_slice($new, 1)), 'a nonexistent id' => array_map('strval', array_merge($new, [999999])), 'a duplicate id' => array_map('strval', array_merge(array_slice($new, 1), [$new[1]])),
        'a nonexistent id swapped in' => array_map('strval', array_merge(array_slice($new, 1), [999999])), 'a text id' => array_merge(array_map('strval', array_slice($new, 1)), ['abc']), 'an SQL string' => array_merge(array_map('strval', array_slice($new, 1)), ['1 OR 1=1']),
        'a negative id' => array_merge(array_map('strval', array_slice($new, 1)), ['-1']), 'integers instead of strings' => $new, 'an empty list' => [], 'not a list' => 'abc', 'an associative array' => ['a' => (string) $new[0]], 'nested arrays' => [[(string) $new[0]]], 'null' => null,
    ];
    foreach ($bad as $label => $ids) {
        check("reorder rejected: $label", $service->reorder($ids, $adminId, $ip, $ua)['status'] === 'invalid');
    }
    check('rejected reorders changed nothing', $repo->allIdsInOrder() === $snapshot);

    // --------------------------------------------------------------- delete
    echo "Delete\n";
    $photoPath = (string) $row($b['id'])['photo_path'];
    $filesBeforeDelete = count($files());
    $del = $service->deleteMember($b['id'], $adminId, $ip, $ua);
    check('valid delete removes the record AND the photo files', $del['status'] === 'ok' && $row($b['id']) === null && !is_file($store . '/' . basename($photoPath)) && !is_file($store . '/' . basename(ImageService::thumbPath($photoPath))) && count($files()) === $filesBeforeDelete - 2);
    check('TEAM_MEMBER_DELETED audited (the row survives the member)', (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type='TEAM_MEMBER_DELETED' AND entity_id=? AND admin_user_id=?", [$b['id'], $adminId])->fetchColumn() === 1);
    check('deleting a missing id or twice = not_found', $service->deleteMember(99999, $adminId, $ip, $ua)['status'] === 'not_found' && $service->deleteMember($b['id'], $adminId, $ip, $ua)['status'] === 'not_found');
    $withPhoto = $make(['name' => 'TEST DATA Vanishing Photo', 'display_order' => '9'], upfile($jpeg, 'v.jpg'));
    unlink($store . '/' . basename((string) $row($withPhoto['id'])['photo_path']));
    $prevLog = ini_set('error_log', $work . '/e3.log');
    $delMissing = $service->deleteMember($withPhoto['id'], $adminId, $ip, $ua);
    ini_set('error_log', (string) $prevLog);
    check('a member whose photo file is already missing is still deleted safely (and the condition is logged)', $delMissing['status'] === 'ok' && $row($withPhoto['id']) === null && str_contains((string) @file_get_contents($work . '/e3.log'), 'was missing'));
    $decoy = $make(['name' => 'TEST DATA Decoy', 'display_order' => '10']);
    run($pdo, 'UPDATE team_members SET photo_path = ? WHERE id = ?', ['../../.env', $decoy['id']]);
    $prevLog = ini_set('error_log', $work . '/e4.log');
    $service->deleteMember($decoy['id'], $adminId, $ip, $ua);
    ini_set('error_log', (string) $prevLog);
    check('a tampered photo path in the database can never make the service delete another file (only the generated shape is accepted)', is_file(dirname(__DIR__, 2) . '/.env') && $row($decoy['id']) === null);

    // ------------------------------------------------------ hostile data
    echo "Hostile data\n";
    $x = $make(['name' => '<script>alert(1)</script> & "q"', 'position' => '"><img src=x onerror=alert(1)>', 'biography' => '<b>x</b><script>alert(2)</script>', 'display_order' => '11']);
    $xr = $row($x['id']);
    check('HTML in name, position and biography is stored byte for byte (escaped on output)', $x['status'] === 'ok' && $xr['name'] === '<script>alert(1)</script> & "q"' && $xr['position'] === '"><img src=x onerror=alert(1)>' && $xr['biography'] === '<b>x</b><script>alert(2)</script>');
    check('escaping neutralises it', !str_contains(e($xr['name']), '<script>') && str_contains(e($xr['name']), '&lt;script&gt;'));
    $sq = $make(['name' => "Robert'); DROP TABLE team_members;--", 'display_order' => '12']);
    check('SQL metacharacters are stored as data; the table survives', $sq['status'] === 'ok' && $row($sq['id'])['name'] === "Robert'); DROP TABLE team_members;--" && $repo->countAll() > 3);
    check('audit rows hold no secrets and no file names', (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type LIKE 'TEAM\\_MEMBER\\_%' AND (description LIKE '%password%' OR description LIKE '%token%' OR description LIKE '%session%' OR description LIKE '%.jpg%' OR description LIKE '%.png%' OR description LIKE '%.webp%')")->fetchColumn() === 0);
    check('alt text helper states only the name', TeamService::photoAlt('Ama Boateng') === 'Photo of Ama Boateng');
} finally {
    $pdo->rollBack();
}

// ------------------------------------------------------ controller
echo "Controller authorization, helpers and error containment\n";
$staff = ['id' => 1, 'name' => 'Staff', 'role' => 'staff'];
$controller = new TeamController();
$auditBefore = (int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE event_type LIKE 'TEAM\\_MEMBER\\_%'")->fetchColumn();
$refused = [
    'index' => $controller->index($staff, []), 'create (GET)' => $controller->create($staff, 'GET', [], [], [], $ip, $ua), 'create (POST)' => $controller->create($staff, 'POST', valid(), [], [], $ip, $ua),
    'edit' => $controller->edit($staff, '1', 'GET', [], [], [], $ip, $ua), 'show' => $controller->show($staff, '1'), 'changeStatus' => $controller->changeStatus($staff, ['id' => '1', 'status' => 'inactive'], $ip, $ua),
    'reorder' => $controller->reorder($staff, ['order' => ['1']], $ip, $ua), 'removePhoto' => $controller->removePhoto($staff, ['id' => '1'], $ip, $ua),
    'confirmDelete' => $controller->confirmDelete($staff, '1'), 'delete' => $controller->delete($staff, ['id' => '1', 'confirm' => 'yes'], $ip, $ua), 'no role' => $controller->index(['id' => 1, 'name' => 'x', 'role' => ''], []),
];
foreach ($refused as $action => $result) {
    check("role without the team permission is refused: $action", $result['http'] === 403 && $result['problem'] === 'forbidden');
}
check('the refused actions changed nothing', (int) $pdo->query('SELECT COUNT(*) FROM team_members')->fetchColumn() === $realTeam && (int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE event_type LIKE 'TEAM\\_MEMBER\\_%'")->fetchColumn() === $auditBefore);
check('administrator role holds the team permission', AuthorizationService::can('administrator', TeamController::PERMISSION));
check('singlePhoto: none chosen = null; a normal file passes; odd shapes become an invalid upload', TeamController::singlePhoto([]) === null
    && TeamController::singlePhoto(['photo' => ['name' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0]]) === null
    && TeamController::singlePhoto(['photo' => ['name' => 'a.jpg', 'tmp_name' => '/tmp/x', 'error' => 0, 'size' => 5]]) === ['name' => 'a.jpg', 'tmp_name' => '/tmp/x', 'error' => 0, 'size' => 5]
    && TeamController::singlePhoto(['photo' => ['name' => ['a.jpg'], 'tmp_name' => ['/tmp/x'], 'error' => [0], 'size' => [5]]])['error'] === UPLOAD_ERR_PARTIAL);
$limits = TeamController::photoLimits();
check('photo limits describe what the server accepts', $limits['maxBytes'] > 0 && $limits['maxBytes'] <= ImageValidator::MAX_BYTES && str_ends_with($limits['limitText'], 'MB') && $limits['notice'] === null);

$failing = new class extends TeamService {
    public function __construct() {}
    public function listMembers(array $filters): array { throw new RuntimeException("SQLSTATE[HY000] secret detail in C:\\path\\file.php"); }
};
$admin = ['id' => 1, 'name' => 'Admin', 'role' => 'administrator'];
$prevLog = ini_set('error_log', $work . '/e5.log');
putenv('APP_ENV=production'); $_ENV['APP_ENV'] = 'production';
$err = (new TeamController($failing))->index($admin, []);
putenv('APP_ENV=development'); $_ENV['APP_ENV'] = 'development';
$errDev = (new TeamController($failing))->index($admin, []);
ini_set('error_log', (string) $prevLog);
check('production: a failing service gives a 500, a generic message and NO detail; the technical text is only in the log', $err['http'] === 500 && $err['detail'] === null && !preg_match('/SQLSTATE|secret|path|file\.php/i', json_encode($err)) && str_contains((string) @file_get_contents($work . '/e5.log'), 'SQLSTATE'));
check('development: a path-free one-line detail is added for the developer', $errDev['http'] === 500 && is_string($errDev['detail']) && str_contains($errDev['detail'], 'RuntimeException') && !str_contains($errDev['detail'], 'C:'));

echo "Cleanup\n";
check('rollback left the real team table as it was', (int) $pdo->query('SELECT COUNT(*) FROM team_members')->fetchColumn() === $realTeam);
check('no team audit rows remain from this run', (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE user_agent = ?", [$ua])->fetchColumn() === 0);
$photosAfter = array_values(array_diff(scandir(dirname(__DIR__, 2) . '/uploads/team') ?: [], ['.', '..', '.gitkeep']));
$referenced = [];
foreach ($pdo->query('SELECT photo_path FROM team_members WHERE photo_path IS NOT NULL')->fetchAll(PDO::FETCH_COLUMN) as $p) {
    $referenced[] = basename((string) $p);
    $referenced[] = basename(ImageService::thumbPath((string) $p));
}
check('every file in the real uploads/team folder belongs to a real team member (these tests left no stray file there; real members\' photos are not touched)', array_diff($photosAfter, $referenced) === [], implode(',', array_diff($photosAfter, $referenced)));
foreach (['team_members', 'audit_logs'] as $table) {
    $pdo->exec("ALTER TABLE `$table` AUTO_INCREMENT = 1");
}

echo "\n$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
