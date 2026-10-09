<?php
// admin/index.php - Live API Admin Dashboard & Management Console
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../includes/mangadex.php';
$pdo = getPdo();

$pwdMsg = '';
$pwdMsgType = '';

// Check if admin is using default password
$adminUserStmt = $pdo->prepare("SELECT * FROM `users` WHERE `id` = ?");
$adminUserStmt->execute([$_SESSION['user_id']]);
$currentAdmin = $adminUserStmt->fetch();
$isDefaultPassword = ($currentAdmin && password_verify('admin123', $currentAdmin['password']));

// Handle change password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_admin_password') {
    requireCsrf();
    $newPass = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';
    if (strlen($newPass) < 8) {
        $pwdMsg = 'The new password must be at least 8 characters long.';
        $pwdMsgType = 'rose';
    } elseif ($newPass !== $confirmPass) {
        $pwdMsg = 'Password confirmation does not match.';
        $pwdMsgType = 'rose';
    } else {
        $hashed = password_hash($newPass, PASSWORD_DEFAULT);
        $up = $pdo->prepare("UPDATE `users` SET `password` = ? WHERE `id` = ?");
        $up->execute([$hashed, $_SESSION['user_id']]);
        $pwdMsg = 'Your Admin password has been successfully updated! Your account is now secured.';
        $pwdMsgType = 'emerald';
        $isDefaultPassword = false;
    }
}

// 1. Live Platform Metrics
$totalUsers = $pdo->query("SELECT COUNT(*) FROM `users`")->fetchColumn();
$totalCoinsBalance = $pdo->query("SELECT SUM(`coins`) FROM `user_rewards`")->fetchColumn() ?: 0;
$totalCoinsEarned = $pdo->query("SELECT SUM(`total_earned`) FROM `user_rewards`")->fetchColumn() ?: 0;
$pendingPayouts = $pdo->query("SELECT COUNT(*) FROM `payout_requests` WHERE `status` = 'pending'")->fetchColumn();
$totalPaidPhp = $pdo->query("SELECT SUM(`amount_php`) FROM `payout_requests` WHERE `status` = 'approved'")->fetchColumn() ?: 0;
$totalComments = $pdo->query("SELECT COUNT(*) FROM `chapter_comments`")->fetchColumn();

// 2. Cache & API Metrics
$cacheDir = __DIR__ . '/../cache';
$cachedCount = is_dir($cacheDir) ? count(glob($cacheDir . '/*.json')) : 0;

// 3. Search or Trending MangaDex API items
$apiSearchQuery = trim($_GET['q'] ?? '');
$liveManhwas = [];

if (!empty($apiSearchQuery)) {
    $searchRes = MangaDexAPI::searchPaged($apiSearchQuery, 16, 1, false);
    $liveManhwas = $searchRes['items'] ?? [];
} else {
    $liveManhwas = MangaDexAPI::getPopularLive(12);
}

