<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/services/ExternalLinksService.php';

/**
 * Input rules for shipments (spec 20_spec_shipments_tracking.md). Pure functions: no database, no HTML.
 * Container numbers are never generated: they are typed by an administrator from the shipping company's paperwork,
 * so the format is enforced (4 letters + 7 digits) but a wrong check digit is only a warning (D9).
 */
final class ShipmentValidator
{
    /** The six approved statuses (business rules section 8), in lifecycle order. */
    public const STATUSES = [
        'NOT_YET_SHIPPED'                => 'Not yet shipped',
        'SHIPMENT_BOOKED'                => 'Shipment booked',
        'TRACKING_INFORMATION_AVAILABLE' => 'Tracking information available',
        'IN_TRANSIT'                     => 'In transit',
        'ARRIVED'                        => 'Arrived',
        'DELIVERED'                      => 'Delivered',
    ];

    /** From this status on, a container number is required (it is what makes tracking possible). */
    public const NEEDS_CONTAINER = ['TRACKING_INFORMATION_AVAILABLE', 'IN_TRANSIT', 'ARRIVED', 'DELIVERED'];

    /** Statuses whose update can be emailed to the customer (D2; "Not yet shipped" has nothing to tell). */
    public const EMAIL_STATUSES = ['SHIPMENT_BOOKED', 'TRACKING_INFORMATION_AVAILABLE', 'IN_TRANSIT', 'ARRIVED', 'DELIVERED'];

    /** Suggestions only (D4 is open): the field accepts any shipping line name. */
    public const LINE_SUGGESTIONS = ['Hapag-Lloyd', 'Maersk', 'MSC', 'CMA CGM', 'Grimaldi', 'ONE', 'Evergreen', 'COSCO'];
    /** The shipping line the business ships with (owner, 2026-10-22): the default for new shipments. */
    public const DEFAULT_LINE = 'Hapag-Lloyd';

    public const NAME_MAX = 150;
    public const LINE_MAX = 150;
    public const NOTES_MAX = 2000;
    public const URL_MAX = 500;
    public const PAGE_SIZE = 20;
    public const SEARCH_MAX = 100;

