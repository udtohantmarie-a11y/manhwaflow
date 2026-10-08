<?php
// admin/mangadex.php - MangaDex API Auto-Importer
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/mangadex.php';
$pdo = getPdo();

$query = trim($_GET['q'] ?? '');
$onlyKorean = isset($_GET['korean']) ? (bool)$_GET['korean'] : true;
$results = [];
$errorMsg = '';
$successMsg = '';
$importedId = 0;

// Handle Import Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import') {
    $mangaId = trim($_POST['manga_id'] ?? '');
    $maxCh = intval($_POST['max_chapters'] ?? 3);

    if (!empty($mangaId)) {
        // Fetch specific manga from MangaDex
        $mangaData = [
            'id' => $mangaId,
            'title' => $_POST['title'] ?? 'Unknown',
            'alt_title' => $_POST['alt_title'] ?? '',
            'author' => $_POST['author'] ?? 'Unknown',
            'status' => $_POST['status'] ?? 'Ongoing',
            'description' => $_POST['description'] ?? '',
            'cover_url' => $_POST['cover_url'] ?? '',
            'tags' => !empty($_POST['tags']) ? explode(',', $_POST['tags']) : []
        ];

        try {
            $importResult = MangaDexAPI::importToDatabase($pdo, $mangaData, $maxCh);
            $importedId = $importResult['manhwa_id'];
            $successMsg = "Matagumpay na na-import ang <strong>" . htmlspecialchars($mangaData['title']) . "</strong> kasama ang " . $importResult['chapters_imported'] . " kabanata!";
        } catch (Exception $e) {
            $errorMsg = "Nagkaroon ng problema sa pag-import: " . $e->getMessage();
        }
    }
}

// Handle Search
if (!empty($query)) {
    try {
        $results = MangaDexAPI::search($query, 12, $onlyKorean);
        if (empty($results) && $onlyKorean) {
            // Fallback search without Korean restriction
            $results = MangaDexAPI::search($query, 12, false);
        }
    } catch (Exception $e) {
        $errorMsg = "Hindi makontak ang MangaDex API: " . $e->getMessage();
    }
}

