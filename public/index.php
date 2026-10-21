<?php

declare(strict_types=1);

/**
 * Public application entry point (spec 09_spec_webpage_ui.md): the front controller of the
 * public website. The web server sends every request that is not an existing file or folder
 * here (see .htaccess; the PHP development server does the same on its own).
 */

require_once dirname(__DIR__) . '/app/config/app.php';
require_once dirname(__DIR__) . '/app/services/PublicRouter.php';
require_once dirname(__DIR__) . '/app/controllers/PublicController.php';

(new PublicController(PublicRouter::create()))->handle(
    (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
    (string) ($_SERVER['REQUEST_URI'] ?? '/')
);
