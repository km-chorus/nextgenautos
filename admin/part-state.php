<?php

declare(strict_types=1);

/**
 * Publish / unpublish, stock status and archive for one part (spec 16, phase 1). POST only; the CSRF token, id and action
 * are validated by PartController. Protected: requireAdmin() runs first.
 */

require_once dirname(__DIR__) . '/app/helpers/auth.php';
require_once dirname(__DIR__) . '/app/controllers/PartController.php';

$admin = requireAdmin();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Method not allowed.\n";
    exit;
}

$view = (new PartController())->changeState($admin, $_POST, admin_client_ip(), admin_user_agent());
if (isset($view['redirect'])) {
    admin_redirect($view['redirect'], 303);
}
require dirname(__DIR__) . '/includes/admin/part_problem.php';
