<?php
// api/rewards.php - Flow Rewards & Payout API
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/security.php';
startSecureSession();
$pdo = getPdo();

$action = $_GET['action'] ?? ($_POST['action'] ?? 'get_status');
$userId = $_SESSION['user_id'] ?? null;

// Rates table
$COIN_RATES = [
    10  => 2500,  // ₱10 = 2,500 coins
    25  => 5000,  // ₱25 = 5,000 coins
    50  => 10000, // ₱50 = 10,000 coins
    100 => 20000  // ₱100 = 20,000 coins
];

// Helper to get or initialize rewards
function getUserRewardsRow($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT * FROM `user_rewards` WHERE `user_id` = ?");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if (!$row) {
        $insert = $pdo->prepare("INSERT INTO `user_rewards` (`user_id`, `coins`, `total_earned`, `streak_days`) VALUES (?, 0, 0, 0)");
        $insert->execute([$userId]);
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
    }
    return $row;
}

// Helper to compute hunter rank
function getHunterRank($totalEarned) {
    if ($totalEarned >= 10000) return ['rank' => 'Rank S', 'title' => 'Shadow Monarch', 'color' => 'from-purple-600 to-indigo-600', 'badge' => '👑'];
    if ($totalEarned >= 5000)  return ['rank' => 'Rank A', 'title' => 'High Ranker', 'color' => 'from-rose-500 to-amber-500', 'badge' => '🔥'];
    if ($totalEarned >= 1500)  return ['rank' => 'Rank B', 'title' => 'Elite Hunter', 'color' => 'from-blue-500 to-cyan-500', 'badge' => '⚡'];
    if ($totalEarned >= 500)   return ['rank' => 'Rank C', 'title' => 'Awakened Hunter', 'color' => 'from-emerald-500 to-teal-500', 'badge' => '⚔️'];
    if ($totalEarned >= 100)   return ['rank' => 'Rank D', 'title' => 'Apprentice', 'color' => 'from-slate-500 to-slate-400', 'badge' => '🛡️'];
    return ['rank' => 'Rank E', 'title' => 'Novice Reader', 'color' => 'from-slate-600 to-slate-700', 'badge' => '🌱'];
}

// Unauthenticated response for guest users
if (!$userId) {
    if ($action === 'get_status') {
        echo json_encode([
            'success' => true,
            'is_logged_in' => false,
            'coins' => 0,
            'streak_days' => 0,
            'can_checkin_today' => false,
            'can_sponsor_today' => true,
            'rank' => getHunterRank(0),
            'rates' => $COIN_RATES,
            'message' => 'Sign in to sync coins and request cashouts!'
        ]);
        exit;
    } else {
        echo json_encode([
            'success' => false,
            'requires_login' => true,
            'message' => 'Please sign in to earn coins and request cashouts!'
        ]);
        exit;
    }
}

$rewards = getUserRewardsRow($pdo, $userId);
$today = date('Y-m-d');

// --- 1. GET STATUS ---
if ($action === 'get_status') {
    $canCheckin = ($rewards['last_checkin_date'] !== $today);
    $canSponsor = ($rewards['last_sponsor_date'] !== $today);

    $lastAdDate = $rewards['last_ad_date'] ?? null;
    $adsWatchedToday = ($lastAdDate === $today) ? intval($rewards['ads_watched_today'] ?? 0) : 0;
    $canWatchAd = ($adsWatchedToday < 5);

    echo json_encode([
        'success' => true,
        'is_logged_in' => true,
        'coins' => intval($rewards['coins']),
        'total_earned' => intval($rewards['total_earned']),
        'streak_days' => intval($rewards['streak_days']),
        'chapters_read' => intval($rewards['chapters_read_count']),
        'can_checkin_today' => $canCheckin,
        'can_sponsor_today' => $canSponsor,
        'ads_watched_today' => $adsWatchedToday,
        'can_watch_ad' => $canWatchAd,
        'max_daily_ads' => 5,
        'rank' => getHunterRank($rewards['total_earned']),
        'default_payout_method' => $rewards['default_payout_method'] ?? 'gcash',
        'default_account_name' => $rewards['default_account_name'] ?? '',
        'default_account_number' => $rewards['default_account_number'] ?? '',
        'rates' => $COIN_RATES
    ]);
    exit;
}

