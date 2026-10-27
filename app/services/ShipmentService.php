<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/repositories/ShipmentRepository.php';
require_once dirname(__DIR__) . '/repositories/AuditLogRepository.php';
require_once dirname(__DIR__) . '/validation/ShipmentValidator.php';
require_once __DIR__ . '/ShipmentEmail.php';
require_once __DIR__ . '/EmailService.php';
require_once __DIR__ . '/EmailTemplate.php';
require_once __DIR__ . '/ExternalLinksService.php';
require_once __DIR__ . '/Transaction.php';

/**
 * Shipment management (spec 20, decisions D1-D10 approved 2026-10-21). Every change is made by an administrator: the
 * website never learns anything from the shipping line. Rules:
 *   - one shipment = one vehicle + one customer; a container may be shared (D3); one open shipment per vehicle;
 *   - only Reserved or Sold vehicles (D6; the vehicle's own status is never changed here);
 *   - the customer's website account is linked when an ACTIVE account has the recorded email (D1);
 *   - a container number is required from "Tracking information available" on; a wrong check digit only warns (D9);
 *   - moving the status backwards needs an explicit confirmation (D8);
 *   - the customer is emailed only when the administrator ticks the box (D2), after the change is saved;
 *   - deletion only while "Not yet shipped" and never emailed (D10).
 * Every change and every email (sent or failed) is written to the audit log; descriptions hold no email address.
 */
class ShipmentService
{
    public const RESULT_OK = 'ok';
    public const RESULT_UNCHANGED = 'unchanged';
    public const RESULT_INVALID = 'invalid';
    public const RESULT_NOT_FOUND = 'not_found';
    public const RESULT_REFUSED = 'refused';

    public function __construct(private PDO $pdo, private ShipmentRepository $shipments, private AuditLogRepository $audit, private EmailService $email)
    {
    }

    public static function create(): self
    {
        $pdo = Database::connection();

        return new self($pdo, new ShipmentRepository($pdo), new AuditLogRepository($pdo), new EmailService());
    }

    /** @param array{q:string,status:string,page:int} $filters */
    public function listShipments(array $filters): array
    {
        $total = $this->shipments->countMatching($filters);
        $pages = max(1, (int) ceil($total / ShipmentValidator::PAGE_SIZE));
        $page = min($filters['page'], $pages);
        $offset = ($page - 1) * ShipmentValidator::PAGE_SIZE;

        return [
            'shipments' => $total === 0 ? [] : $this->shipments->search($filters, ShipmentValidator::PAGE_SIZE, $offset),
            'total' => $total, 'total_all' => $this->shipments->countAll(), 'page' => $page, 'pages' => $pages,
            'from' => $total === 0 ? 0 : $offset + 1, 'to' => min($offset + ShipmentValidator::PAGE_SIZE, $total),
        ];
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $s = $this->shipments->find($id);
        if ($s !== null) {
            $s['history'] = $this->shipments->history($id);
        }

        return $s;
    }

    /**
     * Customer-facing milestones, one per approved status, in lifecycle order (the six statuses of business rules
     * section 8; "ready for collection" is part of "Arrived in Ghana", not a separate status). 'date' names the shipment
     * field that records when it happened; milestones without such a field never show a date.
     */
    public const MILESTONES = [
        'NOT_YET_SHIPPED'                => ['title' => 'Preparing for shipment', 'text' => 'Your vehicle is being prepared for shipping to Ghana.', 'date' => null],
        'SHIPMENT_BOOKED'                => ['title' => 'Shipment booked', 'text' => 'The shipment is booked with the shipping line. The vehicle has not left yet.', 'date' => null],
        'TRACKING_INFORMATION_AVAILABLE' => ['title' => 'Container assigned', 'text' => 'The shipping line has assigned a container. Its number is shown above.', 'date' => null],
        'IN_TRANSIT'                     => ['title' => 'In transit to Ghana', 'text' => 'The vehicle has left and is on its way.', 'date' => 'shipped_date'],
        'ARRIVED'                        => ['title' => 'Arrived in Ghana', 'text' => 'The vehicle has arrived. Our team will contact you about collection.', 'date' => 'arrival_date'],
        'DELIVERED'                      => ['title' => 'Delivered', 'text' => 'The vehicle has been handed over to you.', 'date' => 'delivered_date'],
    ];