$page_title = 'Admin Control Center - ManhwaFlow';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

    <!-- Top Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-dark-800">
        <div>
            <div class="flex items-center gap-2">
                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wider bg-rose-600/20 text-rose-400 border border-rose-500/30">
                    Administrator
                </span>
                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span> MangaDex API Active
                </span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-black text-white mt-1">
                Admin Control Center
            </h1>
            <p class="text-xs text-slate-400">Monitor live API streaming, manage GCash payout requests, and configure platform settings.</p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <a href="<?= BASE_URL ?>admin/payouts.php" 
               class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-bold text-xs shadow-md shadow-emerald-950/40 transition-all flex items-center gap-1.5 relative">
                <i class="fa-solid fa-money-bill-wave"></i> GCash &amp; Maya Payouts
                <?php if ($pendingPayouts > 0): ?>
                    <span class="px-1.5 py-0.2 rounded-full bg-amber-400 text-dark-950 text-[10px] font-black animate-bounce ml-1">
                        <?= $pendingPayouts ?>
                    </span>
                <?php endif; ?>
            </a>
            <a href="<?= BASE_URL ?>admin/redeem_codes.php" 
               class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-bold text-xs shadow-md shadow-blue-950/40 transition-all flex items-center gap-1.5">
                <i class="fa-solid fa-ticket"></i> Redeem Codes
            </a>
            <button onclick="document.getElementById('pwd-modal').classList.remove('hidden')" 
                    class="px-3.5 py-2.5 rounded-xl bg-dark-850 hover:bg-dark-800 text-amber-400 border border-amber-500/30 font-bold text-xs transition-all flex items-center gap-1.5 shadow-sm">
                <i class="fa-solid fa-key"></i> Security / Password
            </button>
            <a href="<?= BASE_URL ?>" target="_blank" 
               class="px-3.5 py-2.5 rounded-xl bg-dark-850 hover:bg-dark-800 text-slate-300 hover:text-white border border-dark-750 font-bold text-xs transition-all flex items-center gap-1.5">
                <i class="fa-solid fa-arrow-up-right-from-square"></i> View Live Site
            </a>
        </div>
    </div>

    <!-- Security Notification / Password Feedback -->
    <?php if (!empty($pwdMsg)): ?>
        <div class="p-4 rounded-xl bg-<?= $pwdMsgType ?>-500/10 border border-<?= $pwdMsgType ?>-500/30 text-<?= $pwdMsgType ?>-400 text-xs font-bold flex items-center gap-2">
            <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($pwdMsg) ?>
        </div>
    <?php endif; ?>

    <?php if ($isDefaultPassword): ?>
        <div class="p-4 sm:p-5 rounded-2xl bg-rose-500/10 border border-rose-500/40 text-rose-300 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-xl">
            <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-xl bg-rose-500/20 text-rose-400 flex items-center justify-center shrink-0 text-lg">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
                <div>
                    <h4 class="text-sm font-black text-white">CRITICAL SECURITY WARNING: Your Admin Password is set to default!</h4>
                    <p class="text-xs text-rose-300/80 mt-0.5">
                        Your admin password is still set to <code class="px-1.5 py-0.5 rounded bg-rose-950 font-mono text-rose-200">admin123</code>. Change this immediately before deploying to protect your platform.
                    </p>
                </div>
            </div>
            <button onclick="document.getElementById('pwd-modal').classList.remove('hidden')" 
                    class="px-4 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs shrink-0 shadow-lg shadow-rose-950/50 transition-all">
                <i class="fa-solid fa-lock mr-1.5"></i> Change Password Now
            </button>
        </div>
    <?php endif; ?>

    <!-- System Architecture Info Banner -->
    <div class="bg-gradient-to-r from-brand-950/40 via-dark-900 to-indigo-950/30 border border-brand-500/30 rounded-2xl p-5 sm:p-6 shadow-xl flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div class="space-y-1">
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 text-[11px] font-bold">
                    <i class="fa-solid fa-network-wired"></i> MangaDex Cloud Streaming Engine
                </span>
                <span class="text-xs text-slate-400">Zero Server Disk Usage</span>
            </div>
            <h3 class="text-base font-bold text-white">100% API-Driven Content Delivery</h3>
            <p class="text-xs text-slate-400 max-w-3xl leading-relaxed">
                ManhwaFlow streams comics, chapters, and high-resolution panels directly from the global MangaDex CDN in real-time. No manual uploads or local storage required.
            </p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <div class="text-right">
                <span class="text-[10px] text-slate-400 uppercase font-bold block">Smart Cache</span>
                <span class="text-base font-mono font-black text-brand-400"><?= $cachedCount ?> Cached Feeds</span>
            </div>
        </div>
    </div>

    <!-- Platform Key Metrics (Real Stats) -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-dark-900 border border-dark-800 rounded-2xl p-4 sm:p-5 flex items-center gap-3.5">
            <div class="w-11 h-11 rounded-xl bg-indigo-600/20 text-indigo-400 flex items-center justify-center text-lg shrink-0">
                <i class="fa-solid fa-users"></i>
            </div>
            <div>
                <p class="text-[11px] text-slate-400 font-medium">Registered Readers</p>
                <h3 class="text-xl sm:text-2xl font-black text-white"><?= number_format($totalUsers) ?></h3>
            </div>
        </div>

        <div class="bg-dark-900 border border-dark-800 rounded-2xl p-4 sm:p-5 flex items-center gap-3.5">
            <div class="w-11 h-11 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center text-lg shrink-0">
                <i class="fa-solid fa-coins"></i>
            </div>
            <div>
                <p class="text-[11px] text-slate-400 font-medium">Circulating Flow Coins</p>
                <h3 class="text-xl sm:text-2xl font-black text-amber-400"><?= number_format($totalCoinsBalance) ?></h3>
            </div>
        </div>

        <div class="bg-dark-900 border border-dark-800 rounded-2xl p-4 sm:p-5 flex items-center gap-3.5">
            <div class="w-11 h-11 rounded-xl bg-emerald-600/20 text-emerald-400 flex items-center justify-center text-lg shrink-0">
                <i class="fa-solid fa-money-bill-transfer"></i>
            </div>
            <div>
                <p class="text-[11px] text-slate-400 font-medium">Pending Payouts</p>
                <h3 class="text-xl sm:text-2xl font-black text-emerald-400"><?= number_format($pendingPayouts) ?></h3>
            </div>
        </div>

        <div class="bg-dark-900 border border-dark-800 rounded-2xl p-4 sm:p-5 flex items-center gap-3.5">
            <div class="w-11 h-11 rounded-xl bg-brand-600/20 text-brand-400 flex items-center justify-center text-lg shrink-0">
                <i class="fa-solid fa-comments"></i>
            </div>
            <div>
                <p class="text-[11px] text-slate-400 font-medium">Community Comments</p>
                <h3 class="text-xl sm:text-2xl font-black text-white"><?= number_format($totalComments) ?></h3>
            </div>
        </div>
    </div>

    <!-- MANGADEX LIVE API CATALOG & EXPLORER -->
    <div class="space-y-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-fire text-amber-400"></i> Live MangaDex Catalog &amp; Search
                </h2>
                <p class="text-xs text-slate-400">Browse trending series streamed live through the API and preview reader pages.</p>
            </div>

            <!-- API Search Form -->
            <form action="<?= BASE_URL ?>admin/index.php" method="GET" class="flex gap-2">
                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
                    <input type="text" name="q" value="<?= htmlspecialchars($apiSearchQuery) ?>" 
                           placeholder="Search live MangaDex API..."
                           class="bg-dark-900 border border-dark-700 rounded-xl pl-8 pr-3 py-1.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-brand-500">
                </div>
                <button type="submit" class="px-3 py-1.5 rounded-xl bg-brand-600 hover:bg-brand-500 text-white text-xs font-bold transition-all">
                    Search
                </button>
                <?php if (!empty($apiSearchQuery)): ?>
                    <a href="<?= BASE_URL ?>admin/index.php" class="px-2.5 py-1.5 rounded-xl bg-dark-800 text-slate-400 text-xs hover:text-white flex items-center">
                        Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <div class="bg-dark-900 rounded-2xl border border-dark-800 overflow-hidden shadow-xl">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-300">
                    <thead class="bg-dark-850 text-slate-400 uppercase tracking-wider text-[11px] font-semibold border-b border-dark-800">
                        <tr>
                            <th class="py-3 px-4">Poster</th>
                            <th class="py-3 px-4">Series Title / Author</th>
                            <th class="py-3 px-4">MangaDex UUID</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4">Rating</th>
                            <th class="py-3 px-4 text-right">Preview</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-dark-800">
                        <?php if (empty($liveManhwas)): ?>
                            <tr>
                                <td colspan="6" class="py-8 text-center text-slate-500">
                                    No series found on MangaDex API.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($liveManhwas as $m): ?>
                                <tr class="hover:bg-dark-850/60 transition-colors">
                                    <!-- Thumbnail -->
                                    <td class="py-3 px-4">
                                        <div class="w-10 h-14 rounded-lg overflow-hidden bg-dark-950 border border-dark-700">
                                            <img src="<?= htmlspecialchars($m['cover_url']) ?>" 
                                                 alt="<?= htmlspecialchars($m['title']) ?>" 
                                                 class="w-full h-full object-cover"
                                                 loading="lazy">
                                        </div>
                                    </td>

                                    <!-- Title -->
                                    <td class="py-3 px-4">
                                        <a href="<?= BASE_URL ?>manhwa.php?md_id=<?= $m['id'] ?>" target="_blank" class="font-bold text-white hover:text-brand-400 text-sm transition-colors block">
                                            <?= htmlspecialchars($m['title']) ?>
                                        </a>
                                        <span class="text-[11px] text-slate-500">Author: <?= htmlspecialchars($m['author'] ?? 'Unknown') ?></span>
                                    </td>

                                    <!-- ID -->
                                    <td class="py-3 px-4 font-mono text-[11px] text-slate-400">
                                        <span class="px-2 py-0.5 rounded bg-dark-800 border border-dark-700 select-all">
                                            <?= htmlspecialchars(substr($m['id'], 0, 13)) ?>...
                                        </span>
                                    </td>

                                    <!-- Status -->
                                    <td class="py-3 px-4">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold <?= ($m['status'] ?? '') === 'completed' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-blue-500/20 text-blue-400' ?>">
                                            <?= htmlspecialchars(ucfirst($m['status'] ?? 'Ongoing')) ?>
                                        </span>
                                    </td>

                                    <!-- Rating -->
                                    <td class="py-3 px-4 text-amber-400 font-bold">
                                        <i class="fa-solid fa-star text-[10px] mr-1"></i><?= number_format($m['rating'] ?? 4.8, 1) ?>
                                    </td>

                                    <!-- Actions -->
                                    <td class="py-3 px-4 text-right space-x-1">
                                        <a href="<?= BASE_URL ?>manhwa.php?md_id=<?= $m['id'] ?>" target="_blank"
                                           class="px-3 py-1.5 rounded-lg bg-brand-600 hover:bg-brand-500 text-white font-bold transition-all inline-flex items-center gap-1.5" title="View on Live Site">
                                            <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- Change Admin Password Modal -->
