<?php

declare(strict_types=1);

/**
 * Admin logout. Works without JavaScript: the sidebar button is a normal POST
 * form carrying the CSRF token. GET never signs anyone out. The session is
 * destroyed server-side and the cookie expired, then the user lands on login.
 */

require_once dirname(__DIR__) . '/app/controllers/AuthController.php';

admin_send_headers();

$target = (new AuthController())->logout(
    $_SERVER['REQUEST_METHOD'] ?? 'GET',
    $_POST,
    admin_client_ip(),
    admin_user_agent()
);

admin_redirect($target, 303);
