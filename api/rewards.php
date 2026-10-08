<?php
// api/rewards.php - Flow Rewards & Payout API
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';
$pdo = getPdo();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
            'message' => 'Kailangan naka-sign in ka para mag-ipon ng coins at mag-cashout!'
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

    echo json_encode([
        'success' => true,
        'is_logged_in' => true,
        'coins' => intval($rewards['coins']),
        'total_earned' => intval($rewards['total_earned']),
        'streak_days' => intval($rewards['streak_days']),
        'chapters_read' => intval($rewards['chapters_read_count']),
        'can_checkin_today' => $canCheckin,
        'can_sponsor_today' => $canSponsor,
        'rank' => getHunterRank($rewards['total_earned']),
        'rates' => $COIN_RATES
    ]);
    exit;
}

// --- 2. DAILY CHECK-IN ---
if ($action === 'checkin') {
    if ($rewards['last_checkin_date'] === $today) {
        echo json_encode(['success' => false, 'message' => 'Naka-check in ka na ngayong araw! Bumalik ulit bukas para sa susunod na reward.']);
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
        'message' => "Matagumpay mong nakuha ang Day {$newStreak} reward na +{$earnedCoins} Flow Coins!"
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

    // Cooldown prevention (5 seconds between awards)
    $lastReadTime = $_SESSION['last_reward_read_time'] ?? 0;
    $now = time();
    if ($now - $lastReadTime < 5) {
        echo json_encode(['success' => true, 'coins' => intval($rewards['coins']), 'awarded' => false]);
        exit;
    }
    $_SESSION['last_reward_read_time'] = $now;

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
    $log->execute([$userId, $earnedCoins, "Finished reading chapter"]);

    $newRow = getUserRewardsRow($pdo, $userId);
    echo json_encode([
        'success' => true,
        'awarded' => true,
        'earned_coins' => $earnedCoins,
        'coins' => intval($newRow['coins']),
        'message' => "+{$earnedCoins} Coins para sa pagtatapos ng chapter!"
    ]);
    exit;
}

// --- 4. DAILY SPONSOR QUEST (MONETAG LINK) ---
if ($action === 'sponsor_quest') {
    if ($rewards['last_sponsor_date'] === $today) {
        echo json_encode([
            'success' => false, 
            'message' => 'Nakuha mo na ang Daily Sponsor Bonus ngayong araw! Bumalik bukas para sa panibagong +50 coins.'
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
        'message' => "+{$earnedCoins} Flow Coins ang naidagdag sa iyong balance!"
    ]);
    exit;
}

// --- 5. REQUEST CASHOUT / PAYOUT ---
if ($action === 'request_payout') {
    $amountPhp = intval($_POST['amount_php'] ?? 0);
    $method = trim($_POST['payout_method'] ?? 'gcash');
    $accName = trim($_POST['account_name'] ?? '');
    $accNumber = trim($_POST['account_number'] ?? '');

    if (!isset($COIN_RATES[$amountPhp])) {
        echo json_encode(['success' => false, 'message' => 'Di-wastong payout amount.']);
        exit;
    }

    $requiredCoins = $COIN_RATES[$amountPhp];
    if ($rewards['coins'] < $requiredCoins) {
        echo json_encode([
            'success' => false, 
            'message' => "Kulang ang iyong coins! Kailangan mo ng {$requiredCoins} coins para sa ₱{$amountPhp}. Kasalukuyang coins: {$rewards['coins']}."
        ]);
        exit;
    }

    if (empty($accName) || empty($accNumber)) {
        echo json_encode(['success' => false, 'message' => 'Pakilagay ang Account Name at Mobile Number.']);
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

        $log = $pdo->prepare("INSERT INTO `reward_logs` (`user_id`, `action_type`, `coins`, `description`) VALUES (?, 'payout_request', ?, ?)");
        $log->execute([$userId, -$requiredCoins, "Redeemed ₱{$amountPhp} {$method} payout"]);

        $pdo->commit();

        $newRow = getUserRewardsRow($pdo, $userId);
        echo json_encode([
            'success' => true,
            'coins' => intval($newRow['coins']),
            'message' => "Matagumpay na naisumite ang iyong payout request na ₱{$amountPhp} sa {$accNumber}! Ipapadala ito sa iyong {$method} sa loob ng 24-48 oras."
        ]);
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Nagkaroon ng error sa pagsusumite: ' . $e->getMessage()]);
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

echo json_encode(['success' => false, 'message' => 'Unknown action']);

