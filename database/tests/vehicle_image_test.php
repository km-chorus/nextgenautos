<?php

declare(strict_types=1);

/**
 * Vehicle image logic tests (spec 07_spec_vehicle_image.md, sections 44 to 49).
 *
 * CLI only:  php database/tests/vehicle_image_test.php
 * Requires:  schema.sql + seed.sql loaded; DB credentials in .env; one administrator;
 *            PHP extensions gd (WebP), fileinfo, exif; composer install done.
 *
 * Covers the upload validator (real content checks, hostile files), image processing
 * (WebP output, aspect ratio, no upscaling, EXIF orientation, metadata removal, thumbnails,
 * random names, safe deletion), and VehicleImageService rules (relationship, order,
 * primary image, alt text, reordering, deletion, IDOR, throttling, cleanup after a failed
 * save, audit rows).
 *
 * Image FILES go to a temporary folder (never to uploads/vehicles). Database changes run
 * inside one transaction that is rolled back. All images are generated here with GD (see
 * image_fixtures.php); nothing is committed and no personal photographs are used.
 * HTTP behaviour (authentication, CSRF, GET refusal, real multipart uploads, XSS) is
 * covered by the HTTP/browser suites.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__, 2) . '/app/services/VehicleImageService.php';
require_once dirname(__DIR__, 2) . '/app/controllers/VehicleImageController.php';
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

$work = sys_get_temp_dir() . '/vip_image_test_' . bin2hex(random_bytes(4));
$storeDir = $work . '/store';
mkdir($storeDir, 0777, true);
register_shutdown_function(static function () use ($work): void {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($work, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) {
        $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
    }
    @rmdir($work);
});
/** Writes bytes to a temp "upload" file and returns its path. */
function tmpfile_with(string $bytes, string $name = 'upload.bin'): string
{
    $path = $GLOBALS['work'] . '/' . bin2hex(random_bytes(4)) . '_' . $name;
    file_put_contents($path, $bytes);
    return $path;
}
/** A normalised $_FILES entry for the service. */
function upfile(string $bytes, string $name = 'photo.jpg', int $error = UPLOAD_ERR_OK): array
{
    $path = tmpfile_with($bytes);
    return ['name' => $name, 'tmp_name' => $path, 'error' => $error, 'size' => strlen($bytes)];
}

echo "Environment\n";
check('GD with WebP, fileinfo and exif are available', ImageService::isAvailable() && extension_loaded('exif'));
check('Intervention Image is installed', class_exists('Intervention\\Image\\ImageManager'));

echo "Server requirements and limits
";
check('this environment has everything image processing needs', ImageService::missingRequirements() === [] && ImageService::isAvailable());
$prevMem = ini_get('memory_limit');
ini_set('memory_limit', '256M'); $m256 = ImageService::iniBytes('memory_limit');
ini_set('memory_limit', '1G'); $m1g = ImageService::iniBytes('memory_limit');
ini_set('memory_limit', '-1'); $mNone = ImageService::iniBytes('memory_limit');
ini_set('memory_limit', (string) $prevMem);
check('php.ini sizes are read correctly (256M, 1G, unlimited)', $m256 === 268435456 && $m1g === 1073741824 && $mNone === 0);
check('the server upload limit is the smaller of upload_max_filesize and post_max_size', ImageService::serverUploadLimit() === min(ImageService::iniBytes('upload_max_filesize'), ImageService::iniBytes('post_max_size')) && ImageService::serverUploadLimit() >= ImageValidator::MAX_BYTES);
check('the upload error messages are useful (INI_SIZE: server config only when PHP is below the application limit)', ImageValidator::uploadErrorMessage(UPLOAD_ERR_INI_SIZE, 2 * 1048576) === 'The uploaded file is too large for the server configuration.'
    && ImageValidator::uploadErrorMessage(UPLOAD_ERR_INI_SIZE, 10 * 1048576) === ImageValidator::ERROR_SIZE && ImageValidator::uploadErrorMessage(UPLOAD_ERR_FORM_SIZE) === ImageValidator::ERROR_SIZE
    && str_contains((string) ImageValidator::uploadErrorMessage(UPLOAD_ERR_NO_TMP_DIR), 'could not store') && str_contains((string) ImageValidator::uploadErrorMessage(UPLOAD_ERR_CANT_WRITE), 'could not store')
    && str_contains((string) ImageValidator::uploadErrorMessage(UPLOAD_ERR_EXTENSION), 'blocked') && ImageValidator::uploadErrorMessage(UPLOAD_ERR_PARTIAL) === ImageValidator::ERROR_UPLOAD);
check('the "not available" sentence names exactly what is missing', str_contains(VehicleImageController::unavailableMessage(['gd', 'fileinfo']), 'gd, fileinfo') && str_contains(VehicleImageController::unavailableMessage(['gd']), 'restart the PHP server'));
$dev = new ReflectionMethod('VehicleImageController', 'developmentDetail'); $dev->setAccessible(true);
$bs = chr(92);
$sample = new RuntimeException("Could not open C:{$bs}Users{$bs}someone{$bs}project{$bs}a.webp and /var/www/site/y.php");
putenv('APP_ENV=development'); $_ENV['APP_ENV'] = 'development'; putenv('APP_DEBUG=1');   // the developer detail needs APP_DEBUG=1
$devText = $dev->invoke(null, $sample);
putenv('APP_ENV=production'); $_ENV['APP_ENV'] = 'production';
$prodText = $dev->invoke(null, $sample);
putenv('APP_ENV=development'); $_ENV['APP_ENV'] = 'development'; putenv('APP_DEBUG=1');   // the developer detail needs APP_DEBUG=1
check('development detail names the exception, hides file-system paths, and is cut short', is_string($devText) && str_starts_with($devText, 'RuntimeException: Could not open [path]') && !str_contains($devText, 'someone') && !str_contains($devText, '/var/www'));
check('in production there is no detail at all', $prodText === null);

