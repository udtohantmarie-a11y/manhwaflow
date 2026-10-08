<?php
// api/history.php - Reading History API for Resume Reading & Continue Reading
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/security.php';
startSecureSession();

$pdo = getPdo();

// Resolve persistent user token for logged-in or guest reader
$userId = $_SESSION['user_id'] ?? null;
if (!empty($userId)) {
    $userToken = 'user_' . $userId;
} else {
    if (empty($_COOKIE['guest_reader_token'])) {
        $userToken = 'guest_' . bin2hex(random_bytes(16));
        setcookie('guest_reader_token', $userToken, [
            'expires' => time() + (86400 * 365),
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    } else {
        $userToken = $_COOKIE['guest_reader_token'];
    }
}

$action = $_GET['action'] ?? 'list';

if ($action === 'save') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }

    $seriesId = trim($input['series_id'] ?? '');
    $seriesTitle = trim($input['series_title'] ?? 'Webtoon');
    $coverImage = trim($input['cover_image'] ?? '');
    $chapterId = trim($input['chapter_id'] ?? '');
    $chapterNumber = floatval($input['chapter_number'] ?? 1);
    $chapterTitle = trim($input['chapter_title'] ?? ('Chapter ' . $chapterNumber));
    $readUrl = trim($input['read_url'] ?? '');
    $scrollPercent = min(100, max(0, intval($input['scroll_percent'] ?? 0)));

    if (empty($seriesId) || empty($chapterId)) {
        echo json_encode(['success' => false, 'message' => 'Missing series or chapter information']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO `reading_history` 
                (`user_id`, `user_token`, `series_id`, `series_title`, `cover_image`, `chapter_id`, `chapter_number`, `chapter_title`, `read_url`, `scroll_percent`, `updated_at`)
            VALUES 
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
                `user_id` = VALUES(`user_id`),
                `series_title` = VALUES(`series_title`),
                `cover_image` = VALUES(`cover_image`),
                `chapter_id` = VALUES(`chapter_id`),
                `chapter_number` = VALUES(`chapter_number`),
                `chapter_title` = VALUES(`chapter_title`),
                `read_url` = VALUES(`read_url`),
                `scroll_percent` = VALUES(`scroll_percent`),
                `updated_at` = NOW()
        ");
        $stmt->execute([
            $userId,
            $userToken,
            $seriesId,
            $seriesTitle,
            $coverImage,
            $chapterId,
            $chapterNumber,
            $chapterTitle,
            $readUrl,
            $scrollPercent
        ]);

        echo json_encode(['success' => true, 'message' => 'Reading history updated']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'get') {
    $seriesId = trim($_GET['series_id'] ?? '');
    if (empty($seriesId)) {
        echo json_encode(['success' => false, 'history' => null]);
        exit;
    }

    try {
        if ($userId) {
            $stmt = $pdo->prepare("
                SELECT * FROM `reading_history` 
                WHERE (`user_id` = ? OR `user_token` = ?) AND `series_id` = ? 
                LIMIT 1
            ");
            $stmt->execute([$userId, $userToken, $seriesId]);
        } else {
            $stmt = $pdo->prepare("
                SELECT * FROM `reading_history` 
                WHERE `user_token` = ? AND `series_id` = ? 
                LIMIT 1
            ");
            $stmt->execute([$userToken, $seriesId]);
        }

        $row = $stmt->fetch();
        echo json_encode(['success' => true, 'history' => $row ?: null]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'history' => null]);
    }
    exit;
}

if ($action === 'list') {
    $limit = min(50, max(1, intval($_GET['limit'] ?? 20)));

    try {
        if ($userId) {
            $stmt = $pdo->prepare("
                SELECT * FROM `reading_history` 
                WHERE `user_id` = ? OR `user_token` = ?
                ORDER BY `updated_at` DESC 
                LIMIT ?
            ");
            $stmt->bindValue(1, $userId, PDO::PARAM_INT);
            $stmt->bindValue(2, $userToken, PDO::PARAM_STR);
            $stmt->bindValue(3, $limit, PDO::PARAM_INT);
            $stmt->execute();
        } else {
            $stmt = $pdo->prepare("
                SELECT * FROM `reading_history` 
                WHERE `user_token` = ?
                ORDER BY `updated_at` DESC 
                LIMIT ?
            ");
            $stmt->bindValue(1, $userToken, PDO::PARAM_STR);
            $stmt->bindValue(2, $limit, PDO::PARAM_INT);
            $stmt->execute();
        }

        $list = $stmt->fetchAll();
        echo json_encode(['success' => true, 'history' => $list]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'history' => []]);
    }
    exit;
}

if ($action === 'remove') {
    $seriesId = trim($_POST['series_id'] ?? $_GET['series_id'] ?? '');
    if (!empty($seriesId)) {
        try {
            if ($userId) {
                $stmt = $pdo->prepare("DELETE FROM `reading_history` WHERE (`user_id` = ? OR `user_token` = ?) AND `series_id` = ?");
                $stmt->execute([$userId, $userToken, $seriesId]);
            } else {
                $stmt = $pdo->prepare("DELETE FROM `reading_history` WHERE `user_token` = ? AND `series_id` = ?");
                $stmt->execute([$userToken, $seriesId]);
            }
            echo json_encode(['success' => true]);
            exit;
        } catch (PDOException $e) {}
    }
    echo json_encode(['success' => false]);
    exit;
}

if ($action === 'clear') {
    try {
        if ($userId) {
            $stmt = $pdo->prepare("DELETE FROM `reading_history` WHERE `user_id` = ? OR `user_token` = ?");
            $stmt->execute([$userId, $userToken]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM `reading_history` WHERE `user_token` = ?");
            $stmt->execute([$userToken]);
        }
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);

