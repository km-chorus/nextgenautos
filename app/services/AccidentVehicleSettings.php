<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/repositories/SettingsRepository.php';
require_once dirname(__DIR__) . '/repositories/AuditLogRepository.php';
require_once __DIR__ . '/Transaction.php';

/**
 * The switch for the Accident Vehicles page (spec 23). Owner decision 2026-10-22: the page is built but stays OFF until
 * the client has confirmed with the Ghana Revenue Authority or a customs broker that the vehicles may be imported
 * (salvaged-vehicle restrictions, Act 1014).
 *
 * Stored in `settings` (key accident_vehicles_enabled, category features, value "1"). No row, any other value or a
 * database failure = OFF: the safe side is to show nothing.
 *
 * While OFF: /accident-vehicles/ is a 404, accident vehicles' own pages are 404, the menu has no link and favourites
 * of accident vehicles are hidden. Normal listings never include accident vehicles, ON or OFF.
 */
final class AccidentVehicleSettings
{
    public const KEY = 'accident_vehicles_enabled';
    public const CATEGORY = 'features';

    private static ?bool $cached = null;

    public function __construct(private PDO $pdo, private SettingsRepository $settings, private AuditLogRepository $audit)
    {
    }

    public static function create(): self
    {
        $pdo = Database::connection();

        return new self($pdo, new SettingsRepository($pdo), new AuditLogRepository($pdo));
    }

    /** Whether the public accident page is switched on (read once per request). */
    public static function enabled(): bool
    {
        if (self::$cached === null) {
            try {
                self::$cached = self::create()->isOn();
            } catch (Throwable $e) {
                error_log('Accident vehicles switch could not be read: ' . get_class($e));
                self::$cached = false;
            }
        }

        return self::$cached;
    }

    /** For tests: forget the value read in this request. */
    public static function forget(): void
    {
        self::$cached = null;
    }

    public function isOn(): bool
    {
        return $this->settings->value(self::KEY, self::CATEGORY) === '1';
    }

    /** Switches the page on or off; audited. Returns 'ok' or 'unchanged'. */
    public function set(bool $on, int $adminId, string $ip, string $userAgent): string
    {
        $result = Transaction::run($this->pdo, function () use ($on, $adminId, $ip, $userAgent): string {
            if ($this->isOn() === $on) {
                return 'unchanged';
            }
            if ($on) {
                $this->settings->put(self::KEY, self::CATEGORY, '1');
            } else {
                $this->settings->remove(self::KEY, self::CATEGORY);
            }
            $this->audit->record('SETTINGS_UPDATED', $adminId, null, null,
                'Accident vehicles page switched ' . ($on ? 'ON (visible on the website)' : 'OFF (hidden from the website)'), $ip, $userAgent);

            return 'ok';
        });
        self::$cached = null;

        return $result;
    }
}
