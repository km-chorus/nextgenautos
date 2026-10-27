<?php

declare(strict_types=1);

/**
 * Runs every automated check in one command (QA audit 2026-10-27). CLI only:
 *   php database/tests/run_all.php            syntax check of every project PHP file, then every *_test.php suite
 *   php database/tests/run_all.php --lint     syntax check only
 *
 * Exit code 0 only when every file parses and every suite reports "0 failed". Each suite runs in its own PHP process
 * (no shell: the command is passed as an argument list), with the database settings from the environment / .env.
 * The suites roll back their own database changes; never point this at the production database.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__, 2);
$php = PHP_BINARY;
/** @return array{0:int,1:string,2:string} exit code, standard output, standard error (the suites' expected log lines go there) */
$run = static function (array $cmd) use ($root): array {
    $errFile = tempnam(sys_get_temp_dir(), 'suite');
    $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['file', $errFile, 'w']], $pipes, $root);
    if (!is_resource($proc)) {
        return [255, '', 'could not start'];
    }
    $out = (string) stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $code = proc_close($proc);
    $err = (string) file_get_contents($errFile);
    @unlink($errFile);

    return [$code, $out, $err];
};

// 1. Syntax check (php -l) of every project PHP file outside vendor/.
$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $file) {
    $path = str_replace('\\', '/', $file->getPathname());
    if (str_ends_with($path, '.php') && !str_contains($path, '/vendor/') && !str_contains($path, '/.git/') && !str_contains($path, '/storage/')) {
        $files[] = $path;
    }
}
sort($files);
$lintFailures = [];
foreach ($files as $f) {
    [$code, $out, $err] = $run([$php, '-l', $f]);
    if ($code !== 0) {
        $lintFailures[] = substr($f, strlen($root) + 1) . ': ' . trim($out . ' ' . $err);
    }
}
echo 'Syntax check: ' . count($files) . ' files, ' . count($lintFailures) . " with errors\n";
foreach ($lintFailures as $l) {
    echo "  $l\n";
}
if (in_array('--lint', $argv, true)) {
    exit($lintFailures === [] ? 0 : 1);
}

// 2. Every test suite.
$failedSuites = [];
$total = 0;
foreach (glob(__DIR__ . '/*_test.php') ?: [] as $suite) {
    [$code, $out, $err] = $run([$php, '-d', 'memory_limit=512M', '-d', 'error_reporting=-1', $suite]);
    $lines = preg_split('/\R/', trim($out)) ?: [''];
    $last = trim((string) end($lines));
    // A PHP warning, notice or deprecation during a suite is a failure too, even when every check passed.
    $warnings = preg_match_all('/PHP (Warning|Notice|Deprecated|Fatal error)/', $err . $out);
    $m = [];
    $ok = $code === 0 && preg_match('/(\d+) passed, 0 failed/', $last, $m) === 1 && $warnings === 0;
    $total += isset($m[1]) ? (int) $m[1] : 0;
    printf("%-4s %-32s %s%s\n", $ok ? 'ok' : 'FAIL', basename($suite), $last, $warnings > 0 ? "  + $warnings PHP warning(s)" : '');
    if (!$ok) {
        $failedSuites[] = basename($suite);
    }
}
echo "\nSuites: " . count(glob(__DIR__ . '/*_test.php') ?: []) . ', checks passed: ' . $total . ', failing suites: ' . count($failedSuites) . "\n";
exit($lintFailures === [] && $failedSuites === [] ? 0 : 1);
