<?php
// config/db.php - Database Configuration & Safe Connection

// 1. Default local development settings (XAMPP)
$host = '127.0.0.1';
$user = 'root';
$pass = '';
$dbname = 'manhwa_db';
$port = 3306;
$baseUrl = '/manhwa/';

// 2. Load custom / production credentials if present (ignored by git for security)
if (file_exists(__DIR__ . '/db.local.php')) {
    require_once __DIR__ . '/db.local.php';
}

if (!defined('BASE_URL')) {
    define('BASE_URL', $baseUrl);
}
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__) . '/');
}
if (!defined('SITE_NAME')) {
    define('SITE_NAME', 'ManhwaFlow');
}
if (!defined('SITE_TAGLINE')) {
    define('SITE_TAGLINE', 'Read Webtoons & Digital Comics Online');
}

$isLocalDev = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', '::1']) 
    || strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost:') === 0;

try {
    // Connect to MySQL
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);

    // Ensure tables & seed data are initialized
    require_once __DIR__ . '/setup.php';
    initializeDatabase($pdo);

} catch (PDOException $e) {
    // If DB does not exist yet on local, attempt to create it
    if ($isLocalDev && (strpos($e->getMessage(), 'Unknown database') !== false || $e->getCode() == 1049)) {
        try {
            $pdoInit = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            $pdoInit->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
            $pdo = new PDO("mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
            require_once __DIR__ . '/setup.php';
            initializeDatabase($pdo);
        } catch (PDOException $ex) {
            dieDatabaseError($ex, $isLocalDev);
        }
    } else {
        dieDatabaseError($e, $isLocalDev);
    }
}

function dieDatabaseError($e, $isLocalDev) {
    $techError = $isLocalDev 
        ? "<p style='color:#a1a1aa;font-size:12px;font-family:monospace;word-break:break-all;'>Technical Error: " . htmlspecialchars($e->getMessage()) . "</p>"
        : "<p style='color:#a1a1aa;font-size:12px;'>The server is currently unable to establish a database connection. Please try again later.</p>";

    die("<div style='background:#12141a;color:#f87171;padding:24px;font-family:sans-serif;max-width:550px;margin:60px auto;border-radius:16px;border:1px solid #dc2626;'>
            <h2 style='margin-top:0;'>⚠️ Database Connection Error</h2>
            <p style='color:#e2e8f0;font-size:14px;'>Unable to connect to the database server.</p>
            {$techError}
         </div>");
}

function getPdo() {
    global $pdo;
    return $pdo;
}