    /** Short milestone names for the horizontal status bar on the Track page (owner request 2026-10-22), same order as MILESTONES. */
    public const BAR_LABELS = ['Preparing', 'Booked', 'Container assigned', 'In transit', 'Arrived in Ghana', 'Delivered'];

    /**
     * Track page search (owner request 2026-10-22): finds a container number among the given shipments, which are the
     * signed-in customer's OWN shipments (forCustomer). Nothing else is searched, so a number from another customer's
     * shipment is simply "not found" and reveals nothing. The number is normalised like the admin form does (spaces and
     * hyphens removed, upper case) and must look like a container number (4 letters, 7 digits).
     *
     * @param list<array<string,mixed>> $shipments
     * @return array{status:'none'|'invalid'|'found'|'not_found',number:?string,index:?int}
     */
    public static function findContainer(array $shipments, mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return ['status' => 'none', 'number' => null, 'index' => null];
        }
        $number = is_string($raw) && strlen($raw) <= 40 ? strtoupper(str_replace([' ', '-'], '', trim($raw))) : '';
        if (preg_match('/^[A-Z]{4}[0-9]{7}$/D', $number) !== 1) {
            return ['status' => 'invalid', 'number' => null, 'index' => null];
        }
        foreach ($shipments as $i => $shipment) {
            if (($shipment['container'] ?? null) === $number) {
                return ['status' => 'found', 'number' => $number, 'index' => $i];
            }
        }