<div id="pwd-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm hidden">
    <div class="bg-dark-900 border border-dark-700 rounded-2xl max-w-md w-full p-6 space-y-5 shadow-2xl relative">
        <button onclick="document.getElementById('pwd-modal').classList.add('hidden')" 
                class="absolute right-4 top-4 text-slate-400 hover:text-white transition-colors">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>

        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-400 flex items-center justify-center text-lg">
                <i class="fa-solid fa-shield-halved"></i>
            </div>
            <div>
                <h3 class="text-base font-bold text-white">Change Admin Password</h3>
                <p class="text-xs text-slate-400">Secure your platform against unauthorized administrative access.</p>
            </div>
        </div>

        <form method="POST" action="" class="space-y-4">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="change_admin_password">

            <div>
                <label class="block text-xs font-bold text-slate-300 mb-1.5">New Password (min. 8 characters)</label>
                <input type="password" name="new_password" required minlength="8" placeholder="••••••••" 
                       class="w-full bg-dark-850 border border-dark-700 rounded-xl px-3.5 py-2.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-brand-500">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-300 mb-1.5">Confirm New Password</label>
                <input type="password" name="confirm_password" required minlength="8" placeholder="••••••••" 
                       class="w-full bg-dark-850 border border-dark-700 rounded-xl px-3.5 py-2.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-brand-500">
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="document.getElementById('pwd-modal').classList.add('hidden')"
                        class="px-4 py-2 rounded-xl bg-dark-800 hover:bg-dark-700 text-slate-300 text-xs font-bold">
                    Cancel
                </button>
                <button type="submit" 
                        class="px-5 py-2 rounded-xl bg-brand-600 hover:bg-brand-500 text-white text-xs font-bold shadow-md shadow-brand-600/30">
                    Save Password
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
