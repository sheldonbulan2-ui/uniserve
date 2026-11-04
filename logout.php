<?php
session_start();

// Get session cookie settings before clearing the session.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        "",
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Clear all session data.
$_SESSION = [];

// Destroy the session.
session_destroy();

// Redirect to login page.
header("Location: login.php");
exit();
?>