<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/services/SessionService.php';

/**
 * One-time messages that survive a single redirect (Post/Redirect/Get), kept in
 * the server-side session. Messages are fixed application text, never browser
 * input, and are shown once then removed.
 */

/** @param string $type success | error | warning | info */
function flash_set(string $type, string $text): void
{
    (new SessionService())->start();
    $_SESSION['flash'] = ['type' => $type, 'text' => $text];
}

/** @return array{type:string,text:string}|null */
function flash_take(): ?array
{
    (new SessionService())->start();
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    if (!is_array($flash) || !is_string($flash['type'] ?? null) || !is_string($flash['text'] ?? null)) {
        return null;
    }

    return ['type' => $flash['type'], 'text' => $flash['text']];
}

/**
 * A one-time list of per-item results (for example one line per uploaded file), kept in the
 * server-side session across one redirect. Names are display-safe text prepared by the caller;
 * they are escaped again when rendered.
 *
 * @param list<array{name:string,ok:bool,message:?string}> $items
 */
function flash_set_report(array $items): void
{
    (new SessionService())->start();
    $_SESSION['flash_report'] = array_slice(array_values($items), 0, 20);
}

/** @return list<array{name:string,ok:bool,message:?string}> */
function flash_take_report(): array
{
    (new SessionService())->start();
    $report = $_SESSION['flash_report'] ?? [];
    unset($_SESSION['flash_report']);

    return is_array($report) ? array_values(array_filter($report, static fn ($r): bool => is_array($r) && is_string($r['name'] ?? null) && is_bool($r['ok'] ?? null))) : [];
}
