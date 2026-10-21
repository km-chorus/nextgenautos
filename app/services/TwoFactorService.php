<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/repositories/AdminUserRepository.php';
require_once dirname(__DIR__) . '/repositories/RecoveryCodeRepository.php';
require_once __DIR__ . '/SecretBox.php';
require_once __DIR__ . '/Transaction.php';

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use OTPHP\TOTP;
use ParagonIE\ConstantTime\Base32;

/**
 * TOTP two-factor authentication (RFC 6238) and one-time recovery codes.
 *
 * The one-time-password maths comes from the vetted spomky-labs/otphp library
 * (verified against the RFC 6238 test vectors); QR codes are rendered locally by
 * bacon/bacon-qr-code, so the secret never leaves this server. This class only
 * adds: secret generation, a small clock-drift window, replay protection (a time
 * step works once), encrypted storage, and recovery codes (random, stored only as
 * keyed hashes, each usable once). It never logs codes or secrets.
 */
final class TwoFactorService
{
    public const PERIOD = 30;
    /** Accepted clock drift: one 30-second step either side of "now". */
    public const DRIFT_STEPS = 1;

    private const RECOVERY_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // 32 symbols, no 0/O/1/I
    private const RECOVERY_LENGTH = 10;

    public function __construct(
        private PDO $pdo,
        private AdminUserRepository $users,
        private RecoveryCodeRepository $recovery
    ) {
    }

    /** True when 2FA can be used here (encryption key configured). */
    public static function isAvailable(): bool
    {
        return SecretBox::isAvailable();
    }

    /** 160 random bits (the RFC 6238 recommendation for SHA-1), as 32 base32 characters. */
    public static function newSecret(): string
    {
        return rtrim(Base32::encodeUpper(random_bytes(20)), '=');
    }

    public static function provisioningUri(string $secret, string $accountLabel): string
    {
        $totp = TOTP::createFromSecret($secret);
        $totp->setLabel($accountLabel);
        $totp->setIssuer(AppConfig::TWO_FACTOR_ISSUER);

        return $totp->getProvisioningUri();
    }

    /** Inline-safe SVG (no scripts, no external references), generated locally. */
    public static function qrSvg(string $provisioningUri): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(224, 2), new SvgImageBackEnd()));
        $svg = $writer->writeString($provisioningUri);

        return trim((string) preg_replace('/^<\?xml[^>]*\?>\s*/', '', $svg));
    }

    /** Six digits, or '' when the input is not a plausible authenticator code. */
    public static function normalizeCode(string $input): string
    {
        $digits = preg_replace('/[\s-]+/', '', $input) ?? '';

        return preg_match('/^[0-9]{6}$/', $digits) === 1 ? $digits : '';
    }

    /** The time-step a code belongs to (within the drift window), or null if it matches none. */
    public static function matchStep(string $secret, string $code, ?int $now = null): ?int
    {
        $code = self::normalizeCode($code);
        if ($code === '') {
            return null;
        }

        $now ??= time();
        $currentStep = intdiv($now, self::PERIOD);
        $totp = TOTP::createFromSecret($secret);

        $matched = null;
        for ($offset = -self::DRIFT_STEPS; $offset <= self::DRIFT_STEPS; $offset++) {
            $step = $currentStep + $offset;
            // No early exit and constant-time comparison: timing does not reveal how close a guess was.
            if (hash_equals($totp->at($step * self::PERIOD), $code)) {
                $matched = $step;
            }
        }

        return $matched;
    }

    /**
     * Verifies a login code for an admin row (from findFullById) and burns its time-step.
     * A decryption failure throws (it is an infrastructure/configuration problem, not a wrong code).
     */
    public function verifyLoginCode(array $adminRow, string $code): bool
    {
        if ((int) $adminRow['two_factor_enabled'] !== 1 || $adminRow['two_factor_secret_encrypted'] === null) {
            return false;
        }

        $step = self::matchStep(SecretBox::decrypt((string) $adminRow['two_factor_secret_encrypted']), $code);

        return $step !== null && $this->users->acceptTotpStep((int) $adminRow['id'], $step);
    }

    /** Consumes a one-time recovery code. True exactly once per code. */
    public function consumeRecoveryCode(int $adminId, string $input): bool
    {
        $normalized = self::normalizeRecoveryCode($input);

        return $normalized !== '' && $this->recovery->consume($adminId, self::hashRecoveryCode($normalized));
    }

    public function remainingRecoveryCodes(int $adminId): int
    {
        return $this->recovery->countUnused($adminId);
    }

    /**
     * Confirms an enrollment: only a valid code from the authenticator turns 2FA on.
     * Returns the recovery codes (shown to the administrator once) or null for a wrong code.
     *
     * @return list<string>|null
     */
    public function confirmEnrollment(int $adminId, string $secret, string $code): ?array
    {
        $step = self::matchStep($secret, $code);
        if ($step === null) {
            return null;
        }

        return Transaction::run($this->pdo, function () use ($adminId, $secret, $step): array {
            $this->users->enableTwoFactor($adminId, SecretBox::encrypt($secret), $step);

            return $this->storeNewRecoveryCodes($adminId);
        });
    }

    /**
     * New recovery codes; every previous code stops working.
     *
     * @return list<string>
     */
    public function regenerateRecoveryCodes(int $adminId): array
    {
        return Transaction::run($this->pdo, fn (): array => $this->storeNewRecoveryCodes($adminId));
    }

    public function disable(int $adminId): void
    {
        Transaction::run($this->pdo, function () use ($adminId): void {
            $this->users->disableTwoFactor($adminId);
            $this->recovery->deleteAll($adminId);
        });
    }

    public static function normalizeRecoveryCode(string $input): string
    {
        $clean = strtoupper((string) preg_replace('/[\s-]+/', '', $input));
        $pattern = '/^[' . self::RECOVERY_ALPHABET . ']{' . self::RECOVERY_LENGTH . '}$/';

        return preg_match($pattern, $clean) === 1 ? $clean : '';
    }

    /** Keyed hash (HMAC-SHA256 with a key derived from APP_KEY): useless without the application key. */
    public static function hashRecoveryCode(string $normalizedCode): string
    {
        return hash_hmac('sha256', $normalizedCode, SecretBox::derive('recovery-codes'));
    }

    /** @return list<string> */
    private function storeNewRecoveryCodes(int $adminId): array
    {
        $codes = [];
        while (count($codes) < AppConfig::RECOVERY_CODE_COUNT) {
            $raw = '';
            for ($i = 0; $i < self::RECOVERY_LENGTH; $i++) {
                $raw .= self::RECOVERY_ALPHABET[random_int(0, 31)];
            }
            $codes[$raw] = substr($raw, 0, 5) . '-' . substr($raw, 5); // keyed by value: duplicates are impossible
        }

        $this->recovery->replaceAll(
            $adminId,
            array_map(static fn (string $raw): string => self::hashRecoveryCode($raw), array_keys($codes))
        );

        return array_values($codes);
    }
}
