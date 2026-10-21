<?php

declare(strict_types=1);

/**
 * Save a new team order (spec 08_spec_team.md). POST only. The submitted list must contain
 * exactly the existing member ids, each once: TeamService rejects anything else (duplicates,
 * missing, unknown or malformed ids). The CSRF token is checked first.
 * Protected: requireAdmin() runs first.
 */

require_once dirname(__DIR__) . '/app/helpers/auth.php';
require_once dirname(__DIR__) . '/app/controllers/TeamController.php';

$admin = requireAdmin();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Method not allowed.\n";
    exit;
}

$view = (new TeamController())->reorder($admin, $_POST, admin_client_ip(), admin_user_agent());

if (isset($view['redirect'])) {
    admin_redirect($view['redirect'], 303);
}
require dirname(__DIR__) . '/includes/admin/team_problem.php';
