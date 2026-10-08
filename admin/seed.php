<?php
// admin/seed.php - Re-seed sample data (Admin Only with CSRF)
require_once __DIR__ . '/auth_check.php';
$pdo = getPdo();

$csrf = $_REQUEST['csrf'] ?? ($_REQUEST['csrf_token'] ?? '');
if (!verifyCsrfToken($csrf)) {
    http_response_code(403);
    die("Security verification failed (Invalid CSRF token). <a href='" . BASE_URL . "admin/index.php'>Bumalik sa Admin Dashboard</a>");
}

try {
    // Truncate existing data
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $pdo->exec("TRUNCATE TABLE `chapter_pages`;");
    $pdo->exec("TRUNCATE TABLE `chapters`;");
    $pdo->exec("TRUNCATE TABLE `manhwa_genres`;");
    $pdo->exec("TRUNCATE TABLE `manhwas`;");
    $pdo->exec("TRUNCATE TABLE `genres`;");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

    // Run setup again
    require_once __DIR__ . '/../config/setup.php';
    initializeDatabase($pdo);

    header("Location: " . BASE_URL . "admin/index.php?msg=seeded");
    exit;
} catch (Exception $e) {
    die("Error sa pag-seed: " . htmlspecialchars($e->getMessage()));
}
