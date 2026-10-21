<?php

declare(strict_types=1);

/**
 * Remove a part photo or make it the main photo (spec 16, phase 1). POST only: the record changes first, the file is
 * removed afterwards. CSRF token, ids and action are validated by PartController. Protected: requireAdmin() runs first.
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

ignore_user_abort(true);
$view = (new PartController())->changePhoto($admin, $_POST, admin_client_ip(), admin_user_agent());
if (isset($view['redirect'])) {
    admin_redirect($view['redirect'], 303);
}
require dirname(__DIR__) . '/includes/admin/part_problem.php';