// --- 2. DAILY CHECK-IN ---
if ($action === 'checkin') {
    if ($rewards['last_checkin_date'] === $today) {
        echo json_encode(['success' => false, 'message' => 'You have already checked in today! Come back tomorrow for your next reward.']);
        exit;
    }

    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $newStreak = 1;
    if ($rewards['last_checkin_date'] === $yesterday) {
        $newStreak = ($rewards['streak_days'] % 7) + 1;
    }

    // Streak rewards table
    $streakRewards = [1 => 10, 2 => 15, 3 => 20, 4 => 25, 5 => 30, 6 => 40, 7 => 60];
    $earnedCoins = $streakRewards[$newStreak] ?? 10;

    $stmt = $pdo->prepare("
        UPDATE `user_rewards` 
        SET `coins` = `coins` + ?, 
            `total_earned` = `total_earned` + ?, 
            `streak_days` = ?, 
            `last_checkin_date` = ? 
        WHERE `user_id` = ?
    ");
    $stmt->execute([$earnedCoins, $earnedCoins, $newStreak, $today, $userId]);

    // Log reward
    $log = $pdo->prepare("INSERT INTO `reward_logs` (`user_id`, `action_type`, `coins`, `description`) VALUES (?, 'checkin', ?, ?)");
    $log->execute([$userId, $earnedCoins, "Day {$newStreak} Daily Check-In Bonus"]);

    $newRow = getUserRewardsRow($pdo, $userId);
    echo json_encode([
        'success' => true,
        'earned_coins' => $earnedCoins,
        'coins' => intval($newRow['coins']),
        'streak_days' => $newStreak,
        'message' => "Successfully claimed Day {$newStreak} reward of +{$earnedCoins} Flow Coins!"
    ]);
    exit;
}

// --- 3. READ CHAPTER COMPLETION REWARD ---
if ($action === 'read_chapter') {
    $chapterId = trim($_POST['chapter_id'] ?? '');
    if (empty($chapterId)) {
        echo json_encode(['success' => false, 'message' => 'Missing chapter ID']);
        exit;
    }

    // Strictly check if user already claimed reward for this specific chapter
    $checkChapter = $pdo->prepare("SELECT 1 FROM `user_chapter_rewards` WHERE `user_id` = ? AND `chapter_id` = ?");
    $checkChapter->execute([$userId, $chapterId]);
    if ($checkChapter->fetch()) {
        echo json_encode([
            'success' => true,
            'awarded' => false,
            'already_claimed' => true,
            'coins' => intval($rewards['coins']),
            'message' => 'You have already claimed your coin reward for this chapter.'
        ]);
        exit;
    }

    // Record chapter reward claim
    try {
        $ins = $pdo->prepare("INSERT INTO `user_chapter_rewards` (`user_id`, `chapter_id`) VALUES (?, ?)");
        $ins->execute([$userId, $chapterId]);
    } catch(Exception $e) {
        echo json_encode([
            'success' => true,
            'awarded' => false,
            'already_claimed' => true,
            'coins' => intval($rewards['coins'])
        ]);
        exit;
    }

    $earnedCoins = 5;
    $stmt = $pdo->prepare("
        UPDATE `user_rewards` 
        SET `coins` = `coins` + ?, 
            `total_earned` = `total_earned` + ?, 
            `chapters_read_count` = `chapters_read_count` + 1 
        WHERE `user_id` = ?
    ");
    $stmt->execute([$earnedCoins, $earnedCoins, $userId]);

    $log = $pdo->prepare("INSERT INTO `reward_logs` (`user_id`, `action_type`, `coins`, `description`) VALUES (?, 'read_chapter', ?, ?)");
    $log->execute([$userId, $earnedCoins, "Finished reading chapter {$chapterId}"]);

    $newRow = getUserRewardsRow($pdo, $userId);
    echo json_encode([
        'success' => true,
        'awarded' => true,
        'earned_coins' => $earnedCoins,
        'coins' => intval($newRow['coins']),
        'message' => "+{$earnedCoins} Flow Coins earned for completing this chapter!"
    ]);
    exit;
}

// --- 4. DAILY SPONSOR QUEST (MONETAG LINK) ---
if ($action === 'sponsor_quest') {
    if ($rewards['last_sponsor_date'] === $today) {
        echo json_encode([
            'success' => false, 
            'message' => 'You have already claimed your Daily Sponsor Bonus today! Come back tomorrow for another +50 coins.'
        ]);
        exit;
    }

    $earnedCoins = 50;
    $stmt = $pdo->prepare("
        UPDATE `user_rewards` 
        SET `coins` = `coins` + ?, 
            `total_earned` = `total_earned` + ?, 
            `last_sponsor_date` = ? 
        WHERE `user_id` = ?
    ");
    $stmt->execute([$earnedCoins, $earnedCoins, $today, $userId]);

    $log = $pdo->prepare("INSERT INTO `reward_logs` (`user_id`, `action_type`, `coins`, `description`) VALUES (?, 'sponsor_offer', ?, ?)");
    $log->execute([$userId, $earnedCoins, "Daily Partner Sponsor Exploration"]);

    $newRow = getUserRewardsRow($pdo, $userId);
    echo json_encode([
        'success' => true,
        'earned_coins' => $earnedCoins,
        'coins' => intval($newRow['coins']),
        'sponsor_url' => 'https://uplcm.com/4/11983803',
        'message' => "+{$earnedCoins} Flow Coins added to your balance!"
    ]);
    exit;
}

// --- 5. DAILY ADS TASK (0/5) ---
if ($action === 'watch_ad') {
    $lastAdDate = $rewards['last_ad_date'] ?? null;
    $currentCount = ($lastAdDate === $today) ? intval($rewards['ads_watched_today'] ?? 0) : 0;

    if ($currentCount >= 5) {
        echo json_encode([
            'success' => false,
            'message' => 'Daily ad watch task completed (5/5)! Resets at midnight tomorrow.'
        ]);
        exit;
    }

    $newCount = $currentCount + 1;
    // 20 coins per ad, with +30 bonus on the 5th ad = 50 coins
    $earnedCoins = ($newCount === 5) ? 50 : 20;

    $stmt = $pdo->prepare("
        UPDATE `user_rewards` 
        SET `coins` = `coins` + ?, 
            `total_earned` = `total_earned` + ?, 
            `ads_watched_today` = ?,
            `last_ad_date` = ? 
        WHERE `user_id` = ?
    ");
    $stmt->execute([$earnedCoins, $earnedCoins, $newCount, $today, $userId]);

    $logDesc = "Daily Ad Watch ({$newCount}/5)" . ($newCount === 5 ? " + Completion Bonus" : "");
    $log = $pdo->prepare("INSERT INTO `reward_logs` (`user_id`, `action_type`, `coins`, `description`) VALUES (?, 'ad_watch', ?, ?)");
    $log->execute([$userId, $earnedCoins, $logDesc]);

    $newRow = getUserRewardsRow($pdo, $userId);
    echo json_encode([
        'success' => true,
        'earned_coins' => $earnedCoins,
        'current_count' => $newCount,
        'max_count' => 5,
        'coins' => intval($newRow['coins']),
        'sponsor_url' => 'https://uplcm.com/4/11983803',
        'message' => ($newCount === 5) 
            ? "🎉 Amazing! Completed 5/5 ads! +{$earnedCoins} Coins credited (includes completion bonus)!"
            : "Watched ad {$newCount}/5! +{$earnedCoins} Flow Coins credited to your balance."
    ]);
    exit;
}

// --- 6. REDEEM MONTHLY PROMO CODE ---
if ($action === 'redeem_code') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?? $_POST;
    $inputCode = strtoupper(trim($data['code'] ?? ($_GET['code'] ?? '')));

    if (empty($inputCode)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a redeem code.']);
        exit;
    }

    // Lookup code in database
    $cStmt = $pdo->prepare("SELECT * FROM `redeem_codes` WHERE UPPER(`code`) = ? AND `is_active` = 1");
    $cStmt->execute([$inputCode]);
    $codeRow = $cStmt->fetch();

    if (!$codeRow) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid or unknown redeem code. Visit and follow our official Facebook page for the latest monthly codes!'
        ]);
        exit;
    }

    // Check expiration
    if (!empty($codeRow['expires_at']) && strtotime($codeRow['expires_at']) < time()) {
        echo json_encode([
            'success' => false,
            'message' => 'This redeem code has expired. Check our Facebook page for new monthly codes!'
        ]);
        exit;
    }

    // Check max uses limit
    if (intval($codeRow['max_uses']) > 0 && intval($codeRow['used_count']) >= intval($codeRow['max_uses'])) {
        echo json_encode([
            'success' => false,
            'message' => 'This redeem code has reached its maximum claim limit.'
        ]);
        exit;
    }

    // Check if user already claimed this code
    $checkUsed = $pdo->prepare("SELECT id FROM `user_redeemed_codes` WHERE `user_id` = ? AND `code_id` = ?");
    $checkUsed->execute([$userId, $codeRow['id']]);
    if ($checkUsed->fetch()) {
        echo json_encode([
            'success' => false,
            'message' => 'You have already redeemed this code! Each account can only claim each monthly code once.'
        ]);
        exit;
    }

    $rewardCoins = intval($codeRow['coins']);

    // Process redemption in transaction
    $pdo->beginTransaction();
    try {
        $insRedeem = $pdo->prepare("INSERT INTO `user_redeemed_codes` (`user_id`, `code_id`, `coins_awarded`) VALUES (?, ?, ?)");
        $insRedeem->execute([$userId, $codeRow['id'], $rewardCoins]);

        $upCode = $pdo->prepare("UPDATE `redeem_codes` SET `used_count` = `used_count` + 1 WHERE `id` = ?");
        $upCode->execute([$codeRow['id']]);

        $upUser = $pdo->prepare("UPDATE `user_rewards` SET `coins` = `coins` + ?, `total_earned` = `total_earned` + ? WHERE `user_id` = ?");
        $upUser->execute([$rewardCoins, $rewardCoins, $userId]);

        $log = $pdo->prepare("INSERT INTO `reward_logs` (`user_id`, `action_type`, `coins`, `description`) VALUES (?, 'redeem_code', ?, ?)");
        $log->execute([$userId, $rewardCoins, "Redeemed monthly code: {$inputCode}"]);

        $pdo->commit();

        $newRow = getUserRewardsRow($pdo, $userId);
        echo json_encode([
            'success' => true,
            'earned_coins' => $rewardCoins,
            'coins' => intval($newRow['coins']),
            'message' => "🎉 Code redeemed successfully! +{$rewardCoins} Flow Coins added to your account balance!"
        ]);
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Could not process redeem code. Please try again.']);
        exit;
    }
}

