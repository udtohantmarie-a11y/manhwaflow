<?php
// logout.php - User Logout
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/config/db.php';
}
require_once __DIR__ . '/includes/security.php';

startSecureSession();

// Revoke persistent remember token from DB and cookies
revokePersistentLoginToken();

$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
setcookie('guest_reader_token', '', time() - 3600, '/');
session_destroy();

header("Location: " . BASE_URL);
exit;