$page_title = 'MangaDex API Importer - Admin';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

    <!-- Header & Breadcrumbs -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-dark-800">
        <div>
            <div class="flex items-center gap-2">
                <a href="<?= BASE_URL ?>admin/index.php" class="text-xs text-brand-400 hover:text-brand-300 font-semibold">
                    <i class="fa-solid fa-arrow-left mr-1"></i> Admin Dashboard
                </a>
                <span class="text-slate-600">/</span>
                <span class="text-xs text-slate-400 font-medium">Auto-Importer</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-black text-white mt-1 flex items-center gap-3">
                <i class="fa-solid fa-cloud-arrow-down text-brand-500"></i> MangaDex API Importer
            </h1>
            <p class="text-xs text-slate-400">Awtomatikong mag-import ng mga manhwa titles, synopsis, official covers, at tunay na kabanata mula sa MangaDex.</p>
        </div>

        <a href="<?= BASE_URL ?>admin/index.php" class="px-4 py-2 rounded-xl bg-dark-800 hover:bg-dark-700 text-slate-300 text-xs font-semibold self-start sm:self-auto">
            Bumalik sa Admin
        </a>
    </div>

    <!-- Success Message -->
    <?php if (!empty($successMsg)): ?>
        <div class="p-5 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-lg shadow-emerald-950/30">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-emerald-500/20 flex items-center justify-center text-emerald-400 text-lg">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <div>
                    <h4 class="font-bold text-white text-base">Import Tagumpay!</h4>
                    <p class="text-xs text-emerald-400/90"><?= $successMsg ?></p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="<?= BASE_URL ?>manhwa.php?id=<?= $importedId ?>" target="_blank" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold transition-colors">
                    Tingnan ang Manhwa <i class="fa-solid fa-arrow-up-right-from-square ml-1"></i>
                </a>
                <a href="<?= BASE_URL ?>" class="px-3.5 py-2 bg-dark-800 hover:bg-dark-700 text-slate-200 rounded-xl text-xs font-medium">
                    Pumunta sa Home
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Error Message -->
    <?php if (!empty($errorMsg)): ?>
        <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-400 text-sm flex items-center gap-2">
            <i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($errorMsg) ?>
        </div>
    <?php endif; ?>

    <!-- Search Section -->
    <div class="bg-dark-900 border border-dark-800 rounded-2xl p-6 sm:p-8 space-y-4 shadow-xl">
        <form action="" method="GET" class="space-y-4">
            <div class="flex flex-col sm:flex-row items-center gap-3">
                <div class="relative flex-1 w-full">
                    <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="text" name="q" value="<?= htmlspecialchars($query) ?>" required
                           placeholder="Mag-type ng manhwa title (hal. Nano Machine, Tower of God, Lookism, Eleceed...)" 
                           class="w-full bg-dark-850 border border-dark-700 rounded-xl pl-11 pr-4 py-3 text-sm text-white placeholder-slate-400 focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20">
                </div>

                <button type="submit" class="w-full sm:w-auto px-6 py-3 rounded-xl bg-gradient-to-r from-brand-600 to-indigo-600 hover:from-brand-500 hover:to-indigo-500 text-white font-bold text-sm shadow-lg shadow-brand-600/30 flex items-center justify-center gap-2 transition-all shrink-0">
                    <i class="fa-solid fa-magnifying-glass"></i> Hanapin sa MangaDex
                </button>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 text-xs text-slate-400">
                <!-- Quick Search Buttons -->
                <div class="flex flex-wrap items-center gap-1.5">
                    <span class="text-slate-400">Mabilisang Pindot:</span>
                    <a href="?q=Nano+Machine" class="px-2.5 py-1 bg-dark-800 hover:bg-dark-700 text-slate-300 rounded-lg">Nano Machine</a>
                    <a href="?q=Tower+of+God" class="px-2.5 py-1 bg-dark-800 hover:bg-dark-700 text-slate-300 rounded-lg">Tower of God</a>
                    <a href="?q=Eleceed" class="px-2.5 py-1 bg-dark-800 hover:bg-dark-700 text-slate-300 rounded-lg">Eleceed</a>
                    <a href="?q=Lookism" class="px-2.5 py-1 bg-dark-800 hover:bg-dark-700 text-slate-300 rounded-lg">Lookism</a>
                    <a href="?q=Mount+Hua" class="px-2.5 py-1 bg-dark-800 hover:bg-dark-700 text-slate-300 rounded-lg">Mount Hua</a>
                </div>

                <label class="flex items-center gap-1.5 cursor-pointer text-slate-300">
                    <input type="checkbox" name="korean" value="1" <?= $onlyKorean ? 'checked' : '' ?> class="rounded bg-dark-800 border-dark-700 text-brand-600 focus:ring-0">
                    <span>Korean Manhwa Only (kr/ko)</span>
                </label>
            </div>
        </form>
    </div>

    <!-- Search Results Grid -->
    <?php if (!empty($query)): ?>
        <div class="space-y-4">
            <div class="flex items-center justify-between border-b border-dark-800 pb-3">
                <h2 class="text-lg font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-list-check text-brand-500"></i> Mga Nahanap sa MangaDex:
                    <span class="text-xs text-slate-400 font-normal">(<?= count($results) ?> resulta)</span>
                </h2>
            </div>

            <?php if (empty($results)): ?>
                <div class="bg-dark-900 border border-dark-800 rounded-2xl p-10 text-center text-slate-400 space-y-2">
                    <i class="fa-solid fa-face-frown text-3xl text-slate-600 mb-2"></i>
                    <p class="text-sm font-semibold text-white">Walang nahanap para sa "<?= htmlspecialchars($query) ?>"</p>
                    <p class="text-xs text-slate-400">Subukang i-uncheck ang "Korean Manhwa Only" o baguhin ang spelling.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($results as $item): ?>
                        <div class="bg-dark-900 rounded-2xl border border-dark-800 hover:border-brand-500/50 p-4 flex flex-col justify-between transition-all duration-300 shadow-lg group">
                            
                            <!-- Top: Cover & Details -->
                            <div class="flex gap-4">
                                <!-- Cover -->
                                <div class="w-24 sm:w-28 shrink-0 aspect-[2/3] rounded-xl overflow-hidden bg-dark-950 border border-dark-800">
                                    <img src="<?= htmlspecialchars($item['cover_url']) ?>" 
                                         alt="<?= htmlspecialchars($item['title']) ?>" 
                                         loading="lazy"
                                         class="w-full h-full object-cover group-hover:scale-105 transition-transform">
                                </div>

                                <!-- Text Meta -->
                                <div class="flex-1 min-w-0 space-y-1.5">
                                    <h3 class="font-bold text-sm text-white line-clamp-2 leading-snug group-hover:text-brand-400 transition-colors" title="<?= htmlspecialchars($item['title']) ?>">
                                        <?= htmlspecialchars($item['title']) ?>
                                    </h3>

                                    <?php if (!empty($item['alt_title'])): ?>
                                        <p class="text-[11px] text-slate-400 line-clamp-1"><?= htmlspecialchars($item['alt_title']) ?></p>
                                    <?php endif; ?>

                                    <div class="flex flex-wrap items-center gap-1.5 text-[10px]">
                                        <span class="px-2 py-0.5 rounded bg-dark-800 text-brand-400 font-semibold">
                                            <?= htmlspecialchars($item['status']) ?>
                                        </span>
                                        <span class="text-slate-400">&bull;</span>
                                        <span class="text-slate-400"><?= htmlspecialchars($item['author']) ?></span>
                                    </div>

                                    <!-- Tags -->
                                    <div class="flex flex-wrap gap-1 pt-1">
                                        <?php foreach (array_slice($item['tags'], 0, 3) as $tag): ?>
                                            <span class="px-1.5 py-0.5 rounded text-[10px] bg-dark-850 text-slate-400 border border-dark-750">
                                                <?= htmlspecialchars($tag) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Synopsis Excerpt -->
                            <div class="my-3 text-xs text-slate-400 line-clamp-3 leading-relaxed border-t border-dark-800/80 pt-2.5">
                                <?= !empty($item['description']) ? htmlspecialchars($item['description']) : 'Walang available na buod mula sa MangaDex.' ?>
                            </div>

                            <!-- Bottom: Import Form -->
                            <form action="" method="POST" class="pt-2 border-t border-dark-800 space-y-2">
                                <input type="hidden" name="action" value="import">
                                <input type="hidden" name="manga_id" value="<?= htmlspecialchars($item['id']) ?>">
                                <input type="hidden" name="title" value="<?= htmlspecialchars($item['title']) ?>">
                                <input type="hidden" name="alt_title" value="<?= htmlspecialchars($item['alt_title']) ?>">
                                <input type="hidden" name="author" value="<?= htmlspecialchars($item['author']) ?>">
                                <input type="hidden" name="status" value="<?= htmlspecialchars($item['status']) ?>">
                                <input type="hidden" name="description" value="<?= htmlspecialchars($item['description']) ?>">
                                <input type="hidden" name="cover_url" value="<?= htmlspecialchars($item['cover_url']) ?>">
                                <input type="hidden" name="tags" value="<?= htmlspecialchars(implode(',', $item['tags'])) ?>">

                                <div class="flex items-center gap-2">
                                    <select name="max_chapters" class="bg-dark-850 border border-dark-700 text-slate-300 rounded-lg px-2 py-1.5 text-[11px] focus:outline-none focus:border-brand-500">
                                        <option value="3">3 Kabanata</option>
                                        <option value="5" selected>5 Kabanata</option>
                                        <option value="10">10 Kabanata</option>
                                        <option value="0">Metadata lang (0 ch)</option>
                                    </select>

                                    <button type="submit" 
                                            class="flex-1 py-1.5 px-3 rounded-lg bg-brand-600 hover:bg-brand-500 text-white font-bold text-xs shadow-md shadow-brand-600/20 transition-all flex items-center justify-center gap-1.5">
                                        <i class="fa-solid fa-bolt-lightning text-amber-300 text-[10px]"></i> I-import Ngayon
                                    </button>
                                </div>
                            </form>

                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