// --- 7. REQUEST CASHOUT / PAYOUT ---
if ($action === 'request_payout') {
    $csrf = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!verifyCsrfToken($csrf)) {
        echo json_encode(['success' => false, 'message' => 'Security token invalid or expired. Please refresh the page.']);
        exit;
    }

    $amountPhp = intval($_POST['amount_php'] ?? 0);
    $rawMethod = strtolower(trim($_POST['payout_method'] ?? 'gcash'));
    $method = in_array($rawMethod, ['gcash', 'maya', 'load']) ? $rawMethod : 'gcash';
    $accName = strip_tags(trim($_POST['account_name'] ?? ''));
    $accNumber = preg_replace('/[^0-9+ -]/', '', trim($_POST['account_number'] ?? ''));

    if (!isset($COIN_RATES[$amountPhp])) {
        echo json_encode(['success' => false, 'message' => 'Invalid payout amount.']);
        exit;
    }

    $requiredCoins = $COIN_RATES[$amountPhp];
    if ($rewards['coins'] < $requiredCoins) {
        echo json_encode([
            'success' => false, 
            'message' => "Insufficient coins! You need {$requiredCoins} coins for ₱{$amountPhp}. Current balance: {$rewards['coins']} coins."
        ]);
        exit;
    }

    if (empty($accName) || empty($accNumber) || strlen($accNumber) < 10) {
        echo json_encode(['success' => false, 'message' => 'Please provide a valid Account Name and Mobile Number (at least 10 digits).']);
        exit;
    }

    // Deduct coins & insert payout request in transaction
    $pdo->beginTransaction();
    try {
        $deduct = $pdo->prepare("UPDATE `user_rewards` SET `coins` = `coins` - ? WHERE `user_id` = ?");
        $deduct->execute([$requiredCoins, $userId]);

        $stmt = $pdo->prepare("
            INSERT INTO `payout_requests` (`user_id`, `amount_php`, `coins_deducted`, `payout_method`, `account_name`, `account_number`, `status`) 
            VALUES (?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$userId, $amountPhp, $requiredCoins, $method, $accName, $accNumber]);

        // Auto-save default payout settings
        $upDefaults = $pdo->prepare("UPDATE `user_rewards` SET `default_payout_method` = ?, `default_account_name` = ?, `default_account_number` = ? WHERE `user_id` = ?");
        $upDefaults->execute([$method, $accName, $accNumber, $userId]);

        $log = $pdo->prepare("INSERT INTO `reward_logs` (`user_id`, `action_type`, `coins`, `description`) VALUES (?, 'payout_request', ?, ?)");
        $log->execute([$userId, -$requiredCoins, "Redeemed ₱{$amountPhp} {$method} payout"]);

        $pdo->commit();

        $newRow = getUserRewardsRow($pdo, $userId);
        echo json_encode([
            'success' => true,
            'coins' => intval($newRow['coins']),
            'message' => "Successfully submitted payout request for ₱{$amountPhp} to {$accNumber}! Funds will be sent via {$method} within 24-48 hours."
        ]);
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'An error occurred while submitting your request. Please try again later.']);
        exit;
    }
}

