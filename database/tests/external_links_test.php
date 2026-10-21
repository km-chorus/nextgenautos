<?php

declare(strict_types=1);

/**
 * Logic tests for the approved external links (spec 11B): ExternalLinksService. CLI only:
 *   php database/tests/external_links_test.php
 * Settings rows are changed inside one transaction that is rolled back.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__, 2) . '/app/services/ExternalLinksService.php';

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

$approved = 'https://autodutychecker.com/calculator/search-mode/';
echo "Approved address\n";
$check('the default is the approved calculator address from the specification', ExternalLinksService::DUTY_CALCULATOR_DEFAULT === $approved);
$check('a service without a database uses the approved address', (new ExternalLinksService(null))->dutyCalculatorUrl() === $approved);
$check('the approved address passes validation', ExternalLinksService::isApprovedDutyUrl($approved));
$check('another path on the approved host is accepted', ExternalLinksService::isApprovedDutyUrl('https://autodutychecker.com/calculator/'));

echo "\nHostile or wrong addresses are refused\n";
foreach ([
    'http://autodutychecker.com/calculator/search-mode/', 'https://evil.example/calculator/', 'https://autodutychecker.com.evil.example/x',
    'https://evil.example/https://autodutychecker.com/', 'https://user:pw@autodutychecker.com/', 'https://autodutychecker.com:8443/',
    'javascript:alert(1)', 'data:text/html,<script>', '//autodutychecker.com/x', 'https://autodutychecker.com/ x', "https://autodutychecker.com/\n",
    'https://autodutychecker.com/"onmouseover="x', 'https://autodutychecker.com/<script>', '', 'autodutychecker.com', 'https://sub.autodutychecker.com/', str_repeat('a', 400),
] as $bad) {
    $check('rejects ' . json_encode(substr($bad, 0, 45)), !ExternalLinksService::isApprovedDutyUrl($bad));
}

echo "\nSettings table (rolled back)\n";
$pdo = Database::connection();
$service = new ExternalLinksService(new SettingsRepository($pdo));
$stored = (string) $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'duty_calculator_url'")->fetchColumn();
$check('the real setting holds the approved address', $stored === $approved, $stored);
$pdo->beginTransaction();
try {
    $check('the stored approved address is used', $service->dutyCalculatorUrl() === $approved);
    $pdo->exec("UPDATE settings SET setting_value = 'https://evil.example/' WHERE setting_key = 'duty_calculator_url'");
    $check('a stored address on another host is ignored (the approved address is used)', $service->dutyCalculatorUrl() === $approved);
    $pdo->exec("UPDATE settings SET setting_value = 'javascript:alert(1)' WHERE setting_key = 'duty_calculator_url'");
    $check('a stored javascript: address is ignored', $service->dutyCalculatorUrl() === $approved);
    $pdo->exec("UPDATE settings SET setting_value = NULL WHERE setting_key = 'duty_calculator_url'");
    $check('an empty stored value falls back to the approved address', $service->dutyCalculatorUrl() === $approved);
    $pdo->exec("DELETE FROM settings WHERE setting_key = 'duty_calculator_url'");
    $check('a missing setting falls back to the approved address', $service->dutyCalculatorUrl() === $approved);
    $pdo->exec("INSERT INTO settings (setting_key, category, setting_value) VALUES ('duty_calculator_url', 'external_links', 'https://autodutychecker.com/calculator/search-mode/')");
    $check('the setting can be restored and is used', $service->dutyCalculatorUrl() === $approved);
} finally {
    $pdo->rollBack();
}
$check('rolled back: the real setting is unchanged', (string) $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'duty_calculator_url'")->fetchColumn() === $approved);

$src = (string) file_get_contents(dirname(__DIR__, 2) . '/includes/import_utilities.php') . (string) file_get_contents(dirname(__DIR__, 2) . '/public/views/home.php');
$check('the template contains no SQL and no hardcoded external address', !preg_match('/SELECT |PDO|Database::|https?:\/\//i', $src));

echo "\nResult: $passed passed, $failed failed\n";
exit($failed > 0 ? 1 : 0);