// ============================================================== validator
echo "Upload validator (real content, not names)\n";
$jpeg = fx_photo(1600, 1200, 'jpeg');
$png = fx_photo(1200, 900, 'png');
$webp = fx_photo(1200, 900, 'webp');
foreach (['jpeg' => $jpeg, 'png' => $png, 'webp' => $webp] as $type => $bytes) {
    $r = ImageValidator::inspect(tmpfile_with($bytes, "x.$type"));
    check("valid $type accepted with real MIME and dimensions", $r['ok'] && $r['mime'] === "image/$type" && $r['width'] > 0, json_encode($r));
}
check('portrait 600 x 800 accepted (orientation is not rejected)', ImageValidator::inspect(tmpfile_with(fx_photo(600, 800)))['ok']);
check('exactly 800 x 600 accepted', ImageValidator::inspect(tmpfile_with(fx_photo(800, 600)))['ok']);
check('799 x 600 rejected as too small', ($r = ImageValidator::inspect(tmpfile_with(fx_photo(799, 600))))['ok'] === false && $r['reason'] === 'too_small');
check('800 x 599 rejected as too small', ImageValidator::inspect(tmpfile_with(fx_photo(800, 599)))['reason'] === 'too_small');
check('tiny 100 x 100 rejected as too small, with the specified wording', ($r = ImageValidator::inspect(tmpfile_with(fx_photo(100, 100)))) && $r['reason'] === 'too_small' && str_contains((string) $r['error'], 'too small'));
$hostile = [
    'PHP script named .jpg' => [fx_php_script(), 'format'],
    'HTML' => [fx_html(), 'format'],
    'SVG with script' => [fx_svg(), 'format'],
    'GIF' => [fx_gif(), 'format'],
    'BMP' => [fx_bmp(), 'format'],
    'PDF' => [fx_pdf(), 'format'],
    'ZIP' => [fx_zip(), 'format'],
    'EXE' => [fx_exe(), 'format'],
    'GIF/PHP polyglot' => [fx_gif_php_polyglot(), ''],
];
foreach ($hostile as $name => [$bytes, $kind]) {
    $r = ImageValidator::inspect(tmpfile_with($bytes, 'evil.jpg'));
    check("rejected whatever its name says: $name", $r['ok'] === false && str_starts_with((string) $r['reason'], $kind) && in_array($r['error'], [ImageValidator::ERROR_FORMAT, ImageValidator::ERROR_INVALID], true), json_encode($r));
}
check('empty file rejected', ImageValidator::inspect(tmpfile_with(''))['reason'] === 'empty');
check('random bytes rejected', ($r = ImageValidator::inspect(tmpfile_with(random_bytes(5000)))) && $r['ok'] === false);
check('truncated JPEG rejected as not a valid image', ($r = ImageValidator::inspect(tmpfile_with(fx_truncated_jpeg()))) && $r['ok'] === false && $r['error'] === ImageValidator::ERROR_INVALID, json_encode($r));
check('oversized file (11 MB, valid image content) rejected before anything else', ($r = ImageValidator::inspect(tmpfile_with(fx_oversized_jpeg()))) && $r['reason'] === 'too_large' && $r['error'] === 'The image is too large. Maximum size is 10 MB.');
check('file of exactly the size limit is not rejected for size', ImageValidator::inspect(tmpfile_with(fx_oversized_jpeg(10 * 1048576)))['reason'] !== 'too_large');
check('header claiming 20000 x 20000 rejected WITHOUT decoding (tiny file)', ($r = ImageValidator::inspect(tmpfile_with(fx_png_claiming(20000, 20000)))) && $r['reason'] === 'dimensions_too_large' && $r['bytes'] < 200);
check('5000 x 5000 (25 megapixels) rejected', ImageValidator::inspect(tmpfile_with(fx_png_claiming(5000, 5000)))['reason'] === 'dimensions_too_large');
check('12001 pixels on one side rejected', ImageValidator::inspect(tmpfile_with(fx_png_claiming(12001, 800)))['reason'] === 'dimensions_too_large');
check('a legitimate large photo (4000 x 3000 = 12 MP) passes the pixel limit', ImageValidator::inspect(tmpfile_with(fx_png_claiming(4000, 3000)))['reason'] !== 'dimensions_too_large');
check('upload error codes map to clear messages', ImageValidator::uploadErrorMessage(UPLOAD_ERR_OK) === null && ImageValidator::uploadErrorMessage(UPLOAD_ERR_INI_SIZE) === ImageValidator::ERROR_SIZE
    && ImageValidator::uploadErrorMessage(UPLOAD_ERR_PARTIAL) === ImageValidator::ERROR_UPLOAD && ImageValidator::uploadErrorMessage(UPLOAD_ERR_NO_FILE) === 'No file was selected.');
check('missing file handled', ImageValidator::inspect($work . '/does-not-exist')['ok'] === false);
check('display names: path and control characters removed, length capped', ImageValidator::displayName('..\\..\\windows\\evil.php') === 'evil.php' && ImageValidator::displayName("a\0b\x07c.jpg") === 'abc.jpg'
    && ImageValidator::displayName('') === 'image' && mb_strlen(ImageValidator::displayName(str_repeat('a', 300) . '.jpg')) === 100 && ImageValidator::displayName("bad\xC3\x28.jpg") === 'image');
check('display names keep angle brackets as inert text (escaped on output); a slash ends the base name', ImageValidator::displayName('<b>x</b>.jpg') === 'b>.jpg' && ImageValidator::displayName('<b>x.jpg') === '<b>x.jpg');

