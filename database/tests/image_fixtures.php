<?php

declare(strict_types=1);

/**
 * Test fixtures for the vehicle image tests (spec 07_spec_vehicle_image.md, section 44).
 *
 * Builds REAL images in memory with GD (nothing is committed to the repository, no personal
 * photographs are used) plus deliberately hostile files: scripts renamed as images, corrupt
 * files, headers that claim enormous dimensions, an image carrying a PHP payload, and more.
 * Test code decides where to write them (always a temporary folder).
 *
 * CLI / test use only.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/** A photograph-like image (gradient sky, road, random shapes for realistic compressed size). */
function fx_photo(int $width, int $height, string $format = 'jpeg', bool $alpha = false): string
{
    mt_srand(7);
    $img = imagecreatetruecolor($width, $height);
    if ($alpha) {
        imagealphablending($img, false);
        imagesavealpha($img, true);
    }
    for ($y = 0; $y < $height; $y += 4) {                       // vertical gradient in bands
        $t = $y / max(1, $height - 1);
        $c = imagecolorallocate($img, (int) (40 + 150 * $t), (int) (90 + 90 * (1 - $t)), (int) (200 - 120 * $t));
        imagefilledrectangle($img, 0, $y, $width, $y + 3, $c);
    }
    for ($i = 0; $i < 400; $i++) {                              // shapes: gives the encoder real work
        $c = imagecolorallocate($img, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
        imagefilledellipse($img, mt_rand(0, $width), mt_rand(0, $height), mt_rand(10, max(11, (int) ($width / 6))), mt_rand(10, max(11, (int) ($height / 6))), $c);
    }
    $mark = imagecolorallocate($img, 255, 255, 255);            // an orientation marker: a white bar at the top-left
    imagefilledrectangle($img, 0, 0, (int) ($width / 4), (int) ($height / 20) + 1, $mark);
    if ($alpha) {
        $clear = imagecolorallocatealpha($img, 0, 0, 0, 127);
        imagefilledrectangle($img, (int) ($width * 0.6), (int) ($height * 0.6), $width - 1, $height - 1, $clear);
    }

    ob_start();
    match ($format) {
        'png'  => imagepng($img, null, 6),
        'webp' => imagewebp($img, null, 80),
        default => imagejpeg($img, null, 85),
    };
    $bytes = (string) ob_get_clean();
    unset($img);

    return $bytes;
}

/** A JPEG whose EXIF says "rotate 90 degrees clockwise to display" (Orientation = 6). */
function fx_exif_rotated_jpeg(int $width, int $height): string
{
    $jpeg = fx_photo($width, $height, 'jpeg');
    // TIFF header (big-endian), one IFD0 entry: tag 0x0112 Orientation, type SHORT, count 1, value 6.
    $tiff = "MM\x00\x2A\x00\x00\x00\x08" . "\x00\x01" . "\x01\x12\x00\x03\x00\x00\x00\x01\x00\x06\x00\x00" . "\x00\x00\x00\x00";
    $payload = "Exif\x00\x00" . $tiff . 'GPS-AND-CAMERA-METADATA-MARKER';
    $segment = "\xFF\xE1" . pack('n', strlen($payload) + 2) . $payload;

    return substr($jpeg, 0, 2) . $segment . substr($jpeg, 2);
}

/** A real, valid JPEG with a PHP payload appended after the image data (a "polyglot"). */
function fx_jpeg_with_php_payload(): string
{
    return fx_photo(900, 700, 'jpeg') . "\n<?php echo 'PWNED'; system(\$_GET['c']); ?>\n";
}

/** A valid JPEG padded with zero bytes to exceed the size limit (the content is still a real image). */
function fx_oversized_jpeg(int $bytes = 11 * 1048576): string
{
    $jpeg = fx_photo(900, 700, 'jpeg');

    return $jpeg . str_repeat("\0", max(0, $bytes - strlen($jpeg)));
}

/** A tiny PNG file whose header claims the given dimensions (no pixel data: a decompression-bomb shape). */
function fx_png_claiming(int $width, int $height): string
{
    $chunk = static fn (string $type, string $data): string => pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    $ihdr = pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0);

    return "\x89PNG\r\n\x1a\n" . $chunk('IHDR', $ihdr) . $chunk('IEND', '');
}

/** A JPEG cut off after its first bytes (corrupt). */
function fx_truncated_jpeg(): string
{
    return substr(fx_photo(900, 700, 'jpeg'), 0, 24);
}

/** A JPEG with its middle destroyed (header intact, data garbage): decodes badly or not at all. */
function fx_damaged_jpeg(): string
{
    $jpeg = fx_photo(1000, 800, 'jpeg');
    $cut = (int) (strlen($jpeg) * 0.1);

    return substr($jpeg, 0, $cut) . str_repeat("\xAB", 4000) . substr($jpeg, $cut + 4000, 50);
}

function fx_gif(): string
{
    $img = imagecreatetruecolor(900, 700);
    imagefill($img, 0, 0, imagecolorallocate($img, 10, 120, 200));
    ob_start();
    imagegif($img);

    return (string) ob_get_clean();
}

function fx_bmp(): string
{
    $img = imagecreatetruecolor(900, 700);
    ob_start();
    imagebmp($img);

    return (string) ob_get_clean();
}

function fx_svg(): string
{
    return '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" width="900" height="700"><script>alert(1)</script><rect width="900" height="700" fill="#369"/></svg>';
}

function fx_php_script(): string
{
    return "<?php echo 'PWNED'; system(\$_GET['c']);";
}

function fx_html(): string
{
    return '<html><body><script>alert(1)</script></body></html>';
}

/** A GIF header followed by PHP code (classic polyglot). */
function fx_gif_php_polyglot(): string
{
    return "GIF89a;\n<?php system(\$_GET['c']); ?>";
}

function fx_pdf(): string
{
    return "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
}

function fx_zip(): string
{
    return "PK\x03\x04" . str_repeat("\0", 60);
}

function fx_exe(): string
{
    return "MZ\x90\x00" . str_repeat("\0", 200) . 'This program cannot be run in DOS mode.';
}
