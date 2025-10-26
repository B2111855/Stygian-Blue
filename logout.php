<?php
session_start(); // Start the session

// Clear all session variables
$_SESSION = array();

// If the session was started with cookies, delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    // Set the cookie expiration to a time in the past
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: login.php");
exit();
?>
