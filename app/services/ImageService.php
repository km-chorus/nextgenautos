<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use Intervention\Image\ImageManager;

/**
 * Image processing and storage for vehicle photographs (spec 07_spec_vehicle_image.md).
 *
 * Validation of the upload itself is ImageValidator's job and must have passed
 * before process() is called. This class only:
 *   - decodes the image with Intervention Image + GD (EXIF orientation is applied),
 *   - scales it DOWN to fit 1600 x 1600 (never up, aspect ratio preserved),
 *   - encodes it as WebP (quality 78, stepping down only if the file is unusually large),
 *   - makes a 640 px WebP thumbnail,
 *   - stores both under uploads/vehicles/ with random names.
 *
 * Re-encoding discards all metadata (EXIF, GPS location, camera data), and the stored
 * file is a pixel re-encode, so nothing the uploader embedded in the original survives.
 * The original upload is never kept.
 *
 * The caller never influences a path: names are 128 random bits plus ".webp" (and
 * "-t.webp" for the thumbnail), the directory is fixed, and delete() accepts only that
 * exact shape. Filesystem work is not transactional, so every failure path removes
 * what it wrote.
 */
final class ImageService
{
    public const THUMB_QUALITY = 70;
    /** Main image quality: starts at 78 and steps down only when the file is larger than the kind's target size. */
    private const QUALITY_STEPS = [78, 68, 58];
    private const MEMORY_LIMIT_BYTES = 402653184; // 384 MB while decoding one image (20 MP cap)
    public const KIND_VEHICLE = 'vehicle';
    public const KIND_TEAM = 'team';
    public const KIND_PART = 'part';

    /**
     * What differs between the kinds of picture this service stores. Everything else (decoding, WebP
     * encoding, random names, exclusive file creation, safe deletion; validation is ImageValidator's job)
     * is shared, so there is one image pipeline, not one per feature.
     *   dir     application-relative storage folder
     *   box     the main image is scaled DOWN to fit this width x height (never up, ratio kept)
     *   thumb   thumbnail box
     *   target  re-encode at lower quality only when the first result is larger than this many bytes
     */
    private const PROFILES = [
        self::KIND_VEHICLE => ['dir' => 'uploads/vehicles', 'box' => [1600, 1600], 'thumb' => [640, 640], 'target' => 716800],
        self::KIND_TEAM    => ['dir' => 'uploads/team', 'box' => [1200, 1600], 'thumb' => [400, 533], 'target' => 460800],
        self::KIND_PART    => ['dir' => 'uploads/parts', 'box' => [1600, 1600], 'thumb' => [640, 640], 'target' => 716800],
    ];

    private string $directory;
    private string $kind;

    /**
     * @param string|null $directory absolute storage directory (tests pass a temporary one); default: the project's folder for this kind
     * @param string $kind ImageService::KIND_VEHICLE (default), KIND_TEAM or KIND_PART
     */
    public function __construct(?string $directory = null, string $kind = self::KIND_VEHICLE)
    {
        if (!isset(self::PROFILES[$kind])) {
            throw new InvalidArgumentException('Unknown image kind.');
        }
        $this->kind = $kind;
        $this->directory = rtrim(str_replace('\\', '/', $directory ?? dirname(__DIR__, 2) . '/' . self::PROFILES[$kind]['dir']), '/');
    }

    /** True when everything this class needs is present. */
    public static function isAvailable(): bool
    {
        return self::missingRequirements() === [];
    }

    /**
     * Names of the missing pieces (empty when image processing can work). Checked before any upload is
     * touched, so a server that lacks them says exactly what is missing instead of failing half way.
     *
     * @return list<string>
     */
    public static function missingRequirements(): array
    {
        $missing = [];
        if (!extension_loaded('gd')) {
            $missing[] = 'gd';
        } elseif (!function_exists('imagewebp')) {
            $missing[] = 'gd with WebP support';
        }
        if (!extension_loaded('fileinfo') || !function_exists('finfo_open')) {
            $missing[] = 'fileinfo';
        }
        if (!class_exists(ImageManager::class)) {
            $missing[] = 'intervention/image (run composer install)';
        }

        return $missing;
    }

    /**
     * The largest single upload this PHP configuration will accept, in bytes: the smaller of
     * upload_max_filesize and post_max_size (0 means PHP imposes no limit).
     */
    public static function serverUploadLimit(): int
    {
        $limits = array_filter([self::iniBytes('upload_max_filesize'), self::iniBytes('post_max_size')], static fn (int $b): bool => $b > 0);

        return $limits === [] ? 0 : min($limits);
    }

    /** A php.ini size such as "10M" or "2G" in bytes (0 when unset or unlimited). */
    public static function iniBytes(string $key): int
    {
        $value = trim((string) ini_get($key));
        if ($value === '' || $value === '-1') {
            return 0;
        }
        $number = (int) $value;

        return $number * match (strtoupper(substr($value, -1))) { 'G' => 1073741824, 'M' => 1048576, 'K' => 1024, default => 1 };
    }

