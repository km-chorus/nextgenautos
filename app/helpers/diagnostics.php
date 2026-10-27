<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';

/**
 * A one-line description of an unexpected error for the DEVELOPER, shown on an error page only
 * when APP_ENV is "development" AND APP_DEBUG=1 (off by default, so visitors and the owner never see technical text). In production it returns null and the page stays generic (the
 * full text always goes to the server log). File-system paths (Windows and Unix) are replaced
 * and the text is cut short.
 */
function development_error_detail(Throwable $e): ?string
{
    if (!AppConfig::debugDetails()) {
        return null;
    }
    $text = get_class($e) . ': ' . $e->getMessage();
    $text = preg_replace('#(?:[A-Za-z]:)?[' . preg_quote(chr(92), '#') . '/][^\s\'"]+#', '[path]', $text) ?? $text;

    return mb_substr($text, 0, 300, 'UTF-8');
}
