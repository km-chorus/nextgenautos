<?php

declare(strict_types=1);

/**
 * Change a vehicle image: set the primary image, save alt text, or save a new gallery order
 * (spec 07_spec_vehicle_image.md). POST only: a GET changes nothing. The CSRF token, the
 * vehicle id, the image id (which must belong to that vehicle) and, for reordering, the
 * complete list of ids are all validated by VehicleImageController / VehicleImageService.
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

ignore_user_abort(true);   // finish a multi-row change even if the browser disconnects

$view = (new VehicleImageController())->update($admin, $_POST, admin_client_ip(), admin_user_agent());

if (isset($view['redirect'])) {
    admin_redirect($view['redirect'], 303);
}
require dirname(__DIR__) . '/includes/admin/vehicle_problem.php';