// ============================================================== processing
echo "Image processing\n";
$files = new ImageService($storeDir);
$info = static function (string $absolute): array {
    $i = getimagesize($absolute);
    return ['w' => (int) $i[0], 'h' => (int) $i[1], 'type' => $i[2]];
};
$big = $files->process(tmpfile_with(fx_photo(3200, 2400, 'jpeg')));
$bigMain = $storeDir . '/' . basename($big['path']);
$bigThumb = $storeDir . '/' . basename($big['thumb']);
check('main image is WebP (type, magic bytes, extension)', $info($bigMain)['type'] === IMAGETYPE_WEBP && str_starts_with((string) file_get_contents($bigMain), 'RIFF') && str_ends_with($big['path'], '.webp'));
check('4:3 source scaled down to 1600 x 1200 (aspect ratio preserved)', $big['width'] === 1600 && $big['height'] === 1200 && $info($bigMain)['w'] === 1600 && $info($bigMain)['h'] === 1200);
check('thumbnail is WebP, 640 x 480, aspect ratio preserved', $info($bigThumb)['type'] === IMAGETYPE_WEBP && $info($bigThumb)['w'] === 640 && $info($bigThumb)['h'] === 480);
check('stored path has the exact generated shape; thumbnail follows the convention', ImageService::isStoredPath($big['path']) && ImageService::thumbPath($big['path']) === $big['thumb'] && preg_match('#^uploads/vehicles/[a-f0-9]{32}\.webp$#', $big['path']) === 1);
check('main file is optimised (well under the original, near the 700 KB target)', filesize($bigMain) < 900000 && $big['bytes'] === filesize($bigMain), (string) filesize($bigMain));
check('thumbnail is much smaller than the main image', filesize($bigThumb) < filesize($bigMain));
check('files are plain readable files (no execute bit semantics, not empty)', filesize($bigMain) > 1000 && is_readable($bigMain));
$small = $files->process(tmpfile_with(fx_photo(1000, 750, 'jpeg')));
check('a smaller image is NOT upscaled (1000 x 750 stays 1000 x 750)', $small['width'] === 1000 && $small['height'] === 750 && $info($storeDir . '/' . basename($small['path']))['w'] === 1000);
check('its thumbnail is scaled down to 640 x 480', $info($storeDir . '/' . basename($small['thumb']))['w'] === 640);
$portrait = $files->process(tmpfile_with(fx_photo(1200, 1800, 'jpeg')));
check('portrait 1200 x 1800 fits the 1600 box: 1067 x 1600 (no distortion)', $portrait['height'] === 1600 && abs($portrait['width'] - 1067) <= 1);
$ultra = $files->process(tmpfile_with(fx_photo(2400, 800, 'jpeg')));
check('wide 3:1 image keeps its ratio: 1600 x 533', $ultra['width'] === 1600 && abs($ultra['height'] - 533) <= 1);
$fromPng = $files->process(tmpfile_with(fx_photo(1800, 1200, 'png', true)));
check('PNG with transparency converts to WebP (alpha kept)', $info($storeDir . '/' . basename($fromPng['path']))['type'] === IMAGETYPE_WEBP && $fromPng['width'] === 1600 && $fromPng['height'] === 1067);
$fromWebp = $files->process(tmpfile_with(fx_photo(1700, 1275, 'webp')));
check('WebP source is re-encoded (and scaled) as WebP', $info($storeDir . '/' . basename($fromWebp['path']))['type'] === IMAGETYPE_WEBP && $fromWebp['width'] === 1600);
$rot = $files->process(tmpfile_with(fx_exif_rotated_jpeg(1600, 1200)));
check('EXIF orientation is applied: a 1600 x 1200 photo marked "rotate 90" becomes 1200 x 1600', $rot['width'] === 1200 && $rot['height'] === 1600, $rot['width'] . 'x' . $rot['height']);
$rotBytes = (string) file_get_contents($storeDir . '/' . basename($rot['path']));
check('metadata is removed: no EXIF block and none of the embedded camera/GPS text survives', !str_contains($rotBytes, 'Exif') && !str_contains($rotBytes, 'GPS-AND-CAMERA-METADATA-MARKER'));
$payload = $files->process(tmpfile_with(fx_jpeg_with_php_payload()));
$payloadBytes = (string) file_get_contents($storeDir . '/' . basename($payload['path'])) . (string) file_get_contents($storeDir . '/' . basename($payload['thumb']));
check('a real image carrying a PHP payload is re-encoded: the stored files contain no PHP code', !str_contains($payloadBytes, '<?php') && !str_contains($payloadBytes, 'PWNED') && !str_contains($payloadBytes, 'system('));
check('names are random and unique', count(array_unique([$big['path'], $small['path'], $portrait['path'], $ultra['path'], $fromPng['path'], $fromWebp['path'], $rot['path'], $payload['path']])) === 8);
check('the storage folder holds only .webp files', array_filter(scandir($storeDir) ?: [], static fn ($f) => $f !== '.' && $f !== '..' && !str_ends_with($f, '.webp')) === []);
$before = count(scandir($storeDir) ?: []);
$threw = false;
try { $files->process(tmpfile_with(fx_damaged_jpeg())); } catch (RuntimeException $e) { $threw = true; }
$afterFail = count(scandir($storeDir) ?: []);
check('a damaged image either fails cleanly or is re-encoded; a failure leaves no partial files', $threw ? $afterFail === $before : $afterFail === $before + 2);
$threw = false;
try { (new ImageService($work . '/missing-dir'))->process(tmpfile_with($jpeg)); } catch (RuntimeException $e) { $threw = true; }
check('an unwritable/missing storage folder fails safely', $threw);

