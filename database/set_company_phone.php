<?php

declare(strict_types=1);

/**
 * Stores (or clears) the company phone number shown in the public header (click-to-call).
 * CLI only.   php database/set_company_phone.php "+233 24 123 4567"
 *             php database/set_company_phone.php --clear
 *
 * The number is validated and normalised exactly as the website does (CompanyContactService), then saved in
 * the existing `settings` table (key company_phone, category contact). No other data is touched.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/app/services/CompanyContactService.php';

$arg = $argv[1] ?? '';
if ($arg === '') {
    fwrite(STDERR, "Usage: php database/set_company_phone.php \"+233 24 123 4567\"   (or --clear)\n");
    exit(1);
}

$pdo = Database::connection();
if ($arg === '--clear') {
    $pdo->prepare('DELETE FROM settings WHERE setting_key = :key')->execute(['key' => CompanyContactService::PHONE_KEY]);
    echo "Company phone number removed: the header shows none.\n";
    exit(0);
}

$phone = CompanyContactService::normalizePhone($arg);
if ($phone === null) {
    fwrite(STDERR, "That does not look like a valid phone number. Use a Ghana number (024 123 4567 or +233 24 123 4567) or an international number starting with +.\n");
    exit(1);
}
$pdo->prepare(
    "INSERT INTO settings (setting_key, category, setting_value) VALUES (:key, 'contact', :value)
     ON DUPLICATE KEY UPDATE category = 'contact', setting_value = VALUES(setting_value)"
)->execute(['key' => CompanyContactService::PHONE_KEY, 'value' => $phone['display']]);

echo 'Saved. The header shows "' . $phone['display'] . '" and calls ' . $phone['tel'] . ".\n";
