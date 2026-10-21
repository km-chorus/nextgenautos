<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';

/**
 * Google sign-in for customers (spec 13_spec_customer_auth.md): OAuth 2.0 authorization-code flow.
 * The only place that talks to Google. No library: one redirect URL and one server-to-server HTTPS call.
 *
 * Security notes:
 *   - `state` (random, one use, held in the customer session) ties the callback to the browser that started it;
 *   - `nonce` (random, one use) is checked inside the ID token, so a token cannot be replayed into another login;
 *   - the ID token is received DIRECTLY from Google's token endpoint over verified TLS in exchange for the one-time
 *     code and the client secret, so its signature need not be re-verified (Google documents this); issuer,
 *     audience, expiry, nonce and verified email are still checked here;
 *   - the client secret stays on the server; nothing Google-related is exposed to the browser except the public client id.
 */
class GoogleOAuthService
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    /** @return string the Google address to send the browser to */
    public function authorizationUrl(string $state, string $nonce): string
    {
        return self::AUTH_URL . '?' . http_build_query([
            'client_id'     => AppConfig::googleClientId(),
            'redirect_uri'  => (string) AppConfig::googleRedirectUri(),
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'state'         => $state,
            'nonce'         => $nonce,
            'prompt'        => 'select_account',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Exchanges the one-time code for the signed-in Google identity.
     *
     * @return array{sub:string,email:string,name:string}|null null for ANY problem (the reason is logged, never shown)
     */
    public function identityFromCode(string $code, string $expectedNonce): ?array
    {
        $body = http_build_query([
            'code'          => $code,
            'client_id'     => AppConfig::googleClientId(),
            'client_secret' => AppConfig::googleClientSecret(),
            'redirect_uri'  => (string) AppConfig::googleRedirectUri(),
            'grant_type'    => 'authorization_code',
        ]);

        $curl = curl_init(self::TOKEN_URL);
        curl_setopt_array($curl, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);
        $response = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_errno($curl);
        curl_close($curl);

        if ($response === false || $status !== 200) {
            error_log('Google sign-in: token request failed (curl error ' . $error . ', HTTP ' . $status . ')');

            return null;
        }

        $json = json_decode((string) $response, true);
        $idToken = is_array($json) ? ($json['id_token'] ?? null) : null;

        return is_string($idToken) ? self::claimsFrom($idToken, $expectedNonce) : null;
    }

    /**
     * Reads and checks the claims of an ID token (public so the checks can be tested without the network).
     *
     * @return array{sub:string,email:string,name:string}|null
     */
    public static function claimsFrom(string $idToken, string $expectedNonce, ?int $now = null): ?array
    {
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            return null;
        }
        $payload = base64_decode(strtr($parts[1], '-_', '+/'), false);
        $claims = $payload === false ? null : json_decode($payload, true);
        if (!is_array($claims)) {
            return null;
        }

        $valid = in_array($claims['iss'] ?? null, ['https://accounts.google.com', 'accounts.google.com'], true)
            && ($claims['aud'] ?? null) === AppConfig::googleClientId()
            && is_int($claims['exp'] ?? null) && $claims['exp'] > ($now ?? time())
            && is_string($claims['nonce'] ?? null) && $expectedNonce !== '' && hash_equals($expectedNonce, $claims['nonce'])
            && is_string($claims['sub'] ?? null) && preg_match('/^[0-9]{1,64}$/', $claims['sub']) === 1
            && is_string($claims['email'] ?? null) && filter_var($claims['email'], FILTER_VALIDATE_EMAIL) !== false
            && strlen($claims['email']) <= 255
            && ($claims['email_verified'] ?? false) === true;
        if (!$valid) {
            error_log('Google sign-in: the ID token failed validation');

            return null;
        }

        $name = is_string($claims['name'] ?? null) ? trim(preg_replace('/[\p{Cc}<>]/u', '', $claims['name']) ?? '') : '';
        if ($name === '') {
            $name = (string) strstr($claims['email'], '@', true);
        }

        return ['sub' => $claims['sub'], 'email' => strtolower($claims['email']), 'name' => mb_substr($name, 0, 100)];
    }
}
