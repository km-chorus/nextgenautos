<?php

declare(strict_types=1);

/**
 * Administrative entry point (/admin/). Signed-out visitors are sent to the
 * login page by the guard and see nothing; signed-in administrators go to the
 * dashboard.
 */

require_once dirname(__DIR__) . '/app/helpers/auth.php';

requireAdmin();
admin_redirect('dashboard.php');
