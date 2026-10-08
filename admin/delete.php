<?php
// admin/delete.php - Delete manhwa or chapter
require_once __DIR__ . '/../config/db.php';
$pdo = getPdo();

$type = $_GET['type'] ?? '';
$id = intval($_GET['id'] ?? 0);

if ($id > 0) {
    if ($type === 'manhwa') {
        $stmt = $pdo->prepare("DELETE FROM `manhwas` WHERE id = ?");
        $stmt->execute([$id]);
    } elseif ($type === 'chapter') {
        $stmt = $pdo->prepare("DELETE FROM `chapters` WHERE id = ?");
        $stmt->execute([$id]);
    }
}

header("Location: " . BASE_URL . "admin/index.php");
exit;

