<?php
// rewards.php - Flow Rewards Hub & Redeem Store
require_once __DIR__ . '/config/db.php';
$pdo = getPdo();

require_once __DIR__ . '/includes/security.php';
startSecureSession();

$userId = $_SESSION['user_id'] ?? null;
$redeemAlert = null;

// Server-side Direct Native POST Handler (Bypasses InfinityFree aes.js AJAX bot barrier 100%)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $act = $_POST['action'];

    // 1. Redeem Code
    if ($act === 'redeem_code') {
        $inputCode = strtoupper(trim($_POST['code'] ?? ''));
        if (!$userId) {
            $redeemAlert = ['type' => 'rose', 'message' => 'Please sign in first to redeem promo codes!'];
        } elseif (empty($inputCode)) {
            $redeemAlert = ['type' => 'rose', 'message' => 'Mangyaring maglagay ng redeem code (Please enter a redeem code).'];
        } else {
            require_once __DIR__ . '/api/rewards.php';
            ensureRedeemInfrastructure($pdo);

            try {
                $cStmt = $pdo->prepare("SELECT * FROM `redeem_codes` WHERE UPPER(`code`) = ? AND `is_active` = 1");
                $cStmt->execute([$inputCode]);
                $codeRow = $cStmt->fetch();

                if (!$codeRow) {
                    $redeemAlert = [
                        'type' => 'rose',
                        'message' => 'Invalid or unknown redeem code. I-follow ang aming official Facebook page para sa mga active promo codes!'
                    ];
                } elseif (!empty($codeRow['expires_at']) && strtotime($codeRow['expires_at']) < time()) {
                    $redeemAlert = ['type' => 'rose', 'message' => 'This redeem code has expired. Check our Facebook page for new monthly codes!'];
                } elseif (intval($codeRow['max_uses']) > 0 && intval($codeRow['used_count']) >= intval($codeRow['max_uses'])) {
                    $redeemAlert = ['type' => 'rose', 'message' => 'This redeem code has reached its maximum claim limit.'];
                } else {
                    $checkUsed = $pdo->prepare("SELECT id FROM `user_redeemed_codes` WHERE `user_id` = ? AND `code_id` = ?");
                    $checkUsed->execute([$userId, $codeRow['id']]);
                    if ($checkUsed->fetch()) {
                        $redeemAlert = ['type' => 'amber', 'message' => 'Na-redeem mo na ang code na ito! (You have already claimed this promo code).' ];
                    } else {
                        $rewardCoins = intval($codeRow['coins']);
                        $pdo->beginTransaction();

                        $insRedeem = $pdo->prepare("INSERT INTO `user_redeemed_codes` (`user_id`, `code_id`, `coins_awarded`) VALUES (?, ?, ?)");
                        $insRedeem->execute([$userId, $codeRow['id'], $rewardCoins]);

                        $upCode = $pdo->prepare("UPDATE `redeem_codes` SET `used_count` = `used_count` + 1 WHERE `id` = ?");
                        $upCode->execute([$codeRow['id']]);

                        $upUser = $pdo->prepare("
                            INSERT INTO `user_rewards` (`user_id`, `coins`, `total_earned`, `streak_days`) 
                            VALUES (?, ?, ?, 0) 
                            ON DUPLICATE KEY UPDATE `coins` = `coins` + ?, `total_earned` = `total_earned` + ?
                        ");
                        $upUser->execute([$userId, $rewardCoins, $rewardCoins, $rewardCoins, $rewardCoins]);

                        try {
                            $log = $pdo->prepare("INSERT INTO `reward_logs` (`user_id`, `action_type`, `coins`, `description`) VALUES (?, 'redeem_code', ?, ?)");
                            $log->execute([$userId, $rewardCoins, "Redeemed promo code: {$inputCode}"]);
                        } catch (Exception $eLog) {}

                        $pdo->commit();

                        $redeemAlert = [
                            'type' => 'emerald',
                            'message' => "🎉 Tagumpay! Nakuha mo ang +{$rewardCoins} Flow Coins mula sa code na {$inputCode}!"
                        ];
                    }
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $redeemAlert = ['type' => 'rose', 'message' => 'Hindi ma-proseso ang redeem code: ' . $e->getMessage()];
            }
        }
    }

    // 2. Check-in
    elseif ($act === 'checkin') {
        if (!$userId) {
            $redeemAlert = ['type' => 'rose', 'message' => 'Please sign in first to claim daily bonus!'];
        } else {
            $today = date('Y-m-d');
            $uRew = $pdo->prepare("SELECT * FROM `user_rewards` WHERE `user_id` = ?");
            $uRew->execute([$userId]);
            $row = $uRew->fetch();

            if ($row && $row['last_checkin_date'] === $today) {
                $redeemAlert = ['type' => 'amber', 'message' => 'Naka-check in ka na ngayong araw! Magbalik bukas para sa susunod na reward.'];
            } else {
                $yesterday = date('Y-m-d', strtotime('-1 day'));
                $newStreak = 1;
                if ($row && $row['last_checkin_date'] === $yesterday) {
                    $newStreak = ($row['streak_days'] % 7) + 1;
                }
                $streakRewards = [1 => 10, 2 => 15, 3 => 20, 4 => 25, 5 => 30, 6 => 40, 7 => 60];
                $earnedCoins = $streakRewards[$newStreak] ?? 10;

                $pdo->prepare("
                    INSERT INTO `user_rewards` (`user_id`, `coins`, `total_earned`, `streak_days`, `last_checkin_date`) 
                    VALUES (?, ?, ?, ?, ?) 
                    ON DUPLICATE KEY UPDATE 
                        `coins` = `coins` + ?, 
                        `total_earned` = `total_earned` + ?, 
                        `streak_days` = ?, 
                        `last_checkin_date` = ?
                ")->execute([$userId, $earnedCoins, $earnedCoins, $newStreak, $today, $earnedCoins, $earnedCoins, $newStreak, $today]);

                try {
                    $log = $pdo->prepare("INSERT INTO `reward_logs` (`user_id`, `action_type`, `coins`, `description`) VALUES (?, 'checkin', ?, ?)");
                    $log->execute([$userId, $earnedCoins, "Day {$newStreak} Daily Check-In Bonus"]);
                } catch (Exception $eLog) {}

                $redeemAlert = [
                    'type' => 'emerald',
                    'message' => "🎉 Tagumpay! Nakuha mo ang Day {$newStreak} login bonus na +{$earnedCoins} Flow Coins!"
                ];
            }
        }
    }

    // 3. Watch Daily Ad
    elseif ($act === 'watch_ad') {
        $visitToken = trim($_POST['visit_token'] ?? '');
        $adblockStatus = trim($_POST['adblock_status'] ?? '');
        $challenge = $_SESSION['ad_visit_challenge'] ?? null;

        if (!$userId) {
            $redeemAlert = ['type' => 'rose', 'message' => 'Please sign in first to watch daily ads!'];
        } elseif ($adblockStatus === 'blocked') {
            $redeemAlert = ['type' => 'rose', 'message' => '⚠️ Naka-on ang iyong AdBlocker! Paki-disable muna ang AdBlocker o Brave Shields upang ma-claim ang Flow Coins.'];
        } elseif (empty($challenge) || empty($visitToken) || $challenge['token'] !== $visitToken || $challenge['type'] !== 'watch_ad') {
            $redeemAlert = ['type' => 'rose', 'message' => '⚠️ Hindi mo pa nabubuksan ang ad link! Mangyaring buksan at bisitahin muna ang link bago mag-claim.'];
        } elseif ((time() - intval($challenge['started_at'])) < 4) {
            $redeemAlert = ['type' => 'amber', 'message' => '⏳ Paki-antay at tingnan ang ad page nang hindi bababa sa 5 segundo bago mag-claim!'];
        } else {
            $today = date('Y-m-d');
            $uRew = $pdo->prepare("SELECT * FROM `user_rewards` WHERE `user_id` = ?");
            $uRew->execute([$userId]);
            $row = $uRew->fetch();
            $lastAdDate = $row['last_ad_date'] ?? null;
            $currentCount = ($row && $lastAdDate === $today) ? intval($row['ads_watched_today'] ?? 0) : 0;

            if ($currentCount >= 5) {
                $redeemAlert = ['type' => 'amber', 'message' => 'Kumpleto na ang 5/5 daily ads mo ngayong araw! Magre-reset ito bukas ng hatinggabi.'];
            } else {
                $newCount = $currentCount + 1;
                $earnedCoins = ($newCount === 5) ? 50 : 20;

                $pdo->prepare("
                    INSERT INTO `user_rewards` (`user_id`, `coins`, `total_earned`, `ads_watched_today`, `last_ad_date`) 
                    VALUES (?, ?, ?, ?, ?) 
                    ON DUPLICATE KEY UPDATE 
                        `coins` = `coins` + ?, 
                        `total_earned` = `total_earned` + ?, 
                        `ads_watched_today` = ?, 
                        `last_ad_date` = ?
                ")->execute([$userId, $earnedCoins, $earnedCoins, $newCount, $today, $earnedCoins, $earnedCoins, $newCount, $today]);

                unset($_SESSION['ad_visit_challenge']);

                try {
                    $logDesc = "Daily Ad Watch ({$newCount}/5)" . ($newCount === 5 ? " + Completion Bonus" : "");
                    $log = $pdo->prepare("INSERT INTO `reward_logs` (`user_id`, `action_type`, `coins`, `description`) VALUES (?, 'ad_watch', ?, ?)");
                    $log->execute([$userId, $earnedCoins, $logDesc]);
                } catch (Exception $eLog) {}

                $msg = ($newCount === 5)
                    ? "🎉 Kumpleto ang 5/5 ads quest! +{$earnedCoins} Coins credited (kasama ang completion bonus)!"
                    : "🎉 Napanood at nabisita ang ad {$newCount}/5! +{$earnedCoins} Flow Coins naidagdag sa iyong balance.";

                $redeemAlert = [
                    'type' => 'emerald',
                    'message' => $msg
                ];
            }
        }
    }

    // 4. Daily Sponsor Quest
    elseif ($act === 'sponsor_quest') {
        $visitToken = trim($_POST['visit_token'] ?? '');
        $adblockStatus = trim($_POST['adblock_status'] ?? '');
        $challenge = $_SESSION['ad_visit_challenge'] ?? null;

        if (!$userId) {
            $redeemAlert = ['type' => 'rose', 'message' => 'Please sign in first to claim daily sponsor bonus!'];
        } elseif ($adblockStatus === 'blocked') {
            $redeemAlert = ['type' => 'rose', 'message' => '⚠️ Naka-on ang iyong AdBlocker! Paki-disable muna ang AdBlocker o Brave Shields upang ma-claim ang Flow Coins.'];
        } elseif (empty($challenge) || empty($visitToken) || $challenge['token'] !== $visitToken || $challenge['type'] !== 'sponsor_quest') {
            $redeemAlert = ['type' => 'rose', 'message' => '⚠️ Hindi mo pa nabubuksan ang sponsor link! Mangyaring buksan at bisitahin muna ang link bago mag-claim.'];
        } elseif ((time() - intval($challenge['started_at'])) < 4) {
            $redeemAlert = ['type' => 'amber', 'message' => '⏳ Paki-antay at tingnan ang sponsor page nang hindi bababa sa 5 segundo bago mag-claim!'];
        } else {
            $today = date('Y-m-d');
            $uRew = $pdo->prepare("SELECT * FROM `user_rewards` WHERE `user_id` = ?");
            $uRew->execute([$userId]);
            $row = $uRew->fetch();

            if ($row && $row['last_sponsor_date'] === $today) {
                $redeemAlert = [
                    'type' => 'amber', 
                    'message' => 'Na-claim mo na ang Daily Sponsor Bonus ngayong araw! Magbalik bukas para sa panibagong +50 coins.'
                ];
            } else {
                $earnedCoins = 50;
                $pdo->prepare("
                    INSERT INTO `user_rewards` (`user_id`, `coins`, `total_earned`, `last_sponsor_date`) 
                    VALUES (?, ?, ?, ?) 
                    ON DUPLICATE KEY UPDATE 
                        `coins` = `coins` + ?, 
                        `total_earned` = `total_earned` + ?, 
                        `last_sponsor_date` = ?
                ")->execute([$userId, $earnedCoins, $earnedCoins, $today, $earnedCoins, $earnedCoins, $today]);

                unset($_SESSION['ad_visit_challenge']);

                try {
                    $log = $pdo->prepare("INSERT INTO `reward_logs` (`user_id`, `action_type`, `coins`, `description`) VALUES (?, 'sponsor_offer', ?, ?)");
                    $log->execute([$userId, $earnedCoins, "Daily Partner Sponsor Exploration"]);
                } catch (Exception $eLog) {}

                $redeemAlert = [
                    'type' => 'emerald',
                    'message' => "🎉 Tagumpay! Nabisita ang sponsor page at nakuha mo ang +{$earnedCoins} Flow Coins mula sa Daily Sponsor Offer!"
                ];
            }
        }
    }

    // 5. Save Payout Settings
    elseif ($act === 'save_payout_settings') {
        if (!$userId) {
            $redeemAlert = ['type' => 'rose', 'message' => 'Please sign in first!'];
        } else {
            $method = trim($_POST['default_payout_method'] ?? 'gcash');
            $name = trim($_POST['default_account_name'] ?? '');
            $number = trim($_POST['default_account_number'] ?? '');
            $pdo->prepare("
                INSERT INTO `user_rewards` (`user_id`, `default_payout_method`, `default_account_name`, `default_account_number`) 
                VALUES (?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                    `default_payout_method` = VALUES(`default_payout_method`), 
                    `default_account_name` = VALUES(`default_account_name`), 
                    `default_account_number` = VALUES(`default_account_number`)
            ")->execute([$userId, $method, $name, $number]);
            $redeemAlert = ['type' => 'emerald', 'message' => 'Na-save na ang iyong payout account details!'];
        }
    }

    // 6. Request Payout
    elseif ($act === 'request_payout') {
        if (!$userId) {
            $redeemAlert = ['type' => 'rose', 'message' => 'Please sign in first to request a payout!'];
        } else {
            $amountPhp = intval($_POST['amount_php'] ?? 0);
            $method = trim($_POST['payout_method'] ?? 'gcash');
            $name = trim($_POST['account_name'] ?? '');
            $number = trim($_POST['account_number'] ?? '');

            $rates = [10 => 2500, 25 => 5000, 50 => 10000, 100 => 20000];
            if (!isset($rates[$amountPhp])) {
                $redeemAlert = ['type' => 'rose', 'message' => 'Invalid cashout amount selected.'];
            } elseif (empty($name) || empty($number)) {
                $redeemAlert = ['type' => 'rose', 'message' => 'Please fill in your account name and mobile number.'];
            } else {
                $costCoins = $rates[$amountPhp];
                $uRew = $pdo->prepare("SELECT `coins` FROM `user_rewards` WHERE `user_id` = ?");
                $uRew->execute([$userId]);
                $currentCoins = intval($uRew->fetchColumn());

                if ($currentCoins < $costCoins) {
                    $redeemAlert = ['type' => 'rose', 'message' => "Kulang ang iyong coins! Kailangan mo ng " . number_format($costCoins) . " coins para sa ₱{$amountPhp}.00."];
                } else {
                    $pdo->beginTransaction();
                    $deduct = $pdo->prepare("UPDATE `user_rewards` SET `coins` = `coins` - ? WHERE `user_id` = ? AND `coins` >= ?");
                    $deduct->execute([$costCoins, $userId, $costCoins]);

                    $req = $pdo->prepare("
                        INSERT INTO `payout_requests` (`user_id`, `amount_php`, `coins_deducted`, `payout_method`, `account_name`, `account_number`, `status`) 
                        VALUES (?, ?, ?, ?, ?, ?, 'pending')
                    ");
                    $req->execute([$userId, $amountPhp, $costCoins, $method, $name, $number]);
                    $pdo->commit();

                    $redeemAlert = [
                        'type' => 'emerald',
                        'message' => "🎉 Tagumpay! Naisumite ang iyong cashout request para sa ₱{$amountPhp}.00! Ipapadala ang payout sa loob ng 24-48 oras."
                    ];
                }
            }
        }
    }
}

$page_title = 'Rewards Hub & GCash Redeem';
require_once __DIR__ . '/includes/header.php';
$userRewards = null;
$payoutHistory = [];

if ($userId) {
    $stmt = $pdo->prepare("SELECT * FROM `user_rewards` WHERE `user_id` = ?");
    $stmt->execute([$userId]);
    $userRewards = $stmt->fetch();

    if (!$userRewards) {
        $insert = $pdo->prepare("INSERT INTO `user_rewards` (`user_id`, `coins`, `total_earned`, `streak_days`) VALUES (?, 0, 0, 0)");
        $insert->execute([$userId]);
        $stmt->execute([$userId]);
        $userRewards = $stmt->fetch();
    }

    // Payout history
    $pStmt = $pdo->prepare("SELECT * FROM `payout_requests` WHERE `user_id` = ? ORDER BY `id` DESC LIMIT 10");
    $pStmt->execute([$userId]);
    $payoutHistory = $pStmt->fetchAll();
}

$today = date('Y-m-d');
$canCheckin = $userRewards ? ($userRewards['last_checkin_date'] !== $today) : false;
$canSponsor = $userRewards ? ($userRewards['last_sponsor_date'] !== $today) : true;
$lastAdDate = $userRewards['last_ad_date'] ?? null;
$adsWatchedToday = ($userRewards && $lastAdDate === $today) ? intval($userRewards['ads_watched_today'] ?? 0) : 0;
$canWatchAd = ($adsWatchedToday < 5);
$coins = $userRewards ? intval($userRewards['coins']) : 0;
$streak = $userRewards ? intval($userRewards['streak_days']) : 0;
$chaptersRead = $userRewards ? intval($userRewards['chapters_read_count']) : 0;
$totalEarned = $userRewards ? intval($userRewards['total_earned']) : 0;
$defaultMethod = $userRewards['default_payout_method'] ?? 'gcash';
$defaultName = $userRewards['default_account_name'] ?? '';
$defaultNumber = $userRewards['default_account_number'] ?? '';

// Hunter rank computation
function calcRank($total) {
    if ($total >= 10000) return ['rank' => 'Rank S', 'title' => 'Shadow Monarch', 'icon' => 'fa-crown', 'color' => 'text-purple-400', 'bg' => 'bg-purple-950/40 border-purple-500/40'];
    if ($total >= 5000)  return ['rank' => 'Rank A', 'title' => 'High Ranker', 'icon' => 'fa-fire', 'color' => 'text-rose-400', 'bg' => 'bg-rose-950/40 border-rose-500/40'];
    if ($total >= 1500)  return ['rank' => 'Rank B', 'title' => 'Elite Hunter', 'icon' => 'fa-bolt', 'color' => 'text-blue-400', 'bg' => 'bg-blue-950/40 border-blue-500/40'];
    if ($total >= 500)   return ['rank' => 'Rank C', 'title' => 'Awakened Hunter', 'icon' => 'fa-shield-halved', 'color' => 'text-emerald-400', 'bg' => 'bg-emerald-950/40 border-emerald-500/40'];
    if ($total >= 100)   return ['rank' => 'Rank D', 'title' => 'Apprentice', 'icon' => 'fa-certificate', 'color' => 'text-slate-300', 'bg' => 'bg-dark-800 border-dark-700'];
    return ['rank' => 'Rank E', 'title' => 'Novice Reader', 'icon' => 'fa-seedling', 'color' => 'text-slate-400', 'bg' => 'bg-dark-800 border-dark-700'];
}
$rankData = calcRank($totalEarned);
?>

<script src="<?= BASE_URL ?>assets/js/ads.js?v=<?= time() ?>"></script>
<div id="ad-detector-slot" class="adsbox ad-banner pub_300x250 text-ad" style="position:absolute;left:-9999px;top:-9999px;width:1px;height:1px;" aria-hidden="true">&nbsp;</div>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-10">

    <!-- AdBlock Warning Notification (Dynamic JS) -->
    <div id="adblock-alert-banner" class="hidden p-4 rounded-2xl bg-amber-500/15 border border-amber-500/40 text-amber-300 text-xs sm:text-sm font-semibold flex items-center justify-between gap-3 shadow-lg">
        <div class="flex items-center gap-2.5">
            <i class="fa-solid fa-shield-halved text-base sm:text-lg text-amber-400 shrink-0"></i>
            <span><strong>AdBlock Detected:</strong> Naka-on ang iyong ad blocker o Brave Shields. Paki-disable ito upang ma-claim ang Flow Coins mula sa Daily Ads at Partner Offers.</span>
        </div>
        <button type="button" onclick="document.getElementById('adblock-alert-banner').classList.add('hidden')" class="text-slate-400 hover:text-white p-1">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <!-- Global Flash Notification for Redeem Alert -->
    <?php if ($redeemAlert): ?>
        <div class="p-4 rounded-2xl bg-<?= $redeemAlert['type'] ?>-500/15 border border-<?= $redeemAlert['type'] ?>-500/40 text-<?= $redeemAlert['type'] ?>-300 text-xs sm:text-sm font-bold flex items-center justify-between gap-3 shadow-2xl animate-fadeIn">
            <div class="flex items-center gap-2.5">
                <i class="fa-solid <?= $redeemAlert['type'] === 'emerald' ? 'fa-circle-check text-base text-emerald-400' : 'fa-circle-exclamation text-base text-rose-400' ?>"></i>
                <span><?= htmlspecialchars($redeemAlert['message']) ?></span>
            </div>
            <button type="button" onclick="this.parentElement.remove()" class="text-slate-400 hover:text-white p-1">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <?php if (!empty($redeemAlert['open_url'])): ?>
            <script>
                window.addEventListener('DOMContentLoaded', function() {
                    window.open('<?= htmlspecialchars($redeemAlert['open_url'], ENT_QUOTES, 'UTF-8') ?>', '_blank');
                });
            </script>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Header Banner -->
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-brand-900 via-indigo-900 to-dark-900 border border-brand-500/30 p-6 sm:p-10 shadow-2xl">
        <div class="absolute -right-16 -top-16 w-64 h-64 bg-brand-500/20 rounded-full blur-3xl pointer-events-none"></div>
        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="space-y-2">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-brand-500/20 border border-brand-400/30 text-brand-300 text-xs font-bold uppercase tracking-wider">
                    <i class="fa-solid fa-coins"></i> Flow Rewards Program
                </div>
                <h1 class="text-2xl sm:text-4xl font-black text-white tracking-tight">
                    Read Webtoons. Earn Coins. <span class="bg-gradient-to-r from-emerald-400 to-teal-300 bg-clip-text text-transparent">Get GCash!</span>
                </h1>
                <p class="text-xs sm:text-sm text-slate-300 max-w-xl leading-relaxed">
                    Read your favorite manhwas daily, complete daily quests, and redeem your coins for real GCash, Maya, or Prepaid Mobile Load.
                </p>
            </div>

            <!-- Balance Card -->
            <?php if ($userId): ?>
                <div class="bg-dark-900/90 backdrop-blur-md border border-dark-750 rounded-2xl p-5 sm:p-6 text-center shrink-0 shadow-2xl space-y-2 min-w-[240px]">
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Your Balance</span>
                    <div class="flex items-center justify-center gap-2">
                        <i class="fa-solid fa-coins text-amber-400 text-2xl sm:text-3xl animate-bounce"></i>
                        <span id="display-user-coins" class="text-3xl sm:text-4xl font-black text-white"><?= number_format($coins) ?></span>
                        <span class="text-xs text-amber-400 font-bold">Coins</span>
                    </div>
                    <div class="text-[11px] text-emerald-400 font-medium">
                        ≈ ₱<?= number_format($coins / 200, 2) ?> Estimated Value
                    </div>
                    <div class="pt-2">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full <?= $rankData['bg'] ?> border text-xs font-bold <?= $rankData['color'] ?>">
                            <i class="fa-solid <?= $rankData['icon'] ?>"></i> <?= $rankData['rank'] ?> &bull; <?= $rankData['title'] ?>
                        </span>
                    </div>
                </div>
            <?php else: ?>
                <div class="bg-dark-900/90 border border-brand-500/40 rounded-2xl p-5 text-center shrink-0 space-y-3 max-w-xs">
                    <h3 class="text-sm font-bold text-white">Start Earning Today</h3>
                    <p class="text-xs text-slate-400">Sign in or create an account to start earning Flow Coins and cash out rewards.</p>
                    <div class="flex gap-2">
                        <a href="<?= BASE_URL ?>login.php" class="flex-1 py-2 rounded-xl bg-dark-800 text-xs font-semibold text-white hover:bg-dark-750">Log In</a>
                        <a href="<?= BASE_URL ?>register.php" class="flex-1 py-2 rounded-xl bg-brand-600 text-xs font-bold text-white hover:bg-brand-500 shadow-lg shadow-brand-600/30">Sign Up</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- DAILY CHECK-IN STREAK CALENDAR -->
    <section class="space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-2 border-b border-dark-800">
            <div>
                <h2 class="text-lg sm:text-xl font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-calendar-check text-brand-400"></i> 7-Day Login Streak Bonus
                </h2>
                <p class="text-xs text-slate-400">Visit daily to increase your streak multiplier and earn bonus coins!</p>
            </div>
            <div class="flex items-center gap-2 text-xs">
                <span class="text-slate-400">Current Streak:</span>
                <span class="px-2.5 py-0.5 rounded-full bg-brand-500/20 text-brand-300 font-extrabold border border-brand-500/30">
                    🔥 <?= $streak ?> Days
                </span>
            </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3">
            <?php
            $streakPlan = [
                1 => ['coins' => 10, 'label' => 'Day 1'],
                2 => ['coins' => 15, 'label' => 'Day 2'],
                3 => ['coins' => 20, 'label' => 'Day 3'],
                4 => ['coins' => 25, 'label' => 'Day 4'],
                5 => ['coins' => 30, 'label' => 'Day 5'],
                6 => ['coins' => 40, 'label' => 'Day 6'],
                7 => ['coins' => 60, 'label' => 'Day 7 🎉']
            ];
            foreach ($streakPlan as $dayNum => $info):
                $isClaimed = ($streak >= $dayNum);
                $isCurrentTarget = ($streak + 1 === $dayNum) || ($streak === 0 && $dayNum === 1);
            ?>
                <div class="rounded-2xl p-4 text-center border transition-all <?= $isClaimed ? 'bg-emerald-950/20 border-emerald-500/40 text-emerald-300' : ($isCurrentTarget ? 'bg-dark-900 border-brand-500 ring-2 ring-brand-500/30' : 'bg-dark-900 border-dark-800 text-slate-400') ?>">
                    <span class="text-[11px] font-bold block mb-1 uppercase tracking-wider <?= $isClaimed ? 'text-emerald-400' : 'text-slate-400' ?>">
                        <?= $info['label'] ?>
                    </span>
                    <div class="w-10 h-10 rounded-xl mx-auto my-2 flex items-center justify-center text-lg <?= $isClaimed ? 'bg-emerald-500/20 text-emerald-300' : 'bg-dark-850 text-amber-400' ?>">
                        <?php if ($isClaimed): ?>
                            <i class="fa-solid fa-check"></i>
                        <?php else: ?>
                            <i class="fa-solid fa-coins"></i>
                        <?php endif; ?>
                    </div>
                    <span class="text-xs font-black block text-white">+<?= $info['coins'] ?></span>
                    <span class="text-[10px] text-slate-500"><?= $isClaimed ? 'Claimed' : 'Coins' ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="pt-2 text-center sm:text-left">
            <?php if ($userId): ?>
                <?php if ($canCheckin): ?>
                    <form method="POST" action="rewards.php" class="inline">
                        <input type="hidden" name="action" value="checkin">
                        <button type="submit" id="btn-checkin"
                                class="px-6 py-3 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-bold text-sm shadow-xl shadow-emerald-950/30 transition-all inline-flex items-center gap-2">
                            <i class="fa-solid fa-gift"></i>
                            <span>Claim Today's Login Bonus</span>
                        </button>
                    </form>
                <?php else: ?>
                    <button disabled class="px-6 py-3 rounded-xl bg-dark-850 border border-dark-750 text-slate-500 font-bold text-sm cursor-not-allowed inline-flex items-center gap-2">
                        <i class="fa-solid fa-circle-check text-emerald-500"></i>
                        <span>Checked In Today! (Come back tomorrow)</span>
                    </button>
                <?php endif; ?>
            <?php else: ?>
                <a href="<?= BASE_URL ?>login.php" class="px-6 py-3 rounded-xl bg-brand-600 hover:bg-brand-500 text-white font-bold text-sm inline-flex items-center gap-2">
                    <i class="fa-solid fa-right-to-bracket"></i> Sign in to Claim Login Bonus
                </a>
            <?php endif; ?>
        </div>
    </section>

    <!-- DAILY MISSIONS & QUESTS -->
    <section class="space-y-4">
        <div class="pb-2 border-b border-dark-800">
            <h2 class="text-lg sm:text-xl font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-bullseye text-brand-400"></i> Daily Quests &amp; Tasks
            </h2>
            <p class="text-xs text-slate-400">Complete tasks daily to earn extra Flow Coins!</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            
            <!-- Quest 1: Watch Ads Quest (0/5) -->
            <div class="bg-dark-900 border border-amber-500/40 rounded-2xl p-5 space-y-4 relative overflow-hidden shadow-xl group">
                <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-amber-500/10 rounded-full blur-xl pointer-events-none"></div>
                <div class="flex items-start justify-between gap-3">
                    <div class="w-12 h-12 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center text-xl shrink-0">
                        <i class="fa-solid fa-play"></i>
                    </div>
                    <span class="px-2.5 py-1 rounded-full bg-amber-500/20 text-amber-300 text-xs font-black border border-amber-500/30">
                        +20-50 Coins
                    </span>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-white group-hover:text-amber-300 transition-colors">Daily Ads Quest (0/5)</h3>
                    <p class="text-xs text-slate-400 mt-1 leading-relaxed">
                        Watch short partner ads (up to 5 times daily). Complete all 5 for an extra finish bonus!
                    </p>
                </div>
                
                <!-- Progress Bar -->
                <div class="space-y-1.5">
                    <div class="flex justify-between items-center text-[11px]">
                        <span class="text-slate-400">Progress:</span>
                        <span class="font-extrabold text-amber-400"><?= $adsWatchedToday ?> / 5 Completed</span>
                    </div>
                    <div class="h-2 w-full bg-dark-800 rounded-full overflow-hidden border border-dark-750">
                        <div class="h-full bg-gradient-to-r from-amber-500 to-yellow-400 rounded-full transition-all duration-500" style="width: <?= min(100, ($adsWatchedToday / 5) * 100) ?>%"></div>
                    </div>
                </div>

                <div>
                    <?php if ($userId): ?>
                        <?php if ($canWatchAd): ?>
                            <form method="POST" action="rewards.php" id="form-watch-ad">
                                <input type="hidden" name="action" value="watch_ad">
                                <input type="hidden" name="visit_token" id="token-watch-ad" value="">
                                <input type="hidden" name="adblock_status" id="adblock-watch-ad" value="">
                                <button type="button" id="btn-watch-ad" onclick="handleAdQuestClick('watch_ad')"
                                        class="w-full py-2.5 px-4 rounded-xl bg-gradient-to-r from-amber-500 to-yellow-500 hover:from-amber-400 hover:to-yellow-400 text-dark-950 font-black text-xs shadow-md shadow-amber-500/20 transition-all flex items-center justify-center gap-2">
                                    <i class="fa-solid fa-play text-[10px]"></i>
                                    <span>Watch Ad (<?= $adsWatchedToday ?>/5)</span>
                                </button>
                            </form>
                        <?php else: ?>
                            <button disabled class="w-full py-2.5 px-4 rounded-xl bg-dark-850 text-slate-500 text-xs font-bold border border-dark-750 cursor-not-allowed">
                                <i class="fa-solid fa-circle-check text-emerald-500 mr-1"></i> Completed (5/5 Today!)
                            </button>
                        <?php endif; ?>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>login.php?redirect=rewards.php" class="block text-center w-full py-2.5 rounded-xl bg-dark-800 text-slate-300 hover:text-white text-xs font-semibold">
                            Sign In to Watch
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quest 2: Daily Sponsor Exploration -->
            <div class="bg-dark-900 border border-brand-500/40 rounded-2xl p-5 space-y-4 relative overflow-hidden shadow-xl group">
                <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-brand-500/10 rounded-full blur-xl pointer-events-none"></div>
                <div class="flex items-start justify-between gap-3">
                    <div class="w-12 h-12 rounded-xl bg-brand-500/20 text-brand-400 flex items-center justify-center text-xl shrink-0">
                        <i class="fa-solid fa-gift"></i>
                    </div>
                    <span class="px-2.5 py-1 rounded-full bg-brand-500/20 text-brand-300 text-xs font-black border border-brand-500/30">
                        +50 Coins
                    </span>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-white group-hover:text-brand-300 transition-colors">Daily Sponsor Bonus</h3>
                    <p class="text-xs text-slate-400 mt-1 leading-relaxed">
                        Check out our partner games and services to support ManhwaFlow hosting and earn bonus coins.
                    </p>
                </div>
                <div class="pt-6">
                    <?php if ($userId): ?>
                        <?php if ($canSponsor): ?>
                            <form method="POST" action="rewards.php" id="form-sponsor-quest">
                                <input type="hidden" name="action" value="sponsor_quest">
                                <input type="hidden" name="visit_token" id="token-sponsor-quest" value="">
                                <input type="hidden" name="adblock_status" id="adblock-sponsor-quest" value="">
                                <button type="button" id="btn-sponsor-quest" onclick="handleAdQuestClick('sponsor_quest')"
                                        class="w-full py-2.5 px-4 rounded-xl bg-gradient-to-r from-brand-600 to-indigo-600 hover:from-brand-500 hover:to-indigo-500 text-white font-bold text-xs shadow-md shadow-brand-600/30 transition-all flex items-center justify-center gap-2">
                                    <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                                    <span>Claim +50 Coins (Visit Offer)</span>
                                </button>
                            </form>
                        <?php else: ?>
                            <button disabled class="w-full py-2.5 px-4 rounded-xl bg-dark-850 text-slate-500 text-xs font-bold border border-dark-750 cursor-not-allowed">
                                <i class="fa-solid fa-check text-emerald-500 mr-1"></i> Claimed Today!
                            </button>
                        <?php endif; ?>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>login.php" class="block text-center w-full py-2.5 rounded-xl bg-dark-800 text-slate-300 hover:text-white text-xs font-semibold">
                            Sign In to Claim
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quest 3: Read Chapters -->
            <div class="bg-dark-900 border border-dark-800 rounded-2xl p-5 space-y-4 shadow-xl">
                <div class="flex items-start justify-between gap-3">
                    <div class="w-12 h-12 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-xl shrink-0">
                        <i class="fa-solid fa-book-open-reader"></i>
                    </div>
                    <span class="px-2.5 py-1 rounded-full bg-emerald-500/20 text-emerald-300 text-xs font-black border border-emerald-500/30">
                        +5 Coins / Ch.
                    </span>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-white">Read Comic Chapters</h3>
                    <p class="text-xs text-slate-400 mt-1 leading-relaxed">
                        Automatically get +5 coins credited to your account every time you complete reading a chapter in the reader!
                    </p>
                </div>
                <div class="pt-6">
                    <a href="<?= BASE_URL ?>" class="block text-center w-full py-2.5 px-4 rounded-xl bg-dark-850 hover:bg-dark-800 border border-dark-750 text-slate-200 text-xs font-bold transition-all">
                        <i class="fa-solid fa-compass mr-1"></i> Explore Comics Catalog
                    </a>
                </div>
            </div>

            <!-- Quest 4: Leave Chapter Comments -->
            <div class="bg-dark-900 border border-dark-800 rounded-2xl p-5 space-y-4 shadow-xl">
                <div class="flex items-start justify-between gap-3">
                    <div class="w-12 h-12 rounded-xl bg-blue-500/20 text-blue-400 flex items-center justify-center text-xl shrink-0">
                        <i class="fa-solid fa-comments"></i>
                    </div>
                    <span class="px-2.5 py-1 rounded-full bg-blue-500/20 text-blue-300 text-xs font-black border border-blue-500/30">
                        Community
                    </span>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-white">Join Discussions</h3>
                    <p class="text-xs text-slate-400 mt-1 leading-relaxed">
                        Leave your thoughts, theories, and comments at the bottom of chapters to connect with other fans and level up!
                    </p>
                </div>
                <div class="pt-6">
                    <a href="<?= BASE_URL ?>history.php" class="block text-center w-full py-2.5 px-4 rounded-xl bg-dark-850 hover:bg-dark-800 border border-dark-750 text-slate-200 text-xs font-bold transition-all">
                        <i class="fa-solid fa-clock-rotate-left mr-1"></i> Continue Your Series
                    </a>
                </div>
            </div>

        </div>
    </section>

    <!-- MONTHLY REDEEM CODES & FACEBOOK COMMUNITY -->
    <section class="bg-gradient-to-r from-brand-950/80 via-dark-900 to-indigo-950/80 border border-brand-500/30 rounded-3xl p-6 sm:p-8 space-y-6 shadow-2xl relative overflow-hidden">
        <div class="absolute -right-10 -bottom-10 w-60 h-60 bg-blue-600/10 rounded-full blur-3xl pointer-events-none"></div>

        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-dark-800">
            <div>
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-500/10 border border-blue-500/30 text-blue-400 text-xs font-bold uppercase tracking-wider mb-2">
                    <i class="fa-solid fa-ticket"></i> Monthly Bonus Codes
                </div>
                <h2 class="text-xl sm:text-2xl font-black text-white flex items-center gap-2">
                    Redeem Monthly Codes &amp; Points
                </h2>
                <p class="text-xs text-slate-300 max-w-xl mt-1">
                    Enter official secret codes released every month to receive instant bonus Flow Coins!
                </p>
            </div>
            
            <!-- Facebook Action Button -->
            <a href="https://www.facebook.com/profile.php?id=61594942004447" target="_blank" rel="noopener noreferrer"
               class="px-5 py-3 rounded-2xl bg-[#1877F2] hover:bg-[#166fe5] text-white font-bold text-xs sm:text-sm shadow-lg shadow-blue-900/40 transition-all flex items-center gap-2 self-start md:self-auto shrink-0">
                <i class="fa-brands fa-facebook text-base"></i>
                <span>Visit Facebook Page &amp; Follow</span>
                <i class="fa-solid fa-arrow-up-right-from-square text-[10px] opacity-80"></i>
            </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-center">
            <!-- Left Info Box: Why Facebook? -->
            <div class="lg:col-span-7 bg-dark-900/80 border border-dark-750 rounded-2xl p-5 sm:p-6 space-y-3">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-blue-500/15 text-blue-400 flex items-center justify-center text-2xl shrink-0">
                        <i class="fa-brands fa-facebook-f"></i>
                    </div>
                    <div class="space-y-1">
                        <h3 class="text-sm font-bold text-white">Where to Find Monthly Redeem Codes?</h3>
                        <p class="text-xs text-slate-300 leading-relaxed">
                            Redeem codes are not published directly on this website! Instead, our <strong>official monthly gift codes and vouchers</strong> are posted exclusively on our <strong>Facebook Page</strong> every month. Follow and like our page to get this month's active codes and stay updated!
                        </p>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 pt-2 text-[11px] text-slate-300">
                    <div class="bg-dark-850/80 rounded-xl p-2.5 border border-dark-800 flex items-center gap-2">
                        <i class="fa-solid fa-bell text-amber-400"></i>
                        <span>1. Follow our Facebook Page</span>
                    </div>
                    <div class="bg-dark-850/80 rounded-xl p-2.5 border border-dark-800 flex items-center gap-2">
                        <i class="fa-solid fa-bullhorn text-emerald-400"></i>
                        <span>2. Copy the monthly code</span>
                    </div>
                    <div class="bg-dark-850/80 rounded-xl p-2.5 border border-dark-800 flex items-center gap-2">
                        <i class="fa-solid fa-gift text-brand-400"></i>
                        <span>3. Paste here for free coins!</span>
                    </div>
                </div>
            </div>

            <!-- Right Input Box: Redeem Form -->
            <div class="lg:col-span-5 bg-dark-900/90 border border-brand-500/40 rounded-2xl p-5 sm:p-6 space-y-3.5 shadow-xl">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold text-white flex items-center gap-2">
                        <i class="fa-solid fa-key text-amber-400"></i> Enter Redeem Code
                    </h3>
                    <span class="text-[10px] text-emerald-400 font-bold bg-emerald-500/10 px-2 py-0.5 rounded border border-emerald-500/20">+100 - 500 Coins</span>
                </div>
                
                <?php if ($userId): ?>
                    <?php if ($redeemAlert): ?>
                        <div class="p-3 rounded-xl bg-<?= $redeemAlert['type'] ?>-500/10 border border-<?= $redeemAlert['type'] ?>-500/30 text-<?= $redeemAlert['type'] ?>-400 text-xs font-bold flex items-center gap-2">
                            <i class="fa-solid <?= $redeemAlert['type'] === 'emerald' ? 'fa-circle-check text-emerald-400' : 'fa-circle-exclamation text-rose-400' ?>"></i>
                            <span><?= htmlspecialchars($redeemAlert['message']) ?></span>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="<?= BASE_URL ?>rewards.php" class="space-y-3">
                        <input type="hidden" name="action" value="redeem_code">
                        <div class="relative">
                            <input type="text" name="code" id="redeem-input-code" required placeholder="e.g. FLOW2026, MANHWAFACEBOOK"
                                   class="w-full bg-dark-850 border border-dark-700 focus:border-brand-500 rounded-xl px-4 py-3 text-xs sm:text-sm text-white font-mono tracking-wider uppercase focus:outline-none placeholder:text-slate-600 placeholder:normal-case">
                        </div>
                        <button type="submit" id="btn-submit-code"
                                class="w-full py-3 rounded-xl bg-gradient-to-r from-brand-600 to-indigo-600 hover:from-brand-500 hover:to-indigo-500 text-white font-bold text-xs sm:text-sm shadow-lg shadow-brand-600/30 transition-all flex items-center justify-center gap-2">
                            <i class="fa-solid fa-gift"></i>
                            <span>Redeem Bonus Coins</span>
                        </button>
                    </form>
                <?php else: ?>
                    <div class="text-center py-4 space-y-3">
                        <p class="text-xs text-slate-400">Sign in is required to claim free bonus points to your account.</p>
                        <a href="<?= BASE_URL ?>login.php?redirect=rewards.php" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-500 text-white text-xs font-bold shadow-md">
                            <i class="fa-solid fa-right-to-bracket text-xs"></i> Sign In to Redeem
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- SAVED PAYOUT DETAILS (GCASH / MAYA) -->
    <?php if ($userId): ?>
    <section class="bg-dark-900 border border-dark-800 rounded-2xl p-5 sm:p-6 space-y-4 shadow-xl">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-dark-800">
            <div>
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-wallet text-emerald-400"></i> Payout Settings (Where to Send Cashouts)
                </h3>
                <p class="text-xs text-slate-400">Save your default GCash or Maya details to automatically pre-fill future redemption requests.</p>
            </div>
            <div>
                <?php if (!empty($defaultNumber)): ?>
                    <span id="payout-status-badge" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-bold">
                        <i class="fa-solid fa-circle-check text-[10px]"></i> Saved: <?= strtoupper($defaultMethod) ?> (<?= htmlspecialchars($defaultNumber) ?>)
                    </span>
                <?php else: ?>
                    <span id="payout-status-badge" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-500/10 border border-amber-500/30 text-amber-400 text-xs font-bold">
                        <i class="fa-solid fa-circle-exclamation text-[10px]"></i> Set up your payout details here
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <form id="payout-settings-form" method="POST" action="rewards.php" class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-1">
            <input type="hidden" name="action" value="save_payout_settings">
            <div>
                <label class="block text-[11px] font-bold text-slate-400 mb-1">Preferred Method</label>
                <select name="default_payout_method" id="setting-method" class="w-full bg-dark-850 border border-dark-700 rounded-xl px-3.5 py-2.5 text-xs text-white focus:outline-none focus:border-emerald-500">
                    <option value="gcash" <?= $defaultMethod === 'gcash' ? 'selected' : '' ?>>GCash</option>
                    <option value="maya" <?= $defaultMethod === 'maya' ? 'selected' : '' ?>>Maya (PayMaya)</option>
                    <option value="load" <?= $defaultMethod === 'load' ? 'selected' : '' ?>>Prepaid Load</option>
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-400 mb-1">Account Full Name</label>
                <input type="text" name="default_account_name" id="setting-name" value="<?= htmlspecialchars($defaultName) ?>" required placeholder="e.g. Maria Santos"
                       class="w-full bg-dark-850 border border-dark-700 rounded-xl px-3.5 py-2.5 text-xs text-white focus:outline-none focus:border-emerald-500">
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-400 mb-1">Mobile / Account Number</label>
                <div class="flex gap-2">
                    <input type="text" name="default_account_number" id="setting-number" value="<?= htmlspecialchars($defaultNumber) ?>" required placeholder="e.g. 09123456789"
                           class="w-full bg-dark-850 border border-dark-700 rounded-xl px-3.5 py-2.5 text-xs text-white font-mono focus:outline-none focus:border-emerald-500">
                    <button type="submit" id="btn-save-settings" 
                            class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shrink-0 shadow-md transition-all">
                        Save
                    </button>
                </div>
            </div>
        </form>
    </section>
    <?php endif; ?>

    <!-- REDEEM STORE & CASHOUT (GCASH / MAYA / LOAD) -->
    <section class="space-y-6">
        <div class="pb-2 border-b border-dark-800 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>
                <h2 class="text-lg sm:text-xl font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-money-bill-wave text-emerald-400"></i> GCash &amp; Load Redeem Store
                </h2>
                <p class="text-xs text-slate-400">Redeem your accumulated Flow Coins for real cash! Minimum cashout is 2,500 coins (₱10.00).</p>
            </div>
            <div class="text-xs text-slate-400">
                Processing time: <strong class="text-emerald-400">24-48 Hours</strong>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            
            <!-- Item 1: ₱10 Starter -->
            <div class="bg-dark-900 border border-dark-800 hover:border-emerald-500/50 rounded-2xl p-5 space-y-4 shadow-xl transition-all flex flex-col justify-between">
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded border border-emerald-500/20">
                            Starter Cashout
                        </span>
                        <i class="fa-solid fa-mobile-screen-button text-slate-500 text-sm"></i>
                    </div>
                    <div class="text-3xl font-black text-white">₱10.00</div>
                    <p class="text-xs text-slate-400">Direct GCash, Maya, or Prepaid Load</p>
                </div>
                <div class="space-y-3 pt-2">
                    <div class="flex items-center justify-between text-xs font-bold">
                        <span class="text-slate-400">Cost:</span>
                        <span class="text-amber-400 flex items-center gap-1"><i class="fa-solid fa-coins text-[11px]"></i> 2,500 Coins</span>
                    </div>
                    <button onclick="openRedeemModal(10, 2500)" 
                            class="w-full py-2.5 rounded-xl <?= $coins >= 2500 ? 'bg-emerald-600 hover:bg-emerald-500 text-white shadow-lg shadow-emerald-950/40' : 'bg-dark-800 text-slate-500 cursor-not-allowed' ?> font-bold text-xs transition-all">
                        <?= $coins >= 2500 ? 'Redeem ₱10' : 'Need 2,500 Coins' ?>
                    </button>
                </div>
            </div>

            <!-- Item 2: ₱25 Standard -->
            <div class="bg-dark-900 border border-dark-800 hover:border-emerald-500/50 rounded-2xl p-5 space-y-4 shadow-xl transition-all flex flex-col justify-between">
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-blue-400 bg-blue-500/10 px-2 py-0.5 rounded border border-blue-500/20">
                            Popular
                        </span>
                        <i class="fa-solid fa-wallet text-slate-500 text-sm"></i>
                    </div>
                    <div class="text-3xl font-black text-white">₱25.00</div>
                    <p class="text-xs text-slate-400">Direct GCash, Maya, or Prepaid Load</p>
                </div>
                <div class="space-y-3 pt-2">
                    <div class="flex items-center justify-between text-xs font-bold">
                        <span class="text-slate-400">Cost:</span>
                        <span class="text-amber-400 flex items-center gap-1"><i class="fa-solid fa-coins text-[11px]"></i> 5,000 Coins</span>
                    </div>
                    <button onclick="openRedeemModal(25, 5000)" 
                            class="w-full py-2.5 rounded-xl <?= $coins >= 5000 ? 'bg-emerald-600 hover:bg-emerald-500 text-white shadow-lg shadow-emerald-950/40' : 'bg-dark-800 text-slate-500 cursor-not-allowed' ?> font-bold text-xs transition-all">
                        <?= $coins >= 5000 ? 'Redeem ₱25' : 'Need 5,000 Coins' ?>
                    </button>
                </div>
            </div>

            <!-- Item 3: ₱50 Hunter -->
            <div class="bg-dark-900 border border-dark-800 hover:border-emerald-500/50 rounded-2xl p-5 space-y-4 shadow-xl transition-all flex flex-col justify-between">
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-amber-400 bg-amber-500/10 px-2 py-0.5 rounded border border-amber-500/20">
                            Great Value
                        </span>
                        <i class="fa-solid fa-money-bill-transfer text-slate-500 text-sm"></i>
                    </div>
                    <div class="text-3xl font-black text-white">₱50.00</div>
                    <p class="text-xs text-slate-400">Direct GCash or Maya Wallet</p>
                </div>
                <div class="space-y-3 pt-2">
                    <div class="flex items-center justify-between text-xs font-bold">
                        <span class="text-slate-400">Cost:</span>
                        <span class="text-amber-400 flex items-center gap-1"><i class="fa-solid fa-coins text-[11px]"></i> 10,000 Coins</span>
                    </div>
                    <button onclick="openRedeemModal(50, 10000)" 
                            class="w-full py-2.5 rounded-xl <?= $coins >= 10000 ? 'bg-emerald-600 hover:bg-emerald-500 text-white shadow-lg shadow-emerald-950/40' : 'bg-dark-800 text-slate-500 cursor-not-allowed' ?> font-bold text-xs transition-all">
                        <?= $coins >= 10000 ? 'Redeem ₱50' : 'Need 10,000 Coins' ?>
                    </button>
                </div>
            </div>

            <!-- Item 4: ₱100 Elite -->
            <div class="bg-dark-900 border border-brand-500/40 rounded-2xl p-5 space-y-4 shadow-xl transition-all flex flex-col justify-between relative overflow-hidden">
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-brand-300 bg-brand-500/20 px-2 py-0.5 rounded border border-brand-500/30">
                            Max Cashout
                        </span>
                        <i class="fa-solid fa-crown text-brand-400 text-sm"></i>
                    </div>
                    <div class="text-3xl font-black text-white">₱100.00</div>
                    <p class="text-xs text-slate-400">Direct GCash or Maya Wallet</p>
                </div>
                <div class="space-y-3 pt-2">
                    <div class="flex items-center justify-between text-xs font-bold">
                        <span class="text-slate-400">Cost:</span>
                        <span class="text-amber-400 flex items-center gap-1"><i class="fa-solid fa-coins text-[11px]"></i> 20,000 Coins</span>
                    </div>
                    <button onclick="openRedeemModal(100, 20000)" 
                            class="w-full py-2.5 rounded-xl <?= $coins >= 20000 ? 'bg-gradient-to-r from-brand-600 to-indigo-600 hover:from-brand-500 hover:to-indigo-500 text-white shadow-lg shadow-brand-600/30' : 'bg-dark-850 text-slate-500 cursor-not-allowed' ?> font-bold text-xs transition-all">
                        <?= $coins >= 20000 ? 'Redeem ₱100' : 'Need 20,000 Coins' ?>
                    </button>
                </div>
            </div>

        </div>
    </section>

    <!-- PAYOUT HISTORY TABLE -->
    <?php if ($userId && !empty($payoutHistory)): ?>
    <section class="space-y-4">
        <div class="pb-2 border-b border-dark-800">
            <h2 class="text-base font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-receipt text-slate-400"></i> Your Payout Requests
            </h2>
        </div>
        <div class="overflow-x-auto bg-dark-900 border border-dark-800 rounded-2xl">
            <table class="w-full text-left text-xs">
                <thead class="bg-dark-850/60 text-slate-400 border-b border-dark-800">
                    <tr>
                        <th class="p-3.5">ID</th>
                        <th class="p-3.5">Amount</th>
                        <th class="p-3.5">Method &amp; Number</th>
                        <th class="p-3.5">Coins Deducted</th>
                        <th class="p-3.5">Status</th>
                        <th class="p-3.5">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-dark-800 text-slate-300">
                    <?php foreach ($payoutHistory as $ph): ?>
                        <tr>
                            <td class="p-3.5 font-mono text-slate-500">#<?= $ph['id'] ?></td>
                            <td class="p-3.5 font-bold text-emerald-400">₱<?= number_format($ph['amount_php'], 2) ?></td>
                            <td class="p-3.5">
                                <span class="uppercase font-semibold text-white"><?= htmlspecialchars($ph['payout_method']) ?></span>: 
                                <span class="font-mono text-slate-400"><?= htmlspecialchars($ph['account_number']) ?></span>
                            </td>
                            <td class="p-3.5 font-mono text-amber-400">-<?= number_format($ph['coins_deducted']) ?></td>
                            <td class="p-3.5">
                                <?php if ($ph['status'] === 'approved'): ?>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                        <i class="fa-solid fa-circle-check mr-1"></i> Paid
                                    </span>
                                <?php elseif ($ph['status'] === 'rejected'): ?>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-500/20 text-rose-300 border border-rose-500/30">
                                        <i class="fa-solid fa-circle-xmark mr-1"></i> Rejected
                                    </span>
                                <?php else: ?>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30">
                                        <i class="fa-solid fa-clock mr-1"></i> Under Review
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="p-3.5 text-slate-500"><?= date('M j, Y', strtotime($ph['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>

</div>

<!-- REDEEM MODAL -->
<div id="redeem-modal" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-dark-900 border border-dark-750 rounded-3xl max-w-md w-full p-6 sm:p-8 space-y-6 shadow-2xl relative">
        <button onclick="closeRedeemModal()" class="absolute top-5 right-5 text-slate-400 hover:text-white p-2">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>

        <div class="text-center space-y-1">
            <div class="w-12 h-12 rounded-2xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center mx-auto text-xl mb-3">
                <i class="fa-solid fa-wallet"></i>
            </div>
            <h3 class="text-xl font-black text-white">Redeem GCash / Maya</h3>
            <p class="text-xs text-slate-400">Enter the account details where your payout will be sent.</p>
        </div>

        <div class="bg-dark-850 rounded-2xl p-4 flex items-center justify-between border border-dark-800">
            <div>
                <span class="text-[10px] uppercase font-bold text-slate-400 block">Redeeming</span>
                <span id="modal-amount-display" class="text-xl font-black text-emerald-400">₱0.00</span>
            </div>
            <div class="text-right">
                <span class="text-[10px] uppercase font-bold text-slate-400 block">Cost</span>
                <span id="modal-coins-display" class="text-sm font-bold text-amber-400">0 Coins</span>
            </div>
        </div>

        <form id="redeem-form" method="POST" action="rewards.php" class="space-y-4">
            <input type="hidden" name="action" value="request_payout">
            <input type="hidden" id="payout-amount" name="amount_php" value="0">

            <div>
                <label class="block text-xs font-bold text-slate-300 mb-1.5">Payment Method</label>
                <select name="payout_method" id="payout-method" class="w-full bg-dark-850 border border-dark-700 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-emerald-500">
                    <option value="gcash">GCash</option>
                    <option value="maya">Maya (PayMaya)</option>
                    <option value="load">Regular Prepaid Load</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-300 mb-1.5">Account / Full Name</label>
                <input type="text" name="account_name" id="payout-name" required placeholder="e.g. Juan Dela Cruz"
                       class="w-full bg-dark-850 border border-dark-700 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-emerald-500">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-300 mb-1.5">Mobile Number</label>
                <input type="text" name="account_number" id="payout-number" required placeholder="e.g. 09123456789"
                       class="w-full bg-dark-850 border border-dark-700 rounded-xl px-4 py-2.5 text-xs text-white font-mono focus:outline-none focus:border-emerald-500">
            </div>

            <button type="submit" id="btn-submit-payout"
                    class="w-full py-3 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-bold text-xs shadow-lg shadow-emerald-950/40 transition-all">
                Confirm Cashout Request
            </button>
        </form>
    </div>
</div>

<!-- ADBLOCK DETECTED MODAL -->
<div id="adblock-modal" class="fixed inset-0 z-50 bg-black/85 backdrop-blur-md hidden flex items-center justify-center p-4">
    <div class="bg-dark-900 border border-rose-500/40 rounded-3xl max-w-md w-full p-6 sm:p-8 space-y-6 shadow-2xl relative text-center">
        <button onclick="closeAdblockModal()" class="absolute top-5 right-5 text-slate-400 hover:text-white p-2">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>

        <div class="w-16 h-16 rounded-3xl bg-rose-500/20 text-rose-400 flex items-center justify-center mx-auto text-2xl shadow-lg shadow-rose-950/40">
            <i class="fa-solid fa-shield-halved"></i>
        </div>

        <div class="space-y-2">
            <h3 class="text-xl font-black text-white">Naka-on ang AdBlocker!</h3>
            <p class="text-xs text-slate-300 leading-relaxed">
                Hindi mo maaaring ma-claim ang Flow Coins habang may active ad blocker, Brave Shields, o DNS adblock.
            </p>
        </div>

        <div class="bg-dark-850 border border-dark-750 rounded-2xl p-4 text-left space-y-2">
            <div class="text-[11px] font-bold text-amber-400 flex items-center gap-1.5">
                <i class="fa-solid fa-lightbulb"></i> Paano ito i-disable:
            </div>
            <ul class="text-[11px] text-slate-400 space-y-1 list-disc list-inside">
                <li>I-off ang extension gaya ng <strong>uBlock Origin</strong> o <strong>AdGuard</strong> para sa site na ito.</li>
                <li>Kung gamit ang <strong>Brave Browser</strong>, i-click ang lion icon at i-toggle OFF ang <strong>Shields</strong>.</li>
                <li>I-refresh ang page pagkatapos i-disable upang ma-claim ang coins.</li>
            </ul>
        </div>

        <div class="flex gap-3">
            <button onclick="closeAdblockModal()" class="flex-1 py-3 rounded-xl bg-dark-800 hover:bg-dark-750 text-slate-300 font-bold text-xs transition-colors">
                Isara
            </button>
            <button onclick="window.location.reload()" class="flex-1 py-3 rounded-xl bg-gradient-to-r from-brand-600 to-indigo-600 hover:from-brand-500 hover:to-indigo-500 text-white font-bold text-xs shadow-lg shadow-brand-900/40 transition-all flex items-center justify-center gap-2">
                <i class="fa-solid fa-rotate-right"></i>
                <span>I-refresh ang Page</span>
            </button>
        </div>
    </div>
</div>

<!-- SPONSOR & AD VISIT VERIFICATION MODAL -->
<div id="sponsor-visit-modal" class="fixed inset-0 z-50 bg-black/85 backdrop-blur-md hidden flex items-center justify-center p-4">
    <div class="bg-dark-900 border border-brand-500/40 rounded-3xl max-w-md w-full p-6 sm:p-8 space-y-6 shadow-2xl relative text-center">
        <button onclick="closeSponsorModal()" class="absolute top-5 right-5 text-slate-400 hover:text-white p-2">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>

        <div id="modal-ad-icon" class="w-16 h-16 rounded-3xl bg-brand-500/20 text-brand-400 flex items-center justify-center mx-auto text-2xl shadow-lg shadow-brand-950/40 animate-pulse">
            <i class="fa-solid fa-arrow-up-right-from-square"></i>
        </div>

        <div class="space-y-2">
            <h3 id="modal-ad-title" class="text-xl font-black text-white">Binubuksan ang Partner Sponsor...</h3>
            <p id="modal-ad-subtitle" class="text-xs text-slate-300 leading-relaxed">
                Pakitingnan at bisitahin ang nabuksang tab nang hindi bababa sa <span class="text-amber-400 font-bold">5 segundo</span> bago i-claim ang coins.
            </p>
        </div>

        <!-- Timer & Progress Bar -->
        <div class="space-y-2 bg-dark-850 border border-dark-750 rounded-2xl p-4">
            <div class="flex items-center justify-between text-xs">
                <span class="text-slate-400 font-semibold" id="modal-timer-status">Verifying visit...</span>
                <span class="font-black text-amber-400 text-sm" id="modal-timer-text">5s</span>
            </div>
            <div class="h-2.5 w-full bg-dark-800 rounded-full overflow-hidden border border-dark-750">
                <div id="modal-timer-bar" class="h-full bg-gradient-to-r from-amber-500 to-emerald-400 rounded-full transition-all duration-300 w-0"></div>
            </div>
        </div>

        <!-- Manual Re-open link if popup blocked -->
        <div id="modal-reopen-wrap" class="hidden text-xs">
            <p class="text-slate-400 mb-1">Hindi bumukas ang tab o na-block ng browser?</p>
            <a id="modal-reopen-link" href="#" target="_blank" class="inline-flex items-center gap-1.5 text-brand-400 hover:text-brand-300 underline font-bold">
                <i class="fa-solid fa-arrow-up-right-from-square text-[11px]"></i> I-click dito para manu-manong buksan ang link
            </a>
        </div>

        <!-- Action Buttons -->
        <div class="space-y-2">
            <button id="btn-claim-verified" disabled onclick="submitVerifiedClaim()"
                    class="w-full py-3.5 rounded-xl bg-dark-800 text-slate-500 font-bold text-xs cursor-not-allowed transition-all flex items-center justify-center gap-2 border border-dark-750">
                <i class="fa-solid fa-lock text-xs"></i>
                <span id="btn-claim-text">Mag-antay nang 5 segundo...</span>
            </button>
            <button onclick="closeSponsorModal()" class="w-full py-2.5 rounded-xl bg-transparent text-slate-400 hover:text-white font-semibold text-xs">
                Kanselahin
            </button>
        </div>
    </div>
</div>

<script>
// Modal controls for GCash/Maya Cashout
function openRedeemModal(amount, coins) {
    const userCoins = <?= $coins ?>;
    if (userCoins < coins) {
        alert(`Insufficient coins! You need ${coins.toLocaleString()} coins for ₱${amount}.00.`);
        return;
    }
    document.getElementById('payout-amount').value = amount;
    document.getElementById('modal-amount-display').textContent = `₱${amount}.00`;
    document.getElementById('modal-coins-display').textContent = `${coins.toLocaleString()} Coins`;

    // Pre-fill with saved details if available
    const defaultMethod = <?= json_encode($defaultMethod) ?>;
    const defaultName = <?= json_encode($defaultName) ?>;
    const defaultNumber = <?= json_encode($defaultNumber) ?>;
    if (defaultMethod) document.getElementById('payout-method').value = defaultMethod;
    if (defaultName) document.getElementById('payout-name').value = defaultName;
    if (defaultNumber) document.getElementById('payout-number').value = defaultNumber;

    document.getElementById('redeem-modal').classList.remove('hidden');
}

function closeRedeemModal() {
    document.getElementById('redeem-modal').classList.add('hidden');
}

// Add native submit spinners for smooth user feedback
document.addEventListener('DOMContentLoaded', function() {
    // 1. Redeem Code Form
    const redeemCodeForm = document.querySelector('form[action*="rewards.php"] input[name="action"][value="redeem_code"]')?.closest('form');
    if (redeemCodeForm) {
        redeemCodeForm.addEventListener('submit', function() {
            const btn = document.getElementById('btn-submit-code');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-xs"></i> Processing Code...';
            }
        });
    }

    // 2. Payout Request Form
    const payoutForm = document.getElementById('redeem-form');
    if (payoutForm) {
        payoutForm.addEventListener('submit', function() {
            const btn = document.getElementById('btn-submit-payout');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-xs"></i> Submitting Cashout...';
            }
        });
    }

    // 3. Payout Settings Form
    const settingsForm = document.getElementById('payout-settings-form');
    if (settingsForm) {
        settingsForm.addEventListener('submit', function() {
            const btn = document.getElementById('btn-save-settings');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-xs"></i> Saving...';
            }
        });
    }
});

// --- ADBLOCK & SPONSOR VISIT VERIFICATION ENGINE ---
let currentAdChallenge = {
    token: null,
    type: null,
    sponsorUrl: null,
    waitSeconds: 5,
    timerInterval: null,
    elapsedSeconds: 0,
    isReadyToClaim: false
};

// 1. Multi-layer AdBlock Detection
function checkAdBlock() {
    // Check 1: Script probe (ads.js)
    if (typeof window.__mf_adblock_check_passed === 'undefined' || window.__mf_adblock_check_passed !== true) {
        return true;
    }
    // Check 2: Element bait
    const bait = document.getElementById('ad-detector-slot');
    if (bait) {
        if (bait.offsetHeight === 0 || bait.offsetWidth === 0 || window.getComputedStyle(bait).display === 'none' || window.getComputedStyle(bait).visibility === 'hidden') {
            return true;
        }
    }
    return false;
}

// Check on load and show banner if blocked
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        if (checkAdBlock()) {
            const banner = document.getElementById('adblock-alert-banner');
            if (banner) banner.classList.remove('hidden');
        }
    }, 400);
});

function openAdblockModal() {
    const modal = document.getElementById('adblock-modal');
    if (modal) modal.classList.remove('hidden');
}

function closeAdblockModal() {
    const modal = document.getElementById('adblock-modal');
    if (modal) modal.classList.add('hidden');
}

function closeSponsorModal() {
    if (currentAdChallenge.timerInterval) {
        clearInterval(currentAdChallenge.timerInterval);
        currentAdChallenge.timerInterval = null;
    }
    const modal = document.getElementById('sponsor-visit-modal');
    if (modal) modal.classList.add('hidden');
}

// 2. Open Sponsor Visit Modal & Launch Sponsor Link
async function handleAdQuestClick(questType) {
    // Check adblock first
    if (checkAdBlock()) {
        openAdblockModal();
        return;
    }

    const modal = document.getElementById('sponsor-visit-modal');
    const modalTitle = document.getElementById('modal-ad-title');
    const modalSubtitle = document.getElementById('modal-ad-subtitle');
    const timerStatus = document.getElementById('modal-timer-status');
    const timerText = document.getElementById('modal-timer-text');
    const timerBar = document.getElementById('modal-timer-bar');
    const claimBtn = document.getElementById('btn-claim-verified');
    const claimBtnText = document.getElementById('btn-claim-text');
    const reopenWrap = document.getElementById('modal-reopen-wrap');
    const reopenLink = document.getElementById('modal-reopen-link');

    // Reset UI
    modal.classList.remove('hidden');
    if (questType === 'watch_ad') {
        modalTitle.textContent = 'Binubuksan ang Partner Ad...';
        modalSubtitle.innerHTML = 'Pakitingnan ang ad page nang hindi bababa sa <span class="text-amber-400 font-bold">5 segundo</span> bago i-claim ang Flow Coins.';
    } else {
        modalTitle.textContent = 'Binubuksan ang Daily Sponsor Offer...';
        modalSubtitle.innerHTML = 'Pakitingnan ang sponsor page nang hindi bababa sa <span class="text-brand-400 font-bold">5 segundo</span> bago i-claim ang +50 coins.';
    }

    timerStatus.textContent = 'Kinukuha ang sponsor link...';
    timerText.textContent = '5s';
    timerBar.style.width = '0%';
    claimBtn.disabled = true;
    claimBtn.className = 'w-full py-3.5 rounded-xl bg-dark-800 text-slate-500 font-bold text-xs cursor-not-allowed transition-all flex items-center justify-center gap-2 border border-dark-750';
    claimBtnText.textContent = 'Mag-antay nang 5 segundo...';
    reopenWrap.classList.add('hidden');

    if (currentAdChallenge.timerInterval) {
        clearInterval(currentAdChallenge.timerInterval);
    }

    currentAdChallenge = {
        token: null,
        type: questType,
        sponsorUrl: null,
        waitSeconds: 5,
        timerInterval: null,
        elapsedSeconds: 0,
        isReadyToClaim: false
    };

    try {
        const resp = await fetch('<?= BASE_URL ?>api/rewards.php?action=start_ad_visit', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ type: questType })
        });
        const resData = await resp.json();

        if (!resData.success) {
            alert(resData.message || 'Hindi masimulan ang sponsor visit.');
            closeSponsorModal();
            return;
        }

        currentAdChallenge.token = resData.visit_token;
        currentAdChallenge.sponsorUrl = resData.sponsor_url;
        currentAdChallenge.waitSeconds = resData.wait_seconds || 5;

        // Set reopen fallback
        reopenLink.href = currentAdChallenge.sponsorUrl;
        reopenWrap.classList.remove('hidden');

        // Immediately open popup in user click context
        const popup = window.open(currentAdChallenge.sponsorUrl, '_blank');
        if (!popup || popup.closed || typeof popup.closed === 'undefined') {
            timerStatus.textContent = 'I-click ang link sa ibaba kung na-block ang tab ng browser!';
        } else {
            timerStatus.textContent = 'Bukas na ang page! Nagka-countdown...';
        }

        // Start countdown
        let totalTime = currentAdChallenge.waitSeconds;
        let elapsed = 0;
        timerBar.style.width = '0%';

        currentAdChallenge.timerInterval = setInterval(() => {
            elapsed++;
            currentAdChallenge.elapsedSeconds = elapsed;
            let remaining = Math.max(0, totalTime - elapsed);
            timerText.textContent = `${remaining}s`;
            let percent = Math.min(100, Math.round((elapsed / totalTime) * 100));
            timerBar.style.width = `${percent}%`;

            if (remaining > 0) {
                claimBtnText.textContent = `Mag-antay nang ${remaining}s...`;
            } else {
                clearInterval(currentAdChallenge.timerInterval);
                currentAdChallenge.timerInterval = null;
                currentAdChallenge.isReadyToClaim = true;

                timerStatus.innerHTML = '<span class="text-emerald-400 font-bold"><i class="fa-solid fa-check-circle"></i> Ready to claim!</span>';
                timerText.textContent = '✓ Done';
                claimBtn.disabled = false;
                claimBtn.className = 'w-full py-3.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-extrabold text-sm shadow-xl shadow-emerald-950/40 transition-all flex items-center justify-center gap-2 cursor-pointer';
                claimBtn.innerHTML = '<i class="fa-solid fa-gift text-base"></i> <span>I-claim ang Coins Ngayon!</span>';
            }
        }, 1000);

    } catch (err) {
        console.error('start_ad_visit error:', err);
        alert('May error sa koneksyon. Pakisubukan muli.');
        closeSponsorModal();
    }
}

