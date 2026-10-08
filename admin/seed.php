<?php
// admin/seed.php - Re-seed sample data
require_once __DIR__ . '/../config/db.php';
$pdo = getPdo();

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
    die("Error sa pag-seed: " . $e->getMessage());
}

