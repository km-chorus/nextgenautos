<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/services/AuthService.php';
require_once __DIR__ . '/admin_ui.php';

/**
 * Authentication guard for the admin area.
 *
 * Every protected admin page calls requireAdmin() BEFORE producing any output.
 * Do not copy session checks into pages: this is the one implementation.
 *
 *   $admin = requireAdmin();                     // any signed-in admin (permission "admin.access")
 *   $admin = requireAdmin('../login.php');       // pages in a subfolder pass the login URL
 *
 * Returns ['id' => int, 'name' => string, 'role' => string]. Unauthenticated,
 * expired or no-longer-permitted sessions are redirected to the login page and
 * no protected content is rendered. If the authentication system itself
 * fails (for example the database is down) the guard fails CLOSED: a generic
 * 503 page, never protected content.
 */
function requireAdmin(string $loginUrl = 'login.php'): array
{
    admin_send_headers();

    try {
        $result = AuthService::create()->resolveSession(admin_client_ip(), admin_user_agent());
    } catch (Throwable $e) {
        error_log('Admin guard failed: ' . get_class($e) . ': ' . $e->getMessage());
        http_response_code(503);
        header('Content-Type: text/plain; charset=UTF-8');
        echo "Service temporarily unavailable. Please try again later.\n";
        exit;
    }

    if ($result['status'] === AuthService::SESSION_OK) {
        return $result['admin'];
    }

    $reason = $result['status'] === AuthService::SESSION_EXPIRED ? '?reason=timeout' : '';
    admin_redirect($loginUrl . $reason);
}

/** Redirects (default 302) and stops the script. Only ever called with fixed, internal URLs. */
function admin_redirect(string $url, int $status = 302): never
{
    header('Location: ' . $url, true, $status);
    exit;
}

/** Client address as seen by the web server (proxy headers are deliberately not trusted). */
function admin_client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : 'unknown';
}

function admin_user_agent(): string
{
    return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
}
