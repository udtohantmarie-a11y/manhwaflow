<?php
// admin/index.php - Modern API-First Admin Dashboard & Management Console
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
        $pwdMsg = 'Ang bagong password ay dapat mayroong hindi bababa sa 8 characters.';
        $pwdMsgType = 'rose';
    } elseif ($newPass !== $confirmPass) {
        $pwdMsg = 'Hindi magkatugma ang kumpirmasyon ng password.';
        $pwdMsgType = 'rose';
    } else {
        $hashed = password_hash($newPass, PASSWORD_DEFAULT);
        $up = $pdo->prepare("UPDATE `users` SET `password` = ? WHERE `id` = ?");
        $up->execute([$hashed, $_SESSION['user_id']]);
        $pwdMsg = 'Matagumpay na nabago ang iyong Admin password! Mas secured na ang iyong website.';
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

// Search or Trending MangaDex API items
$apiSearchQuery = trim($_GET['api_q'] ?? '');
$activeTab = $_GET['tab'] ?? 'api'; // 'api' or 'local'

$liveManhwas = [];
if (!empty($apiSearchQuery)) {
    $searchRes = MangaDexAPI::searchPaged($apiSearchQuery, 12, 1, false);
    $liveManhwas = $searchRes['items'] ?? [];
} else {
    $liveManhwas = MangaDexAPI::getPopularLive(10);
}

// 3. Local Database Fallback Titles
$stmtLocal = $pdo->query("
    SELECT m.*, 
           (SELECT COUNT(*) FROM chapters c WHERE c.manhwa_id = m.id) AS chapter_count,
           (SELECT MAX(chapter_number) FROM chapters c WHERE c.manhwa_id = m.id) AS max_chapter
    FROM manhwas m 
    ORDER BY m.id DESC
");
$localManhwas = $stmtLocal->fetchAll();

$page_title = 'Admin Dashboard - ManhwaFlow';
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
            <p class="text-xs text-slate-400">Pamahalaan ang live API integration, GCash cashouts, at platform settings.</p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <a href="<?= BASE_URL ?>admin/payouts.php" 
               class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-bold text-xs shadow-md shadow-emerald-950/40 transition-all flex items-center gap-1.5 relative">
                <i class="fa-solid fa-money-bill-wave"></i> GCash Payouts
                <?php if ($pendingPayouts > 0): ?>
                    <span class="px-1.5 py-0.2 rounded-full bg-amber-400 text-dark-950 text-[10px] font-black animate-bounce ml-1">
                        <?= $pendingPayouts ?>
                    </span>
                <?php endif; ?>
            </a>
            <a href="<?= BASE_URL ?>admin/mangadex.php" 
               class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-brand-600 hover:from-amber-400 hover:to-brand-500 text-white font-bold text-xs shadow-md shadow-brand-600/30 transition-all flex items-center gap-1.5">
                <i class="fa-solid fa-cloud-arrow-down"></i> MangaDex API Importer
            </a>
            <button onclick="document.getElementById('pwd-modal').classList.remove('hidden')" 
                    class="px-3.5 py-2.5 rounded-xl bg-dark-850 hover:bg-dark-800 text-amber-400 border border-amber-500/30 font-bold text-xs transition-all flex items-center gap-1.5 shadow-sm">
                <i class="fa-solid fa-key"></i> Security / Password
            </button>
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
                    <h4 class="text-sm font-black text-white">KRITIKAL NA SEGURIDAD: Naka-default pa ang Admin Password mo!</h4>
                    <p class="text-xs text-rose-300/80 mt-0.5">
                        Ang password ng admin account mo ay <code class="px-1.5 py-0.5 rounded bg-rose-950 font-mono text-rose-200">admin123</code> pa rin. Maaaring ma-hack ang website kung hindi mo ito papalitan agad bago i-deploy online.
                    </p>
                </div>
            </div>
            <button onclick="document.getElementById('pwd-modal').classList.remove('hidden')" 
                    class="px-4 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs shrink-0 shadow-lg shadow-rose-950/50 transition-all">
                <i class="fa-solid fa-lock mr-1.5"></i> Palitan ang Password Ngayon
            </button>
        </div>
    <?php endif; ?>

    <!-- System Architecture Info Banner (Why API is used) -->
    <div class="bg-gradient-to-r from-brand-950/40 via-dark-900 to-indigo-950/30 border border-brand-500/30 rounded-2xl p-5 sm:p-6 shadow-xl flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div class="space-y-1">
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 text-[11px] font-bold">
                    <i class="fa-solid fa-network-wired"></i> MangaDex API Engine (Cloud Streaming)
                </span>
                <span class="text-xs text-slate-400">Zero Server Disk Usage</span>
            </div>
            <h3 class="text-base font-bold text-white">Paano gumagana ang nilalaman ng ManhwaFlow?</h3>
            <p class="text-xs text-slate-400 max-w-3xl leading-relaxed">
                Ang buong website ay <strong>100% pinatatakbo ng MangaDex Live API</strong>. Lahat ng libo-libong manhwa titles, chapters, at comic panels ay diretsong naii-stream mula sa high-speed MangaDex CDN nang libre. Hindi mo kailangang mag-upload ng libo-libong imahe sa server mo.
            </p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <div class="text-right">
                <span class="text-[10px] text-slate-400 uppercase font-bold block">Cached Feed</span>
                <span class="text-base font-mono font-black text-brand-400"><?= $cachedCount ?> Files</span>
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
                <p class="text-[11px] text-slate-400 font-medium">Nakatala na Readers</p>
                <h3 class="text-xl sm:text-2xl font-black text-white"><?= number_format($totalUsers) ?></h3>
            </div>
        </div>

        <div class="bg-dark-900 border border-dark-800 rounded-2xl p-4 sm:p-5 flex items-center gap-3.5">
            <div class="w-11 h-11 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center text-lg shrink-0">
                <i class="fa-solid fa-coins"></i>
            </div>
            <div>
                <p class="text-[11px] text-slate-400 font-medium">Flow Coins Balanse</p>
                <h3 class="text-xl sm:text-2xl font-black text-amber-400"><?= number_format($totalCoinsBalance) ?></h3>
            </div>
        </div>

        <div class="bg-dark-900 border border-dark-800 rounded-2xl p-4 sm:p-5 flex items-center gap-3.5">
            <div class="w-11 h-11 rounded-xl bg-emerald-600/20 text-emerald-400 flex items-center justify-center text-lg shrink-0">
                <i class="fa-solid fa-money-bill-transfer"></i>
            </div>
            <div>
                <p class="text-[11px] text-slate-400 font-medium">Pending GCash Cashouts</p>
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

    <!-- Tab Selector: MangaDex Live API vs Local Uploads -->
    <div class="flex items-center gap-3 border-b border-dark-800 pb-3">
        <a href="<?= BASE_URL ?>admin/index.php?tab=api" 
           class="px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 <?= $activeTab === 'api' ? 'bg-brand-600 text-white shadow-lg shadow-brand-600/20' : 'bg-dark-900 text-slate-400 hover:text-white border border-dark-800' ?>">
            <i class="fa-solid fa-globe"></i> 
            MangaDex Live API Titles (Live sa Website)
            <span class="px-2 py-0.5 rounded-full text-[10px] <?= $activeTab === 'api' ? 'bg-white/20' : 'bg-dark-800' ?>">Active</span>
        </a>
        <a href="<?= BASE_URL ?>admin/index.php?tab=local" 
           class="px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 <?= $activeTab === 'local' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/20' : 'bg-dark-900 text-slate-400 hover:text-white border border-dark-800' ?>">
            <i class="fa-solid fa-hard-drive"></i> 
            Custom / Local Uploads (Optional: <?= count($localManhwas) ?>)
        </a>
    </div>

    <?php if ($activeTab === 'api'): ?>
        <!-- TAB 1: MANGADEX LIVE API FEED & EXPLORER -->
        <div class="space-y-5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-bold text-white flex items-center gap-2">
                        <i class="fa-solid fa-fire text-amber-400"></i> MangaDex Live Catalog Preview
                    </h2>
                    <p class="text-xs text-slate-400">Ito ang mga trending at real-time titles na nababasa ng mga bisita sa iyong website.</p>
                </div>

                <!-- API Search Form -->
                <form action="<?= BASE_URL ?>admin/index.php" method="GET" class="flex gap-2">
                    <input type="hidden" name="tab" value="api">
                    <div class="relative">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
                        <input type="text" name="api_q" value="<?= htmlspecialchars($apiSearchQuery) ?>" 
                               placeholder="Mag-search sa MangaDex API..."
                               class="bg-dark-900 border border-dark-700 rounded-xl pl-8 pr-3 py-1.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-brand-500">
                    </div>
                    <button type="submit" class="px-3 py-1.5 rounded-xl bg-brand-600 hover:bg-brand-500 text-white text-xs font-bold transition-all">
                        Search
                    </button>
                    <?php if (!empty($apiSearchQuery)): ?>
                        <a href="<?= BASE_URL ?>admin/index.php?tab=api" class="px-2.5 py-1.5 rounded-xl bg-dark-800 text-slate-400 text-xs hover:text-white">
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
                                <th class="py-3 px-4">Pamagat sa API</th>
                                <th class="py-3 px-4">API Manga ID</th>
                                <th class="py-3 px-4">Katayuan</th>
                                <th class="py-3 px-4">Rating</th>
                                <th class="py-3 px-4 text-right">Preview sa Site</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-dark-800">
                            <?php if (empty($liveManhwas)): ?>
                                <tr>
                                    <td colspan="6" class="py-8 text-center text-slate-500">
                                        Walang nahanap na serye sa MangaDex API.
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
                                               class="px-3 py-1.5 rounded-lg bg-brand-600 hover:bg-brand-500 text-white font-bold transition-all inline-flex items-center gap-1.5" title="Tingnan sa Website">
                                                <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i> Tingnan
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

    <?php else: ?>
        <!-- TAB 2: LOCAL / CUSTOM DATABASE UPLOADS (OPTIONAL) -->
        <div class="space-y-5">
            <div class="bg-amber-500/10 border border-amber-500/20 rounded-2xl p-4 sm:p-5 text-amber-300 text-xs flex items-start gap-3">
                <i class="fa-solid fa-circle-info text-base text-amber-400 mt-0.5 shrink-0"></i>
                <div class="space-y-1">
                    <strong class="text-white block font-bold">Ano ang "Custom / Local Uploads"?</strong>
                    <p class="text-slate-300 leading-relaxed">
                        Ito ang lumang 7 sample entries sa MySQL database. <strong>Optional lamang ito:</strong> gamitin mo lamang ito kung may sarili kang komiks, gawang fan-translation, o exclusive manga na gusto mong i-host mismo sa hosting server mo. Kung MangaDex API lang ang plano mong gamitin para sa libre at mabilis na streaming, hindi mo na kailangang galawin ito.
                    </p>
                </div>
            </div>

            <div class="flex items-center justify-between">
                <h2 class="text-base font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-database text-indigo-400"></i> Local Database Titles (<?= count($localManhwas) ?>)
                </h2>
                <div class="flex items-center gap-2">
                    <a href="<?= BASE_URL ?>admin/add_manhwa.php" 
                       class="px-3 py-1.5 rounded-xl bg-brand-600 hover:bg-brand-500 text-white font-bold text-xs shadow-md transition-all flex items-center gap-1.5">
                        <i class="fa-solid fa-plus"></i> Bagong Local Manhwa
                    </a>
                    <a href="<?= BASE_URL ?>admin/add_chapter.php" 
                       class="px-3 py-1.5 rounded-xl bg-dark-800 hover:bg-dark-700 text-slate-200 border border-dark-700 font-bold text-xs transition-all flex items-center gap-1.5">
                        <i class="fa-solid fa-cloud-arrow-up text-brand-400"></i> Mag-upload ng Kabanata
                    </a>
                    <a href="<?= BASE_URL ?>admin/seed.php?csrf=<?= getCsrfToken() ?>" 
                       onclick="return confirm('Nais mo bang i-reset at muling lagyan ng sample manhwas ang database?')"
                       class="px-3 py-1.5 rounded-xl bg-dark-850 hover:bg-rose-900/30 text-slate-400 hover:text-rose-400 border border-dark-800 font-semibold text-xs transition-colors" title="I-reset ang Sample Data">
                        <i class="fa-solid fa-rotate"></i> Re-Seed
                    </a>
                </div>
            </div>

            <div class="bg-dark-900 rounded-2xl border border-dark-800 overflow-hidden shadow-xl">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-300">
                        <thead class="bg-dark-850 text-slate-400 uppercase tracking-wider text-[11px] font-semibold border-b border-dark-800">
                            <tr>
                                <th class="py-3 px-4">Poster</th>
                                <th class="py-3 px-4">Pamagat / Author</th>
                                <th class="py-3 px-4">Uri</th>
                                <th class="py-3 px-4">Katayuan</th>
                                <th class="py-3 px-4">Kabanata</th>
                                <th class="py-3 px-4">Views</th>
                                <th class="py-3 px-4 text-right">Aksyon</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-dark-800">
                            <?php if (empty($localManhwas)): ?>
                                <tr>
                                    <td colspan="7" class="py-8 text-center text-slate-500">
                                        Walang custom local manhwa sa database.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($localManhwas as $m): ?>
                                    <tr class="hover:bg-dark-850/60 transition-colors">
                                        <td class="py-3 px-4">
                                            <div class="w-10 h-14 rounded-lg overflow-hidden bg-dark-950 border border-dark-700">
                                                <img src="<?= htmlspecialchars($m['cover_image']) ?>" 
                                                     alt="<?= htmlspecialchars($m['title']) ?>" 
                                                     class="w-full h-full object-cover">
                                            </div>
                                        </td>
                                        <td class="py-3 px-4">
                                            <a href="<?= BASE_URL ?>manhwa.php?id=<?= $m['id'] ?>" class="font-bold text-white hover:text-brand-400 text-sm transition-colors block">
                                                <?= htmlspecialchars($m['title']) ?>
                                            </a>
                                            <span class="text-[11px] text-slate-400">May-akda: <?= htmlspecialchars($m['author']) ?></span>
                                        </td>
                                        <td class="py-3 px-4">
                                            <span class="px-2 py-0.5 rounded bg-dark-800 text-slate-300 font-semibold text-[10px]">
                                                <?= htmlspecialchars($m['type']) ?>
                                            </span>
                                        </td>
                                        <td class="py-3 px-4">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold <?= $m['status'] === 'Completed' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-blue-500/20 text-blue-400' ?>">
                                                <?= htmlspecialchars($m['status']) ?>
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 font-semibold text-slate-200">
                                            <?= $m['chapter_count'] ?> kabanata
                                            <?php if ($m['max_chapter']): ?>
                                                <span class="text-[10px] text-slate-500 block">(Pinakahuli: Ch. <?= $m['max_chapter'] ?>)</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3 px-4 text-slate-300">
                                            <?= number_format($m['views']) ?>
                                        </td>
                                        <td class="py-3 px-4 text-right space-x-1">
                                            <a href="<?= BASE_URL ?>admin/add_chapter.php?manhwa_id=<?= $m['id'] ?>" 
                                               class="px-2.5 py-1.5 rounded-lg bg-brand-600 hover:bg-brand-500 text-white font-semibold transition-colors" title="Magdagdag ng Chapter">
                                                <i class="fa-solid fa-plus mr-1"></i>Chapter
                                            </a>
                                            <a href="<?= BASE_URL ?>manhwa.php?id=<?= $m['id'] ?>" target="_blank"
                                               class="px-2.5 py-1.5 rounded-lg bg-dark-800 hover:bg-dark-700 text-slate-300 transition-colors" title="Tingnan">
                                                <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                            </a>
                                            <a href="<?= BASE_URL ?>admin/delete.php?type=manhwa&id=<?= $m['id'] ?>&csrf=<?= getCsrfToken() ?>" 
                                               onclick="return confirm('Sigurado ka bang nais mong burahin ang <?= htmlspecialchars(addslashes($m['title'])) ?> at lahat ng kabanata nito?')"
                                               class="px-2.5 py-1.5 rounded-lg bg-rose-500/10 hover:bg-rose-500 text-rose-400 hover:text-white transition-colors" title="Burahin">
                                                <i class="fa-solid fa-trash-can"></i>
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
    <?php endif; ?>

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
                <h3 class="text-base font-bold text-white">Baguhin ang Admin Password</h3>
                <p class="text-xs text-slate-400">Protektahan ang iyong website laban sa unauthorized access.</p>
            </div>
        </div>

        <form method="POST" action="" class="space-y-4">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="change_admin_password">

            <div>
                <label class="block text-xs font-bold text-slate-300 mb-1.5">Bagong Password (min. 8 characters)</label>
                <input type="password" name="new_password" required minlength="8" placeholder="••••••••" 
                       class="w-full bg-dark-850 border border-dark-700 rounded-xl px-3.5 py-2.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-brand-500">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-300 mb-1.5">Kumpirmahin ang Bagong Password</label>
                <input type="password" name="confirm_password" required minlength="8" placeholder="••••••••" 
                       class="w-full bg-dark-850 border border-dark-700 rounded-xl px-3.5 py-2.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-brand-500">
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="document.getElementById('pwd-modal').classList.add('hidden')"
                        class="px-4 py-2 rounded-xl bg-dark-800 hover:bg-dark-700 text-slate-300 text-xs font-bold">
                    Kanselahin
                </button>
                <button type="submit" 
                        class="px-5 py-2 rounded-xl bg-brand-600 hover:bg-brand-500 text-white text-xs font-bold shadow-md shadow-brand-600/30">
                    I-save ang Password
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
