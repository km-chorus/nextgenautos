<?php

declare(strict_types=1);

/**
 * Minimal router for the public website (spec 09_spec_webpage_ui.md). Pure: it takes an HTTP
 * method and a request URI and says what to do. It reads nothing from the database and
 * touches no global state, so it is easy to test.
 *
 * Rules (the URL is untrusted input):
 *   - only GET and HEAD are served (anything else: "method not allowed"), EXCEPT a route that lists POST in its
 *     'methods' (the customer sign-in, registration and account pages, spec 13): POST is accepted there, on the
 *     exact canonical path only, and nowhere else;
 *   - the query string is ignored; the path must be at most 200 characters of letters, digits,
 *     hyphens and slashes, starting with a single slash (no "..", no "//", no backslash, no
 *     percent-encoding, no dots: anything else is "not found");
 *   - URLs are lowercase and end in a slash. A request that is only wrong in case or the missing
 *     trailing slash is redirected (301) to the canonical form, but only when the canonical form
 *     is a real route: a redirect is never built for an unknown page, and the target is always
 *     rebuilt from the validated characters, so it can never point to another site;
 *   - an exact route wins; then dynamic patterns (one validated slug).
 *
 * Results (array, key "type"):
 *   route              + 'route' => the route array, 'slug' => ?string
 *   redirect           + 'to' => canonical path
 *   not_found
 *   method_not_allowed
 */
final class PublicRouter
{
    public const MAX_PATH_LENGTH = 200;

    /** @param array{routes:list<array<string,mixed>>,patterns:list<array<string,mixed>>,development:list<array<string,mixed>>} $config */
    public function __construct(private array $config, private bool $development)
    {
    }

    /** Router built from app/config/public_routes.php; development routes only in development. */
    public static function create(): self
    {
        require_once __DIR__ . '/../config/app.php';

        /** @var array{routes:list<array<string,mixed>>,patterns:list<array<string,mixed>>,development:list<array<string,mixed>>} $config */
        $config = require __DIR__ . '/../config/public_routes.php';

        return new self($config, AppConfig::environment() === 'development');
    }

    /** @return list<array<string,mixed>> every static route that exists in this environment */
    public function routes(): array
    {
        return $this->development ? array_merge($this->config['routes'], $this->config['development']) : $this->config['routes'];
    }

    /** @return array<string,mixed>|null */
    public function byName(string $name): ?array
    {
        foreach ($this->routes() as $route) {
            if ($route['name'] === $name) {
                return $route;
            }
        }

        return null;
    }

    /** @return array<string,mixed> */
    public function resolve(string $method, string $requestUri): array
    {
        $method = strtoupper($method);
        if (!in_array($method, ['GET', 'HEAD'], true) && !$this->routeAllows($method, $requestUri)) {
            return ['type' => 'method_not_allowed'];
        }

        // "//host/path" would be read by parse_url() as a network-path reference (host "host", path "/").
        if (str_starts_with($requestUri, '//')) {
            return ['type' => 'not_found'];
        }

        $path = parse_url($requestUri, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = '/';
        }
        if (strlen($path) > self::MAX_PATH_LENGTH || preg_match('#^/[A-Za-z0-9/-]*$#', $path) !== 1 || str_contains($path, '//')) {
            return ['type' => 'not_found'];
        }

        $canonical = strtolower($path);
        if (!str_ends_with($canonical, '/')) {
            $canonical .= '/';
        }

        $match = $this->match($canonical);
        if ($match === null) {
            return ['type' => 'not_found'];
        }
        if ($canonical !== $path) {
            return ['type' => 'redirect', 'to' => $canonical];
        }

        return ['type' => 'route', 'route' => $match['route'], 'slug' => $match['slug']];
    }

    /** True when the request path is exactly a canonical route that lists this (non-GET) method in its 'methods'. */
    private function routeAllows(string $method, string $requestUri): bool
    {
        if (str_starts_with($requestUri, '//')) {
            return false;
        }
        $path = parse_url($requestUri, PHP_URL_PATH);
        $match = is_string($path) ? $this->match($path) : null;

        return $match !== null && in_array($method, $match['route']['methods'] ?? [], true);
    }

    /** @return array{route:array<string,mixed>,slug:?string}|null */
    private function match(string $canonicalPath): ?array
    {
        foreach ($this->routes() as $route) {
            if ($route['path'] === $canonicalPath) {
                return ['route' => $route, 'slug' => null];
            }
        }
        foreach ($this->config['patterns'] as $pattern) {
            if (preg_match($pattern['regex'], $canonicalPath, $m) === 1 && strlen($m[1]) <= (int) $pattern['max']) {
                return ['route' => $pattern, 'slug' => $m[1]];
            }
        }

        return null;
    }
}
