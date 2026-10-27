<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/services/ShipmentService.php';
require_once dirname(__DIR__) . '/services/AuthorizationService.php';
require_once dirname(__DIR__) . '/helpers/csrf.php';
require_once dirname(__DIR__) . '/helpers/flash.php';
require_once dirname(__DIR__) . '/helpers/diagnostics.php';

/**
 * Shipment management controller (spec 20). Same pattern as the other admin controllers: permission check, CSRF on
 * every POST, ids and input validated, ShipmentService does the work, a view model (or a 'redirect') comes back.
 * A view model always has 'http' and 'problem' (null | forbidden | not_found | csrf | error).
 */
final class ShipmentController
{
    public const PERMISSION = 'shipments.manage';

    public const MESSAGE_FORBIDDEN = 'Your account does not have permission to manage shipments.';
    public const MESSAGE_NOT_FOUND = 'The requested shipment could not be found.';
    public const MESSAGE_CSRF = "We couldn't verify your request. Reload the page and try again.";
    public const MESSAGE_ERROR = 'Something went wrong. Please try again.';
    public const MESSAGE_INVALID_FORM = 'Please correct the highlighted fields.';

    /** What the administrator is told about the customer email after a save. */
    private const EMAIL_MESSAGES = [
        'sent'       => ['success', 'The customer was emailed about the new status.'],
        'failed'     => ['warning', 'The shipment was saved, but the email to the customer could not be sent. Tick "Send the email again" and save to retry.'],
        'no_address' => ['warning', 'The shipment was saved. No email was sent: there is no email address for this customer.'],
    ];

    public function __construct(private ?ShipmentService $service = null)
    {
    }

    public function index(array $admin, array $query): array
    {
        return $this->run($admin, function (ShipmentService $service) use ($query): array {
            $filters = ShipmentValidator::filters($query);
            $data = $service->listShipments($filters);
            $filters['page'] = $data['page'];

            return $data + ['filters' => $filters, 'filtered' => $filters['q'] !== '' || $filters['status'] !== 'all', 'flash' => flash_take()];
        });
    }

    public function create(array $admin, string $method, array $post, string $ip, string $userAgent): array
    {
        return $this->run($admin, function (ShipmentService $service) use ($admin, $method, $post, $ip, $userAgent): array {
            $view = ['shipment' => null, 'values' => self::blankValues(), 'errors' => [], 'vehicleChoices' => $service->vehicleChoices()];
            if ($method !== 'POST') {
                return $view;
            }
            if (!csrf_verify($post[CSRF_FIELD_NAME] ?? null)) {
                return $this->csrfFailure($admin, $ip, $userAgent) + self::keepTyped($view, $post);
            }
            $result = $service->createShipment($post, (int) $admin['id'], $ip, $userAgent);
            if ($result['status'] === ShipmentService::RESULT_OK) {
                $this->flashOutcome('Shipment created.', $result);

                return ['redirect' => 'shipment-edit.php?id=' . $result['id']];
            }

            return $this->invalid($view, $result, $post);
        });
    }

    public function edit(array $admin, mixed $rawId, string $method, array $post, string $ip, string $userAgent): array
    {
        return $this->run($admin, function (ShipmentService $service) use ($admin, $rawId, $method, $post, $ip, $userAgent): array {
            $id = ShipmentValidator::id($rawId);
            $shipment = $id === null ? null : $service->find($id);
            if ($shipment === null) {
                return $this->notFound();
            }
            $view = ['shipment' => $shipment, 'values' => self::valuesFromRecord($shipment), 'errors' => [],
                'vehicleChoices' => $service->vehicleChoices((int) $shipment['vehicle_id']), 'flash' => $method === 'POST' ? null : flash_take()];
            if ($method !== 'POST') {
                return $view;
            }
            if (!csrf_verify($post[CSRF_FIELD_NAME] ?? null)) {
                return $this->csrfFailure($admin, $ip, $userAgent) + self::keepTyped($view, $post);
            }
            $result = $service->updateShipment($id, $post, (int) $admin['id'], $ip, $userAgent);
            switch ($result['status']) {
                case ShipmentService::RESULT_OK:
                    $this->flashOutcome('Shipment updated.', $result);

                    return ['redirect' => 'shipment-edit.php?id=' . $id];
                case ShipmentService::RESULT_UNCHANGED:
                    flash_set('info', 'No changes were made.');

                    return ['redirect' => 'shipment-edit.php?id=' . $id];
                case ShipmentService::RESULT_NOT_FOUND:
                    return $this->notFound();
            }

            return $this->invalid($view, $result, $post);
        });
    }

