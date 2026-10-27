<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/helpers/html.php';

/**
 * The branded email layout (owner request 2026-10-21): every email the site sends uses this one card, so all messages
 * look alike. Pure rendering: no database, no sending (EmailService supplies the brand details and sends).
 *
 * Email-client rules followed: 600 px table layout, inline styles only, no web fonts, no CSS the major clients drop,
 * a bullet-proof button (a table cell, works in Outlook), a hidden preview line, light colour scheme declared so
 * dark-mode clients do not invert the logo, and a plain-text version built from the same content.
 * Every value is escaped; links are accepted only when they are http(s).
 *
 * Content ($c):
 *   subject (string)            used as the document title
 *   preheader (string)          the short preview line inbox lists show after the subject
 *   eyebrow (?string)           small red label above the title, e.g. "Account security"
 *   title (string)              the heading
 *   greeting (?string)          e.g. "Hello Ama,"
 *   paragraphs (list<string>)   body text, one paragraph each
 *   details (list<array{0:string,1:string}>)  optional label / value rows shown in a light panel
 *   button (?array{label:string,url:string})  the one main action
 *   note (?string)              small muted text under the button (expiry, safety notes)
 *   reason (?string)            footer line: why the person received this email
 * Brand ($b):
 *   name (string), logo (?string absolute https URL), site (?string absolute URL), phones (list<string>)
 */
final class EmailTemplate
{
    // Brand palette (the website's tokens: charcoal, one red accent, neutral greys)
    private const INK = '#2B2F35';
    private const ACCENT = '#D42A25';
    private const MUTED = '#6B7280';
    private const LINE = '#E5E7EB';
    private const TINT = '#F8F9FA';
    private const PAGE = '#F1F2F4';
    private const FONT = "'Segoe UI', Helvetica, Arial, sans-serif";

    /** @return array{html:string,text:string} */
    public static function render(array $c, array $b): array
    {
        return ['html' => self::html($c, $b), 'text' => self::text($c, $b)];
    }

    private static function html(array $c, array $b): string
    {
        $font = self::FONT;
        $button = self::button($c['button'] ?? null);
        $logo = self::safeUrl($b['logo'] ?? null, true);
        $site = self::safeUrl($b['site'] ?? null);

        $out = '<!DOCTYPE html><html lang="en" xmlns="http://www.w3.org/1999/xhtml"><head>'
            . '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<meta name="color-scheme" content="light"><meta name="supported-color-schemes" content="light">'
            . '<title>' . e((string) ($c['subject'] ?? $c['title'] ?? '')) . '</title>'
            . '<style>@media (max-width: 620px) { .card { width: 100% !important; } .pad { padding-left: 22px !important; padding-right: 22px !important; } .btn a { display: block !important; } }</style>'
            . '</head><body style="margin:0;padding:0;background:' . self::PAGE . ';-webkit-text-size-adjust:100%;">';

        // Preview line (hidden in the message, shown in inbox lists), padded so the body text does not follow it.
        if (($c['preheader'] ?? '') !== '') {
            $out .= '<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;mso-hide:all;">'
                . e((string) $c['preheader']) . str_repeat('&#8199;&#65279;&#847; ', 30) . '</div>';
        }

        $out .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:' . self::PAGE . ';">'
            . '<tr><td align="center" style="padding:28px 12px;">'
            . '<table role="presentation" class="card" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px;max-width:600px;background:#FFFFFF;border:1px solid ' . self::LINE . ';border-radius:8px;overflow:hidden;">'
            // Red accent bar
            . '<tr><td style="height:4px;line-height:4px;font-size:0;background:' . self::ACCENT . ';">&nbsp;</td></tr>'
            // Header: logo, or the brand name as text until the site has a public https address
            . '<tr><td class="pad" style="padding:22px 36px;border-bottom:1px solid ' . self::LINE . ';">'
            . ($logo !== null
                ? '<img src="' . e($logo) . '" width="180" alt="' . e((string) $b['name']) . '" style="display:block;width:180px;max-width:100%;height:auto;border:0;">'
                : '<span style="font-family:' . $font . ';font-size:20px;font-weight:800;letter-spacing:0.02em;color:' . self::INK . ';"><span style="color:' . self::ACCENT . ';">&#9612;</span> ' . e((string) $b['name']) . '</span>')
            . '</td></tr>'
            // Body
            . '<tr><td class="pad" style="padding:32px 36px 8px;font-family:' . $font . ';color:' . self::INK . ';">';
        if (($c['eyebrow'] ?? '') !== '') {
            $out .= '<p style="margin:0 0 8px;font-size:12px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:' . self::ACCENT . ';">' . e((string) $c['eyebrow']) . '</p>';
        }
        $out .= '<h1 style="margin:0 0 20px;font-size:24px;line-height:1.25;font-weight:700;color:' . self::INK . ';">' . e((string) $c['title']) . '</h1>';
        if (($c['greeting'] ?? '') !== '') {
            $out .= '<p style="margin:0 0 16px;font-size:15px;line-height:1.6;">' . e((string) $c['greeting']) . '</p>';
        }
        foreach ($c['paragraphs'] ?? [] as $p) {
            $out .= '<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:' . self::INK . ';">' . nl2br(e((string) $p)) . '</p>';
        }
        if (($c['details'] ?? []) !== []) {
            $out .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 24px;background:' . self::TINT . ';border:1px solid ' . self::LINE . ';border-radius:6px;">';
            $rows = array_values($c['details']);
            foreach ($rows as $i => [$label, $value]) {
                $border = $i > 0 ? 'border-top:1px solid ' . self::LINE . ';' : '';
                $out .= '<tr><td style="padding:12px 16px;' . $border . 'font-family:' . $font . ';font-size:14px;color:' . self::MUTED . ';">' . e((string) $label) . '</td>'
                    . '<td align="right" style="padding:12px 16px;' . $border . 'font-family:' . $font . ';font-size:14px;font-weight:700;color:' . self::INK . ';">' . e((string) $value) . '</td></tr>';
            }
            $out .= '</table>';
        }
        $out .= $button;
        if (($c['note'] ?? '') !== '') {
            $out .= '<p style="margin:0 0 16px;font-size:13px;line-height:1.6;color:' . self::MUTED . ';">' . nl2br(e((string) $c['note'])) . '</p>';
        }
        $out .= '<p style="margin:24px 0 28px;font-size:15px;line-height:1.6;">Kind regards,<br><strong>' . e((string) $b['name']) . '</strong></p>'
            . '</td></tr>';

        // Footer: brand, contact numbers, website, why this email was sent
        $contact = array_map(static fn (string $p): string => e($p), $b['phones'] ?? []);
        $out .= '<tr><td class="pad" style="padding:22px 36px;background:' . self::INK . ';font-family:' . $font . ';">'
            . '<p style="margin:0 0 6px;font-size:15px;font-weight:700;color:#FFFFFF;">' . e((string) $b['name']) . '</p>'
            . '<p style="margin:0;font-size:13px;line-height:1.6;color:#C9CDD3;">Vehicles from Canada to Ghana, and car parts for popular makes.</p>'
            . ($contact !== [] ? '<p style="margin:10px 0 0;font-size:13px;line-height:1.6;color:#FFFFFF;">' . implode(' &nbsp;&middot;&nbsp; ', $contact) . '</p>' : '')
            . ($site !== null ? '<p style="margin:6px 0 0;font-size:13px;"><a href="' . e($site) . '" style="color:#FFFFFF;text-decoration:underline;">' . e(preg_replace('#^https?://#', '', rtrim($site, '/')) ?? $site) . '</a></p>' : '')
            . '</td></tr>'
            . '</table>';
        if (($c['reason'] ?? '') !== '') {
            $out .= '<p style="max-width:560px;margin:16px auto 0;font-family:' . $font . ';font-size:12px;line-height:1.5;color:' . self::MUTED . ';text-align:center;">' . e((string) $c['reason']) . '</p>';
        }

        return $out . '</td></tr></table></body></html>';
    }

