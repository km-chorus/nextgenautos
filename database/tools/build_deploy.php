<?php

declare(strict_types=1);

/**
 * Builds the upload package for the hosting account (pre-deployment, 2026-10-27). COMMAND LINE ONLY.
 *
 *   php database/tools/build_deploy.php <output folder>
 *
 * Creates <output>/public_html/ (exactly what goes into the web root) and <output>/public_html.zip. Only an allowlist is
 * copied, so anything new in the project stays out unless it is added here on purpose. Never copied: .env and every
 * other hidden file except the .htaccess files, Git data, project documents (CLAUDE.md, Context/), tests, the CI
 * workflow, migrations, import data, Composer files, development-only pages, unused originals, logs, the password
 * reset cache. Then every copied file is scanned: the build FAILS if it finds a secret value from .env, a Brevo key,
 * a private key or a .env file. Secret values are compared in memory and never printed.
 *
 * The site still needs on the server: a .env created by hand (see .env.example and Context/10_go_live_checklist.md).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__, 2);
$out = $argv[1] ?? '';
if ($out === '' || str_starts_with(str_replace('\\', '/', realpath(dirname($out)) ?: ''), str_replace('\\', '/', $root))) {
    fwrite(STDERR, "Usage: php database/tools/build_deploy.php <output folder OUTSIDE the project>\n");
    exit(1);
}
$site = $out . '/public_html';

// What the website needs. Folders are copied recursively, minus EXCLUDE.
const INCLUDE_PATHS = [
    'index.php', '.htaccess', 'robots.txt',
    'admin', 'app', 'assets', 'includes', 'public', 'vendor',
    'uploads/.htaccess', 'uploads/vehicles/.gitkeep', 'uploads/team/.gitkeep', 'uploads/parts',
    'storage/.htaccess', 'storage/password_resets/.htaccess',
    // command-line tools used on the server (the folder is closed to the web by its own .htaccess)
    'database/.htaccess', 'database/create_admin.php', 'database/generate_app_key.php',
    'database/send_test_email.php', 'database/set_company_phone.php',
];
/** Regular expressions on the project-relative path (forward slashes). */
const EXCLUDE = [
    '#(^|/)\.(?!htaccess$|gitkeep$)#',          // hidden files: .env, .git, .DS_Store ... (only .htaccess / .gitkeep pass)
    '#^admin/ui-kit\.php$#',                    // development-only component page
    '#^assets/images/Next Gen Automotive Gear Logo\.png$#',   // original logo source (the site uses logo.webp)
    '#^assets/images/hero/hero-1-desktop\.jpg$#',            // unused duplicate (the .webp is served)
    '#\.(log|md|sql|lock|example|bak|tmp|orig|swp)$#i',
    '#(^|/)error_log$#',
    '#^vendor/[^/]+/[^/]+/(tests?|docs?|examples?|\.github)/#i',   // library tests and docs
];

$copied = 0;
$bytes = 0;
$excluded = [];
$copy = static function (string $rel) use (&$copy, $root, $site, &$copied, &$bytes, &$excluded): void {
    foreach (EXCLUDE as $re) {
        if (preg_match($re, $rel) === 1) {
            $excluded[] = $rel;

            return;
        }
    }
    $src = $root . '/' . $rel;
    if (is_dir($src)) {
        foreach (scandir($src) ?: [] as $name) {
            if ($name !== '.' && $name !== '..') {
                $copy($rel . '/' . $name);
            }
        }

        return;
    }
    if (!is_file($src)) {
        fwrite(STDERR, "Missing: $rel\n");
        exit(1);
    }
    $dest = $site . '/' . $rel;
    if (!is_dir(dirname($dest)) && !mkdir(dirname($dest), 0755, true)) {
        fwrite(STDERR, "Cannot create " . dirname($dest) . "\n");
        exit(1);
    }
    copy($src, $dest);
    $copied++;
    $bytes += filesize($src);
};

// Start from an empty output folder.
if (is_dir($site)) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($site, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) {
        $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    }
}
@mkdir($site, 0755, true);
foreach (INCLUDE_PATHS as $rel) {
    $copy($rel);
}

// Secret scan of everything copied.
$secrets = [];
$envFile = $root . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        if (preg_match('/^\s*(DB_PASSWORD|APP_KEY|SMTP_PASSWORD|SMTP_USERNAME|BREVO_API_KEY|GOOGLE_CLIENT_SECRET)\s*=\s*"?([^"]*)"?\s*$/', $line, $m) === 1 && strlen(trim($m[2])) >= 6) {
            $secrets[$m[1]] = trim($m[2]);
        }
    }
}
$patterns = [
    'Brevo SMTP key' => '/xsmtpsib-[a-f0-9]{20,}/i',
    'Brevo API key'  => '/xkeysib-[a-f0-9]{20,}/i',
    'private key'    => '/-----BEGIN [A-Z ]*PRIVATE KEY-----/',
];
$findings = [];
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($site, FilesystemIterator::SKIP_DOTS));
foreach ($files as $f) {
    $rel = substr(str_replace('\\', '/', $f->getPathname()), strlen($site) + 1);
    if (basename($rel) === '.env') {
        $findings[] = "$rel: a .env file";
        continue;
    }
    if ($f->getSize() > 5_000_000 || preg_match('/\.(webp|png|jpe?g|gif|ico|woff2?)$/i', $rel) === 1) {
        continue;
    }
    $text = (string) file_get_contents($f->getPathname());
    foreach ($secrets as $key => $value) {
        if (str_contains($text, $value)) {
            $findings[] = "$rel: contains the value of $key";
        }
    }
    foreach ($patterns as $label => $re) {
        if (preg_match($re, $text) === 1) {
            $findings[] = "$rel: looks like a $label";
        }
    }
}

echo "Copied $copied files (" . round($bytes / 1048576, 1) . " MB) to $site\n";
echo 'Left out on purpose: ' . count($excluded) . " paths\n";
echo 'Secret scan: ' . count($secrets) . ' .env secret values and ' . count($patterns) . ' key patterns checked; ' . count($findings) . " findings\n";
foreach ($findings as $line) {
    echo "  FOUND $line\n";
}
if ($findings !== []) {
    fwrite(STDERR, "Build stopped: remove the secrets above first.\n");
    exit(1);
}

$zipPath = $out . '/public_html.zip';
@unlink($zipPath);
$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "Cannot create the zip\n");
    exit(1);
}
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($site, FilesystemIterator::SKIP_DOTS)) as $f) {
    $zip->addFile($f->getPathname(), substr(str_replace('\\', '/', $f->getPathname()), strlen($site) + 1));
}
$zip->close();
echo 'Zip: ' . $zipPath . ' (' . round(filesize($zipPath) / 1048576, 1) . " MB)\n";