    /** GET: the delete confirmation (shows whether deletion is allowed). POST: the deletion. */
    public function delete(array $admin, string $method, mixed $rawId, array $post, string $ip, string $userAgent): array
    {
        return $this->run($admin, function (ShipmentService $service) use ($admin, $method, $rawId, $post, $ip, $userAgent): array {
            if ($method === 'POST' && !csrf_verify($post[CSRF_FIELD_NAME] ?? null)) {
                return $this->csrfFailure($admin, $ip, $userAgent);   // checked before anything is looked up
            }
            $id = ShipmentValidator::id($method === 'POST' ? ($post['id'] ?? null) : $rawId);
            $shipment = $id === null ? null : $service->find($id);
            if ($shipment === null) {
                return $this->notFound();
            }
            if ($method !== 'POST') {
                return ['shipment' => $shipment, 'allowed' => ShipmentService::canDelete($shipment)];
            }
            $outcome = $service->deleteShipment($id, (int) $admin['id'], $ip, $userAgent);
            if ($outcome === ShipmentService::RESULT_OK) {
                flash_set('success', 'Shipment deleted.');

                return ['redirect' => 'shipments.php'];
            }
            if ($outcome === ShipmentService::RESULT_REFUSED) {
                flash_set('error', 'This shipment can no longer be deleted: it has been booked or the customer was emailed. It is kept for the record.');

                return ['redirect' => 'shipment-edit.php?id=' . $id];
            }

            return $this->notFound();
        });
    }

    /** GET: the email the customer would receive for a status (rendered HTML), for the administrator's preview. */
    public function preview(array $admin, mixed $rawId, mixed $status): array
    {
        return $this->run($admin, function (ShipmentService $service) use ($rawId, $status): array {
            $id = ShipmentValidator::id($rawId);
            $preview = $id === null || !is_string($status) || !in_array($status, ShipmentValidator::EMAIL_STATUSES, true) ? null : $service->previewEmail($id, $status);

            return $preview === null ? $this->notFound() : ['preview' => $preview];
        });
    }

    private function flashOutcome(string $saved, array $result): void
    {
        $message = $saved;
        $type = 'success';
        if (isset(self::EMAIL_MESSAGES[$result['email']])) {
            [$type, $text] = self::EMAIL_MESSAGES[$result['email']];
            $message = $result['email'] === 'sent' ? $saved . ' ' . $text : $text;
        }
        if ($result['warnings'] !== []) {
            $type = 'warning';
            $message .= ' ' . implode(' ', $result['warnings']);
        }
        flash_set($type, $message);
    }

    private static function blankValues(): array
    {
        return ['vehicle_id' => '', 'status' => 'NOT_YET_SHIPPED', 'customer_name' => '', 'customer_email' => '', 'container_number' => '', 'shipping_company' => ShipmentValidator::DEFAULT_LINE,
            'tracking_url' => '', 'shipped_date' => '', 'estimated_arrival' => '', 'arrival_date' => '', 'delivered_date' => '', 'internal_notes' => ''];
    }

    private static function valuesFromRecord(array $s): array
    {
        $out = [];
        foreach (array_keys(self::blankValues()) as $k) {
            $out[$k] = (string) ($s[$k] ?? '');
        }

        return $out;
    }

    private static function keepTyped(array $view, array $post): array
    {
        foreach ($view['values'] as $k => $current) {
            if (is_string($post[$k] ?? null)) {
                $view['values'][$k] = $post[$k];
            }
        }

        return $view;
    }

    private function invalid(array $view, array $result, array $post): array
    {
        $view = self::keepTyped($view, $post);
        $view['errors'] = $result['errors'];
        $view['formMessage'] = self::MESSAGE_INVALID_FORM;
        $view['notifyChecked'] = ($post['notify'] ?? null) === '1';
        $view['needsBackConfirm'] = str_contains((string) ($result['errors']['status'] ?? ''), 'move the status back');

        return $view;
    }

    private function run(array $admin, callable $action): array
    {
        if (!AuthorizationService::can((string) ($admin['role'] ?? ''), self::PERMISSION)) {
            return ['http' => 403, 'problem' => 'forbidden', 'message' => self::MESSAGE_FORBIDDEN];
        }
        try {
            $result = $action($this->service ?? ShipmentService::create());
        } catch (Throwable $e) {
            error_log('Shipment management failed: ' . get_class($e) . ': ' . $e->getMessage());

            return ['http' => 500, 'problem' => 'error', 'message' => self::MESSAGE_ERROR, 'detail' => development_error_detail($e)];
        }

        return $result + ['http' => 200, 'problem' => null];
    }

    private function notFound(): array
    {
        return ['http' => 404, 'problem' => 'not_found', 'message' => self::MESSAGE_NOT_FOUND];
    }

    private function csrfFailure(array $admin, string $ip, string $userAgent): array
    {
        try {
            (new AuditLogRepository(Database::connection()))->record('CSRF_FAILURE', (int) $admin['id'], null, null,
                'Shipment management request rejected: missing or invalid CSRF token', $ip, $userAgent);
        } catch (Throwable $e) {
            error_log('Shipments: could not log CSRF failure: ' . get_class($e));
        }

        return ['http' => 400, 'problem' => 'csrf', 'message' => self::MESSAGE_CSRF];
    }
}
