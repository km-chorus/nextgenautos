<?php

declare(strict_types=1);

/**
 * Last line of defence for web requests (owner request 2026-10-21: "prevent technical errors from showing on the page").
 *
 * Pages already catch their own errors and show a friendly message. This guard covers what cannot be caught that way:
 * fatal errors (for example "Maximum execution time exceeded"), parse errors and uncaught exceptions. The visitor gets
 * a plain "Something went wrong, please try again" page with status 500; the technical detail goes only to the server
 * log (PHP logs fatal errors itself; uncaught exceptions are logged here). Installed by AppConfig::applyErrorSettings()
 * for web requests only: command-line scripts and tests keep their normal error output.
 */

function error_guard_install(): void
{
    static $installed = false;
    if ($installed) {
        return;
    }
    $installed = true;

    set_exception_handler(static function (Throwable $e): void {
        error_log('Uncaught ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        error_guard_page();
    });

    register_shutdown_function(static function (): void {
        $error = error_get_last();
        $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR];
        if ($error !== null && in_array($error['type'], $fatal, true)) {
            error_guard_page();   // PHP has already written the error itself to the log (log_errors is on)
        }
    });
}

/** Replaces whatever was being built with the plain error page. Never includes any detail about the error. */
function error_guard_page(): void
{
    while (ob_get_level() > 0) {
        @ob_end_clean();   // drop the half-built page
    }
    $admin = str_starts_with((string) ($_SERVER['REQUEST_URI'] ?? ''), '/admin/');
    $back = $admin ? '/admin/dashboard.php' : '/';
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'none'; style-src 'self'; font-src 'self'; img-src 'self'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'");
    }
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<meta name="robots" content="noindex"><title>Something went wrong</title><link rel="stylesheet" href="/assets/css/public.css"></head>'
        . '<body><main class="container section"><h1>Something went wrong</h1>'
        . '<p>Sorry, we could not complete that just now. Please try again in a moment.</p>'
        . '<p>If it keeps happening, please contact us.</p>'
        . '<p><a class="btn btn--primary" href="' . $back . '">' . ($admin ? 'Back to the dashboard' : 'Back to the home page') . '</a></p>'
        . '</main></body></html>';
}
