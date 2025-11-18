<?php
session_start();

require_once __DIR__ . '/database/config.php';
require_once __DIR__ . '/app/helpers/system_log.php';

if (isset($conn)) {
    record_system_log(
        $conn,
        'LOGOUT',
        'auth',
        null,
        [
            'ID_TK' => $_SESSION['ID_TK'] ?? null,
            'role'  => $_SESSION['role'] ?? ($_SESSION['ID_QUYEN'] ?? null)
        ]
    );
}

$_SESSION = array();

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

header('Location: login.php');
exit();
?>
