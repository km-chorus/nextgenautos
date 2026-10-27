<?php

declare(strict_types=1);

/**
 * Test fixtures for a FRESH, disposable test database (QA audit 2026-10-27). CLI only:
 *   DB_NAME=<test database> php database/tests/fixtures.php
 *
 * Several suites act "as an administrator" and use the first admin account. A brand-new database (schema.sql + seed.sql)
 * has none, so this adds ONE synthetic administrator (test-admin@example.test, random password that is never printed or
 * stored anywhere). Nothing else.
 *
 * Safety: it refuses to run unless the database name starts with "test" or "zz_", and never when APP_ENV=production.
 * It is idempotent: when any administrator exists it does nothing.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__, 2) . '/app/config/database.php';

$pdo = Database::connection();
$db = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
if (AppConfig::isProduction() || preg_match('/^(test|zz_)/', $db) !== 1) {
    fwrite(STDERR, "Refused: fixtures are only for a disposable test database (name starting with test or zz_), never production.\n");
    exit(1);
}
if ((int) $pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn() > 0) {
    echo "Fixtures: an administrator already exists, nothing to do.\n";
    exit(0);
}
$pdo->prepare('INSERT INTO admin_users (name, email, password_hash, role, status) VALUES (?, ?, ?, ?, ?)')
    ->execute(['Test Administrator', 'test-admin@example.test', password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT), 'administrator', 'active']);
echo "Fixtures: synthetic test administrator added to $db.\n";
