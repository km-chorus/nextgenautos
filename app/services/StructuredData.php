<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_once __DIR__ . '/CompanyContactService.php';

/**
 * schema.org structured data for the public pages (SEO, 2026-10-27). Plain arrays; public_json_ld() prints them.
 *
 * Only real, visible facts: the business name, the official address of the site, the logo file, and the contact
 * details the administrator entered in Admin > Settings (the same ones the footer and Contact page show). Nothing is
 * filled in when it is missing: no invented address parts, country, opening hours, ratings or reviews.
 *   homepage      Organization + WebSite
 *   contact page  AutoDealer (the business with its office address, as shown on that page)
 *   vehicle page  Car with an Offer (its own price; availability only when it is plainly "available")
 */
final class StructuredData
{
    public const LOGO = '/assets/images/logo.webp';

    /** @param array<string,mixed>|null $contact CompanyContactService::details() */
    public static function organization(?array $contact): array
    {
        $org = ['@type' => 'Organization', '@id' => AppConfig::SITE_URL . '/#organization', 'name' => PUBLIC_SITE_NAME,
            'url' => AppConfig::SITE_URL . '/', 'logo' => AppConfig::SITE_URL . self::LOGO];

        return $org + self::contactFields($contact, false);
    }

    public static function website(): array
    {
        return ['@type' => 'WebSite', '@id' => AppConfig::SITE_URL . '/#website', 'name' => PUBLIC_SITE_NAME, 'url' => AppConfig::SITE_URL . '/',
            'publisher' => ['@id' => AppConfig::SITE_URL . '/#organization']];
    }

    /** @param array<string,mixed>|null $contact */
    public static function autoDealer(?array $contact): array
    {
        $dealer = ['@type' => 'AutoDealer', '@id' => AppConfig::SITE_URL . '/#organization', 'name' => PUBLIC_SITE_NAME,
            'url' => AppConfig::SITE_URL . '/', 'logo' => AppConfig::SITE_URL . self::LOGO, 'image' => AppConfig::SITE_URL . self::LOGO];

        return $dealer + self::contactFields($contact, true);
    }

    /**
     * @param array<string,mixed> $vehicle PublicVehicleService::detail()['vehicle']
     * @param array<string,mixed> $raw     PublicVehicleService::detail()['schema']
     */
    public static function car(array $vehicle, array $raw): array
    {
        $url = AppConfig::SITE_URL . $vehicle['url'];
        $car = ['@type' => 'Car', 'name' => (string) $vehicle['title'], 'url' => $url, 'brand' => ['@type' => 'Brand', 'name' => (string) $vehicle['brand']],
            'model' => $raw['model'], 'vehicleModelDate' => (string) $vehicle['year']];
        $images = [];
        foreach ($vehicle['photos'] as $photo) {
            $path = (string) ($photo['large']['url'] ?? '');
            if (preg_match('#^/uploads/vehicles/[a-f0-9]{32}\.webp$#', $path) === 1) {
                $images[] = AppConfig::SITE_URL . $path;
            }
        }
        if ($images !== []) {
            $car['image'] = array_slice($images, 0, 10);
        }
        if ($raw['mileage'] !== null && in_array($raw['mileage_unit'], ['km', 'mi'], true)) {
            $car['mileageFromOdometer'] = ['@type' => 'QuantitativeValue', 'value' => $raw['mileage'], 'unitCode' => $raw['mileage_unit'] === 'km' ? 'KMT' : 'SMI'];
        }
        if ($raw['fuel'] !== null && $raw['fuel'] !== '') {
            $car['fuelType'] = $raw['fuel'];
        }
        if ($raw['transmission'] !== null && $raw['transmission'] !== '') {
            $car['vehicleTransmission'] = $raw['transmission'];
        }
        if ($vehicle['description'] !== null && $vehicle['description'] !== '') {
            $car['description'] = mb_substr((string) $vehicle['description'], 0, 500);
        }
        $offer = ['@type' => 'Offer', 'url' => $url, 'price' => number_format((float) $raw['price'], 2, '.', ''), 'priceCurrency' => $raw['currency'],
            'seller' => ['@id' => AppConfig::SITE_URL . '/#organization']];
        if (!$vehicle['reserved']) {
            $offer['availability'] = 'https://schema.org/InStock';   // "Available" on the page; a reserved vehicle states no availability
        }
        $car['offers'] = $offer;

        return $car;
    }

    /**
     * Telephone, email, contact points, social profiles and (for the dealer) the office address, as entered in Settings.
     *
     * @param array<string,mixed>|null $contact
     * @return array<string,mixed>
     */
    private static function contactFields(?array $contact, bool $withAddress): array
    {
        if ($contact === null) {
            return [];
        }
        $out = [];
        $phones = [];
        foreach (['phone', 'phone2'] as $key) {
            // details() gives each phone as {display, tel} (tel = "+233..."), already validated
            $tel = $contact[$key]['tel'] ?? null;
            if (is_string($tel) && preg_match('/^\+[1-9][0-9]{7,14}$/', $tel) === 1) {
                $phones[] = $tel;
            }
        }
        if ($phones !== []) {
            $out['telephone'] = $phones[0];
            $out['contactPoint'] = array_map(static fn (string $tel): array => ['@type' => 'ContactPoint', 'telephone' => $tel, 'contactType' => 'customer service'], $phones);
        }
        if (is_string($contact['email'] ?? null) && $contact['email'] !== '') {
            $out['email'] = $contact['email'];
        }
        if ($withAddress && is_string($contact['address'] ?? null) && trim($contact['address']) !== '') {
            $out['address'] = ['@type' => 'PostalAddress', 'streetAddress' => trim(preg_replace('/\s+/', ' ', $contact['address']) ?? '')];
        }
        $same = array_values(array_filter(array_map(static fn (array $s): string => (string) $s['url'], $contact['socials'] ?? []),
            static fn (string $u): bool => str_starts_with($u, 'https://')));
        if ($same !== []) {
            $out['sameAs'] = $same;
        }

        return $out;
    }
}
