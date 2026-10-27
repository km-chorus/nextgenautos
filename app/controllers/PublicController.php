<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/services/PublicRouter.php';
require_once dirname(__DIR__) . '/services/HomepageHeroService.php';
require_once dirname(__DIR__) . '/services/PublicVehicleService.php';
require_once dirname(__DIR__) . '/services/AccidentVehicleSettings.php';
require_once dirname(__DIR__) . '/services/ShipmentService.php';
require_once dirname(__DIR__) . '/services/CompanyContactService.php';
require_once dirname(__DIR__) . '/services/PublicBrandService.php';
require_once dirname(__DIR__) . '/services/ExternalLinksService.php';
require_once dirname(__DIR__) . '/services/PublicTeamService.php';
require_once dirname(__DIR__) . '/services/PublicPartService.php';
require_once dirname(__DIR__) . '/services/StructuredData.php';
require_once dirname(__DIR__) . '/services/PublicEnquiryService.php';
require_once dirname(__DIR__) . '/services/PublicFormToken.php';
require_once dirname(__DIR__) . '/services/CookieConsentService.php';
require_once dirname(__DIR__) . '/services/AssistantService.php';
require_once dirname(__DIR__) . '/services/CustomerDashboardService.php';
require_once dirname(__DIR__) . '/services/CustomerVerificationService.php';
require_once dirname(__DIR__) . '/repositories/CustomerRepository.php';
require_once __DIR__ . '/CustomerAuthController.php';
require_once dirname(__DIR__) . '/helpers/public_ui.php';
require_once dirname(__DIR__) . '/helpers/diagnostics.php';

/**
 * Public website controller (spec 09_spec_webpage_ui.md): turns the router's decision into a
 * response using the shared layout (includes/header.php, includes/footer.php) and a view from
 * public/views/. It sends the security headers, the right status code and the page metadata.
 *
 * It has no business logic and no database access: the foundation shows no data. Later
 * specifications add real pages by switching a route to a view (and, when a page needs data,
 * a service and repository, the same architecture as the admin).
 *
 * Errors are contained: anything unexpected is logged on the server and the visitor sees a
 * clean message; in development only, a path-free one-line detail is added for the developer.
 */
final class PublicController
{
    /** @var array<string,mixed> the parsed query string of the current request (untrusted; validated before use) */
    private array $query = [];

    private string $method = 'GET';

    public function __construct(private PublicRouter $router)
    {
    }

    /** Resolves the request and sends the complete response. */
    public function handle(string $method, string $requestUri): void
    {
        try {
            $this->method = strtoupper($method);
            parse_str((string) parse_url($requestUri, PHP_URL_QUERY), $this->query);
            // SEO 2026-10-27: /sitemap.xml is answered here (route paths never contain a dot).
            if (parse_url($requestUri, PHP_URL_PATH) === '/sitemap.xml') {
                $this->sitemap();

                return;
            }
            $this->respond($this->router->resolve($method, $requestUri));
        } catch (Throwable $e) {
            error_log('Public site error: ' . get_class($e) . ': ' . $e->getMessage());
            $this->sendError($e);
        }
    }

    /** @param array<string,mixed> $resolution */
    private function respond(array $resolution): void
    {
        switch ($resolution['type']) {
            case 'redirect':
                // $resolution['to'] was rebuilt from validated characters only; it is always a local path.
                header('Location: ' . $resolution['to'], true, 301);

                return;
            case 'method_not_allowed':
                http_response_code(405);
                header('Allow: GET, HEAD');
                header('Content-Type: text/plain; charset=UTF-8');
                echo "Method not allowed.\n";

                return;
            case 'not_found':
                $this->notFound();

                return;
        }

        /** @var array<string,mixed> $route */
        $route = $resolution['route'];
        if (($route['view'] ?? null) === 'error-preview') {
            throw new RuntimeException('Preview of the error page (development only).');
        }
        $view = ($route['status'] ?? 'planned') === 'live' ? (string) $route['view'] : 'placeholder';
        $isPlanned = $view === 'placeholder';

        $page = [
            'title'  => (string) $route['title'],
            'path'   => isset($route['path']) ? (string) $route['path'] : '',
            'robots' => $isPlanned ? 'noindex,follow' : 'index,follow',
            'nav'    => (string) ($route['parent'] ?? $route['name']),
            'navExact' => isset($route['path']),
            'description' => $route['description'] ?? null,
            'css'    => $route['css'] ?? [],
            'js'     => $route['js'] ?? [],
        ];
        $data = ['route' => $route];
        if (!empty($route['private'])) {
            $page['robots'] = 'noindex,nofollow';
            $page['private'] = true;
        }
        if ($view === 'track') {
            // Track Your Vehicle (Track Vehicle page spec, 2026-10-21). Guests get an explanation and sign-in links (and are
            // remembered, so signing in or registering brings them back here); shipment data is read ONLY for the signed-in
            // account, by the id from the server-side session. The page takes no id from the URL or a form: no IDOR.
            $access = (new CustomerAuthController())->requireCustomer('/track/');   // an exception here shows the generic error page: fails closed
            if ($access['customer'] === null) {
                // Owner request 2026-10-22: a visitor who is not signed in goes straight to the sign-in page ("Sign in to track
                // your vehicle."); /track/ is remembered, so signing in (or registering, or Google) brings them back here.
                header('Location: ' . $access['redirect'], true, 303);
                header('Cache-Control: no-store');

                return;
            }
            $data['customer'] = $access['customer'];
            $data['trackingUrl'] = $this->trackingProviderUrl();
            $data['trackingName'] = ExternalLinksService::TRACKING_PROVIDER_NAME;
            // Shipments only for a CONFIRMED email (spec 22): an unconfirmed account sees a "confirm your email" state instead.
            $verified = $access['customer'] !== null && $access['customer']['verified'];
            $data['shipments'] = $verified ? $this->customerShipments((int) $access['customer']['id']) : [];
            // Tracking number search (owner request 2026-10-22): only among this customer's own shipments read just above.
            $data['search'] = ShipmentService::findContainer($data['shipments'] ?? [], $this->query['number'] ?? null);
            $data['typed'] = is_string($this->query['number'] ?? null) && mb_check_encoding($this->query['number'], 'UTF-8') ? mb_substr($this->query['number'], 0, 40) : '';
            $data['csrf'] = $access['customer'] !== null ? (new CustomerSessionService())->csrfToken() : '';
            $page['signed_in'] = $access['customer'] !== null;
            $page['title'] = 'Track Your Vehicle';
            $this->render(200, 'track', $page, $data);

            return;
        }
        if ($view === 'vehicle') {
            $this->vehiclePage((string) ($resolution['slug'] ?? ''), $page, $data);

            return;
        }
        if ($view === 'contact') {
            $this->contactPage($page, $data);

            return;
        }
        if ($view === 'assistant') {
            $this->assistant();

            return;
        }
        if ($view === 'favourites') {
            $this->favourite();

            return;
        }
        if ($view === 'verify-email') {
            $result = 'invalid';
            try {
                $ip = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) !== false ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
                $result = CustomerVerificationService::create()->verify($this->query['t'] ?? null, $ip, substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255));
            } catch (Throwable $e) {
                error_log('Email confirmation failed: ' . get_class($e));
                $result = 'error';
            }
            $data['result'] = $result;
            $this->render($result === 'error' ? 503 : 200, 'verify-email', $page, $data);

