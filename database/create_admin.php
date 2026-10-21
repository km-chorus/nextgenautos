<?php

declare(strict_types=1);

/**
 * Creates an administrator account (the ONLY way to create the first admin).
 * Command line only: the script refuses to run from the web, and must not be
 * exposed on the production site.
 *
 * Quick start (Windows PowerShell or Command Prompt, from the project folder):
 *
 *     php database/create_admin.php
 *
 * It asks for a name, an email and a password (twice). The password is never
 * echoed, never stored in shell history, and never printed or logged.
 *
 *   * In a normal console the password is typed at a hidden prompt.
 *   * In terminals without a real console (Git Bash, some editor terminals) a
 *     small secure password window opens instead.
 *   * Universal fallback, from PowerShell (the password stays out of history):
 *
 *       $p = Read-Host "Password" -AsSecureString
 *       [Runtime.InteropServices.Marshal]::PtrToStringBSTR([Runtime.InteropServices.Marshal]::SecureStringToBSTR($p)) |
 *           php database/create_admin.php --name="Your Name" --email=you@example.com --password-stdin
 *
 * Options
 *   --name=NAME  --email=EMAIL   Skip those prompts.
 *   --password-stdin             Read the password from standard input (first line).
 *   --allow-additional           Needed (or confirm interactively) when an administrator
 *                                already exists, to prevent accidental duplicates.
 *
 * Passwords are hashed with password_hash(). Policy: 12 to 72 bytes (see PasswordPolicy).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/app/services/AuthService.php';
require_once dirname(__DIR__) . '/app/services/PasswordPolicy.php';

const RECIPE = <<<'TXT'
To enter the password without a console, run this in PowerShell (the password stays out of history):

  $p = Read-Host "Password" -AsSecureString
  [Runtime.InteropServices.Marshal]::PtrToStringBSTR([Runtime.InteropServices.Marshal]::SecureStringToBSTR($p)) | php database/create_admin.php --name="Your Name" --email=you@example.com --password-stdin
TXT;

function fail(string $message, int $code = 1): never
{
    fwrite(STDERR, "Error: {$message}\n");
    exit($code);
}

function promptLine(string $label): string
{
    fwrite(STDERR, $label);
    $line = fgets(STDIN);

    return $line === false ? '' : trim($line);
}

/** Runs a PowerShell snippet and returns what it prints (no echo of the typed secret). */
function powershell(string $script, bool $inheritStdin): string
{
    $command = 'powershell -NoProfile -Command "' . $script . '"';
    $descriptors = [0 => $inheritStdin ? STDIN : ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => STDERR];
    $process = @proc_open($command, $descriptors, $pipes);
    if (!is_resource($process)) {
        return '';
    }
    if (!$inheritStdin) {
        fclose($pipes[0]);
    }
    $output = rtrim((string) stream_get_contents($pipes[1]), "\r\n");
    fclose($pipes[1]);
    proc_close($process);

    return $output;
}

/** Reads a secret without echoing it; returns '' when no secure input method works here. */
function promptSecret(string $label, bool $hasConsole): string
{
    if (PHP_OS_FAMILY === 'Windows') {
        if ($hasConsole) {
            fwrite(STDERR, $label);
            $secret = powershell(
                '$s = Read-Host -AsSecureString; $b = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($s); '
                . '[Runtime.InteropServices.Marshal]::PtrToStringBSTR($b)',
                true
            );
            fwrite(STDERR, "\n");
            if ($secret !== '') {
                return $secret;
            }
        }

        // No usable console: a masked Windows password window (nothing is typed in this terminal).
        fwrite(STDERR, rtrim($label, ': ') . ": opening a secure password window...\n");

        return powershell(
            '$c = Get-Credential -UserName administrator -Message \'' . addslashes(rtrim($label, ': ')) . '\'; '
            . 'if ($c) { $c.GetNetworkCredential().Password }',
            false
        );
    }

    if (!$hasConsole) {
        return '';
    }
    fwrite(STDERR, $label);
    shell_exec('stty -echo');
    $line = fgets(STDIN);
    shell_exec('stty echo');
    fwrite(STDERR, "\n");

    return $line === false ? '' : rtrim($line, "\r\n");
}