// 3. Submit Claim with AdBlock and Token verification
async function submitVerifiedClaim() {
    if (!currentAdChallenge.isReadyToClaim || !currentAdChallenge.token) {
        return;
    }

    if (checkAdBlock()) {
        closeSponsorModal();
        openAdblockModal();
        return;
    }

    const claimBtn = document.getElementById('btn-claim-verified');
    if (claimBtn) {
        claimBtn.disabled = true;
        claimBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> <span>Vine-verify ang reward...</span>';
    }

    try {
        const resp = await fetch('<?= BASE_URL ?>api/rewards.php?action=claim_ad_reward', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                type: currentAdChallenge.type,
                visit_token: currentAdChallenge.token,
                adblock_status: checkAdBlock() ? 'blocked' : 'allowed'
            })
        });
        const resData = await resp.json();

        if (resData.success) {
            closeSponsorModal();
            alert(resData.message || '🎉 Tagumpay na na-claim ang iyong coins!');
            window.location.reload();
        } else {
            if (resData.is_adblock) {
                closeSponsorModal();
                openAdblockModal();
            } else if (resData.too_fast) {
                alert(resData.message || 'Paki-antay matapos ang 5 segundo bago mag-claim!');
                if (claimBtn) {
                    claimBtn.disabled = false;
                    claimBtn.innerHTML = '<i class="fa-solid fa-gift"></i> <span>I-claim ang Coins Ngayon!</span>';
                }
            } else {
                alert(resData.message || 'Hindi ma-verify ang claim.');
                closeSponsorModal();
            }
        }
    } catch (err) {
        console.error('claim_ad_reward error:', err);
        alert('May error sa koneksyon habang nagki-claim.');
        if (claimBtn) {
            claimBtn.disabled = false;
            claimBtn.innerHTML = '<i class="fa-solid fa-gift"></i> <span>I-claim ang Coins Ngayon!</span>';
        }
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