echo "Safe deletion of files\n";
check('delete() removes the main file and its thumbnail', $files->delete($small['path']) === 'deleted' && !is_file($storeDir . '/' . basename($small['path'])) && !is_file($storeDir . '/' . basename($small['thumb'])));
check('deleting an already missing file reports "missing" without error', $files->delete($small['path']) === 'missing');
$decoy = $work . '/decoy.txt';
file_put_contents($decoy, 'keep me');
foreach (['../decoy.txt', '../../etc/passwd', 'uploads/vehicles/../x.webp', 'uploads/vehicles/abc.webp', 'uploads/vehicles/' . str_repeat('a', 32) . '.php', '/etc/passwd', 'C:\\Windows\\x.webp',
    'uploads/vehicles/' . str_repeat('a', 32) . '.webp/../../x', "uploads/vehicles/" . str_repeat('a', 32) . ".webp\0.php", '', 'uploads/team/' . str_repeat('a', 32) . '.webp'] as $bad) {
    check('delete() refuses a path it did not generate: ' . json_encode($bad), $files->delete($bad) === 'invalid');
}
check('nothing outside the generated shape was touched', is_file($decoy));

// ============================================================== service + database
$pdo = Database::connection();
$adminId = (int) $pdo->query('SELECT id FROM admin_users ORDER BY id LIMIT 1')->fetchColumn();
check('an administrator row exists for audit foreign keys', $adminId > 0);
$ip = '203.0.113.8';
$ua = 'vehicle_image_test';
$realUploads = dirname(__DIR__, 2) . '/uploads/vehicles';
$realBefore = array_values(array_diff(scandir($realUploads) ?: [], ['.', '..']));

