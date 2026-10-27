<?php

declare(strict_types=1);

/**
 * Database foundation tests (spec 01_spec_database.md, section 30).
 *
 * CLI only:  php database/tests/db_test.php            structure, constraints and settings of any database
 *            php database/tests/db_test.php --fresh    also: a NEW install holds only the seed data (run right after
 *                                                       loading schema.sql + seed.sql, before anyone uses the site)
 * Requires:  schema.sql + seed.sql (and the migrations) loaded into the database named in .env / environment.
 *            Use a development database; the data checks never change anything (rolled-back transactions).
 * Reworked 2026-10-26 for deployment: a working database legitimately holds vehicles, customers and parts.
 *
 * Every data-changing test runs inside a transaction that is rolled back, so
 * the database is left exactly as it was found.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__, 2) . '/app/config/database.php';

$passed = 0;
$failed = 0;
$fresh = in_array('--fresh', $argv ?? [], true);

function check(string $label, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) {
        $passed++;
        echo "  PASS  $label\n";
    } else {
        $failed++;
        echo "  FAIL  $label" . ($detail !== '' ? " -> $detail" : '') . "\n";
    }
}

/** Run $fn expecting the database to reject it (PDOException). */
function rejects(PDO $pdo, string $label, callable $fn): void
{
    $pdo->beginTransaction();
    try {
        $fn($pdo);
        check($label, false, 'statement was accepted');
    } catch (PDOException $e) {
        check($label, true);
    } finally {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
}

/** Run $fn expecting success, then roll back. */
function accepts(PDO $pdo, string $label, callable $fn): void
{
    $pdo->beginTransaction();
    try {
        $fn($pdo);
        check($label, true);
    } catch (PDOException $e) {
        check($label, false, $e->getMessage());
    } finally {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
}

function run(PDO $pdo, string $sql, array $params = []): PDOStatement
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

const HASH = '$2y$10$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ0123456'; // 60 chars, NOT a real hash

function newAdmin(PDO $pdo, string $email = 'test.admin@example.test'): int
{
    run($pdo, 'INSERT INTO admin_users (name, email, password_hash) VALUES (?, ?, ?)', ['Test Admin', $email, HASH]);
    return (int) $pdo->lastInsertId();
}

function newVehicle(PDO $pdo, string $slug = 'test-vehicle-1', int $brandId = 0): int
{
    if ($brandId === 0) {
        $brandId = (int) run($pdo, "SELECT id FROM brands WHERE slug = 'toyota'")->fetchColumn();
    }
    run(
        $pdo,
        'INSERT INTO vehicles (brand_id, year, model, price, slug) VALUES (?, 2020, ?, 1000.00, ?)',
        [$brandId, 'TEST MODEL', $slug]
    );
    return (int) $pdo->lastInsertId();
}

// ---------------------------------------------------------------- Connection
echo "Connection\n";
$config = Database::config();
check('configuration provides database name and user', $config['name'] !== '' && $config['user'] !== '');

try {
    $pdo = Database::connection();
    check('valid credentials connect; PDO obtained', $pdo instanceof PDO);
} catch (Throwable $e) {
    check('valid credentials connect; PDO obtained', false, $e->getMessage());
    echo "\nCannot continue without a connection.\n";
    exit(1);
}

$bad = $config;
$bad['password'] = 'definitely-wrong-password';
$bad['user'] = 'no_such_user_xyz';
$prevLog = ini_set('error_log', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null');
try {
    Database::connect($bad);
    check('invalid credentials fail safely', false, 'connected with bad credentials');
} catch (RuntimeException $e) {
    check(
        'invalid credentials fail safely (generic message, no SQL/credential leak)',
        $e->getMessage() === 'Database connection failed.'
    );
} catch (Throwable $e) {
    check('invalid credentials fail safely', false, get_class($e));
}
ini_set('error_log', $prevLog === false ? '' : $prevLog);

check('session time zone is UTC', run($pdo, 'SELECT @@session.time_zone')->fetchColumn() === '+00:00');

$startCounts = ['vehicles' => (int) run($pdo, 'SELECT COUNT(*) FROM vehicles')->fetchColumn(), 'brands' => (int) run($pdo, 'SELECT COUNT(*) FROM brands')->fetchColumn(),
    'settings' => (int) run($pdo, 'SELECT COUNT(*) FROM settings')->fetchColumn()];

// ------------------------------------------------------------------- Schema
echo "\nSchema\n";
$tables = ['admin_users', 'brands', 'vehicles', 'vehicle_images', 'shipments', 'enquiries',
           'team_members', 'site_content', 'settings', 'audit_logs',
           'admin_recovery_codes', 'password_reset_tokens',                // migration 001 (2FA, password reset)
           'customers',                                                     // migrations 003, 004, 009, 011
           'part_categories', 'parts', 'part_fitments', 'part_images',     // migration 006 (parts catalogue)
           'part_numbers',                                                  // migration 012 (spec 27)
           'customer_favourites'];                                          // migration 009 (customer dashboard)
$dbName = $config['name'];
foreach ($tables as $t) {
    $row = run(
        $pdo,
        'SELECT ENGINE, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
        [$dbName, $t]
    )->fetch();
    check(
        "table $t exists (InnoDB, utf8mb4)",
        $row !== false && strtolower((string) $row['ENGINE']) === 'innodb' && str_starts_with((string) $row['TABLE_COLLATION'], 'utf8mb4_'),
        $row === false ? 'missing' : json_encode($row)
    );
    $pk = run(
        $pdo,
        "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_TYPE = 'PRIMARY KEY'",
        [$dbName, $t]
    )->fetchColumn();
    check("table $t has a primary key", (int) $pk === 1);
}

$extra = run(
    $pdo,
    "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'",
    [$dbName]
)->fetchAll(PDO::FETCH_COLUMN);
check('no unexpected tables', array_diff($extra, $tables) === [], implode(',', array_diff($extra, $tables)));

$expectedFks = [
    'fk_vehicles_brand', 'fk_vehicle_images_vehicle', 'fk_shipments_vehicle',
    'fk_enquiries_vehicle', 'fk_audit_logs_admin',
    'fk_recovery_codes_admin', 'fk_password_reset_admin',
];
$fks = run(
    $pdo,
    "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
     WHERE TABLE_SCHEMA = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
    [$dbName]
)->fetchAll(PDO::FETCH_COLUMN);
check('all 7 foreign keys exist', array_diff($expectedFks, $fks) === [], implode(',', array_diff($expectedFks, $fks)));

$priceType = run(
    $pdo,
    "SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'vehicles' AND COLUMN_NAME = 'price'",
    [$dbName]
)->fetchColumn();
check('vehicles.price is fixed-precision DECIMAL', strtolower((string) $priceType) === 'decimal');

$hashLen = run(
    $pdo,
    "SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'admin_users' AND COLUMN_NAME = 'password_hash'",
    [$dbName]
)->fetchColumn();
check('password_hash column is at least 255 chars', (int) $hashLen >= 255);
$tfCols = run($pdo, "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'admin_users'", [$dbName])->fetchAll(PDO::FETCH_COLUMN);
$col = static fn (string $table): array => run($pdo, 'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?', [$dbName, $table])->fetchAll(PDO::FETCH_COLUMN);
check('migrations 007-011 are applied (vehicle photo type, shipment customer, dashboard, accident vehicles, password change time)',
    in_array('photo_type', $col('vehicle_images'), true) && in_array('customer_id', $col('shipments'), true) && in_array('customer_id', $col('enquiries'), true)
    && array_diff(['email_verified_at', 'password_changed_at'], $col('customers')) === []
    && array_diff(['is_accident', 'damage_description', 'damage_areas', 'runs_drives', 'airbags_deployed', 'title_status'], $col('vehicles')) === []);
check('password reset links and codes are not stored in the database (spec 24: file cache only)', !in_array('customer_password_resets', $extra, true));
check('admin_users has the two-factor and password_changed_at columns', array_diff(['two_factor_enabled', 'two_factor_secret_encrypted', 'two_factor_confirmed_at', 'two_factor_last_step', 'password_changed_at'], $tfCols) === []);

// ---------------------------------------------------------------- Seed data
echo "\nSeed data\n";
$approved = ['Hyundai', 'Honda', 'Toyota', 'Mazda', 'Ford', 'Chevrolet', 'Dodge', 'Nissan',
             'Mercedes-Benz', 'BMW', 'Mitsubishi', 'GMC'];
$names = run($pdo, 'SELECT name FROM brands ORDER BY display_order')->fetchAll(PDO::FETCH_COLUMN);
check('no duplicate brands', count(array_unique($names)) === count($names));
if ($fresh) {
    check('fresh install: exactly the 12 approved brands exist, in order', $names === $approved, implode(',', $names));
    foreach (['vehicles', 'vehicle_images', 'shipments', 'enquiries', 'team_members', 'site_content', 'admin_recovery_codes', 'password_reset_tokens',
                 'customers', 'customer_favourites', 'parts', 'part_fitments', 'part_images', 'part_numbers'] as $t) {
        check("fresh install: no seeded rows in $t", (int) run($pdo, "SELECT COUNT(*) FROM `$t`")->fetchColumn() === 0);
    }
} else {
    echo "  (skipped: the empty-database checks of a NEW install; run with --fresh right after installing)\n";
}
// admin_users and audit_logs fill up legitimately at runtime (real administrators, real security events),
// so the check is on the seed FILE: it must never create administrators or log rows.
$seedSql = (string) file_get_contents(dirname(__DIR__) . '/seed.sql');
check('seed.sql inserts nothing into admin_users or audit_logs (no default credentials, no fake events)',
    preg_match('/INSERTs+INTOs+`?(admin_users|audit_logs|admin_recovery_codes|password_reset_tokens)`?/i', $seedSql) !== 1);
$settingKeys = run($pdo, 'SELECT setting_key FROM settings ORDER BY setting_key')->fetchAll(PDO::FETCH_COLUMN);
require_once dirname(__DIR__, 2) . '/app/validation/ContactSettingsValidator.php';
$allowedKeys = array_merge(['duty_calculator_url', 'tracking_provider_url', 'accident_vehicles_enabled'], array_keys(ContactSettingsValidator::FIELDS));
check('settings hold only keys the application knows (no secrets, nothing unexpected)', array_diff($settingKeys, $allowedKeys) === [], implode(',', array_diff($settingKeys, $allowedKeys)));
if ($fresh) {
    check('fresh install: settings contain only the two approved external URLs', $settingKeys === ['duty_calculator_url', 'tracking_provider_url'], implode(',', $settingKeys));
}

// ------------------------------------------------- Relationships (success)
echo "\nRelationships\n";
accepts($pdo, 'brand -> vehicle', fn (PDO $p) => newVehicle($p));
accepts($pdo, 'vehicle -> vehicle image', function (PDO $p) {
    $v = newVehicle($p);
    run($p, 'INSERT INTO vehicle_images (vehicle_id, file_path) VALUES (?, ?)', [$v, 'uploads/vehicles/test.webp']);
});
accepts($pdo, 'vehicle -> shipment (container number NULL = not yet provided)', function (PDO $p) {
    $v = newVehicle($p);
    run($p, 'INSERT INTO shipments (vehicle_id) VALUES (?)', [$v]);
    $row = run($p, 'SELECT status, container_number FROM shipments WHERE vehicle_id = ?', [$v])->fetch();
    if ($row['status'] !== 'NOT_YET_SHIPPED' || $row['container_number'] !== null) {
        throw new PDOException('unexpected shipment defaults');
    }
});
accepts($pdo, 'vehicle -> enquiry', function (PDO $p) {
    $v = newVehicle($p);
    run($p, 'INSERT INTO enquiries (vehicle_id, customer_name, email, message) VALUES (?, ?, ?, ?)', [$v, 'Test Customer', 'c@example.test', 'Test message']);
});
accepts($pdo, 'general enquiry without a vehicle', function (PDO $p) {
    run($p, 'INSERT INTO enquiries (customer_name, email, message) VALUES (?, ?, ?)', ['Test Customer', 'c@example.test', 'Test message']);
});
accepts($pdo, 'admin -> audit log (and anonymous audit log)', function (PDO $p) {
    $a = newAdmin($p);
    run($p, 'INSERT INTO audit_logs (admin_user_id, event_type) VALUES (?, ?)', [$a, 'login_success']);
    run($p, 'INSERT INTO audit_logs (admin_user_id, event_type) VALUES (NULL, ?)', ['login_failed']);
});
accepts($pdo, 'deleting a vehicle removes its images (deliberate cascade)', function (PDO $p) {
    $v = newVehicle($p);
    run($p, 'INSERT INTO vehicle_images (vehicle_id, file_path) VALUES (?, ?)', [$v, 'uploads/vehicles/test.webp']);
    run($p, 'DELETE FROM vehicles WHERE id = ?', [$v]);
    if ((int) run($p, 'SELECT COUNT(*) FROM vehicle_images WHERE vehicle_id = ?', [$v])->fetchColumn() !== 0) {
        throw new PDOException('orphan image left behind');
    }
});
accepts($pdo, 'each of the 6 shipment statuses is accepted', function (PDO $p) {
    $v = newVehicle($p);
    foreach (['NOT_YET_SHIPPED', 'SHIPMENT_BOOKED', 'TRACKING_INFORMATION_AVAILABLE', 'IN_TRANSIT', 'ARRIVED', 'DELIVERED'] as $s) {
        run($p, 'INSERT INTO shipments (vehicle_id, status) VALUES (?, ?)', [$v, $s]);
    }
});
accepts($pdo, 'price keeps exact 2-decimal precision', function (PDO $p) {
    $brand = (int) run($p, "SELECT id FROM brands WHERE slug = 'bmw'")->fetchColumn();
    run($p, 'INSERT INTO vehicles (brand_id, year, model, price, slug) VALUES (?, 2021, ?, 0.10, ?)', [$brand, 'TEST', 'precision-test']);
    $price = run($p, "SELECT price FROM vehicles WHERE slug = 'precision-test'")->fetchColumn();
    if ($price !== '0.10') {
        throw new PDOException("price stored as $price");
    }
});

// -------------------------------------------------------------- Integrity
echo "\nIntegrity (these must be rejected)\n";
rejects($pdo, 'duplicate admin email (case-insensitive)', function (PDO $p) {
    newAdmin($p, 'dup@example.test');
    newAdmin($p, 'DUP@example.test');
});
rejects($pdo, 'duplicate brand name', fn (PDO $p) => run($p, "INSERT INTO brands (name, slug) VALUES ('Toyota', 'toyota-2')"));
rejects($pdo, 'duplicate brand slug', fn (PDO $p) => run($p, "INSERT INTO brands (name, slug) VALUES ('Toyota Two', 'toyota')"));
rejects($pdo, 'invalid brand slug format', fn (PDO $p) => run($p, "INSERT INTO brands (name, slug) VALUES ('Bad Slug', 'Bad Slug!')"));
rejects($pdo, 'duplicate vehicle slug', function (PDO $p) {
    newVehicle($p, 'same-slug');
    newVehicle($p, 'same-slug');
});
rejects($pdo, 'vehicle with nonexistent brand (invalid reference)', fn (PDO $p) => newVehicle($p, 'orphan-brand', 999999));
rejects($pdo, 'vehicle image for nonexistent vehicle', fn (PDO $p) => run($p, 'INSERT INTO vehicle_images (vehicle_id, file_path) VALUES (999999, ?)', ['x.webp']));
rejects($pdo, 'shipment for nonexistent vehicle', fn (PDO $p) => run($p, 'INSERT INTO shipments (vehicle_id) VALUES (999999)'));
rejects($pdo, 'enquiry for nonexistent vehicle', fn (PDO $p) => run($p, 'INSERT INTO enquiries (vehicle_id, customer_name, email, message) VALUES (999999, ?, ?, ?)', ['A', 'a@example.test', 'm']));
rejects($pdo, 'audit log for nonexistent admin', fn (PDO $p) => run($p, "INSERT INTO audit_logs (admin_user_id, event_type) VALUES (999999, 'x')"));
rejects($pdo, 'deleting a brand that has vehicles (RESTRICT)', function (PDO $p) {
    $v = newVehicle($p);
    $brand = (int) run($p, 'SELECT brand_id FROM vehicles WHERE id = ?', [$v])->fetchColumn();
    run($p, 'DELETE FROM brands WHERE id = ?', [$brand]);
});
rejects($pdo, 'deleting a vehicle that has a shipment (RESTRICT)', function (PDO $p) {
    $v = newVehicle($p);
    run($p, 'INSERT INTO shipments (vehicle_id) VALUES (?)', [$v]);
    run($p, 'DELETE FROM vehicles WHERE id = ?', [$v]);
});
rejects($pdo, 'deleting a vehicle that has an enquiry (RESTRICT)', function (PDO $p) {
    $v = newVehicle($p);
    run($p, 'INSERT INTO enquiries (vehicle_id, customer_name, email, message) VALUES (?, ?, ?, ?)', [$v, 'A', 'a@example.test', 'm']);
    run($p, 'DELETE FROM vehicles WHERE id = ?', [$v]);
});
rejects($pdo, 'deleting an admin that has audit logs (RESTRICT)', function (PDO $p) {
    $a = newAdmin($p);
    run($p, "INSERT INTO audit_logs (admin_user_id, event_type) VALUES (?, 'x')", [$a]);
    run($p, 'DELETE FROM admin_users WHERE id = ?', [$a]);
});
rejects($pdo, 'two primary images for one vehicle', function (PDO $p) {
    $v = newVehicle($p);
    run($p, 'INSERT INTO vehicle_images (vehicle_id, file_path, is_primary) VALUES (?, ?, 1)', [$v, 'a.webp']);
    run($p, 'INSERT INTO vehicle_images (vehicle_id, file_path, is_primary) VALUES (?, ?, 1)', [$v, 'b.webp']);
});
rejects($pdo, 'negative price', function (PDO $p) {
    $brand = (int) run($p, "SELECT id FROM brands WHERE slug = 'ford'")->fetchColumn();
    run($p, 'INSERT INTO vehicles (brand_id, year, model, price, slug) VALUES (?, 2020, ?, -1, ?)', [$brand, 'X', 'neg-price']);
});
rejects($pdo, 'out-of-range year', function (PDO $p) {
    $brand = (int) run($p, "SELECT id FROM brands WHERE slug = 'ford'")->fetchColumn();
    run($p, 'INSERT INTO vehicles (brand_id, year, model, price, slug) VALUES (?, 1800, ?, 1, ?)', [$brand, 'X', 'bad-year']);
});
rejects($pdo, 'missing required vehicle field (model NULL)', function (PDO $p) {
    $brand = (int) run($p, "SELECT id FROM brands WHERE slug = 'ford'")->fetchColumn();
    run($p, 'INSERT INTO vehicles (brand_id, year, model, price, slug) VALUES (?, 2020, NULL, 1, ?)', [$brand, 'no-model']);
});
rejects($pdo, 'invalid vehicle status value', function (PDO $p) {
    $v = newVehicle($p);
    run($p, "UPDATE vehicles SET availability_status = 'bogus' WHERE id = ?", [$v]);
});
rejects($pdo, 'key_features that is not a JSON array', function (PDO $p) {
    $v = newVehicle($p);
    run($p, "UPDATE vehicles SET key_features = '{\"a\":1}' WHERE id = ?", [$v]);
});
rejects($pdo, 'invalid shipment status (carrier-style value)', function (PDO $p) {
    $v = newVehicle($p);
    run($p, "INSERT INTO shipments (vehicle_id, status) VALUES (?, 'LOADED_ON_VESSEL')", [$v]);
});
rejects($pdo, 'empty-string container number (NULL is the only "none")', function (PDO $p) {
    $v = newVehicle($p);
    run($p, "INSERT INTO shipments (vehicle_id, container_number) VALUES (?, '  ')", [$v]);
});
rejects($pdo, 'non-https tracking URL', function (PDO $p) {
    $v = newVehicle($p);
    run($p, "INSERT INTO shipments (vehicle_id, tracking_url) VALUES (?, 'javascript:alert(1)')", [$v]);
});
rejects($pdo, 'password hash too short to be a real hash', function (PDO $p) {
    run($p, "INSERT INTO admin_users (name, email, password_hash) VALUES ('A', 'short@example.test', 'plaintext')");
});
rejects($pdo, 'duplicate setting key', fn (PDO $p) => run($p, "INSERT INTO settings (setting_key, category) VALUES ('duty_calculator_url', 'external_links')"));
rejects($pdo, 'setting with invalid category', fn (PDO $p) => run($p, "INSERT INTO settings (setting_key, category) VALUES ('x_key', 'secrets')"));
rejects($pdo, 'duplicate site_content key', function (PDO $p) {
    run($p, "INSERT INTO site_content (content_key, title, body) VALUES ('about_us', 'A', 'B')");
    run($p, "INSERT INTO site_content (content_key, title, body) VALUES ('about_us', 'A', 'B')");
});
rejects($pdo, 'enquiry with empty message', fn (PDO $p) => run($p, "INSERT INTO enquiries (customer_name, email, message) VALUES ('A', 'a@example.test', '')"));

// -------------------------------------------------------------- Post-check
echo "\nCleanup check\n";
check('tests left the data exactly as found (vehicles, brands, settings)', (int) run($pdo, 'SELECT COUNT(*) FROM vehicles')->fetchColumn() === $startCounts['vehicles']
    && (int) run($pdo, 'SELECT COUNT(*) FROM brands')->fetchColumn() === $startCounts['brands']
    && (int) run($pdo, 'SELECT COUNT(*) FROM settings')->fetchColumn() === $startCounts['settings']);

echo "\nResult: $passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
