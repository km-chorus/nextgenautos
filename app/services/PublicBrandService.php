<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/repositories/BrandRepository.php';

/**
 * Brands for the PUBLIC website (spec 10C, Browse by Brand). Reuses the Brand Management repository.
 *
 * Each brand gets:
 *   - its name and slug from the brands table (active brands only, the administrator's order);
 *   - a link to the Vehicles page filtered by that brand (/vehicles/?brand=<slug>), built only from a
 *     slug that matches the slug format, so a bad row is skipped rather than linked;
 *   - a LOCAL logo file: the brands.logo_path column when it holds a valid path (assets/images/brands/<name>.svg,
 *     .webp or .png), otherwise the convention assets/images/brands/<slug>.svg. Replacing a logo therefore means
 *     replacing one file (or setting logo_path), never touching PHP. No remote image is ever used.
 *     A brand whose logo file is missing is still listed (name only, never a made-up logo) and the problem is logged.
 */
final class PublicBrandService
{
    private const LOGO_DIR = 'assets/images/brands';

    private string $root;

    public function __construct(private BrandRepository $brands, ?string $projectRoot = null)
    {
        $this->root = $projectRoot ?? dirname(__DIR__, 2);
    }

    public static function create(): self
    {
        return new self(new BrandRepository(Database::connection()));
    }

    /**
     * @return list<array{name:string,slug:string,url:string,logo:?string}>
     */
    public function forBrowse(): array
    {
        $items = [];
        foreach ($this->brands->publicActive() as $brand) {
            $slug = (string) $brand['slug'];
            if (strlen($slug) > 100 || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
                continue;
            }
            $logo = $this->logo($slug, $brand['logo_path'] ?? null);
            if ($logo === null) {
                error_log('Brand logo missing for "' . $slug . '": expected ' . self::LOGO_DIR . '/' . $slug . '.svg');
            }
            $items[] = ['name' => (string) $brand['name'], 'slug' => $slug, 'url' => '/vehicles/?brand=' . $slug, 'logo' => $logo];
        }

        return $items;
    }

    /** Root-relative URL (with a cache-busting version) of the brand's logo file, or null when there is none. */
    private function logo(string $slug, mixed $logoPath): ?string
    {
        $candidates = [];
        if (is_string($logoPath) && preg_match('#^' . preg_quote(self::LOGO_DIR, '#') . '/[a-z0-9]+(?:-[a-z0-9]+)*\.(?:svg|webp|png)$#', $logoPath) === 1) {
            $candidates[] = $logoPath;
        }
        $candidates[] = self::LOGO_DIR . '/' . $slug . '.svg';
        foreach ($candidates as $relative) {
            $file = $this->root . '/' . $relative;
            if (is_file($file)) {
                return '/' . $relative . '?v=' . (string) filemtime($file);
            }
        }

        return null;
    }
}
