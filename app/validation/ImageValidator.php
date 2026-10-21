<?php

declare(strict_types=1);

/**
 * Server-side validation of an uploaded image (spec 07_spec_vehicle_image.md, sections 6 and 7).
 *
 * Nothing the browser says is trusted: not the file name, not the extension, not
 * $_FILES['type'], not any client-side compression. The decision is made from the
 * file's actual content:
 *
 *   1. PHP's own upload error code
 *   2. size (1 byte to 10 MB)
 *   3. real MIME type from finfo (content sniffing), only JPEG / PNG / WebP
 *   4. the image header must parse (getimagesize) and agree with that MIME type
 *   5. dimensions: at least 800 x 600 (either orientation) and at most 12000 per side
 *      and 20 megapixels in total. These are checked from the header BEFORE the image
 *      is decoded, so a small file that claims enormous dimensions (a decompression
 *      bomb) is refused without ever being expanded in memory
 *   6. animated WebP is refused (only still photographs are accepted)
 *
 * Pure inspection: nothing is written, moved or decoded here.
 */
final class ImageValidator
{
    public const MAX_BYTES = 10 * 1024 * 1024;
    public const MIN_LONG_SIDE = 800;
    public const MIN_SHORT_SIDE = 600;
    public const MAX_SIDE = 12000;
    public const MAX_PIXELS = 20000000;

    /** Real MIME type => the getimagesize() type constant it must agree with. */
    private const ALLOWED = [
        'image/jpeg' => IMAGETYPE_JPEG,
        'image/png'  => IMAGETYPE_PNG,
        'image/webp' => IMAGETYPE_WEBP,
    ];

    public const ERROR_FORMAT = 'This image format is not supported.';
    public const ERROR_SIZE = 'The image is too large. Maximum size is 10 MB.';
    public const ERROR_INVALID = 'The uploaded file is not a valid image.';
    public const ERROR_SMALL = 'The image is too small. It must be at least 800 x 600 pixels.';
    public const ERROR_DIMENSIONS = 'The image dimensions are too large.';
    public const ERROR_UPLOAD = 'The file could not be uploaded. Please try again.';
    public const ERROR_SERVER_LIMIT = 'The uploaded file is too large for the server configuration.';
    public const ERROR_EMPTY = 'The file is empty.';

    /**
     * Message for a PHP upload error code (UPLOAD_ERR_*), or null when the upload itself was fine.
     */
    public static function uploadErrorMessage(int $code, ?int $serverLimit = null): ?string
    {
        return match ($code) {
            UPLOAD_ERR_OK => null,
            // PHP's own limit was hit. If that limit is lower than the application's 10 MB the server configuration
            // is the problem and the message says so; otherwise the file really is over the application limit.
            UPLOAD_ERR_INI_SIZE => ($serverLimit !== null && $serverLimit > 0 && $serverLimit < self::MAX_BYTES) ? self::ERROR_SERVER_LIMIT : self::ERROR_SIZE,
            UPLOAD_ERR_FORM_SIZE => self::ERROR_SIZE,
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'The server could not store the upload. Please contact the site administrator.',
            UPLOAD_ERR_EXTENSION => 'The upload was blocked by a server extension.',
            UPLOAD_ERR_NO_FILE => 'No file was selected.',
            default => self::ERROR_UPLOAD,
        };
    }