    /**
     * Processes a validated source file and stores the main image and thumbnail.
     *
     * @return array{path:string,thumb:string,width:int,height:int,bytes:int}
     *         `path` is the application-relative path stored in the database (uploads/vehicles/<random>.webp).
     * @throws RuntimeException on any failure (the caller shows a generic message; detail is logged here)
     */
    public function process(string $sourcePath): array
    {
        if (!self::isAvailable()) {
            throw new RuntimeException('Image processing is not available (gd/webp/fileinfo/intervention missing).');
        }
        if (!is_dir($this->directory) || !is_writable($this->directory)) {
            error_log('ImageService: the upload directory is missing or not writable.');
            throw new RuntimeException('Upload directory unavailable.');
        }

        $previousLimit = (string) ini_get('memory_limit');
        $this->raiseMemoryLimit();

        $written = [];
        try {
            $manager = ImageManager::gd();   // EXIF orientation is applied while reading

            $profile = self::PROFILES[$this->kind];
            $main = $manager->read($sourcePath)->scaleDown(width: $profile['box'][0], height: $profile['box'][1]);
            $mainBytes = '';
            foreach (self::QUALITY_STEPS as $quality) {
                $mainBytes = (string) $main->toWebp(quality: $quality);
                if (strlen($mainBytes) <= $profile['target']) {
                    break;
                }
            }
            $width = $main->width();
            $height = $main->height();
            unset($main);

            $thumb = $manager->read($sourcePath)->scaleDown(width: $profile['thumb'][0], height: $profile['thumb'][1]);
            $thumbBytes = (string) $thumb->toWebp(quality: self::THUMB_QUALITY);
            unset($thumb);

            if (!$this->isWebp($mainBytes) || !$this->isWebp($thumbBytes)) {
                throw new RuntimeException('Encoder did not produce WebP data.');
            }

            $id = bin2hex(random_bytes(16));
            $mainPath = $this->directory . '/' . $id . '.webp';
            $thumbPath = $this->directory . '/' . $id . '-t.webp';
            $this->writeNew($mainPath, $mainBytes);
            $written[] = $mainPath;
            $this->writeNew($thumbPath, $thumbBytes);
            $written[] = $thumbPath;

            return [
                'path'   => $profile['dir'] . '/' . $id . '.webp',
                'thumb'  => $profile['dir'] . '/' . $id . '-t.webp',
                'width'  => $width,
                'height' => $height,
                'bytes'  => strlen($mainBytes),
            ];
        } catch (Throwable $e) {
            foreach ($written as $file) {
                @unlink($file);
            }
            error_log('ImageService: processing failed: ' . get_class($e) . ': ' . $e->getMessage());
            throw new RuntimeException('Image processing failed.', 0, $e);
        } finally {
            if ($previousLimit !== '') {
                @ini_set('memory_limit', $previousLimit);
            }
        }
    }

    /**
     * Removes a stored image and its thumbnail. Only the exact generated path shape is accepted.
     *
     * @return string 'deleted' (the main file existed and is gone), 'missing' (it was already gone), or 'invalid' (not a path this class generates; nothing touched)
     */
    public function delete(string $relativePath): string
    {
        if (!self::isStoredPath($relativePath, $this->kind)) {
            return 'invalid';
        }
        $main = $this->directory . '/' . basename($relativePath);
        $thumb = $this->directory . '/' . basename(self::thumbPath($relativePath));

        $existed = is_file($main);
        if ($existed && !@unlink($main)) {
            error_log('ImageService: could not delete a stored image file.');
            throw new RuntimeException('Could not delete the image file.');
        }
        if (is_file($thumb)) {
            @unlink($thumb);
        }

        return $existed ? 'deleted' : 'missing';
    }

    /** True for exactly the paths process() generates for this kind of picture (folder, 32 random hex characters, .webp). */
    public static function isStoredPath(string $path, string $kind = self::KIND_VEHICLE): bool
    {
        if (!isset(self::PROFILES[$kind])) {
            return false;
        }

        return preg_match('#^' . preg_quote(self::PROFILES[$kind]['dir'], '#') . '/[a-f0-9]{32}\.webp$#', $path) === 1;
    }

    /** "uploads/vehicles/abc.webp" => "uploads/vehicles/abc-t.webp". */
    public static function thumbPath(string $relativePath): string
    {
        return preg_replace('/\.webp$/', '-t.webp', $relativePath) ?? $relativePath;
    }

    /** Creates a new file and fails if it already exists (a name can never overwrite another image). */
    private function writeNew(string $path, string $bytes): void
    {
        $handle = @fopen($path, 'xb');
        if ($handle === false) {
            throw new RuntimeException('Could not create the image file.');
        }
        try {
            if (fwrite($handle, $bytes) !== strlen($bytes)) {
                throw new RuntimeException('Could not write the image file.');
            }
        } finally {
            fclose($handle);
        }
        @chmod($path, 0644);   // readable static file, never executable
    }

    private function isWebp(string $bytes): bool
    {
        return strlen($bytes) > 12 && str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WEBP';
    }

    private function raiseMemoryLimit(): void
    {
        $current = ini_get('memory_limit');
        if ($current === '-1' || $current === false) {
            return;
        }
        $bytes = (int) $current;
        $unit = strtoupper(substr(trim((string) $current), -1));
        $bytes *= match ($unit) { 'G' => 1073741824, 'M' => 1048576, 'K' => 1024, default => 1 };
        if ($bytes < self::MEMORY_LIMIT_BYTES) {
            @ini_set('memory_limit', (string) self::MEMORY_LIMIT_BYTES);
        }
    }
}
