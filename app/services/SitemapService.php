<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/repositories/VehicleRepository.php';
require_once __DIR__ . '/AccidentVehicleSettings.php';

/**
 * sitemap.xml (SEO 2026-10-27), built on every request from the route table and the database, so it always matches
 * the live inventory: a vehicle appears as soon as it is published and disappears when it is sold, hidden or archived.
 *
 * Included: the public pages meant for search results (INDEXABLE below, and only while their route is live) and every
 * public vehicle page. Accident Vehicles and accident vehicle pages only while the administrator has that page on.
 * Never included: sign-in, account, tracking, favourites, assistant, cookie and form-only routes, "coming soon"
 * pages, filtered or later list pages, development pages, the admin area. All addresses use AppConfig::SITE_URL.
 * lastmod is given only for vehicle pages (a real date from the database); there is no priority or changefreq.
 */
final class SitemapService
{
    /** Route names that are real pages meant for search results. Any other route stays out (see the test). */
    public const INDEXABLE = ['home', 'vehicles', 'accident-vehicles', 'parts', 'about', 'contact', 'privacy', 'cookies'];

    /** @param array{routes:list<array<string,mixed>>} $routes app/config/public_routes.php */
    public function __construct(private array $routes, private ?VehicleRepository $vehicles, private bool $accidentEnabled)
    {
    }

    public static function create(): self
    {
        return new self(require dirname(__DIR__) . '/config/public_routes.php', new VehicleRepository(Database::connection()), AccidentVehicleSettings::enabled());
    }

    /** @return list<array{loc:string,lastmod:?string}> */
    public function entries(): array
    {
        $out = [];
        foreach ($this->routes['routes'] as $route) {
            if (!in_array($route['name'], self::INDEXABLE, true) || ($route['status'] ?? '') !== 'live' || !empty($route['private'])) {
                continue;
            }
            if ($route['name'] === 'accident-vehicles' && !$this->accidentEnabled) {
                continue;
            }
            $out[] = ['loc' => AppConfig::SITE_URL . $route['path'], 'lastmod' => null];
        }
        foreach ($this->vehicles?->sitemapEntries($this->accidentEnabled) ?? [] as $v) {
            if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $v['slug']) === 1) {
                $out[] = ['loc' => AppConfig::SITE_URL . '/vehicle/' . $v['slug'] . '/',
                    'lastmod' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $v['lastmod']) === 1 ? $v['lastmod'] : null];
            }
        }

        return $out;
    }

    /** The XML document (sitemaps.org protocol 0.9). */
    public function xml(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($this->entries() as $e) {
            $xml .= '  <url><loc>' . htmlspecialchars($e['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc>'
                . ($e['lastmod'] !== null ? '<lastmod>' . $e['lastmod'] . '</lastmod>' : '') . "</url>\n";
        }

        return $xml . "</urlset>\n";
    }
}