    /**
     * Inspects one file on disk.
     *
     * @return array{ok:bool,error:?string,reason:?string,mime:?string,width:int,height:int,bytes:int}
     *         `reason` is a short machine-readable code for the audit log (never the file name).
     */
    public static function inspect(string $path, int $minLongSide = self::MIN_LONG_SIDE, int $minShortSide = self::MIN_SHORT_SIDE): array
    {
        $fail = static fn (string $reason, string $message, array $more = []): array => $more + [
            'ok' => false, 'error' => $message, 'reason' => $reason, 'mime' => null, 'width' => 0, 'height' => 0, 'bytes' => 0,
        ];

        if (!is_file($path) || !is_readable($path)) {
            return $fail('unreadable', self::ERROR_UPLOAD);
        }
        $bytes = (int) filesize($path);
        if ($bytes <= 0) {
            return $fail('empty', self::ERROR_EMPTY);
        }
        if ($bytes > self::MAX_BYTES) {
            return $fail('too_large', self::ERROR_SIZE, ['bytes' => $bytes]);
        }

        // Real type from the content, never from the name or the browser.
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo === false ? false : @finfo_file($finfo, $path);   // @: libmagic can warn on crafted files; a false result is handled below
        if (!is_string($mime) || !isset(self::ALLOWED[$mime])) {
            // Not an allowed type. A text/script/archive file is "not supported"; unreadable junk is "not valid".
            $known = is_string($mime) && $mime !== 'application/octet-stream' && $mime !== 'inode/x-empty';
            return $known
                ? $fail('format_' . preg_replace('/[^a-z0-9]+/', '_', $mime), self::ERROR_FORMAT, ['bytes' => $bytes])
                : $fail('not_an_image', self::ERROR_INVALID, ['bytes' => $bytes]);
        }

        // The header must parse and agree with the sniffed type. getimagesize reads only the header.
        $info = @getimagesize($path);
        if ($info === false || !isset($info[0], $info[1], $info[2]) || $info[2] !== self::ALLOWED[$mime]) {
            return $fail('corrupt', self::ERROR_INVALID, ['bytes' => $bytes, 'mime' => $mime]);
        }
        $width = (int) $info[0];
        $height = (int) $info[1];
        if ($width < 1 || $height < 1) {
            return $fail('corrupt', self::ERROR_INVALID, ['bytes' => $bytes, 'mime' => $mime]);
        }

        // Dimension limits, decided before any decoding.
        if ($width > self::MAX_SIDE || $height > self::MAX_SIDE || $width * $height > self::MAX_PIXELS) {
            return $fail('dimensions_too_large', self::ERROR_DIMENSIONS, ['bytes' => $bytes, 'mime' => $mime, 'width' => $width, 'height' => $height]);
        }
        if (max($width, $height) < $minLongSide || min($width, $height) < $minShortSide) {
            // The default (vehicles: 800 x 600) keeps its fixed wording; other callers get their own minimum in the message.
            $message = ($minLongSide === self::MIN_LONG_SIDE && $minShortSide === self::MIN_SHORT_SIDE)
                ? self::ERROR_SMALL
                : 'The image is too small. It must be at least ' . $minLongSide . ' x ' . $minShortSide . ' pixels.';

            return $fail('too_small', $message, ['bytes' => $bytes, 'mime' => $mime, 'width' => $width, 'height' => $height]);
        }

        if ($mime === 'image/webp' && self::isAnimatedWebp($path)) {
            return $fail('animated', self::ERROR_FORMAT, ['bytes' => $bytes, 'mime' => $mime, 'width' => $width, 'height' => $height]);
        }

        return ['ok' => true, 'error' => null, 'reason' => null, 'mime' => $mime, 'width' => $width, 'height' => $height, 'bytes' => $bytes];
    }

    /**
     * A display-safe version of a client-supplied file name: base name only, no control
     * characters, at most 100 characters. It is metadata for the administrator to read;
     * it is never used to build a path.
     */
    public static function displayName(string $name): string
    {
        $name = str_replace('\\', '/', $name);
        $name = basename($name);
        if (!mb_check_encoding($name, 'UTF-8')) {
            $name = 'image';
        }
        $name = preg_replace('/[\p{Cc}\p{Cf}\p{Zl}\p{Zp}]+/u', '', $name) ?? '';
        $name = trim($name);

        return $name === '' ? 'image' : mb_substr($name, 0, 100, 'UTF-8');
    }

    /** The WebP "VP8X" header advertises animation with a flag, and animated files contain an ANIM chunk. */
    private static function isAnimatedWebp(string $path): bool
    {
        $head = (string) file_get_contents($path, false, null, 0, 4096);

        return str_contains($head, 'ANIM') || (strlen($head) > 20 && substr($head, 12, 4) === 'VP8X' && (ord($head[20]) & 0x02) !== 0);
    }
}
