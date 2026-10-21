<?php

declare(strict_types=1);

/**
 * Upload vehicle images (spec 07_spec_vehicle_image.md). POST only. The vehicle id is in the
 * URL (validated); CSRF token, permission, every file's content, size and dimensions are
 * checked by VehicleImageController and VehicleImageService, never by the browser.
 * Success and per-file failures are reported on the gallery page after a redirect.
 * Protected: requireAdmin() runs first.
 */

require_once dirname(__DIR__) . '/app/helpers/auth.php';
require_once dirname(__DIR__) . '/app/controllers/VehicleImageController.php';

$admin = requireAdmin();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Method not allowed.\n";
    exit;
}

// Processing writes files and then a database record. A browser that navigates away or drops the connection must not
// stop the script between those two steps (that would leave a file with no record), and ten large photographs can take
// longer than the default 30 seconds on a slow host.
ignore_user_abort(true);
@set_time_limit(180);

$view = (new VehicleImageController())->upload($admin, $_GET['id'] ?? null, $_POST, $_FILES, $_SERVER, admin_client_ip(), admin_user_agent());

if (isset($view['redirect'])) {
    admin_redirect($view['redirect'], 303);
}
require dirname(__DIR__) . '/includes/admin/vehicle_problem.php';