// --- 6. USER PAYOUT HISTORY ---
if ($action === 'payout_history') {
    $stmt = $pdo->prepare("
        SELECT `id`, `amount_php`, `coins_deducted`, `payout_method`, `account_number`, `status`, `created_at` 
        FROM `payout_requests` 
        WHERE `user_id` = ? 
        ORDER BY `id` DESC 
        LIMIT 20
    ");
    $stmt->execute([$userId]);
    $history = $stmt->fetchAll();

    echo json_encode(['success' => true, 'history' => $history]);
    exit;
}

// --- 7. SAVE PAYOUT SETTINGS ---
if ($action === 'save_payout_settings') {
    $csrf = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!verifyCsrfToken($csrf)) {
        echo json_encode(['success' => false, 'message' => 'Security token invalid or expired. Please refresh the page.']);
        exit;
    }

    $rawMethod = strtolower(trim($_POST['default_payout_method'] ?? 'gcash'));
    $method = in_array($rawMethod, ['gcash', 'maya', 'load']) ? $rawMethod : 'gcash';
    $name = strip_tags(trim($_POST['default_account_name'] ?? ''));
    $number = preg_replace('/[^0-9+ -]/', '', trim($_POST['default_account_number'] ?? ''));

    if (empty($name) || empty($number) || strlen($number) < 10) {
        echo json_encode(['success' => false, 'message' => 'Please provide a valid Account Name and Mobile Number (at least 10 digits).']);
        exit;
    }

    $up = $pdo->prepare("
        UPDATE `user_rewards` 
        SET `default_payout_method` = ?, `default_account_name` = ?, `default_account_number` = ? 
        WHERE `user_id` = ?
    ");
    $up->execute([$method, $name, $number, $userId]);

    echo json_encode(['success' => true, 'message' => 'Payout details successfully saved!']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);

