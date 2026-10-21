<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/services/PublicRouter.php';
require_once dirname(__DIR__) . '/services/HomepageHeroService.php';
require_once dirname(__DIR__) . '/services/PublicVehicleService.php';
require_once dirname(__DIR__) . '/services/CompanyContactService.php';
require_once dirname(__DIR__) . '/services/PublicBrandService.php';
require_once dirname(__DIR__) . '/services/ExternalLinksService.php';
require_once dirname(__DIR__) . '/services/PublicTeamService.php';
require_once dirname(__DIR__) . '/services/PublicPartService.php';
require_once dirname(__DIR__) . '/services/PublicEnquiryService.php';
require_once dirname(__DIR__) . '/services/PublicFormToken.php';
require_once dirname(__DIR__) . '/services/CookieConsentService.php';
require_once dirname(__DIR__) . '/services/AssistantService.php';
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
            $access = (new CustomerAuthController())->requireCustomer('/track/');   // an exception here shows the generic error page: fails closed
            if ($access['redirect'] !== null) {
                header('Location: ' . $access['redirect'], true, 303);
                header('Cache-Control: no-store');

                return;
            }
            $data['customer'] = $access['customer'];
            $data['trackingUrl'] = $this->trackingProviderUrl();
            $page['signed_in'] = true;
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
        if ($view === 'cookies') {
            $this->cookiesPage($page, $data);

            return;
        }
        if ($view === 'google-start' || $view === 'google-callback') {
            $this->googleRedirect($view === 'google-callback');

            return;
        }
        if (in_array($view, ['login', 'register', 'account'], true)) {
            $status = $this->customerPage($view, $page, $data);
            if ($status !== null) {   // null = a redirect was already sent
                $this->render($status, $view, $page, $data);
            }

            return;
        }
        if ($view === 'home') {
            $data['hero'] = HomepageHeroService::create()->slides(0);   // slide 1 (the strongest image) always opens: predictable for visitors and search engines
            $data['searchOptions'] = $this->searchOptions();
            $data['latest'] = $this->latestVehicles();
            $data['brands'] = $this->browseBrands();
            $data['dutyUrl'] = $this->dutyCalculatorUrl();
            $data['team'] = $this->homeTeam();
            $data['parts'] = $this->latestParts();
            $data['whatsapp'] = $this->whatsappDigits();
            if ($data['parts'] !== null && ($data['parts']['type'] !== null || $data['parts']['make'] !== null)) {
                $page['robots'] = 'noindex,follow';   // a filtered homepage is not a page of its own for search engines
            }
            $page['preloads'] = $data['hero'] === [] ? [] : public_hero_preloads($data['hero'][0]);
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
        $url = $vehicle['url'];   // built from the validated, stored slug
        $form = ['kind' => null, 'values' => [], 'errors' => [], 'message' => null];
        $status = 200;

        if ($this->method === 'POST') {
            $kind = in_array($_POST['kind'] ?? null, EnquiryValidator::KINDS, true) ? (string) $_POST['kind'] : 'contact';
            $ip = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) !== false ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
            $userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
            try {
                $result = PublicEnquiryService::create()->submit($detail['id'], $vehicle['reserved'], $kind, $_POST, $ip, $userAgent);
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

        $page['title'] = $vehicle['title'];
        $page['path'] = $url;
        $page['type'] = 'product';
        $page['description'] = $vehicle['title'] . ' for ' . $vehicle['price'] . '. ' . $vehicle['status']
            . ' through ' . PUBLIC_SITE_NAME . ': photos, specifications, and contact, WhatsApp or reservation.';
        $page['image'] = $vehicle['photos'][0]['large']['url'] ?? null;
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
        $form = ['values' => [], 'errors' => [], 'message' => null];
        $status = 200;

        if ($this->method === 'POST') {
            $ip = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) !== false ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
            $userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
            try {
                $result = PublicEnquiryService::create()->submit(null, false, 'general', $_POST, $ip, $userAgent);
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
        $data['prefill'] = $this->method === 'POST' ? [] : $this->customerPrefill() + $this->partPrefill();
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
            $label = PublicPartService::create()->enquiryLabel($this->query['part']);
        } catch (Throwable $e) {
            error_log('Part prefill unavailable: ' . get_class($e));
            $label = null;
        }

        return $label === null ? [] : ['topic' => 'part', 'message' => "Hello, I'm interested in this part: " . $label . '. '];
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

    /** The WhatsApp link for this vehicle with a pre-filled message, or null when no valid number is configured. */
    private function vehicleWhatsapp(array $vehicle): ?string
    {
        try {
            $digits = CompanyContactService::create()->whatsapp();
        } catch (Throwable $e) {
            error_log('WhatsApp number unavailable: ' . get_class($e));
            $digits = null;
        }
        if ($digits === null) {
            return null;
        }
        $link = (AppConfig::appUrl() ?? '') . $vehicle['url'];

        return CompanyContactService::whatsappLink($digits, "Hello, I'm interested in the " . $vehicle['title'] . ' (' . $vehicle['price'] . '): ' . $link);
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
            default    => $controller->account($this->method, $_POST, $ip, $userAgent),
        };

        if ($result['redirect'] !== null) {
            header('Location: ' . $result['redirect'], true, 303);   // a fixed internal path chosen by the controller, never user input
            header('Cache-Control: no-store');

            return null;
        }

        $data += $result;
        if ($view === 'account' && $result['customer'] !== null) {
            $page['signed_in'] = true;
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
     * The first active team members for the About + Team section. Null when the database cannot be read: the team
     * block is then simply left out (the company introduction still shows).
     *
     * @return list<array<string,mixed>>|null
     */
    private function homeTeam(): ?array
    {
        try {
            return PublicTeamService::create()->forHomepage();
        } catch (Throwable $e) {
            error_log('Homepage team unavailable: ' . get_class($e) . ': ' . $e->getMessage());

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
        if (session_status() === PHP_SESSION_ACTIVE && !isset($_SESSION['return_to']) && !isset($_SESSION['google'])) {
            $session->destroy();   // nothing worth keeping (a sign-in in progress keeps its session)
        }

        return false;
    }
}