    /**
     * @param array<string,mixed> $post
     * @return array{values:array<string,mixed>,errors:array<string,string>,warnings:list<string>}
     */
    public static function validate(array $post): array
    {
        $errors = [];
        $warnings = [];
        $text = static fn (string $k): string => is_string($post[$k] ?? null) ? trim(preg_replace('/\s+/u', ' ', $post[$k]) ?? '') : '';

        $vehicleId = self::id($post['vehicle_id'] ?? null);
        if ($vehicleId === null) {
            $errors['vehicle_id'] = 'Choose the vehicle.';
        }

        $status = $text('status');
        if (!isset(self::STATUSES[$status])) {
            $errors['status'] = 'Choose a status from the list.';
            $status = 'NOT_YET_SHIPPED';
        }

        $name = $text('customer_name');
        if ($name === '') {
            $errors['customer_name'] = "Enter the customer's name.";
        } elseif (mb_strlen($name) > self::NAME_MAX || preg_match('/[<>\p{Cc}]/u', $name) === 1) {
            $errors['customer_name'] = 'Use up to ' . self::NAME_MAX . ' characters, without < or >.';
        }

        $email = strtolower($text('customer_email'));
        if ($email !== '' && (strlen($email) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
            $errors['customer_email'] = 'Enter a valid email address, or leave it empty.';
        }

        $container = strtoupper(str_replace([' ', '-'], '', $text('container_number')));
        if ($container !== '') {
            if (preg_match('/^[A-Z]{4}[0-9]{7}$/D', $container) !== 1) {
                $errors['container_number'] = 'Enter the container number as on the shipping documents: 4 letters and 7 digits, for example HLXU1234567.';
            } elseif (!self::checkDigitOk($container)) {
                $warnings[] = 'The check digit of container number ' . $container . ' does not match. It was saved as typed; please compare it with the shipping documents.';
            }
        } elseif (in_array($status, self::NEEDS_CONTAINER, true)) {
            $errors['container_number'] = 'Enter the container number: it is required from "Tracking information available" on.';
        }

        $line = $text('shipping_company');
        if (mb_strlen($line) > self::LINE_MAX || preg_match('/[<>\p{Cc}]/u', $line) === 1) {
            $errors['shipping_company'] = 'Use up to ' . self::LINE_MAX . ' characters, without < or >.';
        }

        $url = $text('tracking_url');
        if ($url !== '' && (strlen($url) > self::URL_MAX || !ExternalLinksService::isApprovedTrackingUrl($url))) {
            $errors['tracking_url'] = 'Only a Hapag-Lloyd tracking address (https://www.hapag-lloyd.com/...) can be used. Leave it empty to use Hapag-Lloyd\'s container tracking.';
        }

        $dates = [];
        foreach (['shipped_date' => 'Shipped', 'estimated_arrival' => 'Estimated arrival', 'arrival_date' => 'Arrived', 'delivered_date' => 'Delivered'] as $key => $label) {
            $raw = $text($key);
            $dates[$key] = null;
            if ($raw === '') {
                continue;
            }
            $d = DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
            if ($d === false || $d->format('Y-m-d') !== $raw || (int) $d->format('Y') < 2000 || (int) $d->format('Y') > 2100) {
                $errors[$key] = $label . ': enter a valid date.';
            } else {
                $dates[$key] = $raw;
            }
        }
        foreach ([['estimated_arrival', 'shipped_date', 'The estimated arrival cannot be before the shipped date.'],
                     ['arrival_date', 'shipped_date', 'The arrival date cannot be before the shipped date.'],
                     ['delivered_date', 'arrival_date', 'The delivery date cannot be before the arrival date.']] as [$later, $earlier, $message]) {
            if ($dates[$later] !== null && $dates[$earlier] !== null && $dates[$later] < $dates[$earlier] && !isset($errors[$later])) {
                $errors[$later] = $message;
            }
        }

        $notes = str_replace(["\r\n", "\r"], "\n", is_string($post['internal_notes'] ?? null) ? trim($post['internal_notes']) : '');
        if (mb_strlen($notes) > self::NOTES_MAX || preg_match('/[^\P{Cc}\n\t]/u', $notes) === 1) {
            $errors['internal_notes'] = 'Use plain text, up to ' . self::NOTES_MAX . ' characters.';
        }

        return [
            'values' => [
                'vehicle_id' => $vehicleId, 'status' => $status, 'customer_name' => $name, 'customer_email' => $email === '' ? null : $email,
                'container_number' => $container === '' ? null : $container, 'shipping_company' => $line === '' ? null : $line,
                'tracking_url' => $url === '' ? null : $url, 'internal_notes' => $notes === '' ? null : $notes,
            ] + $dates,
            'errors'   => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * ISO 6346 check digit: letters take the values 10..38 skipping multiples of 11, each of the first ten characters is
     * weighted by 2^position, the sum modulo 11 (10 counts as 0) must equal the eleventh character.
     */
    public static function checkDigitOk(string $container): bool
    {
        if (preg_match('/^[A-Z]{4}[0-9]{7}$/D', $container) !== 1) {
            return false;
        }
        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $ch = $container[$i];
            $sum += (ctype_digit($ch) ? (int) $ch : self::letterValue($ch)) * (2 ** $i);
        }

        return ($sum % 11) % 10 === (int) $container[10];
    }

    /** ISO 6346 letter values (multiples of 11 are skipped). */
    private static function letterValue(string $letter): int
    {
        static $map = null;
        if ($map === null) {
            $map = [];
            $v = 10;
            foreach (range('A', 'Z') as $l) {
                if ($v % 11 === 0) {
                    $v++;
                }
                $map[$l] = $v++;
            }
        }

        return $map[$letter];
    }

    /** Lifecycle position (0..5) of a status; -1 for anything else. */
    public static function position(string $status): int
    {
        $i = array_search($status, array_keys(self::STATUSES), true);

        return $i === false ? -1 : $i;
    }

    /** @return array{q:string,status:string,page:int} */
    public static function filters(array $query): array
    {
        $q = is_string($query['q'] ?? null) ? trim($query['q']) : '';
        if (mb_strlen($q) > self::SEARCH_MAX || preg_match('/\p{Cc}/u', $q) === 1) {
            $q = '';
        }
        $status = is_string($query['status'] ?? null) && isset(self::STATUSES[$query['status']]) ? $query['status'] : 'all';
        $page = is_string($query['page'] ?? null) && preg_match('/^[1-9][0-9]{0,5}$/D', $query['page']) === 1 ? (int) $query['page'] : 1;

        return ['q' => $q, 'status' => $status, 'page' => $page];
    }

    public static function id(mixed $value): ?int
    {
        if (is_int($value)) {
            $value = (string) $value;
        }
        if (!is_string($value) || preg_match('/^[1-9][0-9]{0,9}$/D', $value) !== 1 || (int) $value > 4294967295) {
            return null;
        }

        return (int) $value;
    }
}