    /** A button that works in every client (a coloured table cell around the link), plus the link in text below it. */
    private static function button(?array $button): string
    {
        $url = self::safeUrl($button['url'] ?? null);
        if ($button === null || $url === null) {
            return '';
        }
        $font = self::FONT;

        return '<table role="presentation" class="btn" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 20px;"><tr>'
            . '<td align="center" bgcolor="' . self::ACCENT . '" style="border-radius:6px;background:' . self::ACCENT . ';">'
            . '<a href="' . e($url) . '" target="_blank" rel="noopener" style="display:inline-block;padding:14px 28px;font-family:' . $font . ';font-size:15px;font-weight:700;color:#FFFFFF;text-decoration:none;border-radius:6px;">'
            . e((string) $button['label']) . '</a></td></tr></table>'
            . '<p style="margin:0 0 16px;font-size:12px;line-height:1.5;color:' . self::MUTED . ';">If the button does not work, copy this link into your browser:<br>'
            . '<a href="' . e($url) . '" style="color:' . self::INK . ';word-break:break-all;">' . e($url) . '</a></p>';
    }

    private static function text(array $c, array $b): string
    {
        $lines = [(string) $b['name'], str_repeat('=', mb_strlen((string) $b['name'])), ''];
        if (($c['eyebrow'] ?? '') !== '') {
            $lines[] = strtoupper((string) $c['eyebrow']);
        }
        $lines[] = (string) $c['title'];
        $lines[] = '';
        if (($c['greeting'] ?? '') !== '') {
            $lines[] = (string) $c['greeting'];
            $lines[] = '';
        }
        foreach ($c['paragraphs'] ?? [] as $p) {
            $lines[] = (string) $p;
            $lines[] = '';
        }
        foreach ($c['details'] ?? [] as [$label, $value]) {
            $lines[] = $label . ': ' . $value;
        }
        if (($c['details'] ?? []) !== []) {
            $lines[] = '';
        }
        $url = self::safeUrl($c['button']['url'] ?? null);
        if ($url !== null) {
            $lines[] = $c['button']['label'] . ':';
            $lines[] = $url;
            $lines[] = '';
        }
        if (($c['note'] ?? '') !== '') {
            $lines[] = (string) $c['note'];
            $lines[] = '';
        }
        $lines[] = 'Kind regards,';
        $lines[] = (string) $b['name'];
        $lines[] = '';
        $lines[] = '--';
        foreach ($b['phones'] ?? [] as $phone) {
            $lines[] = 'Phone: ' . $phone;
        }
        $site = self::safeUrl($b['site'] ?? null);
        if ($site !== null) {
            $lines[] = $site;
        }
        if (($c['reason'] ?? '') !== '') {
            $lines[] = '';
            $lines[] = (string) $c['reason'];
        }

        return implode("\n", $lines) . "\n";
    }

    /** The URL when it is an absolute http(s) address (https only when $httpsOnly), else null. */
    private static function safeUrl(mixed $url, bool $httpsOnly = false): ?string
    {
        if (!is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false || preg_match('/[\s"<>]/', $url) === 1) {
            return null;
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return $scheme === 'https' || (!$httpsOnly && $scheme === 'http') ? $url : null;
    }
}
