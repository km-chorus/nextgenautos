<?php

declare(strict_types=1);

/**
 * Data integrity and audit regressions (QA and security audit, 2026-10-27). CLI only:
 *   php database/tests/integrity_test.php
 * Everything happens inside transactions that are rolled back; nothing is left in the database.
 *
 *  - Transaction::run: a failure after the first write leaves nothing behind (atomic); a failure inside a nested call
 *    rolls back the whole outer operation; a success commits both writes.
 *  - The database refuses bad data by itself (strict mode, foreign keys, unique and check constraints).
 *  - Connection settings: exceptions, real prepared statements, UTC, utf8mb4, strict SQL mode.
 *  - SEC-01 regression: the PHP version header is removed (code and .htaccess).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__, 2);
require_once $root . '/app/config/database.php';
require_once $root . '/app/services/Transaction.php';

$passed = 0;
$failed = 0;
$check = static function (string $label, bool $ok, string $detail = '') use (&$passed, &$failed): void {
    $ok ? $passed++ : $failed++;
    echo '  ' . ($ok ? 'PASS' : 'FAIL') . "  $label" . (!$ok && $detail !== '' ? " -> $detail" : '') . "\n";
};
$pdo = Database::connection();
$count = static fn (string $slug): int => (int) $pdo->query('SELECT COUNT(*) FROM brands WHERE slug = ' . $pdo->quote($slug))->fetchColumn();
$addBrand = static function (string $slug) use ($pdo): void {
    $pdo->prepare('INSERT INTO brands (name, slug, status, display_order) VALUES (?, ?, ?, ?)')->execute([strtoupper($slug), $slug, 'active', 999]);
};

echo "Transactions\n";
try {
    Transaction::run($pdo, static function () use ($addBrand): void {
        $addBrand('zzqa-one');
        throw new RuntimeException('simulated failure after the first write');
    });
} catch (RuntimeException) {
}
$check('a failure after the first write leaves nothing behind', $count('zzqa-one') === 0);

try {
    Transaction::run($pdo, static function () use ($pdo, $addBrand): void {
        $addBrand('zzqa-outer');
        Transaction::run($pdo, static function () use ($addBrand): void {   // nested: joins the outer transaction
            $addBrand('zzqa-inner');
            throw new RuntimeException('simulated failure in a nested step');
        });
    });
} catch (RuntimeException) {
}
$check('a failure in a nested step rolls back the whole operation', $count('zzqa-outer') === 0 && $count('zzqa-inner') === 0);
$check('no transaction is left open after a failure', !$pdo->inTransaction());

try {
    Transaction::run($pdo, static function () use ($addBrand): void {
        $addBrand('zzqa-dup');
        $addBrand('zzqa-dup');   // the unique key refuses the second row
    });
} catch (PDOException) {
}
$check('a database error (duplicate) mid-way also rolls back the first write', $count('zzqa-dup') === 0);

$pdo->beginTransaction();   // outer test transaction: the successful case is rolled back afterwards
try {
    Transaction::run($pdo, static function () use ($addBrand): void {
        $addBrand('zzqa-ok-1');
        $addBrand('zzqa-ok-2');
    });
    $check('a successful operation keeps both writes', $count('zzqa-ok-1') === 1 && $count('zzqa-ok-2') === 1);
} finally {
    $pdo->rollBack();
}
$check('rolled back: no test brand remains', (int) $pdo->query("SELECT COUNT(*) FROM brands WHERE slug LIKE 'zzqa-%'")->fetchColumn() === 0);

echo "\nThe database refuses bad data by itself\n";
$refused = static function (string $sql) use ($pdo): bool {
    $pdo->beginTransaction();
    try {
        $pdo->exec($sql);

        return false;
    } catch (PDOException) {
        return true;
    } finally {
        $pdo->rollBack();
    }
};
$check('a vehicle for a brand that does not exist (foreign key)', $refused("INSERT INTO vehicles (brand_id, year, model, price, currency, availability_status, slug) VALUES (999999, 2020, 'X', 1, 'GHS', 'available', 'zzqa-fk')"));
$check('a negative price (check constraint)', $refused("INSERT INTO vehicles (brand_id, year, model, price, currency, availability_status, slug) VALUES ((SELECT MIN(id) FROM brands), 2020, 'X', -1, 'GHS', 'available', 'zzqa-neg')"));
$check('an unknown status value (strict mode: no silent truncation)', $refused("INSERT INTO vehicles (brand_id, year, model, price, currency, availability_status, slug) VALUES ((SELECT MIN(id) FROM brands), 2020, 'X', 1, 'GHS', 'flying', 'zzqa-enum')"));
$check('a second customer with the same email (unique)', $refused("INSERT INTO customers (name, email, password_hash, status) VALUES ('A', 'zzqa@example.test', 'x', 'active'), ('B', 'zzqa@example.test', 'x', 'active')"));
$check('deleting a brand that vehicles use (restrict)', !(int) $pdo->query('SELECT COUNT(*) FROM vehicles')->fetchColumn()
    || $refused('DELETE FROM brands WHERE id = (SELECT brand_id FROM (SELECT brand_id FROM vehicles LIMIT 1) x)'));

echo "\nConnection settings\n";
$check('errors are exceptions; prepared statements are real (not emulated)', $pdo->getAttribute(PDO::ATTR_ERRMODE) === PDO::ERRMODE_EXCEPTION && $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES) === false);
$mode = (string) $pdo->query('SELECT @@SESSION.sql_mode')->fetchColumn();
$check('strict SQL mode', str_contains($mode, 'STRICT_ALL_TABLES') && str_contains($mode, 'STRICT_TRANS_TABLES'));
$check('UTC time zone and utf8mb4', $pdo->query('SELECT @@SESSION.time_zone')->fetchColumn() === '+00:00' && $pdo->query('SELECT @@character_set_connection')->fetchColumn() === 'utf8mb4');
$check('every table is InnoDB (transactions and foreign keys)', (int) $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND ENGINE <> 'InnoDB'")->fetchColumn() === 0);

echo "\nAudit regressions\n";
$app = (string) file_get_contents($root . '/app/config/app.php');
$ht = (string) file_get_contents($root . '/.htaccess');
$check('SEC-01: PHP removes the X-Powered-By header on every web request', str_contains($app, "header_remove('X-Powered-By')"));
$check('SEC-01: Apache also removes it (mod_headers)', str_contains($ht, 'Header always unset X-Powered-By'));

echo "\nResult: $passed passed, $failed failed\n";
exit($failed > 0 ? 1 : 0);
