<?php
// admin/delete.php - Secure Delete Manhwa or Chapter
require_once __DIR__ . '/auth_check.php';
$pdo = getPdo();

$csrf = $_REQUEST['csrf'] ?? ($_REQUEST['csrf_token'] ?? '');
if (!verifyCsrfToken($csrf)) {
    http_response_code(403);
    die("Security verification failed (Invalid CSRF token). <a href='" . BASE_URL . "admin/index.php'>Bumalik sa Admin Dashboard</a>");
}

$type = $_REQUEST['type'] ?? '';
$id = intval($_REQUEST['id'] ?? 0);

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
