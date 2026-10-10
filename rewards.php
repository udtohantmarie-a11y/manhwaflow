<?php
// rewards.php - Flow Rewards Hub & Redeem Store
require_once __DIR__ . '/config/db.php';
$pdo = getPdo();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$page_title = 'Rewards Hub & GCash Redeem';
require_once __DIR__ . '/includes/header.php';

$userId = $_SESSION['user_id'] ?? null;
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

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-10">

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
                    <button onclick="claimCheckin()" id="btn-checkin"
                            class="px-6 py-3 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-bold text-sm shadow-xl shadow-emerald-950/30 transition-all inline-flex items-center gap-2">
                        <i class="fa-solid fa-gift"></i>
                        <span>Claim Today's Login Bonus</span>
                    </button>
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
                            <button onclick="watchDailyAd()" id="btn-watch-ad"
                                    class="w-full py-2.5 px-4 rounded-xl bg-gradient-to-r from-amber-500 to-yellow-500 hover:from-amber-400 hover:to-yellow-400 text-dark-950 font-black text-xs shadow-md shadow-amber-500/20 transition-all flex items-center justify-center gap-2">
                                <i class="fa-solid fa-play text-[10px]"></i>
                                <span>Watch Ad (<?= $adsWatchedToday ?>/5)</span>
                            </button>
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
                            <button onclick="claimSponsorQuest()" id="btn-sponsor-quest"
                                    class="w-full py-2.5 px-4 rounded-xl bg-gradient-to-r from-brand-600 to-indigo-600 hover:from-brand-500 hover:to-indigo-500 text-white font-bold text-xs shadow-md shadow-brand-600/30 transition-all flex items-center justify-center gap-2">
                                <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                                <span>Claim +50 Coins (Visit Offer)</span>
                            </button>
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
                    <form onsubmit="submitRedeemCode(event)" class="space-y-3">
                        <div class="relative">
                            <input type="text" id="redeem-input-code" required placeholder="e.g. FLOW2026, MANHWAFACEBOOK"
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

        <form id="payout-settings-form" onsubmit="savePayoutSettings(event)" class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-1">
            <div>
                <label class="block text-[11px] font-bold text-slate-400 mb-1">Preferred Method</label>
                <select id="setting-method" class="w-full bg-dark-850 border border-dark-700 rounded-xl px-3.5 py-2.5 text-xs text-white focus:outline-none focus:border-emerald-500">
                    <option value="gcash" <?= $defaultMethod === 'gcash' ? 'selected' : '' ?>>GCash</option>
                    <option value="maya" <?= $defaultMethod === 'maya' ? 'selected' : '' ?>>Maya (PayMaya)</option>
                    <option value="load" <?= $defaultMethod === 'load' ? 'selected' : '' ?>>Prepaid Load</option>
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-400 mb-1">Account Full Name</label>
                <input type="text" id="setting-name" value="<?= htmlspecialchars($defaultName) ?>" required placeholder="e.g. Maria Santos"
                       class="w-full bg-dark-850 border border-dark-700 rounded-xl px-3.5 py-2.5 text-xs text-white focus:outline-none focus:border-emerald-500">
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-400 mb-1">Mobile / Account Number</label>
                <div class="flex gap-2">
                    <input type="text" id="setting-number" value="<?= htmlspecialchars($defaultNumber) ?>" required placeholder="e.g. 09123456789"
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

        <form id="redeem-form" onsubmit="submitPayout(event)" class="space-y-4">
            <input type="hidden" id="payout-amount" value="0">

            <div>
                <label class="block text-xs font-bold text-slate-300 mb-1.5">Payment Method</label>
                <select id="payout-method" class="w-full bg-dark-850 border border-dark-700 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-emerald-500">
                    <option value="gcash">GCash</option>
                    <option value="maya">Maya (PayMaya)</option>
                    <option value="load">Regular Prepaid Load</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-300 mb-1.5">Account / Full Name</label>
                <input type="text" id="payout-name" required placeholder="e.g. Juan Dela Cruz"
                       class="w-full bg-dark-850 border border-dark-700 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-emerald-500">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-300 mb-1.5">Mobile Number</label>
                <input type="text" id="payout-number" required placeholder="e.g. 09123456789"
                       class="w-full bg-dark-850 border border-dark-700 rounded-xl px-4 py-2.5 text-xs text-white font-mono focus:outline-none focus:border-emerald-500">
            </div>

            <button type="submit" id="btn-submit-payout"
                    class="w-full py-3 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-bold text-xs shadow-lg shadow-emerald-950/40 transition-all">
                Confirm Cashout Request
            </button>
        </form>
    </div>
</div>

