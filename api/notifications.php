<?php
// api/notifications.php - Live Notification System for User's Bookmarked Series
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/mangadex.php';
$pdo = getPdo();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isLoggedIn = isset($_SESSION['user_id']);
$userId = $isLoggedIn ? intval($_SESSION['user_id']) : 0;
$action = $_REQUEST['action'] ?? 'get';

if (!$isLoggedIn) {
    echo json_encode([
        'logged_in' => false,
        'unread_count' => 0,
        'notifications' => [],
        'message' => 'Sign in to receive notifications for your bookmarked series.'
    ]);
    exit;
}

// 1. Mark Notifications as Read
if ($action === 'mark_read') {
    $upd = $pdo->prepare("UPDATE user_notifications SET is_read = 1 WHERE user_id = ?");
    $upd->execute([$userId]);
    echo json_encode(['success' => true, 'unread_count' => 0]);
    exit;
}

// 2. Fetch / Check Updates for Bookmarked Series
if ($action === 'get' || $action === 'poll') {
    // Fetch all series bookmarked by this user
    $bmStmt = $pdo->prepare("SELECT series_id, title, cover_image, last_known_chapter FROM user_bookmarks WHERE user_id = ?");
    $bmStmt->execute([$userId]);
    $bookmarks = $bmStmt->fetchAll();

    if (!empty($bookmarks)) {
        // Check recent chapters for bookmarked series
        foreach ($bookmarks as $bm) {
            $seriesId = $bm['series_id'];
            $isMdUuid = strlen($seriesId) > 20;

            if ($isMdUuid) {
                // Live MangaDex Series: Fetch latest chapter
                $chapters = MangaDexAPI::getChaptersLive($seriesId, 1);
                if (!empty($chapters)) {
                    $latestCh = end($chapters); // highest chapter in ASC or $chapters[count-1]
                    $chNum = $latestCh['chapter_number'];
                    $chId = $latestCh['id'];

                    // Check if notification already logged for this user + series + chapter
                    $notifCheck = $pdo->prepare("SELECT id FROM user_notifications WHERE user_id = ? AND series_id = ? AND message LIKE ?");
                    $notifCheck->execute([$userId, $seriesId, "%Chapter {$chNum}%"]);
                    
                    if (!$notifCheck->fetch()) {
                        // Insert new live update notification
                        $readUrl = BASE_URL . "reader.php?md_ch={$chId}&md_manga={$seriesId}&ch_num={$chNum}";
                        $ins = $pdo->prepare("
                            INSERT INTO user_notifications (user_id, series_id, title, message, read_url, cover_image, is_read)
                            VALUES (?, ?, ?, ?, ?, ?, 0)
                        ");
                        $ins->execute([
                            $userId,
                            $seriesId,
                            $bm['title'],
                            "Chapter {$chNum} is now available to read!",
                            $readUrl,
                            $bm['cover_image']
                        ]);

                        // Update last known chapter on bookmark
                        $updBm = $pdo->prepare("UPDATE user_bookmarks SET last_known_chapter = ? WHERE user_id = ? AND series_id = ?");
                        $updBm->execute([$chNum, $userId, $seriesId]);
                    }
                }
            } else {
                // Local MySQL Series
                $chLocal = $pdo->prepare("SELECT id, chapter_number FROM chapters WHERE manhwa_id = ? ORDER BY chapter_number DESC LIMIT 1");
                $chLocal->execute([intval($seriesId)]);
                $localChRow = $chLocal->fetch();

                if ($localChRow) {
                    $chNum = $localChRow['chapter_number'];
                    $notifCheck = $pdo->prepare("SELECT id FROM user_notifications WHERE user_id = ? AND series_id = ? AND message LIKE ?");
                    $notifCheck->execute([$userId, $seriesId, "%Chapter {$chNum}%"]);

                    if (!$notifCheck->fetch()) {
                        $readUrl = BASE_URL . "reader.php?chapter_id=" . $localChRow['id'];
                        $ins = $pdo->prepare("
                            INSERT INTO user_notifications (user_id, series_id, title, message, read_url, cover_image, is_read)
                            VALUES (?, ?, ?, ?, ?, ?, 0)
                        ");
                        $ins->execute([
                            $userId,
                            $seriesId,
                            $bm['title'],
                            "Chapter {$chNum} is now available to read!",
                            $readUrl,
                            $bm['cover_image']
                        ]);
                    }
                }
            }
        }
    }

    // Retrieve notifications for this user
    $notifStmt = $pdo->prepare("
        SELECT * FROM user_notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 15
    ");
    $notifStmt->execute([$userId]);
    $notifications = $notifStmt->fetchAll();

    // Count unread
    $unreadStmt = $pdo->prepare("SELECT COUNT(*) FROM user_notifications WHERE user_id = ? AND is_read = 0");
    $unreadStmt->execute([$userId]);
    $unreadCount = intval($unreadStmt->fetchColumn());

    echo json_encode([
        'logged_in' => true,
        'unread_count' => $unreadCount,
        'notifications' => $notifications,
        'has_bookmarks' => !empty($bookmarks)
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action.']);
exit;