            return;
        }
        if ($view === 'cookies') {
            $this->cookiesPage($page, $data);

            return;
        }
        if ($view === 'google-start' || $view === 'google-callback') {
            $this->googleRedirect($view === 'google-callback');

            return;
        }
        if (in_array($view, ['login', 'register', 'account', 'account-settings', 'forgot-password', 'reset-password'], true)) {
            $status = $this->customerPage($view, $page, $data);
            if ($status !== null) {   // null = a redirect was already sent
                $this->render($status, $view, $page, $data);
            }

            return;
        }
        if ($view === 'privacy') {
            // Spec 29: the company's email and address from Admin > Settings for "Contact us" (left out when unreadable).
            require_once dirname(__DIR__) . '/services/CustomerPasswordResetService.php';
            try {
                $data['contact'] = CompanyContactService::create()->details();
            } catch (Throwable $e) {
                error_log('Privacy page contact details unavailable: ' . get_class($e));
                $data['contact'] = null;
            }
        }
        if ($view === 'about') {
            // About page (2026-10-26): team from Admin > Team, the buying steps (config) and the office address (Settings).
            // A part that cannot be read is simply left out (logged); the page itself always shows.
            $data['steps'] = (require dirname(__DIR__) . '/config/how_it_works.php')['steps'];
            try {
                $data['team'] = PublicTeamService::create()->forAboutPage();
            } catch (Throwable $e) {
                error_log('About page team unavailable: ' . get_class($e));
                $data['team'] = [];
            }
            try {
                $data['address'] = CompanyContactService::create()->details()['address'] ?? null;
            } catch (Throwable $e) {
                error_log('About page address unavailable: ' . get_class($e));
                $data['address'] = null;
            }
        }
        if ($view === 'parts') {
            // Spec 27: every published part from Admin > Parts, with "fit my car", category, search and pages. Unreadable =
            // the page says parts are unavailable (logged), never an error page.
            try {
                $data['result'] = PublicPartService::create()->browse($this->query);
            } catch (Throwable $e) {
                error_log('Parts page unavailable: ' . get_class($e) . ': ' . $e->getMessage());
                $data['result'] = null;
            }
            try {
                $data['whatsapp'] = CompanyContactService::create()->ghanaWhatsapp();   // the Ghana WhatsApp number, as on vehicle pages
            } catch (Throwable $e) {
                error_log('WhatsApp number unavailable: ' . get_class($e));
                $data['whatsapp'] = null;
            }
            $r = $data['result'];
            if ($r !== null && ($r['page'] > 1 || array_filter($r['filters'], static fn ($v): bool => $v !== null && $v !== '') !== [])) {
                $page['robots'] = 'noindex,follow';   // a filtered or later page is not a page of its own for search engines
            }
        }
        if ($view === 'home') {
            $data['hero'] = HomepageHeroService::create()->slides(0);   // slide 1 (the strongest image) always opens: predictable for visitors and search engines
            $data['searchOptions'] = $this->searchOptions();
            $data['latest'] = $this->latestVehicles();
            $data['brands'] = $this->browseBrands();
            $data['dutyUrl'] = $this->dutyCalculatorUrl();
            $data['parts'] = $this->latestParts();
            $data['whatsapp'] = $this->whatsappDigits();
            if ($data['parts'] !== null && ($data['parts']['type'] !== null || $data['parts']['make'] !== null)) {
                $page['robots'] = 'noindex,follow';   // a filtered homepage is not a page of its own for search engines
            }
            $page['preloads'] = $data['hero'] === [] ? [] : public_hero_preloads($data['hero'][0]);
            // SEO 2026-10-27: brand-first title; the first hero photo as the share image; Organization + WebSite data.
            $page['fullTitle'] = PUBLIC_SITE_NAME . ' | Canadian Vehicles & Car Parts in Ghana';
            $heroImage = $data['hero'][0]['desktop']['url'] ?? null;
            if (is_string($heroImage)) {
                $page['image'] = strtok($heroImage, '?');
                $page['imageAlt'] = is_string($data['hero'][0]['alt'] ?? null) ? $data['hero'][0]['alt'] : null;
            }
            $page['jsonld'] = [StructuredData::organization($this->contactDetails()), StructuredData::website()];
        }

        if ($view === 'vehicles') {
            $filters = PublicVehicleSearchValidator::fromQuery($this->query);
            $service = PublicVehicleService::create();
            $result = $service->search($filters);
            $data += ['filters' => $filters, 'result' => $result, 'searchOptions' => $service->searchOptions()];
            $page['title'] = 'Vehicles for Sale';
            // A filtered or later page is not a page of its own for search engines; the canonical URL stays /vehicles/.
            if (PublicVehicleSearchValidator::hasFilters($filters) || $result['page'] > 1) {
                $page['robots'] = 'noindex,follow';
            }
        }

        if ($view === 'accident-vehicles') {
            // Spec 23: only while the administrator has switched the page on (owner decision: hidden until Act 1014 is confirmed).
            $filters = PublicVehicleSearchValidator::fromQuery($this->query);
            $service = PublicVehicleService::create();
            $result = $service->accidentSearch($filters, AccidentVehicleSettings::enabled());
            if ($result === null) {
                $this->notFound();

                return;
            }
            $data += ['mode' => 'accident', 'filters' => $filters, 'result' => $result, 'searchOptions' => $service->searchOptions(VehicleRepository::SECTION_ACCIDENT)];
            if (PublicVehicleSearchValidator::hasFilters($filters) || $result['page'] > 1) {
                $page['robots'] = 'noindex,follow';
            }
            $view = 'vehicles';
        }

        $this->render(200, $view, $page, $data);
    }

    /** Visitor-facing wording for each form outcome (spec 14), keyed by PublicEnquiryService status. */
    private const ENQUIRY_MESSAGES = [
        'invalid'   => 'Please correct the highlighted fields.',
        'retry'     => 'Please check your details and press Send again.',
        'expired'   => 'Your form expired. Please check your details and send it again.',
        'throttled' => 'You have sent several requests in a short time. Please wait a while, or contact us by phone or WhatsApp.',
        'reserved'  => 'This vehicle has just been reserved. You can still send us a question about it.',
        'error'     => 'Your request could not be sent right now. Please try again shortly, or contact us by phone or WhatsApp.',
    ];
    private const ENQUIRY_HTTP = ['invalid' => 200, 'retry' => 200, 'reserved' => 200, 'expired' => 400, 'throttled' => 429, 'error' => 503];

    /**
     * Vehicle detail page (spec 14): the public vehicle for this slug (404 when there is none), the Contact and Reserve forms
     * (POST, Post/Redirect/Get on success) and the WhatsApp link. The vehicle id is looked up here from the slug; the forms
     * never carry it.
     *
     * @param array<string,mixed> $page
     * @param array<string,mixed> $data
     */
    private function vehiclePage(string $slug, array $page, array $data): void
    {
        $detail = PublicVehicleService::create()->detail($slug);   // a database failure here shows the generic error page
        if ($detail === null) {
            $this->notFound();

            return;
        }
        $vehicle = $detail['vehicle'];
        if ($vehicle['accident'] !== null) {
            $page['nav'] = 'accident-vehicles';   // spec 23: an accident vehicle belongs to the Accident Vehicles page
        }
        $url = $vehicle['url'];   // built from the validated, stored slug
        $form = ['kind' => null, 'values' => [], 'errors' => [], 'message' => null];
        $status = 200;

        if ($this->method === 'POST') {
            $kind = in_array($_POST['kind'] ?? null, EnquiryValidator::KINDS, true) ? (string) $_POST['kind'] : 'contact';
            $ip = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) !== false ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
            $userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
            try {
                $result = PublicEnquiryService::create()->submit($detail['id'], $vehicle['reserved'], $kind, $_POST, $ip, $userAgent, $this->signedInCustomerId(), (string) $vehicle['title']);
            } catch (Throwable $e) {
                error_log('Vehicle enquiry could not be saved: ' . get_class($e) . ': ' . $e->getMessage());
                $result = ['status' => 'error', 'values' => EnquiryValidator::validate($kind, $_POST)['values'], 'errors' => []];
            }
            if ($result['status'] === 'sent') {
                header('Location: ' . $url . '?sent=' . $kind . '#' . $kind, true, 303);
                header('Cache-Control: no-store');

                return;
            }
            $form = ['kind' => $kind, 'values' => $result['values'], 'errors' => $result['errors'], 'message' => self::ENQUIRY_MESSAGES[$result['status']]];
            $status = self::ENQUIRY_HTTP[$result['status']];
            $page['private'] = true;   // the response repeats what the visitor typed: never cached
        }

        $sent = in_array($this->query['sent'] ?? null, EnquiryValidator::KINDS, true) ? (string) $this->query['sent'] : null;
        $data['vehicle'] = $vehicle;
        $data['form'] = $form;
        $data['sent'] = $form['kind'] === null ? $sent : null;
        $data['prefill'] = $form['kind'] === null ? $this->customerPrefill() : [];
        $data['tokens'] = ['contact' => PublicFormToken::issue('vehicle-contact'), 'reserve' => PublicFormToken::issue('vehicle-reserve')];
        $data['whatsapp'] = $this->vehicleWhatsapp($vehicle);
        // Spec 28: in-stock parts the administrator listed as compatible with this vehicle. Unreadable = no section (logged).
        try {
            $data['vehicleParts'] = PublicPartService::create()->forVehicle($detail['fit']);
            $data['partsWhatsapp'] = CompanyContactService::create()->ghanaWhatsapp();
        } catch (Throwable $e) {
            error_log('Vehicle page parts unavailable: ' . get_class($e));
            $data['vehicleParts'] = null;
            $data['partsWhatsapp'] = null;
        }

        $page['title'] = $vehicle['title'];
        $page['path'] = $url;
        $page['type'] = 'product';
        $page['description'] = $vehicle['title'] . ' for ' . $vehicle['price'] . '. ' . $vehicle['status']
            . ' through ' . PUBLIC_SITE_NAME . ': photos, specifications, and contact, WhatsApp or reservation.';
        $page['image'] = $vehicle['photos'][0]['large']['url'] ?? null;
        $page['imageAlt'] = $vehicle['photos'][0]['alt'] ?? null;
        $page['jsonld'] = [StructuredData::car($vehicle, $detail['schema'])];   // SEO 2026-10-27: the visible price and specifications
        if ($sent !== null || $this->method === 'POST') {
            $page['robots'] = 'noindex,follow';
        }
        $this->render($status, 'vehicle', $page, $data);
    }

    /**
     * Contact page (spec 17): the business's contact details from the admin Settings (only what is filled in and valid), a
     * WhatsApp chat button, and the contact form with a topic (POST, Post/Redirect/Get on success). If the settings cannot
     * be read the page still works: the details are left out and the form is still offered.
     *
     * @param array<string,mixed> $page
     * @param array<string,mixed> $data
     */
    private function contactPage(array $page, array $data): void
    {
        try {
            $details = CompanyContactService::create()->details();
        } catch (Throwable $e) {
            error_log('Contact details unavailable: ' . get_class($e) . ': ' . $e->getMessage());
            $details = ['phone' => null, 'phone2' => null, 'whatsapp' => null, 'email' => null, 'hours' => null, 'socials' => []];
        }
        $page['jsonld'] = [StructuredData::autoDealer($details)];   // SEO 2026-10-27: the business, address and phones shown on this page
        $form = ['values' => [], 'errors' => [], 'message' => null];
        $status = 200;

        if ($this->method === 'POST') {
            $ip = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) !== false ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
            $userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
            try {
                $result = PublicEnquiryService::create()->submit(null, false, 'general', $_POST, $ip, $userAgent, $this->signedInCustomerId());
            } catch (Throwable $e) {
                error_log('Contact enquiry could not be saved: ' . get_class($e) . ': ' . $e->getMessage());
                $result = ['status' => 'error', 'values' => EnquiryValidator::validate('general', $_POST)['values'], 'errors' => []];
            }
            if ($result['status'] === 'sent') {
                header('Location: /contact/?sent=1#message', true, 303);
                header('Cache-Control: no-store');

                return;
            }
            $form = ['values' => $result['values'], 'errors' => $result['errors'], 'message' => self::ENQUIRY_MESSAGES[$result['status']]];
            $status = self::ENQUIRY_HTTP[$result['status']];
            $page['private'] = true;
        }

        $sent = $this->method !== 'POST' && ($this->query['sent'] ?? null) === '1';
        $data['details'] = $details;
        $data['form'] = $form;
        $data['sent'] = $sent;
        $data['prefill'] = $this->method === 'POST' ? [] : $this->customerPrefill() + $this->partPrefill() + $this->requestPrefill();
        $data['token'] = PublicFormToken::issue(PublicEnquiryService::tokenPurpose('general'));
        $data['whatsapp'] = $details['whatsapp'] === null ? null
            : CompanyContactService::whatsappLink($details['whatsapp'], 'Hello, I have a question for ' . PUBLIC_SITE_NAME . '.');

        $page['description'] = 'Contact ' . PUBLIC_SITE_NAME . ' about vehicles from Canada, car parts or shipping to Ghana: send a message, chat on WhatsApp, call or email.';
        if ($sent || $this->method === 'POST') {
            $page['robots'] = 'noindex,follow';
        }
        $this->render($status, 'contact', $page, $data);
    }

    /**
     * Save / remove a favourite (spec 22). POST only, customer CSRF token required. The customer comes from the session;
     * the vehicle is named by its slug and must be public to be added. A guest is sent to sign in (and brought back to
     * the vehicle). Answers JSON when the browser asks for it (assets/js/favourites.js), else redirects back.
     */
    private function favourite(): void
    {
        $json = str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
        $back = $_POST['return'] ?? '/account/';
        $back = is_string($back) && CustomerSessionService::isReturnTarget($back) ? $back : '/account/';
        $respond = static function (int $status, array $body, string $location) use ($json): void {
            header('Cache-Control: no-store');
            if ($json) {
                http_response_code($status);
                header('Content-Type: application/json; charset=UTF-8');
                header('X-Content-Type-Options: nosniff');
                echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);

                return;
            }
            header('Location: ' . $location, true, 303);
        };
        try {
            $sessions = new CustomerSessionService();
            $customerId = $sessions->currentCustomerId();
            if ($customerId === null) {
                $sessions->setReturnTo($back);
                $respond(401, ['login' => '/login/?reason=favourite'], '/login/?reason=favourite');

                return;
            }
            if (!$sessions->verifyCsrf($_POST[CustomerSessionService::CSRF_FIELD] ?? null)) {
                $respond(400, ['error' => 'Please reload the page and try again.'], $back);

                return;
            }
            $slug = is_string($_POST['vehicle'] ?? null) ? $_POST['vehicle'] : '';
            $result = CustomerDashboardService::create()->setFavourite($customerId, $slug, ($_POST['action'] ?? '') === 'add');
            $respond($result === 'not_found' ? 404 : ($result === 'full' ? 409 : 200),
                ['favourite' => $result === 'added', 'result' => $result], $back);
        } catch (Throwable $e) {
            error_log('Favourite could not be saved: ' . get_class($e));
            $respond(503, ['error' => 'Could not save right now. Please try again.'], $back);
        }
    }

    /**
     * The site assistant (spec 19): one answer as JSON, from ?intent=<id> (a quick reply) or ?q=<typed question>.
     * Read-only: nothing is stored and the question is never logged. A failure gives a short, generic JSON error.
     */
    private function assistant(): void
    {
        try {
            $body = AssistantService::create()->reply($this->query['intent'] ?? null, $this->query['q'] ?? null);
            $status = 200;
        } catch (Throwable $e) {
            error_log('Assistant failed: ' . get_class($e));
            $body = ['error' => 'The assistant is unavailable right now.'];
            $status = 503;
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
        header('X-Content-Type-Options: nosniff');
        header('X-Robots-Tag: noindex, nofollow');
        header('Cache-Control: no-store');
        header('Referrer-Policy: same-origin');
        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR);
    }

    /**
     * Cookie policy and settings (spec 18). POST saves the choice (from this page, the banner or the settings dialog) and
     * goes back to the page the visitor was on (Post/Redirect/Get); a cross-site POST is refused.
     *
     * @param array<string,mixed> $page
     * @param array<string,mixed> $data
     */
    private function cookiesPage(array $page, array $data): void
    {
        $status = 200;
        $data['error'] = null;
        if ($this->method === 'POST') {
            $activity = CookieConsentService::choiceFromForm($_POST);
            if (!CookieConsentService::isSameOrigin($_SERVER)) {
                $status = 403;
                $data['error'] = 'Your cookie settings could not be saved. Please choose them again on this page.';
            } elseif ($activity === null) {
                $status = 400;
                $data['error'] = 'Please choose one of the options below.';
            } else {
                CookieConsentService::save($activity);
                $back = CookieConsentService::safeReturn($_POST['return'] ?? null);
                header('Location: ' . (str_starts_with($back, '/cookies/') ? '/cookies/?saved=1' : $back), true, 303);
                header('Cache-Control: no-store');

                return;
            }
            $page['robots'] = 'noindex,follow';
        }
        $data['consent'] = CookieConsentService::current();
        $data['saved'] = $this->method !== 'POST' && ($this->query['saved'] ?? null) === '1';
        if ($data['saved']) {
            $page['robots'] = 'noindex,follow';
        }
        $this->render($status, 'cookies', $page, $data);
    }

    /**
     * "Ask about this part" (homepage parts cards): ?part=<slug> of a PUBLIC part pre-selects the "Car parts" topic and starts
     * the message with the part's name. An unknown or malformed slug simply gives an empty form.
     *
     * @return array<string,string>
     */
    private function partPrefill(): array
    {
        if (!isset($this->query['part'])) {
            return [];
        }
        try {
            $service = PublicPartService::create();
            $label = $service->enquiryLabel($this->query['part']);
            // The customer's car: from a vehicle's page (spec 28, ?vehicle=<public vehicle slug>) or from "fit my car" (spec 27); validated
            $vehicle = $label === null ? null
                : (isset($this->query['vehicle']) ? PublicVehicleService::create()->titleBySlug($this->query['vehicle']) : $service->vehicleLine($this->query));
        } catch (Throwable $e) {
            error_log('Part prefill unavailable: ' . get_class($e));
            $label = null;
            $vehicle = null;
        }

        return $label === null ? [] : ['topic' => 'part', 'message' => "Hello, I'm interested in this part: " . $label . ".\nMy vehicle (make, model, year): " . ($vehicle ?? '')];
    }

    /**
     * "Looking for a part that is not listed?" (/parts/): ?request=other pre-selects "Car parts" with a message to fill in.
     * Anything else (including the old catalogue keys of spec 26) gives an empty form.
     *
     * @return array<string,string>
     */
    private function requestPrefill(): array
    {
        return ($this->query['request'] ?? null) === 'other'
            ? ['topic' => 'part', 'message' => "Hello, I'm looking for a car part.\nPart: \nMy vehicle (make, model, year): "]
            : [];
    }

    /** Name and email of a signed-in customer, to pre-fill the vehicle forms. Empty when not signed in (no session is created). */
    private function customerPrefill(): array
    {
        try {
            $id = (new CustomerSessionService())->currentCustomerId();
            $customer = $id === null ? null : (new CustomerRepository(Database::connection()))->findById($id);
        } catch (Throwable $e) {
            error_log('Customer prefill unavailable: ' . get_class($e));
            $customer = null;
        }

        return is_array($customer) ? ['name' => (string) ($customer['name'] ?? ''), 'email' => (string) ($customer['email'] ?? '')] : [];
    }

    /** The signed-in customer's account id from the server-side session (never from the request), or null. No session is created. */
    private function signedInCustomerId(): ?int
    {
        try {
            return (new CustomerSessionService())->currentCustomerId();
        } catch (Throwable $e) {
            error_log('Customer session unavailable: ' . get_class($e));

            return null;
        }
    }

    /**
     * "Ask a question" on the vehicle page: a WhatsApp chat with the company's GHANA number (CompanyContactService::ghanaWhatsapp)
     * and a pre-filled question naming the vehicle, its price and its page. Null when no Ghana number is configured.
     */
    private function vehicleWhatsapp(array $vehicle): ?string
    {
        try {
            $digits = CompanyContactService::create()->ghanaWhatsapp();
        } catch (Throwable $e) {
            error_log('WhatsApp number unavailable: ' . get_class($e));
            $digits = null;
        }
        if ($digits === null) {
            return null;
        }
        $link = AppConfig::linkUrl() . $vehicle['url'];

        return CompanyContactService::whatsappLink($digits, 'Hello ' . PUBLIC_SITE_NAME . ', I have a question about the ' . $vehicle['title'] . ' (' . $vehicle['price'] . ')'
            . ($link !== $vehicle['url'] ? ': ' . $link : '.') . "

My question: ");
    }

    /**
     * Google sign-in start and callback (spec 13): no page of their own, only a redirect. The start sends the
     * browser to Google (the one external address this site redirects to, built by GoogleOAuthService from fixed
     * parts); the callback always ends at a fixed local path chosen by the controller.
     */
    private function googleRedirect(bool $callback): void
    {
        $ip = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) !== false ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
        $userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
        $controller = new CustomerAuthController();
        $to = $callback ? $controller->googleCallback($this->query, $ip, $userAgent) : $controller->googleStart($ip, $userAgent);

        header('Location: ' . $to, true, 303);
        header('Cache-Control: no-store');
        header('Referrer-Policy: no-referrer');   // the authorization code in the callback address must not leak onward
    }

    /**
     * Customer sign-in, registration and account pages (spec 13). Fills $data for the view and returns the HTTP
     * status to render with, or null after sending a redirect (303: a POST is always answered with Post/Redirect/Get).
     *
     * @param array<string,mixed> $page
     * @param array<string,mixed> $data
     */
    private function customerPage(string $view, array &$page, array &$data): ?int
    {
        $ip = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) !== false ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
        $userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
        $controller = new CustomerAuthController();

        $result = match ($view) {
            'login'    => $controller->login($this->method, $_POST, $this->query, $ip, $userAgent),
            'register' => $controller->register($this->method, $_POST, $ip, $userAgent),
            'account-settings' => $controller->settings($this->method, $_POST, $this->query, $ip, $userAgent),
            'forgot-password'  => $controller->forgot($this->method, $_POST, $ip, $userAgent),
            'reset-password'   => $controller->resetPassword($this->method, $_POST, $this->query, $ip, $userAgent),
            default    => $controller->account($this->method, $_POST, $ip, $userAgent),
        };

        if ($result['redirect'] !== null) {
            header('Location: ' . $result['redirect'], true, 303);   // a fixed internal path chosen by the controller, never user input
            header('Cache-Control: no-store');

            return null;
        }

        $data += $result;
        if (!empty($result['toast'])) {
            $page['toast'] = $result['toast'];   // rate-limit and confirmation messages (toast with a progress bar)
        }
        if ($view === 'account-settings' && $result['customer'] !== null) {
            $page['signed_in'] = true;
        }
        if ($view === 'account' && $result['customer'] !== null) {
            $page['signed_in'] = true;
            $page['title'] = 'My Account';
            $data['verificationNotice'] = [
                'sent' => ['success', 'We sent a new confirmation link. Please check your inbox (and spam folder).'],
                'throttled' => ['warning', 'Several links were sent in the last hour. Please use the latest one, or try again later.'],
                'failed' => ['error', 'The email could not be sent right now. Please try again shortly.'],
                'unavailable' => ['error', 'Confirmation emails are not available right now. Please contact us.'],
                'already' => ['success', 'Your email address is already confirmed.'],
            ][is_string($this->query['verification'] ?? null) ? $this->query['verification'] : ''] ?? null;
        }

        return (int) $result['http'];
    }

    /**
     * What the hero search form offers. If the database cannot be read the homepage must still work:
     * the error is logged and null is returned, and the hero is then shown without the search panel.
     *
     * @return array<string,mixed>|null
     */
    private function searchOptions(): ?array
    {
        try {
            return PublicVehicleService::create()->searchOptions();
        } catch (Throwable $e) {
            error_log('Homepage search unavailable: ' . get_class($e) . ': ' . $e->getMessage());

            return null;
        }
    }

    /**
     * The four newest public vehicles for the homepage Latest Vehicles section (spec 10B). Null when the database
     * cannot be read: the section then says so briefly and still links to the Vehicles page.
     *
     * @return list<array<string,mixed>>|null
     */
    private function latestVehicles(): ?array
    {
        try {
            return PublicVehicleService::create()->latest(4);
        } catch (Throwable $e) {
            error_log('Homepage latest vehicles unavailable: ' . get_class($e) . ': ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Latest Parts & Accessories for the homepage (spec 16), narrowed by ?parts_type= or ?parts_make= when the visitor chose
     * one without JavaScript. Null when the database cannot be read: the section then says so and the homepage still works.
     *
     * @return array<string,mixed>|null
     */
    private function latestParts(): ?array
    {
        try {
            return PublicPartService::create()->homepage($this->query['parts_type'] ?? null, $this->query['parts_make'] ?? null);
        } catch (Throwable $e) {
            error_log('Homepage parts unavailable: ' . get_class($e) . ': ' . $e->getMessage());

            return null;
        }
    }

    /**
     * sitemap.xml (SEO 2026-10-27): GET and HEAD only. When the database cannot be read the answer is 503 (try again
     * later), never a short sitemap that would tell search engines the vehicles are gone.
     */
    private function sitemap(): void
    {
        AppConfig::sendCommonSecurityHeaders();
        header('X-Content-Type-Options: nosniff');
        if (!in_array($this->method, ['GET', 'HEAD'], true)) {
            header('Allow: GET, HEAD');
            http_response_code(405);

            return;
        }
        try {
            require_once dirname(__DIR__) . '/services/SitemapService.php';
            $xml = SitemapService::create()->xml();
        } catch (Throwable $e) {
            error_log('Sitemap unavailable: ' . get_class($e) . ': ' . $e->getMessage());
            header('Retry-After: 3600');
            http_response_code(503);

            return;
        }
        header('Content-Type: application/xml; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        header('X-Robots-Tag: noindex');   // the file itself is not a search result; its addresses are
        if ($this->method === 'GET') {
            echo $xml;
        }
    }

    /** The company's contact details from Settings (structured data), or null when they cannot be read (logged). */
    private function contactDetails(): ?array
    {
        try {
            return CompanyContactService::create()->details();
        } catch (Throwable $e) {
            error_log('Contact details unavailable: ' . get_class($e));

            return null;
        }
    }

    /** The WhatsApp number (international digits) for wa.me links, or null when none is configured or it cannot be read. */
    private function whatsappDigits(): ?string
    {
        try {
            return CompanyContactService::create()->whatsapp();
        } catch (Throwable $e) {
            error_log('WhatsApp number unavailable: ' . get_class($e));

            return null;
        }
    }

    /**
     * The signed-in customer's own shipments for Track Vehicle (spec 20, D5), by account id only. Null when they cannot
     * be read: the page then shows its general explanation and says shipment details are unavailable right now.
     *
     * @return list<array<string,mixed>>|null
     */
    private function customerShipments(int $customerId): ?array
    {
        try {
            require_once dirname(__DIR__) . '/services/ShipmentService.php';

            return ShipmentService::create()->forCustomer($customerId);
        } catch (Throwable $e) {
            error_log('Customer shipments unavailable: ' . get_class($e));

            return null;
        }
    }

    /** The approved official tracking provider link (settings table, validated, with the approved address as the fallback). */
    private function trackingProviderUrl(): string
    {
        try {
            return ExternalLinksService::create()->trackingProviderUrl();
        } catch (Throwable $e) {
            error_log('Tracking provider link: ' . get_class($e));

            return ExternalLinksService::TRACKING_PROVIDER_DEFAULT;
        }
    }

    /** The approved duty calculator link (settings table, validated, with the approved address as the fallback). */
    private function dutyCalculatorUrl(): string
    {
        try {
            return ExternalLinksService::create()->dutyCalculatorUrl();
        } catch (Throwable $e) {
            error_log('Duty calculator link: ' . get_class($e));

            return ExternalLinksService::DUTY_CALCULATOR_DEFAULT;
        }
    }

    /**
     * The brands for the Browse by Brand strip. Null when the database cannot be read (the section then shows
     * its "unavailable" message instead of breaking the homepage).
     *
     * @return list<array{name:string,slug:string,url:string,logo:?string}>|null
     */
    private function browseBrands(): ?array
    {
        try {
            return PublicBrandService::create()->forBrowse();
        } catch (Throwable $e) {
            error_log('Homepage brands unavailable: ' . get_class($e) . ': ' . $e->getMessage());

            return null;
        }
    }

    /**
     * The company phone numbers from the settings table (main number first), for the header and footer (click-to-call).
     * Empty when none is stored or the database cannot be read: the page then simply shows no number.
     *
     * @return list<array{display:string,tel:string}>
     */
    private function companyPhones(): array
    {
        try {
            return CompanyContactService::create()->phones();
        } catch (Throwable $e) {
            error_log('Company phone unavailable: ' . get_class($e));

            return [];
        }
    }

    private function notFound(): void
    {
        $this->render(404, 'not-found', ['title' => 'Page not found', 'path' => '', 'robots' => 'noindex,follow', 'nav' => ''], []);
    }

    private function sendError(Throwable $cause): void
    {
        try {
            if (!headers_sent()) {
                // Discard any half-built page before showing the error page.
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
            }
            $this->render(500, 'error', ['title' => 'Something went wrong', 'path' => '', 'robots' => 'noindex,nofollow', 'nav' => ''],
                ['detail' => development_error_detail($cause)]);
        } catch (Throwable $again) {
            // Even the error page failed: the plainest possible response, still no internals.
            error_log('Public error page failed: ' . get_class($again));
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: text/plain; charset=UTF-8');
            }
            echo "Something went wrong. Please try again later.\n";
        }
    }

    /**
     * @param array<string,mixed> $page metadata and navigation state for the layout
     * @param array<string,mixed> $data data for the view
     */
    private function render(int $status, string $view, array $page, array $data): void
    {
        $file = dirname(__DIR__, 2) . '/public/views/' . $view . '.php';
        if (preg_match('/^[a-z-]+$/', $view) !== 1 || !is_file($file)) {
            throw new RuntimeException('Unknown public view.');
        }
        $page['phones'] = $this->companyPhones();   // footer: every number; header: the main one
        $page['phone'] = $page['phones'][0] ?? null;
        $page['dutyUrl'] = $this->dutyCalculatorUrl();   // for the desktop utility bar (validated setting, approved fallback)
        // "Account" instead of "Login" only for a live customer session (no database read; /account/ re-checks the customer).
        // An expired, empty or forged session cookie is destroyed here, so a signed-out visitor never sees "Account".
        $page['signed_in'] = ($page['signed_in'] ?? false) || $this->hasCustomerSession();
        $page['consent'] = CookieConsentService::current();
        $page['team'] = PublicTeamService::hasVisibleTeam();   // footer "Our Team" link only when the About page shows members
        $page['accident'] = AccidentVehicleSettings::enabled();   // spec 23: the menu links to the Accident Vehicles page only while it is on
        $page['favourites'] = $page['signed_in'] ? $this->favouriteState() : null;
        $page['customerCsrf'] = $page['favourites']['csrf'] ?? '';   // the header's Sign out form (2026-10-22)
        $page['toast'] = $page['toast'] ?? ($page['signed_in'] ? $this->welcomeToast() : null);
        $page['return'] = CookieConsentService::safeReturn((string) ($_SERVER['REQUEST_URI'] ?? '/'));
        ob_start();                       // build the whole page first, so a failure half way never sends half a page
        $router = $this->router;
        require dirname(__DIR__, 2) . '/includes/header.php';
        require $file;
        require dirname(__DIR__, 2) . '/includes/footer.php';
        $html = (string) ob_get_clean();

        http_response_code($status);
        public_send_headers();
        // Sign-in pages, anything personal, and every page when the visitor has not allowed "browsing activity" (spec 18,
        // also the default) are never kept by the browser or a proxy. Allowed: kept by this browser only, always re-checked.
        if (!empty($page['private']) || $page['signed_in'] || !$page['consent']['activity']) {
            header('Cache-Control: no-store');
        } else {
            header('Cache-Control: private, no-cache');
        }
        header('Vary: Cookie');
        echo $html;
    }

    /** @return array{slugs:list<string>,csrf:string}|null the signed-in customer's favourite slugs and CSRF token, for the Save buttons */
    private function favouriteState(): ?array
    {
        try {
            $sessions = new CustomerSessionService();
            $id = $sessions->currentCustomerId();

            return $id === null ? null : ['slugs' => CustomerDashboardService::create()->favouriteSlugs($id), 'csrf' => $sessions->csrfToken()];
        } catch (Throwable $e) {
            error_log('Favourites unavailable: ' . get_class($e));

            return null;
        }
    }

    /**
     * "Welcome back, Ama" once, on the first page after signing in ("Welcome, Ama" for a new account); owner request
     * 2026-10-22. The flag is set by CustomerSessionService::establish and removed when read; the name is read only then.
     */
    private function welcomeToast(): ?array
    {
        try {
            $sessions = new CustomerSessionService();
            $welcome = $sessions->consumeWelcome();
            $id = $welcome === null ? null : $sessions->currentCustomerId();
            $customer = $id === null ? null : (new CustomerRepository(Database::connection()))->findById($id);
            if ($customer === null) {
                return null;
            }
            $first = trim((string) $customer['name']);

            return ['type' => 'success', 'text' => ($welcome === 'new' ? 'Welcome, ' : 'Welcome back, ') . ($first !== '' ? $first : 'there') . '!'];
        } catch (Throwable $e) {
            error_log('Welcome message unavailable: ' . get_class($e));

            return null;
        }
    }

    /** True when this request carries a live customer session. A dead one is destroyed (cookie expired); no session is ever created. */
    private function hasCustomerSession(): bool
    {
        if (!isset($_COOKIE[CustomerSessionService::COOKIE_NAME])) {
            return false;
        }
        $session = new CustomerSessionService();
        if ($session->currentCustomerId() !== null) {
            return true;
        }
        // Nothing worth keeping is destroyed. A sign-in in progress keeps its session: a remembered page, a Google sign-in, a
        // form security token (fixed 2026-10-22: a visitor who opened a second sign-in page lost the token and got
        // "couldn't verify your request") or a password reset in progress.
        if (session_status() === PHP_SESSION_ACTIVE && !isset($_SESSION['return_to']) && !isset($_SESSION['google'])
            && !isset($_SESSION['csrf_token']) && !isset($_SESSION['reset_email'])) {
            $session->destroy();
        }

        return false;
    }
}
