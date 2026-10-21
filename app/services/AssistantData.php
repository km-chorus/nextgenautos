<?php

declare(strict_types=1);

require_once __DIR__ . '/PublicVehicleService.php';
require_once __DIR__ . '/PublicPartService.php';
require_once __DIR__ . '/PublicBrandService.php';
require_once __DIR__ . '/CompanyContactService.php';
require_once __DIR__ . '/ExternalLinksService.php';

/**
 * Real site data for the assistant (spec 19). Every value comes from the same services the public pages use, so the
 * assistant can never say more than the website shows. Each source is read at most once per request; a source that
 * cannot be read gives null (the assistant then says so) instead of an error. Tests replace this class with fixed data.
 */
class AssistantData
{
    /** @var array<string,mixed> */
    private array $cache = [];

    /** @return list<array<string,mixed>>|null public vehicle cards (title, brand, name, year, price, status, duty, url), newest first */
    public function vehicles(): ?array
    {
        return $this->once('vehicles', static fn (): array => PublicVehicleService::create()->search([], 48)['cards']);
    }

    /** @return list<array<string,mixed>>|null published part cards (PublicPartService), newest first */
    public function parts(): ?array
    {
        return $this->once('parts', static fn (): array => PublicPartService::create()->homepage()['cards']);
    }

    /** @return list<string>|null names of the active brands */
    public function brands(): ?array
    {
        return $this->once('brands', static fn (): array => array_map(static fn (array $b): string => (string) $b['name'], PublicBrandService::create()->forBrowse()));
    }

    /** @return array{phones:list<array{display:string,tel:string}>,whatsapp:?string,email:?string,hours:?string}|null */
    public function contact(): ?array
    {
        return $this->once('contact', static function (): array {
            $service = CompanyContactService::create();
            $details = $service->details();
            $digits = $details['whatsapp'];

            return [
                'phones'   => $service->phones(),
                'whatsapp' => $digits === null ? null : CompanyContactService::whatsappLink($digits, 'Hello, I have a question for Amponsah Autos.'),
                'email'    => $details['email'],
                'hours'    => $details['hours'],
            ];
        });
    }

    /** @return list<array{title:string,text:string}> the How It Works steps (fixed configuration) */
    public function steps(): array
    {
        $config = require dirname(__DIR__) . '/config/how_it_works.php';

        return $config['steps'];
    }

    public function dutyUrl(): string
    {
        return $this->once('duty', static fn (): string => ExternalLinksService::create()->dutyCalculatorUrl()) ?? ExternalLinksService::DUTY_CALCULATOR_DEFAULT;
    }

    public function trackingUrl(): string
    {
        return $this->once('tracking', static fn (): string => ExternalLinksService::create()->trackingProviderUrl()) ?? ExternalLinksService::TRACKING_PROVIDER_DEFAULT;
    }

    private function once(string $key, callable $load): mixed
    {
        if (!array_key_exists($key, $this->cache)) {
            try {
                $this->cache[$key] = $load();
            } catch (Throwable $e) {
                error_log('Assistant: ' . $key . ' unavailable: ' . get_class($e));   // never the visitor's question
                $this->cache[$key] = null;
            }
        }

        return $this->cache[$key];
    }
}