        return ['status' => 'not_found', 'number' => $number, 'index' => null];
    }

    /**
     * The signed-in customer's own shipments for the Track Vehicle page (spec 20 D5; Track Vehicle page spec of
     * 2026-10-21), selected by ACCOUNT ID only. Plain display values; internal notes, emails and other customers'
     * data are never included. Nothing is invented: a value that was not recorded is null and the page says so.
     *
     * @return list<array{vehicle:string,status:string,step:int,updated:string,container:?string,line:?string,tracking:?string,
     *                     estimate:?string,reached:list<array{title:string,text:string,date:?string,current:bool}>,upcoming:list<string>}>
     */
    public function forCustomer(int $customerId): array
    {
        $fmt = static fn (?string $d): ?string => $d === null ? null : (DateTimeImmutable::createFromFormat('!Y-m-d', $d) ?: null)?->format('j M Y');
        $out = [];
        foreach ($this->shipments->forCustomer($customerId) as $s) {
            $step = ShipmentValidator::position((string) $s['status']);
            $reached = [];
            $upcoming = [];
            $bar = [];
            foreach (array_values(self::MILESTONES) as $i => $m) {
                $bar[] = ['label' => self::BAR_LABELS[$i], 'title' => $m['title'], 'state' => $i < $step ? 'done' : ($i === $step ? 'current' : 'todo'),
                    'date' => $i <= $step && $m['date'] !== null ? $fmt($s[$m['date']] ?? null) : null];
                if ($i <= $step) {
                    $reached[] = ['title' => $m['title'], 'text' => $m['text'], 'date' => $m['date'] === null ? null : $fmt($s[$m['date']] ?? null), 'current' => $i === $step];
                } else {
                    $upcoming[] = $m['title'];
                }
            }
            $updated = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) $s['updated_at'], new DateTimeZone('UTC'));
            $out[] = [
                'vehicle'   => ShipmentEmail::vehicleLabel($s),
                'status'    => self::MILESTONES[$s['status']]['title'] ?? '',
                'step'      => $step,
                'updated'   => $updated === false ? '' : $updated->format('j M Y, H:i') . ' GMT',
                'container' => $s['container_number'],
                'line'      => $s['shipping_company'] ?? ExternalLinksService::TRACKING_PROVIDER_NAME,   // default: the line the business ships with
                // The official tracker only makes sense once there is a real container number to enter.
                'tracking'  => $s['container_number'] !== null ? self::trackingUrl($s) : null,
                'estimate'  => $step < ShipmentValidator::position('ARRIVED') ? $fmt($s['estimated_arrival']) : null,
                'reached'   => $reached,
                'upcoming'  => $upcoming,
                'bar'       => $bar,                          // the six steps of the horizontal status bar
                'photo'     => self::vehiclePhoto($s['vehicle_photo'] ?? null),   // the vehicle's main photo (Track page result, 2026-10-22)
                'details'   => self::vehicleDetails($s),
                'total'     => count(self::MILESTONES),
            ];
        }

        return $out;
    }

    /**
     * The vehicle's main photo for the Track page: the stored WebP thumbnail, only when the path has the upload format
     * and the file really exists (same rule as the public vehicle cards). Null otherwise: the page shows a placeholder.
     *
     * @return array{url:string,width:int,height:int}|null
     */
    private static function vehiclePhoto(mixed $path): ?array
    {
        if (!is_string($path) || preg_match('#^uploads/vehicles/([a-f0-9]{32})\.webp$#D', $path, $m) !== 1) {
            return null;
        }
        foreach (['-t', ''] as $suffix) {
            $relative = 'uploads/vehicles/' . $m[1] . $suffix . '.webp';
            $info = is_file(dirname(__DIR__, 2) . '/' . $relative) ? @getimagesize(dirname(__DIR__, 2) . '/' . $relative) : false;
            if ($info !== false && $info[2] === IMAGETYPE_WEBP && $info[0] > 0 && $info[1] > 0) {
                return ['url' => '/' . $relative, 'width' => (int) $info[0], 'height' => (int) $info[1]];
            }
        }

        return null;
    }

    /**
     * A few recorded facts about the vehicle (only what exists; nothing is invented).
     *
     * @return list<array{0:string,1:string}>
     */
    private static function vehicleDetails(array $s): array
    {
        $text = static fn (mixed $v): ?string => is_string($v) && trim($v) !== '' ? trim($v) : null;
        $mileage = isset($s['vehicle_mileage']) && $s['vehicle_mileage'] !== null
            ? number_format((int) $s['vehicle_mileage']) . ' ' . ($text($s['vehicle_mileage_unit'] ?? null) ?? '') : null;

        return array_values(array_filter([
            ['Year', (string) ($s['vehicle_year'] ?? '')],
            ['Make', (string) ($s['brand_name'] ?? '')],
            ['Model', trim(($s['vehicle_model'] ?? '') . ' ' . ($text($s['vehicle_trim'] ?? null) ?? ''))],
            ['Mileage', $mileage !== null ? trim($mileage) : ''],
            ['Transmission', $text($s['vehicle_transmission'] ?? null) ?? ''],
            ['Fuel', $text($s['vehicle_fuel'] ?? null) ?? ''],
            ['Engine', $text($s['vehicle_engine'] ?? null) ?? ''],
        ], static fn (array $row): bool => $row[1] !== ''));
    }

    public function vehicleChoices(?int $currentId = null): array
    {
        return $this->shipments->vehicleChoices($currentId);
    }

    /** @return array{status:string,id:?int,values:array,errors:array,warnings:list<string>,email:string} */
    public function createShipment(array $post, int $adminId, string $ip, string $userAgent): array
    {
        $checked = ShipmentValidator::validate($post);
        $values = $checked['values'];
        $errors = $checked['errors'] + $this->vehicleErrors($values['vehicle_id'], null, null);
        $base = ['id' => null, 'values' => $values, 'warnings' => $checked['warnings'], 'email' => 'none'];
        if ($errors !== []) {
            return ['status' => self::RESULT_INVALID, 'errors' => $errors] + $base;
        }
        $values['customer_id'] = $values['customer_email'] === null ? null : $this->shipments->activeCustomerIdByEmail($values['customer_email']);

        $id = Transaction::run($this->pdo, function () use ($values, $adminId, $ip, $userAgent): int {
            $id = $this->shipments->insert($values);
            $this->audit->record('SHIPMENT_CREATED', $adminId, 'shipment', $id, 'Shipment created: ' . $this->label($id) . ', status '
                . ShipmentValidator::STATUSES[$values['status']] . ($values['customer_id'] !== null ? ', linked to customer account ' . $values['customer_id'] : ''), $ip, $userAgent);

            return $id;
        });
        $email = $this->maybeNotify($id, $values['status'], true, $adminId, $ip, $userAgent);   // a new shipment is its first status update

        return ['status' => self::RESULT_OK, 'errors' => [], 'id' => $id, 'email' => $email] + $base;
    }

    /** @return array{status:string,id:int,values:array,errors:array,warnings:list<string>,email:string} */
    public function updateShipment(int $id, array $post, int $adminId, string $ip, string $userAgent): array
    {
        $existing = $this->shipments->find($id);
        $checked = ShipmentValidator::validate($post);
        $values = $checked['values'];
        $base = ['id' => $id, 'values' => $values, 'warnings' => $checked['warnings'], 'email' => 'none'];
        if ($existing === null) {
            return ['status' => self::RESULT_NOT_FOUND, 'errors' => []] + $base;
        }
        $errors = $checked['errors'] + $this->vehicleErrors($values['vehicle_id'], $id, (int) $existing['vehicle_id']);
        $backwards = ShipmentValidator::position($values['status']) < ShipmentValidator::position((string) $existing['status']);
        if ($backwards && ($post['confirm_back'] ?? null) !== '1' && !isset($errors['status'])) {
            $errors['status'] = 'You are moving the status back from "' . ShipmentValidator::STATUSES[$existing['status']] . '" to "'
                . ShipmentValidator::STATUSES[$values['status']] . '". Tick "Yes, move the status back" to confirm.';
        }
        if ($errors !== []) {
            return ['status' => self::RESULT_INVALID, 'errors' => $errors] + $base;
        }
        $values['customer_id'] = $values['customer_email'] === null ? null : $this->shipments->activeCustomerIdByEmail($values['customer_email']);

        $changed = [];
        foreach (['vehicle_id', 'customer_id', 'customer_name', 'customer_email', 'container_number', 'shipping_company', 'status', 'shipped_date',
                     'estimated_arrival', 'arrival_date', 'delivered_date', 'tracking_url', 'internal_notes'] as $field) {
            if ((string) ($existing[$field] ?? '') !== (string) ($values[$field] ?? '')) {
                $changed[] = $field;
            }
        }
        $resend = ($post['notify'] ?? null) === '1';                      // "Send the email again" (status unchanged)
        if ($changed === [] && !$resend) {
            return ['status' => self::RESULT_UNCHANGED, 'errors' => []] + $base;
        }
        if ($changed !== []) {
            $outcome = Transaction::run($this->pdo, function () use ($id, $values, $existing, $changed, $adminId, $ip, $userAgent): string {
                if ($this->shipments->find($id, true) === null) {
                    return self::RESULT_NOT_FOUND;
                }
                $this->shipments->update($id, $values);
                $this->audit->record('SHIPMENT_UPDATED', $adminId, 'shipment', $id, 'Shipment updated: ' . $this->label($id) . ' (changed: '
                    . implode(', ', array_map(static fn (string $f): string => str_replace('_', ' ', $f), $changed)) . ')', $ip, $userAgent);
                if (in_array('status', $changed, true)) {
                    $this->audit->record('SHIPMENT_STATUS_CHANGED', $adminId, 'shipment', $id, 'Status: ' . ShipmentValidator::STATUSES[$existing['status']]
                        . ' -> ' . ShipmentValidator::STATUSES[$values['status']], $ip, $userAgent);
                }
                if (in_array('container_number', $changed, true) && $values['container_number'] !== null) {
                    $this->audit->record('SHIPMENT_CONTAINER_RECORDED', $adminId, 'shipment', $id, 'Container number recorded: ' . $values['container_number'], $ip, $userAgent);
                }

                return self::RESULT_OK;
            });
            if ($outcome !== self::RESULT_OK) {
                return ['status' => $outcome, 'errors' => []] + $base;
            }
        }
        // Owner request 2026-10-22: the customer is emailed automatically on EVERY status change (no box to tick).
        $email = $this->maybeNotify($id, $values['status'], in_array('status', $changed, true) || $resend, $adminId, $ip, $userAgent);

        return ['status' => self::RESULT_OK, 'errors' => [], 'email' => $email] + $base;
    }

    /** D10: only while "Not yet shipped" and never emailed. @return string RESULT_OK | RESULT_NOT_FOUND | RESULT_REFUSED */
    public function deleteShipment(int $id, int $adminId, string $ip, string $userAgent): string
    {
        return Transaction::run($this->pdo, function () use ($id, $adminId, $ip, $userAgent): string {
            $s = $this->shipments->find($id, true);
            if ($s === null) {
                return self::RESULT_NOT_FOUND;
            }
            if (!self::canDelete($s)) {
                return self::RESULT_REFUSED;
            }
            $label = ShipmentEmail::vehicleLabel($s);
            $this->shipments->delete($id);
            $this->audit->record('SHIPMENT_DELETED', $adminId, 'shipment', $id, 'Shipment deleted: ' . $label, $ip, $userAgent);

            return self::RESULT_OK;
        });
    }

    public static function canDelete(array $s): bool
    {
        return $s['status'] === 'NOT_YET_SHIPPED' && $s['notified_at'] === null;
    }

    /**
     * The official tracking address customers get (emails, Track page, dashboard): Hapag-Lloyd's container tracking
     * (owner, 2026-10-22). A shipment's own address is used only when it is also a Hapag-Lloyd address; anything else
     * stored earlier is ignored, so no other link can reach a customer.
     */
    public static function trackingUrl(array $s): string
    {
        $own = (string) ($s['tracking_url'] ?? '');
        if ($own !== '' && ExternalLinksService::isApprovedTrackingUrl($own)) {
            return $own;
        }
        try {
            return ExternalLinksService::create()->trackingProviderUrl();
        } catch (Throwable $e) {
            return ExternalLinksService::TRACKING_PROVIDER_DEFAULT;
        }
    }

    /** The email the customer would receive for this shipment and status, rendered (admin preview). Null when none. */
    public function previewEmail(int $id, string $status): ?array
    {
        $s = $this->shipments->find($id);
        if ($s === null) {
            return null;
        }
        $content = ShipmentEmail::content($s, $status, self::trackingUrl($s));

        return $content === null ? null : EmailTemplate::render($content, EmailService::brand()) + ['subject' => $content['subject'], 'to' => ShipmentEmail::recipient($s)];
    }

    /**
     * Emails the customer about the saved status (owner request 2026-10-22: automatically on every status change and for a
     * new shipment; "Send the email again" re-sends it). "Not yet shipped" has no email. Runs after the change is committed,
     * so a mail failure never undoes the update. 'sent' | 'failed' | 'no_address' | 'none'.
     */
    private function maybeNotify(int $id, string $status, bool $wanted, int $adminId, string $ip, string $userAgent): string
    {
        if (!$wanted || !in_array($status, ShipmentValidator::EMAIL_STATUSES, true)) {
            return 'none';
        }
        $s = $this->shipments->find($id);
        $to = $s === null ? null : ShipmentEmail::recipient($s);
        if ($s === null || $to === null) {
            return 'no_address';
        }
        $content = ShipmentEmail::content($s, $status, self::trackingUrl($s));
        $sent = $content !== null && $this->email->sendTemplate($to, (string) $s['customer_name'], $content);
        try {
            if ($sent) {
                $this->shipments->markNotified($id);
            }
            $this->audit->record($sent ? 'SHIPMENT_EMAIL_SENT' : 'SHIPMENT_EMAIL_FAILED', $adminId, 'shipment', $id,
                ($sent ? 'Customer emailed: ' : 'Customer email could not be sent: ') . ShipmentValidator::STATUSES[$status], $ip, $userAgent);
        } catch (Throwable $e) {
            error_log('Shipments: could not record the email result: ' . get_class($e));
        }

        return $sent ? 'sent' : 'failed';
    }

    /** @return array<string,string> */
    private function vehicleErrors(?int $vehicleId, ?int $shipmentId, ?int $currentVehicleId): array
    {
        if ($vehicleId === null) {
            return [];
        }
        $v = $this->shipments->vehicle($vehicleId);
        if ($v === null) {
            return ['vehicle_id' => 'Choose the vehicle from the list.'];
        }
        if ($vehicleId !== $currentVehicleId && ($v['deleted_at'] !== null || !in_array($v['availability_status'], ['reserved', 'sold'], true))) {
            return ['vehicle_id' => 'Only a vehicle marked Reserved or Sold can be shipped. Update the vehicle first.'];
        }
        if ($this->shipments->vehicleHasOpenShipment($vehicleId, $shipmentId)) {
            return ['vehicle_id' => 'This vehicle already has a shipment that is not delivered yet.'];
        }

        return [];
    }

    private function label(int $id): string
    {
        $s = $this->shipments->find($id);

        return $s === null ? 'shipment ' . $id : ShipmentEmail::vehicleLabel($s) . ($s['container_number'] !== null ? ' (' . $s['container_number'] . ')' : '');
    }
}