$options = getopt('', ['name:', 'email:', 'password-stdin', 'allow-additional', 'help']);
if (isset($options['help'])) {
    echo "Usage: php database/create_admin.php [--name=NAME] [--email=EMAIL] [--password-stdin] [--allow-additional]\n\n" . RECIPE . "\n";
    exit(0);
}

$hasConsole = function_exists('stream_isatty') && stream_isatty(STDIN);
$fromStdin = isset($options['password-stdin']);

// --- name and email ---------------------------------------------------------
$name = is_string($options['name'] ?? null) ? trim($options['name']) : '';
if ($name === '' && !$fromStdin) {
    $name = promptLine('Administrator name: ');
}
if ($name === '' || strlen($name) > 100 || preg_match('/[\x00-\x1F\x7F]/', $name) === 1) {
    fail("Enter a name of 1 to 100 characters (use --name=\"Your Name\").");
}

$emailInput = is_string($options['email'] ?? null) ? $options['email'] : ($fromStdin ? '' : promptLine('Administrator email: '));
$email = AuthService::normalizeEmail($emailInput);
if ($email === '' || strlen($email) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    fail('Enter a valid email address (use --email=you@example.com).');
}

// --- password ---------------------------------------------------------------
if ($fromStdin) {
    $line = fgets(STDIN);
    $password = $line === false ? '' : rtrim($line, "\r\n");
} else {
    $password = promptSecret('Password (at least ' . PasswordPolicy::MIN_LENGTH . ' characters): ', $hasConsole);
    if ($password === '') {
        fail("The password could not be read securely in this terminal.\n\n" . RECIPE);
    }
    if (!hash_equals($password, promptSecret('Repeat password: ', $hasConsole))) {
        fail('The passwords do not match.');
    }
}

$problems = PasswordPolicy::validate($password, $email);
if ($problems !== []) {
    fail("The password was rejected:\n  - " . implode("\n  - ", $problems));
}
if (!$fromStdin) {
    fwrite(STDERR, 'Password strength: ' . PasswordPolicy::strength($password) . "\n");
}

// --- database ---------------------------------------------------------------
try {
    $pdo = Database::connection();
} catch (Throwable $e) {
    fail('Could not connect to the database. Check .env and that the schema is installed.');
}

$users = new AdminUserRepository($pdo);
$audit = new AuditLogRepository($pdo);

try {
    if ($users->count() > 0 && !isset($options['allow-additional'])) {
        if ($fromStdin || !$hasConsole) {
            fail('An administrator already exists. Pass --allow-additional to create another.', 2);
        }
        if (strtolower(promptLine('An administrator already exists. Create another? (yes/no): ')) !== 'yes') {
            fail('Cancelled. No account was created.', 2);
        }
    }

    if ($users->findByEmail($email) !== null) {
        fail('An account with that email already exists.', 2);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    unset($password); // keep the plaintext out of memory as soon as it is no longer needed

    $pdo->beginTransaction();
    $id = $users->create($name, $email, $hash, AuthorizationService::ROLE_ADMINISTRATOR);
    $audit->record('ADMIN_CREATED', $id, 'admin_user', $id, 'Administrator account created with the setup script');
    $pdo->commit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('create_admin failed: ' . $e->getMessage());
    fail('The account could not be created (it may already exist).');
}

echo "Administrator created: {$name} <{$email}> (id {$id}, role " . AuthorizationService::ROLE_ADMINISTRATOR . ").\n";
echo "Next: start the site and sign in at /admin/login.php. Then open Account security to turn on two-factor authentication.\n";
exit(0);
