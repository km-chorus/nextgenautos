<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/services/SessionService.php';
require_once __DIR__ . '/html.php';

/**
 * CSRF protection for state-changing admin requests (login, logout, and every
 * future admin form). One random token per session, generated server-side with
 * random_bytes(), checked server-side with a constant-time comparison. The
 * token is rotated when a session is established (see SessionService::establish).
 * It lives only in the server-side session and in hidden form fields.
 */

const CSRF_FIELD_NAME = 'csrf_token';

function csrf_token(): string
{
    (new SessionService())->start();

    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/** Hidden input for a POST form. */
function csrf_field(): string
{
    return '<input type="hidden" name="' . CSRF_FIELD_NAME . '" value="' . e(csrf_token()) . '">';
}

/** True only when the submitted value equals this session's token. */
function csrf_verify(mixed $submitted): bool
{
    if (!is_string($submitted) || $submitted === '') {
        return false;
    }
    (new SessionService())->start();
    $expected = $_SESSION['csrf_token'] ?? '';

    return is_string($expected) && $expected !== '' && hash_equals($expected, $submitted);
}
