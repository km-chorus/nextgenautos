<?php

declare(strict_types=1);

/**
 * Remove a team member's photo (spec 08_spec_team.md). POST only: the member stays, without a
 * photo. The record is changed first; the files are removed afterwards. Uploading or replacing
 * a photo is part of the add/edit form. CSRF token and id are validated by TeamController.
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

ignore_user_abort(true);
$view = (new TeamController())->removePhoto($admin, $_POST, admin_client_ip(), admin_user_agent());

if (isset($view['redirect'])) {
    admin_redirect($view['redirect'], 303);
}
require dirname(__DIR__) . '/includes/admin/team_problem.php';