<script>
// 1. Watch Daily Ad (0/5 Quests)
async function watchDailyAd() {
    const btn = document.getElementById('btn-watch-ad');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-[10px]"></i> Loading Ad...';
    }

    try {
        const res = await fetch('<?= BASE_URL ?>api/rewards.php?action=watch_ad', { method: 'POST' });
        const data = await res.json();
        if (data.success) {
            // Open sponsored ad link
            if (data.sponsor_url) {
                window.open(data.sponsor_url, '_blank');
            }
            alert(data.message);
            window.location.reload();
        } else {
            alert(data.message);
        }
    } catch(e) {
        alert('Could not record ad watch. Please check your connection and try again.');
    } finally {
        if (btn) btn.disabled = false;
    }
}

// 2. Submit Monthly Redeem Code
async function submitRedeemCode(e) {
    e.preventDefault();
    const input = document.getElementById('redeem-input-code');
    const btn = document.getElementById('btn-submit-code');
    if (!input || !input.value.trim()) return;

    const code = input.value.trim().toUpperCase();
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-xs"></i> Checking Code...';
    }

    try {
        const res = await fetch('<?= BASE_URL ?>api/rewards.php?action=redeem_code', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ code: code })
        });
        
        const rawText = await res.text();
        let data;
        try {
            data = JSON.parse(rawText);
        } catch(pErr) {
            console.error('Non-JSON server response:', rawText);
            const cleanErr = rawText.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().substring(0, 150);
            alert('Server response: ' + (cleanErr || 'Could not parse response.'));
            return;
        }

        alert(data.message || (data.success ? 'Code successfully redeemed!' : 'Could not process redeem code.'));
        if (data.success) {
            window.location.reload();
        }
    } catch(err) {
        console.error(err);
        alert('Could not process redeem code. Please check your internet connection and try again.');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-gift"></i> <span>Redeem Bonus Coins</span>';
        }
    }
}

// Check-In API call
async function claimCheckin() {
    const btn = document.getElementById('btn-checkin');
    if (btn) btn.disabled = true;

    try {
        const res = await fetch('<?= BASE_URL ?>api/rewards.php?action=checkin', { method: 'POST' });
        const data = await res.json();
        alert(data.message);
        if (data.success) {
            window.location.reload();
        }
    } catch(e) {
        alert('Something went wrong. Please try again.');
    } finally {
        if (btn) btn.disabled = false;
    }
}

// Sponsor Quest API call (Monetag Link)
async function claimSponsorQuest() {
    const btn = document.getElementById('btn-sponsor-quest');
    if (btn) btn.disabled = true;

    try {
        const res = await fetch('<?= BASE_URL ?>api/rewards.php?action=sponsor_quest', { method: 'POST' });
        const data = await res.json();
        if (data.success) {
            // Open Monetag direct link in new tab
            window.open(data.sponsor_url, '_blank');
            alert(data.message);
            window.location.reload();
        } else {
            alert(data.message);
        }
    } catch(e) {
        alert('Something went wrong. Please try again.');
    } finally {
        if (btn) btn.disabled = false;
    }
}

// Save Payout Settings Form
async function savePayoutSettings(e) {
    e.preventDefault();
    const btn = document.getElementById('btn-save-settings');
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Saving...';
    }

    const method = document.getElementById('setting-method').value;
    const name = document.getElementById('setting-name').value;
    const number = document.getElementById('setting-number').value;

    try {
        const formData = new URLSearchParams();
        formData.append('csrf_token', '<?= getCsrfToken() ?>');
        formData.append('default_payout_method', method);
        formData.append('default_account_name', name);
        formData.append('default_account_number', number);

        const res = await fetch('<?= BASE_URL ?>api/rewards.php?action=save_payout_settings', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
        });
        const data = await res.json();
        alert(data.message);
        if (data.success) {
            window.location.reload();
        }
    } catch(err) {
        alert('Problem saving settings. Please try again.');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Save';
        }
    }
}

// Modal controls
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

// Submit Payout Request
async function submitPayout(e) {
    e.preventDefault();
    const btn = document.getElementById('btn-submit-payout');
    btn.disabled = true;
    btn.textContent = 'Submitting...';

    const amount = document.getElementById('payout-amount').value;
    const method = document.getElementById('payout-method').value;
    const name = document.getElementById('payout-name').value;
    const number = document.getElementById('payout-number').value;

    try {
        const formData = new URLSearchParams();
        formData.append('csrf_token', '<?= getCsrfToken() ?>');
        formData.append('amount_php', amount);
        formData.append('payout_method', method);
        formData.append('account_name', name);
        formData.append('account_number', number);

        const res = await fetch('<?= BASE_URL ?>api/rewards.php?action=request_payout', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
        });
        const data = await res.json();
        alert(data.message);
        if (data.success) {
            closeRedeemModal();
            window.location.reload();
        }
    } catch(err) {
        alert('Network error while submitting payout request.');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Confirm Cashout Request';
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

