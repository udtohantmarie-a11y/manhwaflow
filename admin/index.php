<?php
// admin/index.php - Admin Dashboard & Manhwa Management
require_once __DIR__ . '/../config/db.php';
$pdo = getPdo();

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
            <a href="<?= BASE_URL ?>admin/seed.php" 
               onclick="return confirm('Nais mo bang i-reset at muling lagyan ng sample manhwas ang database?')"
               class="px-3.5 py-2.5 rounded-xl bg-dark-850 hover:bg-rose-900/30 text-slate-400 hover:text-rose-400 border border-dark-800 font-semibold text-xs transition-colors" title="I-reset ang Sample Data">
                <i class="fa-solid fa-rotate"></i> Re-Seed
            </a>
        </div>
    </div>

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
                                    <a href="<?= BASE_URL ?>admin/delete.php?type=manhwa&id=<?= $m['id'] ?>" 
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

