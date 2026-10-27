<?php

declare(strict_types=1);

/**
 * Preview of the customer email for a shipment and status (spec 20): exactly what EmailTemplate would send. Shows the
 * message only; sends nothing. Protected: requireAdmin() runs first.
 *
 * Emails rely on inline styles, which the admin Content-Security-Policy forbids, so this page replaces it with a narrow
 * one: inline styles and https / data images only, NO scripts, no framing. All content is escaped by EmailTemplate.
 */

require_once dirname(__DIR__) . '/app/helpers/auth.php';
require_once dirname(__DIR__) . '/app/controllers/ShipmentController.php';

$admin = requireAdmin();

$view = (new ShipmentController())->preview($admin, $_GET['id'] ?? null, $_GET['status'] ?? null);
if ($view['problem'] !== null) {
    require dirname(__DIR__) . '/includes/admin/shipment_problem.php';
}
$p = $view['preview'];

header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; img-src https: data:; base-uri 'none'; form-action 'none'; frame-ancestors 'none'");
$banner = '<div style="font-family:Arial,sans-serif;font-size:13px;padding:10px 16px;background:#FFF7E6;border-bottom:1px solid #E5C17A;color:#5A4300;">'
    . 'Preview only, nothing was sent. Subject: <strong>' . e((string) $p['subject']) . '</strong>. To: <strong>' . e((string) ($p['to'] ?? 'no email address')) . '</strong>.</div>';
echo preg_replace('/<body([^>]*)>/', '<body$1>' . $banner, (string) $p['html'], 1);
