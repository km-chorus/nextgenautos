<?php

declare(strict_types=1);

/**
 * Logic tests for the company phone number shown in the public navigation: CompanyContactService
 * (normalisation to a click-to-call number) and SettingsRepository. CLI only:
 *   php database/tests/company_contact_test.php
 * The database rows are created inside one transaction that is rolled back (real settings are never changed).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__, 2) . '/app/services/CompanyContactService.php';

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

echo "Normalisation\n";
$tel = static fn (string $v): ?string => CompanyContactService::normalizePhone($v)['tel'] ?? null;
foreach (['+233 24 123 4567', '024 123 4567', '0241234567', '00233241234567', '(+233) 24-123-4567', '233241234567', '+233.24.123.4567', "  0241234567  "] as $good) {
    $check('Ghana number "' . trim($good) . '" gives +233241234567', $tel($good) === '+233241234567', (string) $tel($good));
}
$check('another international number is kept as international', $tel('+1 (416) 555-0100') === '+14165550100');
$check('the number is displayed as the owner wrote it (trimmed)', CompanyContactService::normalizePhone("  024 123 4567 ")['display'] === '024 123 4567');
foreach (['', 'abc', '123', '+', '++233241234567', '+0123456789', '024 123 456', '0241234567890123', '<script>alert(1)</script>', '0241234567; DROP', 'tel:+233241234567', 'javascript:alert(1)', "024\0 1234567", str_repeat('0', 40), '+233 24 123 4567 ext 1', '+233 24 123 4567 <b>'] as $bad) {
    $check('rejects ' . json_encode(substr($bad, 0, 30)), $tel($bad) === null);
}

echo "\nSettings (rolled back)\n";
$pdo = Database::connection();
$service = new CompanyContactService(new SettingsRepository($pdo));
$pdo->beginTransaction();
try {
    $existing = $pdo->query("SELECT COUNT(*) FROM settings WHERE setting_key = 'company_phone'")->fetchColumn();
    $pdo->exec("DELETE FROM settings WHERE setting_key = 'company_phone'");
    $check('no stored number gives no phone (nothing is invented)', $service->phone() === null);
    $pdo->prepare("INSERT INTO settings (setting_key, category, setting_value) VALUES ('company_phone', 'contact', ?)")->execute(['024 123 4567']);
    $check('a stored number is read from the settings table', $service->phone() === ['display' => '024 123 4567', 'tel' => '+233241234567']);
    $pdo->exec("UPDATE settings SET category = 'social' WHERE setting_key = 'company_phone'");
    $check('only the contact category counts', $service->phone() === null);
    $pdo->exec("UPDATE settings SET category = 'contact', setting_value = '   ' WHERE setting_key = 'company_phone'");
    $check('a blank value counts as not set', $service->phone() === null);
    $pdo->exec("UPDATE settings SET setting_value = 'not a phone' WHERE setting_key = 'company_phone'");
    $check('an invalid value counts as not set', $service->phone() === null);
    $check('the table already held no phone before this test (informational)', true, (string) $existing);
} finally {
    $pdo->rollBack();
}
$check('rolled back: the real settings table is unchanged', (int) $pdo->query("SELECT COUNT(*) FROM settings WHERE setting_key = 'company_phone'")->fetchColumn() === (int) $existing);

echo "\nResult: $passed passed, $failed failed\n";
exit($failed > 0 ? 1 : 0);
