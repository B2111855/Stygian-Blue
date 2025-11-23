<?php
// Central SMTP configuration with dotenv support.
// Priority: .env overrides system env → fallback defaults.
// Expected keys: SMTP_HOST, SMTP_PORT, SMTP_USERNAME, SMTP_PASSWORD, SMTP_ENCRYPTION (tls|ssl), MAIL_FROM, MAIL_FROM_NAME.

if (!class_exists('Dotenv\\Dotenv')) {
    // Attempt vendor autoload relative to project root (three levels up from helpers)
    $autoloadPath = realpath(__DIR__ . '/../../vendor/autoload.php');
    if ($autoloadPath) { require_once $autoloadPath; }
}

// Load .env if present (safeLoad: ignores missing file or invalid entries)
try {
    if (class_exists('Dotenv\\Dotenv')) {
        $rootDir = dirname(__DIR__, 2); // project root (StygianBlue)
        if (is_file($rootDir . '/.env')) {
            Dotenv\Dotenv::createImmutable($rootDir)->safeLoad();
        }
    }
} catch (Throwable $e) {
    // Silently ignore dotenv errors but log if needed
    error_log('Dotenv load warning: ' . $e->getMessage());
}

function env_value(string $key, $default = '') {
    $v = getenv($key);
    return ($v === false || $v === '') ? $default : $v;
}

// Resolve credentials with legacy key support (SMTP_USER / SMTP_PASS / SMTP_FROM)
$username = env_value('SMTP_USERNAME', '');
if ($username === '') { $username = env_value('SMTP_USER', 'example@gmail.com'); }
$password = env_value('SMTP_PASSWORD', '');
if ($password === '') { $password = env_value('SMTP_PASS', ''); }
$fromEmail = env_value('MAIL_FROM', '');
if ($fromEmail === '') { $fromEmail = env_value('SMTP_FROM', ''); }
if ($fromEmail === '') { $fromEmail = $username ?: 'example@gmail.com'; }
$fromName  = env_value('MAIL_FROM_NAME', env_value('SMTP_FROM_NAME', 'Stygian Blue Studio'));

$cfg = [
    'host'       => env_value('SMTP_HOST', 'smtp.gmail.com'),
    'port'       => (int) env_value('SMTP_PORT', 587),
    'username'   => $username,
    'password'   => $password,
    'encryption' => strtolower(env_value('SMTP_ENCRYPTION', 'tls')) === 'ssl' ? 'ssl' : 'tls',
    'from_email' => $fromEmail,
    'from_name'  => $fromName,
    'charset'    => 'UTF-8',
];

if ((env_value('APP_ENV','') === 'local') && env_value('MAIL_DEBUG','0') === '1') {
    error_log('[mail_config] Using SMTP username=' . $cfg['username'] . ' from_email=' . $cfg['from_email']);
}

return $cfg;
