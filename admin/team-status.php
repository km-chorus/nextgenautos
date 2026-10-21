<?php

declare(strict_types=1);

/**
 * Activate / deactivate a team member (spec 08_spec_team.md). POST only: a GET (a link, an image
 * tag, a prefetch) is refused and does nothing. CSRF token, id and target status are validated
 * by TeamController. Success redirects back (Post/Redirect/Get). Protected: requireAdmin() first.
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

$view = (new TeamController())->changeStatus($admin, $_POST, admin_client_ip(), admin_user_agent());

if (isset($view['redirect'])) {
    admin_redirect($view['redirect'], 303);
}
require dirname(__DIR__) . '/includes/admin/team_problem.php';
