<?php
// admin/index.php - Admin Dashboard & Manhwa Management
require_once __DIR__ . '/auth_check.php';
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

// Stats
$totalManhwa = $pdo->query("SELECT COUNT(*) FROM `manhwas`")->fetchColumn();
$totalChapters = $pdo->query("SELECT COUNT(*) FROM `chapters`")->fetchColumn();
$totalViews = $pdo->query("SELECT SUM(views) FROM `manhwas`")->fetchColumn() ?: 0;

// Manhwa list with chapter count
$stmt = $pdo->query("
    SELECT m.*, 
           (SELECT COUNT(*) FROM chapters c WHERE c.manhwa_id = m.id) AS chapter_count,
           (SELECT MAX(chapter_number) FROM chapters c WHERE c.manhwa_id = m.id) AS max_chapter
    FROM manhwas m 
    ORDER BY m.id DESC
");
$manhwas = $stmt->fetchAll();

$page_title = 'Admin Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

    <!-- Top Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-dark-800">
        <div>
            <div class="flex items-center gap-2">
                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wider bg-brand-600/20 text-brand-400 border border-brand-500/30">
                    Administrator
                </span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-black text-white mt-1">
                Pamamahala ng Manhwa &amp; Chapters
            </h1>
            <p class="text-xs text-slate-400">Dito ka maaaring magdagdag ng bagong serye, mag-upload ng mga kabanata, at mag-manage ng content.</p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <a href="<?= BASE_URL ?>admin/payouts.php" 
               class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-bold text-xs shadow-md shadow-emerald-950/40 transition-all flex items-center gap-1.5">
                <i class="fa-solid fa-money-bill-wave"></i> GCash Payouts
            </a>
            <a href="<?= BASE_URL ?>admin/mangadex.php" 
               class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-brand-600 hover:from-amber-400 hover:to-brand-500 text-white font-bold text-xs shadow-md shadow-brand-600/30 transition-all flex items-center gap-1.5">
                <i class="fa-solid fa-cloud-arrow-down"></i> MangaDex API
            </a>
            <a href="<?= BASE_URL ?>admin/add_manhwa.php" 
               class="px-4 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-500 text-white font-bold text-xs shadow-md shadow-brand-600/30 transition-all flex items-center gap-1.5">
                <i class="fa-solid fa-plus"></i> Bagong Manhwa
            </a>
            <a href="<?= BASE_URL ?>admin/add_chapter.php" 
               class="px-4 py-2.5 rounded-xl bg-dark-800 hover:bg-dark-700 text-slate-200 hover:text-white border border-dark-700 font-bold text-xs transition-all flex items-center gap-1.5">
                <i class="fa-solid fa-cloud-arrow-up text-brand-400"></i> Mag-upload ng Kabanata
            </a>
            <button onclick="document.getElementById('pwd-modal').classList.remove('hidden')" 
                    class="px-3.5 py-2.5 rounded-xl bg-dark-850 hover:bg-dark-800 text-amber-400 border border-amber-500/30 font-bold text-xs transition-all flex items-center gap-1.5 shadow-sm">
                <i class="fa-solid fa-key"></i> Security / Password
            </button>
            <a href="<?= BASE_URL ?>admin/seed.php?csrf=<?= getCsrfToken() ?>" 
               onclick="return confirm('Nais mo bang i-reset at muling lagyan ng sample manhwas ang database?')"
               class="px-3.5 py-2.5 rounded-xl bg-dark-850 hover:bg-rose-900/30 text-slate-400 hover:text-rose-400 border border-dark-800 font-semibold text-xs transition-colors" title="I-reset ang Sample Data">
                <i class="fa-solid fa-rotate"></i> Re-Seed
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

    <!-- Stats Overview Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
        <div class="bg-dark-900 border border-dark-800 rounded-2xl p-5 flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-brand-600/20 text-brand-400 flex items-center justify-center text-xl">
                <i class="fa-solid fa-book-journal-whills"></i>
            </div>
            <div>
                <p class="text-xs text-slate-400 font-medium">Kabuuang Manhwa</p>
                <h3 class="text-2xl font-black text-white"><?= number_format($totalManhwa) ?></h3>
            </div>
        </div>

        <div class="bg-dark-900 border border-dark-800 rounded-2xl p-5 flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-indigo-600/20 text-indigo-400 flex items-center justify-center text-xl">
                <i class="fa-solid fa-layer-group"></i>
            </div>
            <div>
                <p class="text-xs text-slate-400 font-medium">Kabuuang Kabanata (Chapters)</p>
                <h3 class="text-2xl font-black text-white"><?= number_format($totalChapters) ?></h3>
            </div>
        </div>

        <div class="bg-dark-900 border border-dark-800 rounded-2xl p-5 flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-emerald-600/20 text-emerald-400 flex items-center justify-center text-xl">
                <i class="fa-solid fa-chart-simple"></i>
            </div>
            <div>
                <p class="text-xs text-slate-400 font-medium">Kabuuang Views</p>
                <h3 class="text-2xl font-black text-white"><?= number_format($totalViews) ?></h3>
            </div>
        </div>
    </div>

    <!-- Manhwa List Table -->
    <div class="bg-dark-900 rounded-2xl border border-dark-800 overflow-hidden shadow-xl">
        <div class="p-5 border-b border-dark-800 flex items-center justify-between">
            <h2 class="text-base font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-table-list text-brand-500"></i> Lahat ng Manhwa Titles
            </h2>
            <span class="text-xs text-slate-400"><?= count($manhwas) ?> nakatala</span>
        </div>

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
                    <?php if (empty($manhwas)): ?>
                        <tr>
                            <td colspan="7" class="py-8 text-center text-slate-500">
                                Walang manhwa sa database. Magdagdag ng bago o i-click ang Re-Seed.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($manhwas as $m): ?>
                            <tr class="hover:bg-dark-850/60 transition-colors">
                                <!-- Thumbnail -->
                                <td class="py-3 px-4">
                                    <div class="w-10 h-14 rounded-lg overflow-hidden bg-dark-950 border border-dark-700">
                                        <img src="<?= htmlspecialchars($m['cover_image']) ?>" 
                                             alt="<?= htmlspecialchars($m['title']) ?>" 
                                             class="w-full h-full object-cover">
                                    </div>
                                </td>

                                <!-- Title -->
                                <td class="py-3 px-4">
                                    <a href="<?= BASE_URL ?>manhwa.php?id=<?= $m['id'] ?>" class="font-bold text-white hover:text-brand-400 text-sm transition-colors block">
                                        <?= htmlspecialchars($m['title']) ?>
                                    </a>
                                    <span class="text-[11px] text-slate-400">May-akda: <?= htmlspecialchars($m['author']) ?></span>
                                </td>

                                <!-- Type -->
                                <td class="py-3 px-4">
                                    <span class="px-2 py-0.5 rounded bg-dark-800 text-slate-300 font-semibold text-[10px]">
                                        <?= htmlspecialchars($m['type']) ?>
                                    </span>
                                </td>

                                <!-- Status -->
                                <td class="py-3 px-4">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold <?= $m['status'] === 'Completed' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-blue-500/20 text-blue-400' ?>">
                                        <?= htmlspecialchars($m['status']) ?>
                                    </span>
                                </td>

                                <!-- Chapters -->
                                <td class="py-3 px-4 font-semibold text-slate-200">
                                    <?= $m['chapter_count'] ?> kabanata
                                    <?php if ($m['max_chapter']): ?>
                                        <span class="text-[10px] text-slate-500 block">(Pinakahuli: Ch. <?= $m['max_chapter'] ?>)</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Views -->
                                <td class="py-3 px-4 text-slate-300">
                                    <?= number_format($m['views']) ?>
                                </td>

                                <!-- Actions -->
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

