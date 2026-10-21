<?php

declare(strict_types=1);

/**
 * Prints a new application encryption key for the APP_KEY setting.
 *
 *     php database/generate_app_key.php
 *
 * Put the printed line in the server's environment configuration (.env locally; the
 * hosting control panel or a private .env in production). It is a SECRET:
 *   - never commit it, never store it in the database;
 *   - back it up somewhere safe: if it is lost, stored authenticator secrets and
 *     recovery codes can no longer be used (administrators would have to set up two-factor again);
 *   - changing it later invalidates existing two-factor enrollments.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

echo 'APP_KEY=base64:' . base64_encode(random_bytes(32)) . "\n";
