<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/repositories/SettingsRepository.php';

/**
 * Company contact details for the public site. The company phone number is NEVER written into the
 * code: it comes from the `settings` table (key `company_phone`, category `contact`). Until the owner
 * stores a valid number the site simply shows none (nothing is invented).
 *
 * The stored text is shown as the owner wrote it; the click-to-call number is built from it in
 * international form (+233...). Anything that does not look like a real phone number is ignored.
 */
final class CompanyContactService
{
    public const PHONE_KEY = 'company_phone';
    public const PHONE_2_KEY = 'company_phone_2';   // optional second number (Contact page and footer)

    public function __construct(private SettingsRepository $settings)
    {
    }

    public static function create(): self
    {
        return new self(new SettingsRepository(Database::connection()));
    }

    /** @return array{display:string,tel:string}|null */
    public function phone(): ?array
    {
        $stored = $this->settings->value(self::PHONE_KEY, 'contact');

        return $stored === null ? null : self::normalizePhone($stored);
    }

    /**
     * Every valid company phone number, main number first (the footer lists them all). One query; invalid or empty values are left out.
     *
     * @return list<array{display:string,tel:string}>
     */
    public function phones(): array
    {
        $stored = $this->settings->valuesIn(['contact']);
        $phones = [];
        foreach ([self::PHONE_KEY, self::PHONE_2_KEY] as $key) {
            $phone = isset($stored[$key]) ? self::normalizePhone($stored[$key]) : null;
            if ($phone !== null && !in_array($phone['tel'], array_column($phones, 'tel'), true)) {
                $phones[] = $phone;
            }
        }

        return $phones;
    }

    public const WHATSAPP_KEY = 'whatsapp_number';

    /**
     * The WhatsApp number for wa.me links (spec 14), as international digits without "+" (e.g. "233241234567"), from the
     * settings table (key `whatsapp_number`, category `contact`, edited in the admin by spec 15). Null when none is stored
     * or it is not a valid number: the WhatsApp buttons are then not shown.
     */
    public function whatsapp(): ?string
    {
        $stored = $this->settings->value(self::WHATSAPP_KEY, 'contact');
        $phone = $stored === null ? null : self::normalizePhone($stored);

        return $phone === null ? null : substr($phone['tel'], 1);
    }

    /**
     * The GHANA number customers message about a vehicle (owner request 2026-10-22: "Ask a question" opens WhatsApp with the
     * Ghanaian number), as international digits ("233..."). The WhatsApp number from Settings when it is a Ghana number,
     * otherwise the first company phone that is a Ghana number. Null when there is none: the button then opens the contact form.
     * One query; nothing is invented.
     */
    public function ghanaWhatsapp(): ?string
    {
        $stored = $this->settings->valuesIn(['contact']);
        foreach ([self::WHATSAPP_KEY, self::PHONE_KEY, self::PHONE_2_KEY] as $key) {
            $phone = isset($stored[$key]) ? self::normalizePhone($stored[$key]) : null;
            if ($phone !== null && str_starts_with($phone['tel'], '+233')) {
                return substr($phone['tel'], 1);
            }
        }

        return null;
    }

    /**
     * Everything the Contact page shows (spec 17), from ONE query, each value re-checked by ContactSettingsValidator: anything
     * missing or not valid is left out (null / not listed), never shown half-broken.
     *
     * @return array{phone:?array{display:string,tel:string},phone2:?array{display:string,tel:string},whatsapp:?string,email:?string,hours:?string,socials:list<array{key:string,label:string,url:string}>}
     */
    public function details(): array
    {
        require_once dirname(__DIR__) . '/validation/ContactSettingsValidator.php';
        $stored = $this->settings->valuesIn(['contact', 'social']);
        $valid = static fn (string $key): ?string => isset($stored[$key]) && ContactSettingsValidator::isValid($key, $stored[$key]) ? $stored[$key] : null;

        $phone = $valid(self::PHONE_KEY);
        $phone2 = $valid(self::PHONE_2_KEY);
        $whatsapp = $valid(self::WHATSAPP_KEY);
        $socials = [];
        foreach (ContactSettingsValidator::FIELDS as $key => [$category, $kind, $label]) {
            if ($category === 'social' && ($url = $valid($key)) !== null) {
                $socials[] = ['key' => substr($key, 7), 'label' => $label, 'url' => $url];
            }
        }

        return [
            'phone'    => $phone === null ? null : self::normalizePhone($phone),
            'phone2'   => $phone2 === null ? null : self::normalizePhone($phone2),
            'whatsapp' => $whatsapp === null ? null : substr((string) self::normalizePhone($whatsapp)['tel'], 1),
            'email'    => $valid('contact_email'),
            'hours'    => $valid('opening_hours'),
            'address'  => $valid('office_address'),
            'socials'  => $socials,
        ];
    }

    /** Google Maps search link for an address (opens Google Maps; no key needed). */
    public static function mapLink(string $address): string
    {
        return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode(preg_replace('/\s+/', ' ', trim($address)) ?? '');
    }

    /** Google Maps embed address for the same place (loaded in a frame only after the visitor asks for it). */
    public static function mapEmbed(string $address): string
    {
        return 'https://www.google.com/maps?q=' . rawurlencode(preg_replace('/\s+/', ' ', trim($address)) ?? '') . '&output=embed';
    }

    /**
     * A wa.me link that opens a chat with a pre-filled message. Only digits go into the path; the text is URL-encoded.
     */
    public static function whatsappLink(string $digits, string $message): ?string
    {
        if (preg_match('/^[1-9][0-9]{7,14}$/', $digits) !== 1) {
            return null;
        }

        return 'https://wa.me/' . $digits . '?text=' . rawurlencode($message);
    }

    /**
     * "+233 24 123 4567", "024 123 4567", "0241234567", "00233241234567" and "(+233) 24-123-4567" all give tel "+233241234567".
     * A local Ghana number (one leading 0, ten digits) becomes +233. Returns null for anything else.
     *
     * @return array{display:string,tel:string}|null
     */
    public static function normalizePhone(string $stored): ?array
    {
        $display = trim($stored);
        if (mb_strlen($display) > 30 || preg_match('/^[0-9+()\-.\s]+$/', $display) !== 1) {
            return null;
        }
        $digits = preg_replace('/[^0-9+]/', '', $display) ?? '';
        if (str_starts_with($digits, '00')) {
            $digits = '+' . substr($digits, 2);
        } elseif (preg_match('/^0[0-9]{9}$/', $digits) === 1) {
            $digits = '+233' . substr($digits, 1);
        } elseif (preg_match('/^233[0-9]{9}$/', $digits) === 1) {
            $digits = '+' . $digits;
        }
        if (preg_match('/^\+[1-9][0-9]{7,14}$/', $digits) !== 1) {
            return null;
        }

        return ['display' => $display, 'tel' => $digits];
    }
}
