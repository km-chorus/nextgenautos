<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/validation/ShipmentValidator.php';
require_once __DIR__ . '/ExternalLinksService.php';

/**
 * The customer email for one shipment status (spec 20, section 7), as EmailTemplate content. Pure: no database, no
 * sending. Only values an administrator entered appear (a row with no value is left out); nothing about location or
 * arrival is invented, and every email says that live updates come from the shipping line.
 */
final class ShipmentEmail
{
    /**
     * @param array<string,mixed> $s a shipment row from ShipmentRepository (with vehicle and account columns)
     * @param string $trackingUrl the official tracking address for this shipment (its own, or the site-wide one)
     * @return array<string,mixed>|null EmailTemplate content, or null when this status has no customer email
     */
    public static function content(array $s, string $status, string $trackingUrl): ?array
    {
        if (!in_array($status, ShipmentValidator::EMAIL_STATUSES, true)) {
            return null;
        }
        $brand = AppConfig::mailFromName();
        $vehicle = self::vehicleLabel($s);
        $site = AppConfig::linkUrl();
        $hasAccount = $s['customer_id'] !== null;
        $row = static fn (string $label, mixed $value): ?array => $value === null || $value === '' ? null : [$label, (string) $value];
        $date = static fn (?string $d): ?string => $d === null || $d === '' ? null : (DateTimeImmutable::createFromFormat('!Y-m-d', $d) ?: null)?->format('j M Y');
        $line = ExternalLinksService::TRACKING_PROVIDER_NAME;
        $s['shipping_company'] = $s['shipping_company'] ?? $line;      // default: the line the business ships with
        $track = ['label' => 'Track on ' . $line, 'url' => $trackingUrl];   // always the official Hapag-Lloyd tracking (ShipmentService::trackingUrl)

        [$eyebrow, $title, $text, $details, $button] = match ($status) {
            'SHIPMENT_BOOKED' => ['Shipment update', 'Your shipment is booked',
                'The shipment of your ' . $vehicle . ' to Ghana has been booked with the shipping line. We will send you the container number as soon as the shipping line assigns it.',
                [$row('Vehicle', $vehicle), $row('Shipping line', $s['shipping_company'])],
                $hasAccount && $site !== null ? ['label' => 'View on Track Vehicle', 'url' => $site . '/track/'] : null],
            'TRACKING_INFORMATION_AVAILABLE' => ['Shipment update', 'Your container number is ready',
                'Your ' . $vehicle . ' has been assigned to a container. Use the container number below on ' . $line . '\'s official tracking page to follow it.',
                [$row('Vehicle', $vehicle), $row('Container number', $s['container_number']), $row('Shipping line', $s['shipping_company'])], $track],
            'IN_TRANSIT' => ['Shipment update', 'Your vehicle is on its way',
                'Your ' . $vehicle . ' is now in transit to Ghana.',
                [$row('Vehicle', $vehicle), $row('Container number', $s['container_number']), $row('Shipping line', $s['shipping_company']),
                    $row('Shipped', $date($s['shipped_date'])), $row('Estimated arrival (given by the shipping line; may change)', $date($s['estimated_arrival']))], $track],
            'ARRIVED' => ['Shipment update', 'Your vehicle has arrived in Ghana',
                'Your ' . $vehicle . ' has arrived in Ghana. Our team will contact you about the next steps and collection.',
                [$row('Vehicle', $vehicle), $row('Container number', $s['container_number']), $row('Arrived', $date($s['arrival_date']))],
                $site !== null ? ['label' => 'Contact us about collection', 'url' => $site . '/contact/'] : null],
            'DELIVERED' => ['Shipment complete', 'Your vehicle has been delivered',
                'Your ' . $vehicle . ' has been delivered. Thank you for choosing ' . $brand . '. We hope you enjoy your vehicle.',
                [$row('Vehicle', $vehicle), $row('Delivered', $date($s['delivered_date']))], null],
        };
        $name = trim((string) ($s['customer_name'] ?? ''));

        return [
            'subject'    => $title . ' — ' . $vehicle,
            'preheader'  => $text,
            'eyebrow'    => $eyebrow,
            'title'      => $title,
            'greeting'   => 'Hello ' . ($name !== '' ? $name : 'there') . ',',
            'paragraphs' => [$text],
            'details'    => array_values(array_filter($details)),
            'button'     => $button,
            'note'       => $status === 'DELIVERED' ? null
                : 'Location and arrival updates come from the shipping line. For questions about your shipment, contact our team using the details below.',
            'reason'     => 'You received this email because ' . $brand . ' is handling the shipment of your vehicle.',
        ];
    }

    /** "2023 Honda Accord EX" */
    public static function vehicleLabel(array $s): string
    {
        return trim(($s['vehicle_year'] ?? '') . ' ' . ($s['brand_name'] ?? '') . ' ' . ($s['vehicle_model'] ?? '') . ' ' . ($s['vehicle_trim'] ?? ''));
    }

    /** Where the email goes: the linked account's email, else the email recorded on the shipment; null when there is none. */
    public static function recipient(array $s): ?string
    {
        $email = $s['customer_id'] !== null && ($s['account_email'] ?? null) !== null ? (string) $s['account_email'] : (string) ($s['customer_email'] ?? '');

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
    }
}
