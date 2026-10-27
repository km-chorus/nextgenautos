<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/repositories/SettingsRepository.php';

/**
 * The approved external service links shown on the public site (spec 11B). Fixed destinations: no visitor input
 * ever reaches these URLs.
 *
 * The duty calculator address is the `duty_calculator_url` setting (category external_links, seeded with the approved
 * address). It is accepted only if it is an https URL on the approved calculator host; otherwise, or if the database
 * cannot be read, the approved address below is used. The website does not calculate duty and does not embed, proxy or
 * scrape the calculator: it only links to it.
 */
final class ExternalLinksService
{
    public const DUTY_CALCULATOR_DEFAULT = 'https://autodutychecker.com/calculator/search-mode/';
    private const DUTY_CALCULATOR_HOST = 'autodutychecker.com';

    /** The approved tracking provider page (the `tracking_provider_url` setting is seeded with this address). */
    public const TRACKING_PROVIDER_DEFAULT = 'https://www.hapag-lloyd.com/en/online-business/track/track-by-container-solution.html';
    private const TRACKING_PROVIDER_HOSTS = ['www.hapag-lloyd.com', 'hapag-lloyd.com'];
    /** The shipping line the business ships with (owner, 2026-10-22). Only its tracking addresses are accepted above. */
    public const TRACKING_PROVIDER_NAME = 'Hapag-Lloyd';
    public function __construct(private ?SettingsRepository $settings = null)
    {
    }

    public static function create(): self
    {
        return new self(new SettingsRepository(Database::connection()));
    }

    public function dutyCalculatorUrl(): string
    {
        $stored = null;
        try {
            $stored = $this->settings?->value('duty_calculator_url', 'external_links');
        } catch (Throwable $e) {
            error_log('Duty calculator setting unavailable: ' . get_class($e));
        }

        return $stored !== null && self::isApprovedDutyUrl($stored) ? $stored : self::DUTY_CALCULATOR_DEFAULT;
    }

    /** The official tracking provider's page where a customer enters the container number (a link only: nothing is embedded, proxied or scraped). */
    public function trackingProviderUrl(): string
    {
        $stored = null;
        try {
            $stored = $this->settings?->value('tracking_provider_url', 'external_links');
        } catch (Throwable $e) {
            error_log('Tracking provider setting unavailable: ' . get_class($e));
        }

        return $stored !== null && self::isApprovedTrackingUrl($stored) ? $stored : self::TRACKING_PROVIDER_DEFAULT;
    }

    public static function isApprovedTrackingUrl(string $url): bool
    {
        if (strlen($url) > 300 || preg_match('/[\x00-\x20"<>\\\\]/', $url) === 1) {
            return false;
        }
        $parts = parse_url($url);

        return is_array($parts)
            && ($parts['scheme'] ?? '') === 'https'
            && in_array(strtolower((string) ($parts['host'] ?? '')), self::TRACKING_PROVIDER_HOSTS, true)
            && !isset($parts['user']) && !isset($parts['pass']) && !isset($parts['port']);
    }

    public static function isApprovedDutyUrl(string $url): bool
    {
        if (strlen($url) > 300 || preg_match('/[\x00-\x20"<>\\\\]/', $url) === 1) {
            return false;
        }
        $parts = parse_url($url);

        return is_array($parts)
            && ($parts['scheme'] ?? '') === 'https'
            && strtolower((string) ($parts['host'] ?? '')) === self::DUTY_CALCULATOR_HOST
            && !isset($parts['user']) && !isset($parts['pass']) && !isset($parts['port']);
    }
}
