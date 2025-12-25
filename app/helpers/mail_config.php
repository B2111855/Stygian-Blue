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

if (!function_exists('env_value')) {
    function env_value(string $key, $default = '') {
        // Prefer $_ENV populated by phpdotenv, fallback to getenv()
        if (array_key_exists($key, $_ENV) && $_ENV[$key] !== '') {
            return $_ENV[$key];
        }
        $v = getenv($key);
        return ($v === false || $v === null || $v === '') ? $default : $v;
    }
}

if (!function_exists('tp_mail_cfg')) {
    function tp_mail_cfg(): array
    {
        // Resolve credentials with legacy key support (SMTP_USER / SMTP_PASS / SMTP_FROM)
        $username = env_value('SMTP_USERNAME', '');
        if ($username === '') { $username = env_value('SMTP_USER', 'example@gmail.com'); }
        $password = env_value('SMTP_PASSWORD', '');
        if ($password === '') { $password = env_value('SMTP_PASS', ''); }
        $fromEmail = env_value('MAIL_FROM', '');
        if ($fromEmail === '') { $fromEmail = env_value('SMTP_FROM', ''); }
        if ($fromEmail === '') { $fromEmail = $username ?: 'example@gmail.com'; }
        $fromName  = env_value('MAIL_FROM_NAME', env_value('SMTP_FROM_NAME', 'Stygian Blue Studio'));

        return [
            'host'       => env_value('SMTP_HOST', 'smtp.gmail.com'),
            'port'       => (int) env_value('SMTP_PORT', 587),
            'username'   => $username,
            'password'   => $password,
            'encryption' => strtolower(env_value('SMTP_ENCRYPTION', 'tls')) === 'ssl' ? 'ssl' : 'tls',
            'from_email' => $fromEmail,
            'from_name'  => $fromName,
            'charset'    => 'UTF-8',
        ];
    }
}

$cfg = tp_mail_cfg();

if ((env_value('APP_ENV','') === 'local') && env_value('MAIL_DEBUG','0') === '1') {
    error_log('[mail_config] Using SMTP username=' . $cfg['username'] . ' from_email=' . $cfg['from_email']);
}

// Optional helper to directly configure a PHPMailer instance
if (!function_exists('tp_configure_mail')) {
    function tp_configure_mail(\PHPMailer\PHPMailer\PHPMailer $mail): void
    {
        $cfg = tp_mail_cfg();
        $mail->isSMTP();
        $mail->Host       = $cfg['host'] ?? 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $cfg['username'] ?? '';
        $mail->Password   = $cfg['password'] ?? '';
        $mail->SMTPSecure = (($cfg['encryption'] ?? 'tls') === 'ssl') ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int)($cfg['port'] ?? 587);
        $mail->CharSet    = $cfg['charset'] ?? 'UTF-8';
        $fromEmail        = $cfg['from_email'] ?? ($cfg['username'] ?? 'no-reply@example.com');
        $fromName         = $cfg['from_name'] ?? 'Stygian Blue Studio';
        try { $mail->setFrom($fromEmail, $fromName); } catch (\Throwable $e) { /* ignore */ }
    }
}

return $cfg;