$pdo->beginTransaction();
try {
    $vehiclesRepo = new VehicleRepository($pdo);
    $imagesRepo = new VehicleImageRepository($pdo);
    $service = new VehicleImageService($pdo, $vehiclesRepo, $imagesRepo, new AuditLogRepository($pdo), new ImageService($storeDir));
    $brand = (int) run($pdo, "SELECT id FROM brands WHERE slug = 'toyota'")->fetchColumn();
    $newVehicle = static function (string $model) use ($vehiclesRepo, $brand): int {
        return $vehiclesRepo->insert(['brand_id' => $brand, 'year' => 2022, 'model' => $model, 'trim' => null, 'engine_size' => null, 'mileage' => null, 'mileage_unit' => null, 'transmission' => null,
            'fuel_type' => null, 'drive_type' => null, 'price' => '1.00', 'currency' => 'GHS', 'description' => null, 'key_features' => [], 'shipping_info' => null, 'duty_included' => 0,
            'availability_status' => 'available', 'is_published' => 0, 'slug' => 'test-data-img-' . bin2hex(random_bytes(3))]);
    };
    $v1 = $newVehicle('TEST DATA One');
    $v2 = $newVehicle('TEST DATA Two');
    $up = static fn (int $vehicle, array $f, int $admin = 0) => $GLOBALS['service']->upload($vehicle, $f, $admin ?: $GLOBALS['adminId'], $GLOBALS['ip'], $GLOBALS['ua'], false);
    $rows = static fn (int $vehicle): array => $GLOBALS['imagesRepo']->forVehicle($vehicle);
    $storedFiles = static fn (): array => array_values(array_filter(scandir($GLOBALS['storeDir']) ?: [], static fn ($f) => $f !== '.' && $f !== '..'));

    echo "Upload\n";
    $start = count($storedFiles());
    $r = $up($v1, [upfile($jpeg, 'front.jpg')]);
    check('a valid JPEG upload succeeds', $r['status'] === 'ok' && $r['results'][0]['ok'] === true && $r['results'][0]['id'] > 0, json_encode($r));
    $a = $rows($v1)[0];
    check('record: right vehicle, generated path, original name kept as metadata, order 1', (int) $a['vehicle_id'] === $v1 && ImageService::isStoredPath((string) $a['file_path']) && $a['original_filename'] === 'front.jpg' && (int) $a['display_order'] === 1);
    check('the first image of a vehicle becomes its primary image, and the result says so', (int) $a['is_primary'] === 1 && $r['primary_assigned'] === true);
    check('main file and thumbnail exist on disk (2 files)', count($storedFiles()) === $start + 2 && is_file($storeDir . '/' . basename((string) $a['file_path'])));
    check('created_at is set and alt text starts empty (nothing invented)', $a['created_at'] !== null && $a['alt_text'] === null);
    check('VEHICLE_IMAGE_UPLOADED audited with admin, image id and vehicle', (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type='VEHICLE_IMAGE_UPLOADED' AND admin_user_id=? AND entity_type='vehicle_image' AND entity_id=? AND description LIKE ?", [$adminId, $a['id'], '%vehicle ' . $v1 . '%'])->fetchColumn() === 1);
    $r2 = $up($v1, [upfile($png, 'side.png'), upfile($webp, 'rear.webp')]);
    check('multiple files in one request all succeed (PNG and WebP too)', $r2['status'] === 'ok' && count($r2['results']) === 2 && $r2['results'][0]['ok'] && $r2['results'][1]['ok'] && $r2['primary_assigned'] === false);
    $list = $rows($v1);
    check('display order is 1, 2, 3 in upload order and only the first is primary', array_map(static fn ($x) => (int) $x['display_order'], $list) === [1, 2, 3] && array_map(static fn ($x) => (int) $x['is_primary'], $list) === [1, 0, 0]);
    check('original names are kept as metadata in order', array_column($list, 'original_filename') === ['front.jpg', 'side.png', 'rear.webp']);

    echo "Mixed valid and invalid uploads\n";
    $beforeMixed = count($rows($v1));
    $rejectedBefore = (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type='VEHICLE_IMAGE_UPLOAD_REJECTED' AND admin_user_id=? AND description LIKE 'Image rejected: %' AND description NOT LIKE '%shell.jpg%'", [$adminId])->fetchColumn();   // real rejections may already exist: count only this run's
    $mixed = $up($v1, [upfile($jpeg, 'ok1.jpg'), upfile(fx_php_script(), 'shell.jpg'), upfile(fx_photo(1000, 800), 'ok2.jpg'), upfile(fx_oversized_jpeg(), 'huge.jpg'), upfile(fx_gif(), 'anim.gif'), upfile('', 'none.jpg', UPLOAD_ERR_INI_SIZE)]);
    $okMap = array_map(static fn ($x) => $x['ok'], $mixed['results']);
    check('each file is judged on its own: ok, rejected, ok, rejected, rejected, rejected', $okMap === [true, false, true, false, false, false], json_encode($okMap));
    check('every failure is reported by (display) name with a clear message', $mixed['results'][1]['name'] === 'shell.jpg' && $mixed['results'][1]['message'] === 'This image format is not supported.'
        && $mixed['results'][3]['message'] === 'The image is too large. Maximum size is 10 MB.' && $mixed['results'][5]['message'] === 'The image is too large. Maximum size is 10 MB.');
    check('only the two valid images were added', count($rows($v1)) === $beforeMixed + 2);
    check('VEHICLE_IMAGE_UPLOAD_REJECTED audited once per rejected file, with a reason code and no file name', (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type='VEHICLE_IMAGE_UPLOAD_REJECTED' AND admin_user_id=? AND description LIKE 'Image rejected: %' AND description NOT LIKE '%shell.jpg%'", [$adminId])->fetchColumn() === $rejectedBefore + 4);
    check('rejected files left nothing on disk', count($storedFiles()) === $start + 2 * count($rows($v1)));
    $tooMany = $up($v1, array_map(static fn ($i) => upfile($jpeg, "f$i.jpg"), range(1, 11)));
    check('more than 10 files in one request is refused as a whole, nothing processed', $tooMany['status'] === 'invalid' && count($rows($v1)) === $beforeMixed + 2 && str_contains((string) $tooMany['results'][0]['message'], '10'));
    check('no file at all is handled', $up($v1, [])['status'] === 'invalid');
    check('a nonexistent vehicle id is refused', $up(99999, [upfile($jpeg)])['status'] === 'not_found');
    check('an upload for an archived (soft-deleted) vehicle is refused', (function () use ($pdo, $newVehicle, $up, $jpeg): bool {
        $id = $newVehicle('TEST DATA Archived'); run($pdo, 'UPDATE vehicles SET deleted_at = UTC_TIMESTAMP() WHERE id = ?', [$id]);
        return $up($id, [upfile($jpeg)])['status'] === 'not_found';
    })());
    $notHttp = $service->upload($v1, [upfile($jpeg, 'forged.jpg')], $adminId, $ip, $ua, true);
    check('with HTTP-upload enforcement on, a file that is not a real upload is rejected (a browser cannot name a server path)', $notHttp['results'][0]['ok'] === false);

    echo "Primary image\n";
    $list = $rows($v1);
    $second = (int) $list[1]['id'];
    $first = (int) $list[0]['id'];
    $p = $service->setPrimary($v1, $second, $adminId, $ip, $ua);
    $prim = array_values(array_filter($rows($v1), static fn ($x) => (int) $x['is_primary'] === 1));
    check('setting another image as primary demotes the old one: exactly one primary, the new one', $p['status'] === 'ok' && count($prim) === 1 && (int) $prim[0]['id'] === $second);
    check('VEHICLE_IMAGE_PRIMARY_CHANGED audited', (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type='VEHICLE_IMAGE_PRIMARY_CHANGED' AND entity_id=? AND admin_user_id=?", [$second, $adminId])->fetchColumn() === 1);
    check('making the current primary primary again is a no-op', $service->setPrimary($v1, $second, $adminId, $ip, $ua)['status'] === 'unchanged');
    $e = null;
    try { run($pdo, 'SAVEPOINT p'); run($pdo, 'INSERT INTO vehicle_images (vehicle_id, file_path, is_primary) VALUES (?, ?, 1)', [$v1, 'uploads/vehicles/' . str_repeat('c', 32) . '.webp']); }
    catch (PDOException $ex) { $e = $ex; run($pdo, 'ROLLBACK TO SAVEPOINT p'); }
    check('the database itself refuses a second primary image for a vehicle (unique key)', $e !== null && (int) $e->errorInfo[1] === 1062 && str_contains($e->getMessage(), 'uq_vehicle_images_one_primary'));
    check('setting primary for an image id of ANOTHER vehicle fails (IDOR): not found, nothing changed', $service->setPrimary($v2, $first, $adminId, $ip, $ua)['status'] === 'not_found'
        && (int) $rows($v1)[1]['is_primary'] === 1);
    check('a nonexistent image id is not found', $service->setPrimary($v1, 99999, $adminId, $ip, $ua)['status'] === 'not_found');

    echo "Alt text\n";
    $alt = $service->updateAlt($v1, $first, "  Front view,   passenger side  ", $adminId, $ip, $ua);
    check('alt text saved, trimmed and tidied', $alt['status'] === 'ok' && $imagesRepo->findForVehicle($v1, $first)['alt_text'] === 'Front view, passenger side');
    check('saving the same alt text again is unchanged', $service->updateAlt($v1, $first, 'Front view, passenger side', $adminId, $ip, $ua)['status'] === 'unchanged');
    check('empty alt text clears it (the page falls back to the vehicle label)', $service->updateAlt($v1, $first, '   ', $adminId, $ip, $ua)['status'] === 'ok' && $imagesRepo->findForVehicle($v1, $first)['alt_text'] === null);
    foreach ([str_repeat('a', 256), "a\0b", "a\u{200B}b", ['x'], "bad\xC3\x28"] as $bad) {
        check('invalid alt text rejected: ' . json_encode(is_array($bad) ? 'array' : substr($bad, 0, 12)), $service->updateAlt($v1, $first, $bad, $adminId, $ip, $ua)['status'] === 'invalid');
    }
    check('HTML in alt text is stored as inert text', $service->updateAlt($v1, $first, '<script>alert(1)</script>', $adminId, $ip, $ua)['status'] === 'ok' && $imagesRepo->findForVehicle($v1, $first)['alt_text'] === '<script>alert(1)</script>');
    check('alt text of another vehicle\'s image cannot be edited (IDOR)', $service->updateAlt($v2, $first, 'hijacked', $adminId, $ip, $ua)['status'] === 'not_found' && $imagesRepo->findForVehicle($v1, $first)['alt_text'] === '<script>alert(1)</script>');
    check('alt fallback is the vehicle label, never a generated "image1"', VehicleImageService::altFor(['alt_text' => null], '2022 Toyota Camry') === '2022 Toyota Camry' && VehicleImageService::altFor(['alt_text' => 'Rear'], 'x') === 'Rear');
    check('VEHICLE_IMAGE_ALT_UPDATED audited', (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type='VEHICLE_IMAGE_ALT_UPDATED' AND admin_user_id=?", [$adminId])->fetchColumn() >= 3);

    echo "Reordering\n";
    $ids = array_map(static fn ($x) => (int) $x['id'], $rows($v1));
    $new = array_reverse($ids);
    $ro = $service->reorder($v1, array_map('strval', $new), $adminId, $ip, $ua);
    check('a valid full reorder succeeds and display_order becomes 1..n in the submitted order', $ro['status'] === 'ok' && array_map(static fn ($x) => (int) $x['id'], $rows($v1)) === $new && array_map(static fn ($x) => (int) $x['display_order'], $rows($v1)) === range(1, count($ids)));
    check('VEHICLE_IMAGE_REORDERED audited', (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type='VEHICLE_IMAGE_REORDERED' AND entity_id=?", [$v1])->fetchColumn() === 1);
    check('submitting the same order again is unchanged', $service->reorder($v1, array_map('strval', $new), $adminId, $ip, $ua)['status'] === 'unchanged');
    $snapshot = array_map(static fn ($x) => [(int) $x['id'], (int) $x['display_order']], $rows($v1));
    $v2img = (int) $up($v2, [upfile($jpeg, 'v2.jpg')])['results'][0]['id'];
    $cases = [
        'missing one image' => array_map('strval', array_slice($new, 1)),
        'extra nonexistent id' => array_map('strval', array_merge($new, [99999])),
        'an image of ANOTHER vehicle swapped in' => array_map('strval', array_merge(array_slice($new, 1), [$v2img])),
        'duplicate ids' => array_map('strval', array_merge(array_slice($new, 1), [$new[1]])),
        'malformed (text)' => array_merge(array_slice(array_map('strval', $new), 1), ['abc']),
        'malformed (SQL)' => array_merge(array_slice(array_map('strval', $new), 1), ["1 OR 1=1"]),
        'negative id' => array_merge(array_slice(array_map('strval', $new), 1), ['-1']),
        'integers instead of strings' => $new,
        'empty list' => [],
        'not a list' => 'abc',
        'associative array' => ['a' => (string) $new[0]],
        'nested arrays' => [[(string) $new[0]]],
    ];
    foreach ($cases as $name => $bad) {
        check("reorder rejected: $name", $service->reorder($v1, $bad, $adminId, $ip, $ua)['status'] === 'invalid');
    }
    check('rejected reorders changed nothing', array_map(static fn ($x) => [(int) $x['id'], (int) $x['display_order']], $rows($v1)) === $snapshot);
    check('another vehicle\'s gallery is untouched and cannot be reordered with this vehicle\'s ids', $service->reorder($v2, array_map('strval', $new), $adminId, $ip, $ua)['status'] === 'invalid' && count($rows($v2)) === 1);
    check('reorder of a nonexistent vehicle', $service->reorder(99999, ['1'], $adminId, $ip, $ua)['status'] === 'not_found');

    echo "Delete\n";
    $list = $rows($v1);
    $primary = array_values(array_filter($list, static fn ($x) => (int) $x['is_primary'] === 1))[0];
    $nonPrimary = array_values(array_filter($list, static fn ($x) => (int) $x['is_primary'] === 0))[0];
    $filesBefore = count($storedFiles());
    $d1 = $service->delete($v1, (int) $nonPrimary['id'], false, $adminId, $ip, $ua);
    check('deleting a non-primary image removes its record and both files', $d1['status'] === 'ok' && $d1['was_primary'] === false && $imagesRepo->findForVehicle($v1, (int) $nonPrimary['id']) === null
        && count($storedFiles()) === $filesBefore - 2 && !is_file($storeDir . '/' . basename((string) $nonPrimary['file_path'])));
    check('the gap in the order is closed (1..n)', array_map(static fn ($x) => (int) $x['display_order'], $rows($v1)) === range(1, count($rows($v1))));
    check('the primary image is unchanged by that', count(array_filter($rows($v1), static fn ($x) => (int) $x['is_primary'] === 1)) === 1);
    check('VEHICLE_IMAGE_DELETED audited', (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type='VEHICLE_IMAGE_DELETED' AND entity_id=? AND admin_user_id=?", [$nonPrimary['id'], $adminId])->fetchColumn() === 1);
    check('another vehicle\'s image cannot be deleted through this vehicle id (IDOR): record and files survive', (function () use ($service, $v1, $v2img, $adminId, $ip, $ua, $imagesRepo, $v2, $storeDir): bool {
        $r = $service->delete($v1, $v2img, false, $adminId, $ip, $ua);
        $img = $imagesRepo->findForVehicle($v2, $v2img);
        return $r['status'] === 'not_found' && $img !== null && is_file($storeDir . '/' . basename((string) $img['file_path']));
    })());
    $cur = $rows($v1);
    $curPrimary = array_values(array_filter($cur, static fn ($x) => (int) $x['is_primary'] === 1))[0];
    $idx = array_search((int) $curPrimary['id'], array_map(static fn ($x) => (int) $x['id'], $cur), true);
    $expectedPromoted = (int) ($cur[$idx + 1]['id'] ?? $cur[$idx - 1]['id']);
    $d2 = $service->delete($v1, (int) $curPrimary['id'], true, $adminId, $ip, $ua);
    $afterPrim = array_values(array_filter($rows($v1), static fn ($x) => (int) $x['is_primary'] === 1));
    check('deleting the primary image with "promote next": the next image (deterministic rule) becomes the only primary', $d2['status'] === 'ok' && $d2['was_primary'] === true && $d2['promoted'] === $expectedPromoted && count($afterPrim) === 1 && (int) $afterPrim[0]['id'] === $expectedPromoted);
    check('the audit entry for a primary deletion says who was promoted', (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type='VEHICLE_IMAGE_DELETED' AND entity_id=? AND description LIKE '%primary image%' AND description LIKE ?", [$curPrimary['id'], '%image ' . $expectedPromoted . ' is now primary%'])->fetchColumn() === 1);
    $cur = $rows($v1);
    $newPrimary = array_values(array_filter($cur, static fn ($x) => (int) $x['is_primary'] === 1))[0];
    $d3 = $service->delete($v1, (int) $newPrimary['id'], false, $adminId, $ip, $ua);
    check('deleting the primary WITHOUT promotion leaves no primary: allowed, stated, no arbitrary pick', $d3['status'] === 'ok' && $d3['promoted'] === null && array_filter($rows($v1), static fn ($x) => (int) $x['is_primary'] === 1) === []);
    check('a vehicle without a primary can get one assigned', ($rest = $rows($v1)) !== [] && $service->setPrimary($v1, (int) $rest[0]['id'], $adminId, $ip, $ua)['status'] === 'ok');
    $last = $rows($v1);
    $lastPrimaryId = (int) array_values(array_filter($last, static fn ($x) => (int) $x['is_primary'] === 1))[0]['id'];
    // delete everything that is left for v1, finishing with the primary
    foreach ($last as $img) { $service->delete($v1, (int) $img['id'], true, $adminId, $ip, $ua); }
    check('deleting the last image leaves an empty gallery', $rows($v1) === []);
    // a record whose file is already gone
    $v3 = $newVehicle('TEST DATA Three');
    $m = $up($v3, [upfile($jpeg, 'will-vanish.jpg')]);
    $mid = (int) $m['results'][0]['id'];
    $mrow = $imagesRepo->findForVehicle($v3, $mid);
    unlink($storeDir . '/' . basename((string) $mrow['file_path']));
    $prevLog = ini_set('error_log', $work . '/php_errors.log');
    $dm = $service->delete($v3, $mid, true, $adminId, $ip, $ua);
    ini_set('error_log', (string) $prevLog);
    check('a record whose file is missing is still removed, the condition is logged', $dm['status'] === 'ok' && $dm['file'] === 'missing' && $imagesRepo->findForVehicle($v3, $mid) === null
        && (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type='VEHICLE_IMAGE_FILE_MISSING' AND entity_id=?", [$mid])->fetchColumn() === 1 && str_contains((string) @file_get_contents($work . '/php_errors.log'), 'stored file was missing'));
    check('deleting a nonexistent image is not found', $service->delete($v3, 99999, false, $adminId, $ip, $ua)['status'] === 'not_found');

    echo "Failure handling\n";
    $v4 = $newVehicle('TEST DATA Four');
    $filesNow = $storedFiles();
    run($pdo, 'SAVEPOINT failing');
    $prevLog = ini_set('error_log', $work . '/php_errors2.log');
    $bad = $service->upload($v4, [upfile($jpeg, 'x.jpg')], 999999999, $ip, $ua, false);   // the audit insert violates its foreign key, so the save fails
    ini_set('error_log', (string) $prevLog);
    run($pdo, 'ROLLBACK TO SAVEPOINT failing');
    check('when the record cannot be saved the upload is reported as failed with a generic message', $bad['results'][0]['ok'] === false && $bad['results'][0]['message'] === 'The image could not be saved. Please try again.');
    check('...and the files already written are cleaned up (no orphans)', $storedFiles() === $filesNow);
    check('...and no database record remains', $rows($v4) === []);
    check('the technical error went to the server log, not the response', str_contains((string) @file_get_contents($work . '/php_errors2.log'), 'saving the record failed') && !str_contains(json_encode($bad), 'SQLSTATE'));
    $failing = new ImageService($work . '/nowhere');
    $svc2 = new VehicleImageService($pdo, $vehiclesRepo, $imagesRepo, new AuditLogRepository($pdo), $failing);
    $prevLog = ini_set('error_log', $work . '/php_errors3.log');
    $pf = $svc2->upload($v4, [upfile($jpeg, 'y.jpg')], $adminId, $ip, $ua, false);
    ini_set('error_log', (string) $prevLog);
    check('a processing failure creates no record and says "could not be processed"', $pf['results'][0]['ok'] === false && $pf['results'][0]['message'] === 'The image could not be processed. Please try another image.' && $rows($v4) === []);

    echo "Throttling\n";
    for ($i = 0; $i < VehicleImageService::THROTTLE_LIMIT; $i++) {
        (new AuditLogRepository($pdo))->record('VEHICLE_IMAGE_UPLOAD_REJECTED', $adminId, 'vehicle', $v4, 'Image rejected: test', $ip, $ua);
    }
    check('after too many uploads in a short time further uploads are throttled', $up($v4, [upfile($jpeg, 'z.jpg')])['status'] === 'throttled' && $rows($v4) === []);

    echo "Hostile text\n";
    $v5 = $newVehicle('TEST DATA Five');
    run($pdo, "DELETE FROM audit_logs WHERE event_type = 'VEHICLE_IMAGE_UPLOAD_REJECTED' AND user_agent = ?", [$ua]);   // reset the throttle counter inside this transaction
    $sqlName = "x'); DROP TABLE vehicle_images;--<script>alert(1)<script>.jpg";
    $sq = $up($v5, [upfile($jpeg, $sqlName)]);
    check('SQL and HTML in a file name are stored as plain data and the table survives', $sq['results'][0]['ok'] === true && $rows($v5)[0]['original_filename'] === $sqlName && (int) $pdo->query('SELECT COUNT(*) FROM vehicle_images')->fetchColumn() > 0);
    check('the stored file name on disk is still the random generated one', ImageService::isStoredPath((string) $rows($v5)[0]['file_path']));

    // ------------------------------------------------------------ controller
    echo "Controller helpers and authorization\n";
    $one = ['name' => 'a.jpg', 'tmp_name' => '/tmp/x', 'error' => 0, 'size' => 5];
    check('normaliseFiles: a multi-file field becomes a flat list', count(VehicleImageController::normaliseFiles(['name' => ['a.jpg', 'b.jpg'], 'tmp_name' => ['/tmp/a', '/tmp/b'], 'error' => [0, 0], 'size' => [1, 2]])) === 2);
    check('normaliseFiles: a single-file field works', count(VehicleImageController::normaliseFiles($one)) === 1);
    check('normaliseFiles: empty slots (no file chosen) are dropped', VehicleImageController::normaliseFiles(['name' => [''], 'tmp_name' => [''], 'error' => [UPLOAD_ERR_NO_FILE], 'size' => [0]]) === []);
    check('normaliseFiles: nested arrays or odd types yield no files', VehicleImageController::normaliseFiles(['name' => [['a']], 'tmp_name' => [['b']], 'error' => [[0]], 'size' => [[1]]]) === [] && VehicleImageController::normaliseFiles('x') === [] && VehicleImageController::normaliseFiles(null) === []
        && VehicleImageController::normaliseFiles(['name' => ['a'], 'tmp_name' => ['b'], 'error' => ['0'], 'size' => [1]]) === []);
    $staff = ['id' => 1, 'name' => 'Staff', 'role' => 'staff'];
    $ctl = new VehicleImageController();
    $refused = [
        'manage' => $ctl->manage($staff, '1'), 'upload' => $ctl->upload($staff, '1', [], [], [], $ip, $ua), 'update' => $ctl->update($staff, ['action' => 'primary', 'vehicle_id' => '1', 'image_id' => '1'], $ip, $ua),
        'confirmDelete' => $ctl->confirmDelete($staff, '1', '1'), 'delete' => $ctl->delete($staff, ['vehicle_id' => '1', 'image_id' => '1', 'confirm' => 'yes'], $ip, $ua),
    ];
    foreach ($refused as $action => $res) {
        check("role without the vehicles permission is refused: $action", $res['http'] === 403 && $res['problem'] === 'forbidden');
    }
    $failingSvc = new class extends VehicleImageService {
        public function __construct() {}
        public function gallery(int $vehicleId): ?array { throw new RuntimeException('SQLSTATE[HY000] secret in C:\\path\\file.php'); }
    };
    $prevLog = ini_set('error_log', $work . '/php_errors4.log');
    putenv('APP_ENV=production'); $_ENV['APP_ENV'] = 'production';
    $err = (new VehicleImageController($failingSvc))->manage(['id' => 1, 'name' => 'Admin', 'role' => 'administrator'], '1');
    putenv('APP_ENV=development'); $_ENV['APP_ENV'] = 'development'; putenv('APP_DEBUG=1');   // the developer detail needs APP_DEBUG=1
    $errDev = (new VehicleImageController($failingSvc))->manage(['id' => 1, 'name' => 'Admin', 'role' => 'administrator'], '1');
    ini_set('error_log', (string) $prevLog);
    check('production: a failing service gives a 500 with a generic message and NO detail; the technical text is only in the log', $err['http'] === 500 && $err['detail'] === null && !preg_match('/SQLSTATE|secret|path|file\.php/i', json_encode($err)) && str_contains((string) @file_get_contents($work . '/php_errors4.log'), 'SQLSTATE'));
    check('development: the same failure also shows a path-free one-line detail for the developer', $errDev['http'] === 500 && is_string($errDev['detail']) && str_contains($errDev['detail'], 'RuntimeException') && !str_contains($errDev['detail'], 'C:'));
    check('audit text has no secrets and no file names', (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE event_type LIKE 'VEHICLE\\_IMAGE\\_%' AND (description LIKE '%password%' OR description LIKE '%token%' OR description LIKE '%session%' OR description LIKE '%.jpg%' OR description LIKE '%.png%')")->fetchColumn() === 0);
} finally {
    $pdo->rollBack();
}

echo "Cleanup\n";
check('rollback left no image rows from this run', (int) run($pdo, "SELECT COUNT(*) FROM vehicle_images WHERE file_path LIKE 'uploads/vehicles/%' AND original_filename IN ('front.jpg','side.png','rear.webp','will-vanish.jpg','v2.jpg')")->fetchColumn() === 0);
check('no vehicle audit rows remain from this run', (int) run($pdo, "SELECT COUNT(*) FROM audit_logs WHERE user_agent = ?", [$ua])->fetchColumn() === 0);
$realAfter = array_values(array_diff(scandir($realUploads) ?: [], ['.', '..']));
check('the real uploads/vehicles folder was never written to by these tests', $realAfter === $realBefore);
foreach (['vehicle_images', 'vehicles', 'audit_logs'] as $table) {
    $pdo->exec("ALTER TABLE `$table` AUTO_INCREMENT = 1");
}

echo "\n$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
