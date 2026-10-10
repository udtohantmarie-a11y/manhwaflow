<?php
// api/comments.php - Chapter Comments API
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/security.php';
startSecureSession();
$pdo = getPdo();

$isLoggedIn = isset($_SESSION['user_id']);
$userId = $isLoggedIn ? intval($_SESSION['user_id']) : null;
$username = $isLoggedIn ? $_SESSION['username'] : 'Guest';
$avatar = $isLoggedIn ? ($_SESSION['avatar'] ?? 'default.png') : 'default.png';

$action = $_REQUEST['action'] ?? 'list';

// 1. Fetch Comments for a Chapter
if ($action === 'list') {
    $chapterId = trim($_GET['chapter_id'] ?? '');
    if (empty($chapterId)) {
        echo json_encode(['comments' => [], 'count' => 0]);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT id, chapter_id, series_id, author_name, avatar, comment, likes, created_at
        FROM chapter_comments 
        WHERE chapter_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$chapterId]);
    $comments = $stmt->fetchAll();

    echo json_encode([
        'comments' => $comments,
        'count' => count($comments),
        'is_logged_in' => $isLoggedIn,
        'current_user' => $username
    ]);
    exit;
}

// 2. Add New Comment
if ($action === 'add') {
    if (!$isLoggedIn) {
        echo json_encode([
            'success' => false,
            'auth_required' => true,
            'message' => 'Please sign in or create an account to post a comment.'
        ]);
        exit;
    }

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?? $_POST;

    // Rate limit comments per user
    $cLimit = checkRateLimit('comment_' . $userId, 10, 60);
    if (!$cLimit['allowed']) {
        echo json_encode(['success' => false, 'message' => 'You are posting comments too quickly. Please wait a moment before trying again.']);
        exit;
    }

    $chapterId = trim($data['chapter_id'] ?? '');
    $seriesId = trim($data['series_id'] ?? '');
    $comment = strip_tags(trim($data['comment'] ?? ''));

    if (empty($chapterId) || empty($comment)) {
        echo json_encode(['success' => false, 'message' => 'Comment text cannot be empty.']);
        exit;
    }

    if (strlen($comment) > 1000) {
        echo json_encode(['success' => false, 'message' => 'Comment is too long (maximum 1,000 characters).']);
        exit;
    }

    $ins = $pdo->prepare("
        INSERT INTO chapter_comments (chapter_id, series_id, user_id, author_name, avatar, comment, likes)
        VALUES (?, ?, ?, ?, ?, ?, 0)
    ");
    $ins->execute([$chapterId, $seriesId, $userId, $username, $avatar, $comment]);
    $newId = $pdo->lastInsertId();

    // Award Flow Coins for chapter commenting (+5 Flow Coins per comment, up to 5 times daily)
    $coinsAwarded = 0;
    try {
        $today = date('Y-m-d');
        $cCountStmt = $pdo->prepare("SELECT COUNT(*) FROM `chapter_comments` WHERE `user_id` = ? AND DATE(`created_at`) = ?");
        $cCountStmt->execute([$userId, $today]);
        $cCount = intval($cCountStmt->fetchColumn());

        if ($cCount <= 5) {
            $coinsAwarded = 5;
            $pdo->prepare("
                INSERT INTO `user_rewards` (`user_id`, `coins`, `total_earned`, `streak_days`) 
                VALUES (?, ?, ?, 0) 
                ON DUPLICATE KEY UPDATE `coins` = `coins` + ?, `total_earned` = `total_earned` + ?
            ")->execute([$userId, $coinsAwarded, $coinsAwarded, $coinsAwarded, $coinsAwarded]);

            try {
                $log = $pdo->prepare("INSERT INTO `reward_logs` (`user_id`, `action_type`, `coins`, `description`) VALUES (?, 'comment', ?, ?)");
                $log->execute([$userId, $coinsAwarded, "Chapter Discussion Comment on Chapter {$chapterId}"]);
            } catch (Exception $eLog) {}
        }
    } catch (Exception $e) {}

    echo json_encode([
        'success' => true,
        'coins_awarded' => $coinsAwarded,
        'message' => ($coinsAwarded > 0) ? "Comment posted! +{$coinsAwarded} Flow Coins earned! 🎉" : "Comment posted successfully!",
        'comment' => [
            'id' => $newId,
            'chapter_id' => $chapterId,
            'author_name' => htmlspecialchars($username, ENT_QUOTES, 'UTF-8'),
            'avatar' => htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8'),
            'comment' => htmlspecialchars($comment, ENT_QUOTES, 'UTF-8'),
            'likes' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ],
        'count' => $totalCount
    ]);
    exit;
}

// 3. Like a Comment
if ($action === 'like') {
    $commentId = intval($_POST['comment_id'] ?? ($_GET['comment_id'] ?? 0));
    if ($commentId > 0) {
        $upd = $pdo->prepare("UPDATE chapter_comments SET likes = likes + 1 WHERE id = ?");
        $upd->execute([$commentId]);

        $stmt = $pdo->prepare("SELECT likes FROM chapter_comments WHERE id = ?");
        $stmt->execute([$commentId]);
        $likes = intval($stmt->fetchColumn());

        echo json_encode(['success' => true, 'likes' => $likes]);
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Invalid action.']);
exit;

