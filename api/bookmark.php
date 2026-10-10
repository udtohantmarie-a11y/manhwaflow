<?php
// api/bookmark.php - User Bookmarks API (Account Required)
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/chapters_fallback.php';
require_once __DIR__ . '/../includes/mangadex.php';
startSecureSession();
$pdo = getPdo();

$isLoggedIn = isset($_SESSION['user_id']);
$userId = $isLoggedIn ? intval($_SESSION['user_id']) : 0;
$action = $_REQUEST['action'] ?? 'toggle';

// 1. Check Bookmark Status
if ($action === 'status') {
    $seriesId = trim($_GET['series_id'] ?? '');
    if (!$isLoggedIn || empty($seriesId)) {
        echo json_encode(['logged_in' => $isLoggedIn, 'bookmarked' => false, 'count' => 0]);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id FROM user_bookmarks WHERE user_id = ? AND series_id = ?");
    $stmt->execute([$userId, $seriesId]);
    $bookmarked = (bool)$stmt->fetch();

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM user_bookmarks WHERE user_id = ?");
    $countStmt->execute([$userId]);
    $count = intval($countStmt->fetchColumn());

    echo json_encode(['logged_in' => true, 'bookmarked' => $bookmarked, 'count' => $count]);
    exit;
}

// 2. Clear All Bookmarks
if ($action === 'clear') {
    if (!$isLoggedIn) {
        echo json_encode(['success' => false, 'auth_required' => true, 'message' => 'Please sign in to manage bookmarks.']);
        exit;
    }
    $del = $pdo->prepare("DELETE FROM user_bookmarks WHERE user_id = ?");
    $del->execute([$userId]);
    echo json_encode(['success' => true, 'count' => 0]);
    exit;
}

// 3. Toggle Bookmark (Account Required)
if ($action === 'toggle') {
    if (!$isLoggedIn) {
        echo json_encode([
            'success' => false,
            'auth_required' => true,
            'message' => 'Account required. Please log in or sign up to bookmark series.'
        ]);
        exit;
    }

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?? $_POST;

    $seriesId = trim($data['id'] ?? ($data['series_id'] ?? ''));
    $title = trim($data['title'] ?? '');
    $cover = trim($data['cover_image'] ?? ($data['cover'] ?? ''));
    $cover = ChaptersFallback::resolveCover($seriesId, $title, $cover);
    $status = trim($data['status'] ?? 'Ongoing');
    $rating = floatval($data['rating'] ?? 4.8);

    if (empty($seriesId) || empty($title)) {
        echo json_encode(['success' => false, 'message' => 'Invalid series details.']);
        exit;
    }

    // Check if exists
    $check = $pdo->prepare("SELECT id FROM user_bookmarks WHERE user_id = ? AND series_id = ?");
    $check->execute([$userId, $seriesId]);
    $existing = $check->fetch();

    if ($existing) {
        $del = $pdo->prepare("DELETE FROM user_bookmarks WHERE id = ?");
        $del->execute([$existing['id']]);
        $bookmarked = false;
    } else {
        $ins = $pdo->prepare("
            INSERT INTO user_bookmarks (user_id, series_id, title, cover_image, status, rating)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $ins->execute([$userId, $seriesId, $title, $cover, $status, $rating]);
        $bookmarked = true;
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM user_bookmarks WHERE user_id = ?");
    $countStmt->execute([$userId]);
    $count = intval($countStmt->fetchColumn());

    echo json_encode([
        'success' => true,
        'bookmarked' => $bookmarked,
        'count' => $count
    ]);
    exit;
}

// 4. Sync Guest Bookmarks (When logging in / registering)
if ($action === 'sync_guest') {
    if (!$isLoggedIn) {
        echo json_encode(['success' => false, 'message' => 'Please sign in to sync bookmarks.']);
        exit;
    }

    $raw = file_get_contents('php://input');
    $items = json_decode($raw, true) ?? [];
    if (is_array($items) && !empty($items)) {
        foreach ($items as $item) {
            $seriesId = trim($item['id'] ?? ($item['series_id'] ?? ''));
            $title = trim($item['title'] ?? '');
            $cover = trim($item['cover_image'] ?? ($item['cover'] ?? ''));
            $cover = ChaptersFallback::resolveCover($seriesId, $title, $cover);
            $status = trim($item['status'] ?? 'Ongoing');
            $rating = floatval($item['rating'] ?? 4.8);

            if (!empty($seriesId) && !empty($title)) {
                $check = $pdo->prepare("SELECT id FROM user_bookmarks WHERE user_id = ? AND series_id = ?");
                $check->execute([$userId, $seriesId]);
                if (!$check->fetch()) {
                    $ins = $pdo->prepare("
                        INSERT INTO user_bookmarks (user_id, series_id, title, cover_image, status, rating)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $ins->execute([$userId, $seriesId, $title, $cover, $status, $rating]);
                }
            }
        }
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM user_bookmarks WHERE user_id = ?");
    $countStmt->execute([$userId]);
    $count = intval($countStmt->fetchColumn());

    echo json_encode(['success' => true, 'count' => $count]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action.']);
exit;

