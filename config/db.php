<?php
// config/db.php

$host = '127.0.0.1';
$user = 'root';
$pass = '';
$dbname = 'manhwa_db';
$port = 3306;

define('BASE_URL', '/manhwa/');
define('ROOT_PATH', dirname(__DIR__) . '/');
define('SITE_NAME', 'ManhwaFlow');
define('SITE_TAGLINE', 'Read Webtoons & Digital Comics Online');

try {
    // 1. Initial connection without db to check/create database
    $pdoInit = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    $pdoInit->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");

    // 2. Connect to the specific database
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);

    // 3. Ensure tables & seed data are initialized
    require_once __DIR__ . '/setup.php';
    initializeDatabase($pdo);

} catch (PDOException $e) {
    die("<div style='background:#18181b;color:#f87171;padding:24px;font-family:sans-serif;max-width:600px;margin:40px auto;border-radius:12px;border:1px solid #dc2626;'>
            <h2 style='margin-top:0;'>⚠️ Database Connection Error</h2>
            <p>Hindi maka-connect sa MySQL. Siguraduhin na naka-start ang <strong>MySQL</strong> sa iyong XAMPP Control Panel.</p>
            <p style='color:#a1a1aa;font-size:13px;word-break:break-all;'>Technical Error: " . htmlspecialchars($e->getMessage()) . "</p>
         </div>");
}

function getPdo() {
    global $pdo;
    return $pdo;
}

