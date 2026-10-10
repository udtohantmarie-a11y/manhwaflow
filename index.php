<?php
// index.php - Official Webtoon & Comic Platform
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/mangadex.php';
require_once __DIR__ . '/includes/ads.php';
$pdo = getPdo();

$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$genreSlug = isset($_GET['genre']) ? trim($_GET['genre']) : '';
$typeFilter = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : '';
if ($typeFilter !== 'manhwa' && $typeFilter !== 'manhua') {
    $typeFilter = '';
}
$sourceFilter = isset($_GET['source']) ? strtolower(trim($_GET['source'])) : '';

// English genres list for filter pills
$genres = [
    ['name' => 'Action', 'slug' => 'action'],
    ['name' => 'Adventure', 'slug' => 'adventure'],
    ['name' => 'Fantasy', 'slug' => 'fantasy'],
    ['name' => 'Martial Arts', 'slug' => 'martial-arts'],
    ['name' => 'Comedy', 'slug' => 'comedy'],
    ['name' => 'Supernatural', 'slug' => 'supernatural'],
    ['name' => 'Drama', 'slug' => 'drama'],
    ['name' => 'Romance', 'slug' => 'romance'],
    ['name' => 'Isekai', 'slug' => 'isekai'],
    ['name' => 'School Life', 'slug' => 'school-life'],
    ['name' => 'Magic', 'slug' => 'magic'],
    ['name' => 'Mystery', 'slug' => 'mystery'],
    ['name' => 'Sci-Fi', 'slug' => 'sci-fi'],
    ['name' => 'Psychological', 'slug' => 'psychological'],
    ['name' => 'Slice of Life', 'slug' => 'slice-of-life'],
    ['name' => 'Horror', 'slug' => 'horror'],
    ['name' => 'Thriller', 'slug' => 'thriller'],
    ['name' => 'Reincarnation', 'slug' => 'reincarnation'],
    ['name' => 'Villainess', 'slug' => 'villainess'],
    ['name' => 'Historical', 'slug' => 'historical']
];

$page = max(1, intval($_GET['page'] ?? 1));
$limit = 30;

// Helper to preserve filter query parameters across page numbers
function getPageUrl($p, $search, $genreSlug, $typeFilter = '', $sourceFilter = '') {
    $params = [];
    if (!empty($search)) $params['q'] = $search;
    if (!empty($genreSlug)) $params['genre'] = $genreSlug;
    if (!empty($typeFilter)) $params['type'] = $typeFilter;
    if (!empty($sourceFilter)) $params['source'] = $sourceFilter;
    if ($p > 1) $params['page'] = $p;
    $qs = http_build_query($params);
    return BASE_URL . 'index.php' . ($qs ? '?' . $qs : '');
}

require_once __DIR__ . '/includes/chapters_fallback.php';
$athreaSpotlight = ChaptersFallback::getAthreaDirectory(12);

// Fetch items directly from real-time library with pagination
$pagedResult = [];
if ($sourceFilter === 'athrea') {
    $athreaList = ChaptersFallback::getAthreaDirectory(35);
    $pagedResult = [
        'items' => $athreaList,
        'total' => count($athreaList),
        'total_pages' => 1
    ];
} elseif (!empty($search)) {
    $pagedResult = MangaDexAPI::searchPaged($search, $limit, $page, $typeFilter);
} elseif (!empty($genreSlug)) {
    $pagedResult = MangaDexAPI::getByGenrePaged($genreSlug, $limit, $page, $typeFilter);
} else {
    $pagedResult = MangaDexAPI::getLatestLiveUpdatesPaged($limit, $page, $typeFilter);
}

$displayItems = $pagedResult['items'] ?? [];
$totalItems = $pagedResult['total'] ?? count($displayItems);
$totalPages = $pagedResult['total_pages'] ?? 1;

// Fetch recent reading history for "Continue Reading" bar
$userToken = !empty($_SESSION['user_id']) ? 'user_' . $_SESSION['user_id'] : ($_COOKIE['guest_reader_token'] ?? '');
$recentHistory = [];
if (!empty($userToken)) {
    try {
        $stmtH = $pdo->prepare("
            SELECT * FROM `reading_history` 
            WHERE (`user_id` = ? OR `user_token` = ?) 
            ORDER BY `updated_at` DESC 
            LIMIT 6
        ");
        $stmtH->execute([$_SESSION['user_id'] ?? null, $userToken]);
        $recentHistory = $stmtH->fetchAll();
    } catch (PDOException $e) {}
}

// Hero spotlight series (Live Solo Leveling with authentic official artwork)
$heroLive = MangaDexAPI::getMangaDetailsLive('32d76d19-8a05-4db0-9fc2-e0b0648fe9d0');
$heroCover = !empty($heroLive['cover_url']) 
    ? $heroLive['cover_url'] 
    : 'https://uploads.mangadex.org/covers/32d76d19-8a05-4db0-9fc2-e0b0648fe9d0/e90bdc47-c8b9-4df7-b2c0-17641b645ee1.jpg.512.jpg';

$heroManhwa = [
    'id' => '32d76d19-8a05-4db0-9fc2-e0b0648fe9d0', // Solo Leveling
    'title' => 'Solo Leveling',
    'alt_title' => 'Only I Level Up (나 혼자만 레벨업)',
    'author' => 'Chugong & DUBU (REDICE Studio)',
    'rating' => 4.9,
    'views' => '24.8M',
    'status' => 'Completed',
    'cover_image' => $heroCover,
    'banner_image' => $heroCover,
    'synopsis' => '10 years ago, after "the Gate" opened and connected the real world with the realm of magic and monsters, ordinary people were granted superhuman powers. Sung Jin-woo, known as the "Weakest Hunter", awakens a mysterious quest system that only he can see.',
    'is_live' => true
];

$page_title = 'Read Webtoons & Manhwa Online';
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-10">

    <!-- HERO SPOTLIGHT BANNER (Only on main view without search/genre on page 1) -->
    <?php if (empty($search) && empty($genreSlug) && $page === 1 && $heroManhwa): ?>
    <section class="relative rounded-2xl overflow-hidden shadow-2xl border border-dark-800 bg-dark-900 group">
        <div class="absolute inset-0 z-0">
            <img src="<?= htmlspecialchars($heroManhwa['banner_image']) ?>" 
                 alt="<?= htmlspecialchars($heroManhwa['title']) ?>" 
                 referrerpolicy="no-referrer"
                 class="w-full h-full object-cover object-center opacity-30 blur-sm scale-105 group-hover:scale-100 transition-transform duration-700">
            <div class="absolute inset-0 bg-gradient-to-t from-dark-950 via-dark-900/80 to-transparent"></div>
            <div class="absolute inset-0 bg-gradient-to-r from-dark-950 via-dark-950/70 to-transparent"></div>
        </div>

        <div class="relative z-10 p-6 sm:p-10 lg:p-12 flex flex-col md:flex-row items-center gap-8">
            <!-- Cover Poster -->
            <div class="w-48 sm:w-56 shrink-0 aspect-[2/3] rounded-xl overflow-hidden shadow-2xl shadow-brand-900/40 border border-dark-700/80 group-hover:border-brand-500/50 transition-all">
                <img src="<?= htmlspecialchars($heroManhwa['cover_image']) ?>" 
                     alt="<?= htmlspecialchars($heroManhwa['title']) ?>" 
                     referrerpolicy="no-referrer"
                     class="w-full h-full object-cover">
            </div>

            <!-- Spotlight Info -->
            <div class="flex-1 text-center md:text-left space-y-4">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-brand-600/20 border border-brand-500/40 text-brand-400 text-xs font-bold uppercase tracking-wider">
                    <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                    Trending #1 Spotlight &bull; Featured Series
                </div>
                
                <h1 class="text-3xl sm:text-4xl lg:text-5xl font-black text-white tracking-tight leading-tight">
                    <?= htmlspecialchars($heroManhwa['title']) ?>
                </h1>

                <?php if (!empty($heroManhwa['alt_title'])): ?>
                    <p class="text-sm font-medium text-slate-400 -mt-2"><?= htmlspecialchars($heroManhwa['alt_title']) ?></p>
                <?php endif; ?>

                <div class="flex flex-wrap items-center justify-center md:justify-start gap-4 text-xs font-semibold text-slate-300">
                    <span class="flex items-center gap-1.5 text-amber-400">
                        <i class="fa-solid fa-star"></i> <?= number_format($heroManhwa['rating'] ?? 4.9, 1) ?>
                    </span>
                    <span class="text-slate-600">&bull;</span>
                    <span class="px-2 py-0.5 rounded text-[11px] bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                        <?= htmlspecialchars($heroManhwa['status']) ?>
                    </span>
                    <span class="text-slate-600">&bull;</span>
                    <span class="text-slate-400">Author: <?= htmlspecialchars($heroManhwa['author']) ?></span>
                </div>

                <p class="text-slate-300 text-sm leading-relaxed max-w-2xl line-clamp-3">
                    <?= htmlspecialchars($heroManhwa['synopsis']) ?>
                </p>

                <div class="pt-2 flex flex-wrap items-center justify-center md:justify-start gap-3">
                    <a href="<?= BASE_URL ?>manhwa.php?md_id=<?= $heroManhwa['id'] ?>" 
                       class="px-6 py-3 rounded-xl bg-gradient-to-r from-brand-600 to-indigo-600 hover:from-brand-500 hover:to-indigo-500 text-white font-bold text-sm shadow-lg shadow-brand-600/30 transition-all flex items-center gap-2">
                        <i class="fa-solid fa-book-open-reader"></i> Start Reading
                    </a>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- CONTINUE READING SECTION (Jump straight back into your comic!) -->
    <section id="continue-reading-section" class="space-y-4 <?= empty($recentHistory) ? 'hidden' : '' ?>">
        <div class="flex items-center justify-between pb-2 border-b border-dark-800">
            <h2 class="text-base sm:text-lg font-black text-white flex items-center gap-2.5">
                <span class="w-2.5 h-5 rounded-full bg-emerald-500"></span>
                <i class="fa-solid fa-clock-rotate-left text-emerald-400"></i> Continue Reading
            </h2>
            <a href="<?= BASE_URL ?>history.php" class="text-xs text-brand-400 hover:text-brand-300 font-semibold flex items-center gap-1">
                View All History <i class="fa-solid fa-chevron-right text-[10px]"></i>
            </a>
        </div>

        <div id="continue-reading-list" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3.5">
            <?php foreach ($recentHistory as $rh): ?>
                <div class="continue-reading-card bg-dark-900 border border-dark-800 hover:border-emerald-500/50 rounded-xl p-3 flex gap-3 items-center group transition-all shadow-md hover:shadow-emerald-950/20"
                     data-series="<?= htmlspecialchars($rh['series_id']) ?>">
                    <a href="<?= htmlspecialchars($rh['read_url']) ?>" class="shrink-0 block">
                        <img src="<?= htmlspecialchars($rh['cover_image']) ?>" 
                             alt="<?= htmlspecialchars($rh['series_title']) ?>" 
                             class="w-12 h-16 rounded-lg object-cover border border-dark-750 group-hover:scale-105 transition-transform duration-200">
                    </a>
                    <div class="flex-1 min-w-0 space-y-1">
                        <h4 class="text-xs font-bold text-slate-100 group-hover:text-emerald-400 transition-colors truncate" title="<?= htmlspecialchars($rh['series_title']) ?>">
                            <a href="<?= htmlspecialchars($rh['read_url']) ?>">
                                <?= htmlspecialchars($rh['series_title']) ?>
                            </a>
                        </h4>
                        <div class="flex items-center gap-1.5">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                Ch. <?= $rh['chapter_number'] ?>
                            </span>
                        </div>
                        <a href="<?= htmlspecialchars($rh['read_url']) ?>" 
                           class="inline-flex items-center gap-1 text-[11px] text-brand-400 hover:text-white font-semibold transition-colors mt-0.5">
                            <i class="fa-solid fa-play text-[9px]"></i> Resume
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- GENRE FILTER PILLS -->
    <section id="genres" class="space-y-4">
        <!-- Type Format Tabs (All, Manhwa, Manhua) -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-dark-800">
            <div class="flex items-center gap-2">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400 flex items-center gap-2">
                    <i class="fa-solid fa-layer-group text-brand-500"></i> Format:
                </span>
                <div class="flex items-center gap-1.5">
                    <a href="<?= getPageUrl(1, $search, $genreSlug, '') ?>" 
                       class="px-3 py-1 rounded-lg text-xs font-bold transition-all <?= empty($typeFilter) ? 'bg-brand-600 text-white shadow-md' : 'bg-dark-850 hover:bg-dark-800 text-slate-400 hover:text-white border border-dark-750' ?>">
                        All Comics
                    </a>
                    <a href="<?= getPageUrl(1, $search, $genreSlug, 'manhwa') ?>" 
                       class="px-3 py-1 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 <?= $typeFilter === 'manhwa' ? 'bg-indigo-600 text-white shadow-md' : 'bg-dark-850 hover:bg-dark-800 text-slate-400 hover:text-white border border-dark-750' ?>">
                        <span>🇰🇷 Manhwa</span>
                    </a>
                    <a href="<?= getPageUrl(1, $search, $genreSlug, 'manhua') ?>" 
                       class="px-3 py-1 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 <?= $typeFilter === 'manhua' ? 'bg-emerald-600 text-white shadow-md' : 'bg-dark-850 hover:bg-dark-800 text-slate-400 hover:text-white border border-dark-750' ?>">
                        <span>🇨🇳 Manhua</span>
                    </a>
                </div>
            </div>

            <?php if (!empty($genreSlug) || !empty($search) || !empty($typeFilter)): ?>
                <a href="<?= BASE_URL ?>" class="text-xs text-brand-400 hover:text-brand-300 font-semibold self-start sm:self-auto">
                    <i class="fa-solid fa-rotate-left mr-1"></i> Clear Filters
                </a>
            <?php endif; ?>
        </div>

        <!-- Genre Filter Pills -->
        <div class="space-y-2">
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400 flex items-center gap-2">
                <i class="fa-solid fa-tags text-brand-500"></i> Browse by Genre &amp; Sources
            </h2>
            <div class="flex items-center gap-2 overflow-x-auto pb-2 scrollbar-none">
                <a href="<?= getPageUrl(1, $search, '', $typeFilter, '') ?>" 
                   class="shrink-0 px-3.5 py-1.5 rounded-full text-xs font-semibold transition-all <?= empty($genreSlug) && empty($sourceFilter) ? 'bg-brand-600 text-white shadow-md' : 'bg-dark-850 hover:bg-dark-800 text-slate-300 border border-dark-700' ?>">
                    All Genres
                </a>
                <a href="<?= getPageUrl(1, '', '', '', $sourceFilter === 'athrea' ? '' : 'athrea') ?>" 
                   class="shrink-0 px-3.5 py-1.5 rounded-full text-xs font-bold transition-all flex items-center gap-1.5 <?= $sourceFilter === 'athrea' ? 'bg-gradient-to-r from-pink-600 to-rose-600 text-white shadow-md shadow-pink-900/40 ring-2 ring-pink-400/50' : 'bg-pink-950/40 hover:bg-pink-900/40 text-pink-300 border border-pink-500/40' ?>">
                    <span>🌸 Athrea Scans</span>
                    <span class="text-[10px] px-1.5 py-0.2 rounded-full bg-pink-500/20 text-pink-200">Romance</span>
                </a>
                <?php foreach ($genres as $g): ?>
                    <a href="<?= getPageUrl(1, $search, $g['slug'], $typeFilter, '') ?>" 
                       class="shrink-0 px-3.5 py-1.5 rounded-full text-xs font-semibold transition-all <?= $genreSlug === $g['slug'] ? 'bg-brand-600 text-white shadow-md' : 'bg-dark-850 hover:bg-dark-800 text-slate-300 border border-dark-700' ?>">
                        <?= htmlspecialchars($g['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ATHREA SCANS EXCLUSIVE SHOWCASE (Featured on default Homepage view) -->
    <?php if (empty($search) && empty($genreSlug) && empty($sourceFilter) && empty($typeFilter) && $page === 1 && !empty($athreaSpotlight)): ?>
    <section class="space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-2 border-b border-dark-800">
            <div>
                <h2 class="text-xl sm:text-2xl font-black text-white flex items-center gap-2.5">
                    <span class="w-2.5 h-6 rounded-full bg-pink-500"></span>
                    <span>🌸 Athrea Scans Collection</span>
                    <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-pink-500/20 text-pink-300 border border-pink-500/30 uppercase tracking-wider">Top Romance &amp; Shoujo</span>
                </h2>
                <p class="text-xs text-slate-400 mt-0.5">High-definition romance &amp; drama webtoons direct from Athrea Scans.</p>
            </div>
            <a href="<?= BASE_URL ?>index.php?source=athrea" class="text-xs font-bold text-pink-400 hover:text-pink-300 flex items-center gap-1.5 transition-colors self-start sm:self-auto bg-pink-950/40 px-3 py-1.5 rounded-xl border border-pink-500/30 hover:border-pink-500/60">
                <span>View Full Athrea Catalog</span>
                <i class="fa-solid fa-arrow-right text-[10px]"></i>
            </a>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4 sm:gap-6">
            <?php foreach (array_slice($athreaSpotlight, 0, 6) as $ath): ?>
                <?php 
                    $cardUrl = BASE_URL . "manhwa.php?md_id=" . urlencode($ath['id']);
                    $coverUrl = $ath['cover_url'];
                    if (str_contains($coverUrl, 'athreascans.com')) {
                        $coverUrl = BASE_URL . 'api/image_proxy.php?url=' . urlencode($coverUrl);
                    }
                    $title = $ath['title'];
                ?>
                <div class="group flex flex-col bg-dark-900 rounded-xl overflow-hidden border border-dark-800/80 hover:border-pink-500/60 shadow-lg hover:shadow-pink-900/20 transition-all duration-300">
                    <a href="<?= $cardUrl ?>" class="relative aspect-[2/3] overflow-hidden bg-dark-950 block">
                        <img src="<?= htmlspecialchars($coverUrl) ?>" 
                             alt="<?= htmlspecialchars($title) ?>" 
                             referrerpolicy="no-referrer"
                             loading="lazy"
                             class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                        <div class="absolute top-2 left-2 flex flex-col gap-1">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-black/75 backdrop-blur-md text-amber-400 border border-white/10 flex items-center gap-1">
                                <i class="fa-solid fa-star text-[9px]"></i> <?= htmlspecialchars($ath['rating']) ?>
                            </span>
                        </div>
                        <div class="absolute top-2 right-2 flex flex-col gap-1">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-pink-600 text-white shadow-sm backdrop-blur-md border border-white/10">
                                Athrea
                            </span>
                        </div>
                        <div class="absolute bottom-2 right-2">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-dark-900/90 backdrop-blur-md text-slate-200 border border-dark-700">
                                <?= htmlspecialchars($ath['latest_chapter']) ?>
                            </span>
                        </div>
                    </a>
                    <div class="p-3 flex-1 flex flex-col justify-between space-y-2">
                        <div>
                            <h3 class="font-bold text-sm text-slate-100 group-hover:text-pink-400 transition-colors line-clamp-1" title="<?= htmlspecialchars($title) ?>">
                                <a href="<?= $cardUrl ?>"><?= htmlspecialchars($title) ?></a>
                            </h3>
                            <p class="text-[11px] text-pink-400/80 font-medium line-clamp-1 mt-0.5">Romance &bull; Drama</p>
                        </div>
                        <a href="<?= $cardUrl ?>" class="w-full py-1.5 px-3 rounded-lg bg-pink-600/20 hover:bg-pink-600 text-pink-300 hover:text-white border border-pink-500/30 text-xs font-bold transition-all text-center flex items-center justify-center gap-1.5 shadow-sm">
                            <i class="fa-solid fa-book-open text-[10px]"></i> Read Series
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Non-Intrusive Sponsored Slot -->
    <?php renderAdSlot('home_middle'); ?>

    <!-- MAIN MANHWA CATALOG -->
    <section class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-3 border-b border-dark-800">
            <div>
                <h2 class="text-xl sm:text-2xl font-black text-white flex items-center gap-2.5">
                    <span class="w-2.5 h-6 rounded-full <?= $sourceFilter === 'athrea' ? 'bg-pink-500' : 'bg-brand-500' ?>"></span>
                    <?php if ($sourceFilter === 'athrea'): ?>
                        <span class="text-pink-400">🌸 Athrea Scans Collection</span> &bull; Romance &amp; Shoujo
                    <?php elseif (!empty($search)): ?>
                        Search Results for: "<span class="text-brand-400"><?= htmlspecialchars($search) ?></span>"
                    <?php elseif (!empty($genreSlug)): ?>
                        Genre: <span class="text-brand-400"><?= htmlspecialchars(ucwords(str_replace('-', ' ', $genreSlug))) ?></span>
                    <?php elseif (!empty($typeFilter)): ?>
                        <?= ucfirst($typeFilter) ?> Catalog
                    <?php else: ?>
                        Latest Releases &amp; Updates
                    <?php endif; ?>
                </h2>
                <p class="text-xs text-slate-400 mt-1">
                    Showing <span class="text-white font-semibold"><?= count($displayItems) ?></span> of <span class="text-brand-400 font-semibold"><?= number_format($totalItems) ?></span> comics available
                    <?php if ($totalPages > 1): ?> &bull; Page <?= $page ?> of <?= number_format($totalPages) ?><?php endif; ?>
                </p>
            </div>
        </div>

        <?php if (empty($displayItems)): ?>
            <div class="bg-dark-900 border border-dark-800 rounded-2xl p-12 text-center max-w-md mx-auto space-y-3">
                <div class="w-16 h-16 rounded-full bg-dark-800 flex items-center justify-center mx-auto text-slate-500 text-2xl">
                    <i class="fa-solid fa-ghost"></i>
                </div>
                <h3 class="text-lg font-bold text-white">No Series Found</h3>
                <p class="text-xs text-slate-400">Try searching with a different title or clear your filters.</p>
                <a href="<?= BASE_URL ?>" class="inline-block px-4 py-2 bg-brand-600 text-white rounded-lg text-xs font-bold">
                    Back to All Series
                </a>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4 sm:gap-6">
                <?php foreach ($displayItems as $m): ?>
                    <?php 
                        $cardUrl = BASE_URL . "manhwa.php?md_id=" . urlencode($m['id']);
                        $coverUrl = $m['cover_url'];
                        if (str_contains($coverUrl, 'athreascans.com')) {
                            $coverUrl = BASE_URL . 'api/image_proxy.php?url=' . urlencode($coverUrl);
                        }
                        $title = $m['title'];
                        $status = $m['status'];
                        $genresText = implode(', ', array_slice($m['tags'] ?? [], 0, 2)) ?: 'Webtoon';
                    ?>
                    <div class="group flex flex-col bg-dark-900 rounded-xl overflow-hidden border border-dark-800/80 hover:border-brand-500/60 shadow-lg hover:shadow-brand-900/20 transition-all duration-300">
                        
                        <!-- Poster Thumbnail -->
                        <a href="<?= $cardUrl ?>" class="relative aspect-[2/3] overflow-hidden bg-dark-950 block">
                            <img src="<?= htmlspecialchars($coverUrl) ?>" 
                                 alt="<?= htmlspecialchars($title) ?>" 
                                 referrerpolicy="no-referrer"
                                 loading="lazy"
                                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                            
                            <!-- Badges Overlay -->
                            <div class="absolute top-2 left-2 flex flex-col gap-1">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-black/75 backdrop-blur-md text-amber-400 border border-white/10 flex items-center gap-1">
                                    <i class="fa-solid fa-star text-[9px]"></i> <?= htmlspecialchars($m['rating'] ?? '4.8') ?>
                                </span>
                            </div>

                            <div class="absolute top-2 right-2 flex flex-col gap-1">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider <?= ($m['type'] ?? 'Manhwa') === 'Manhua' ? 'bg-emerald-600 text-white shadow-sm' : 'bg-indigo-600 text-white shadow-sm' ?> backdrop-blur-md border border-white/10">
                                    <?= htmlspecialchars($m['type'] ?? 'Manhwa') ?>
                                </span>
                            </div>

                            <div class="absolute bottom-2 right-2">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-dark-900/90 backdrop-blur-md text-slate-200 border border-dark-700">
                                    <?= htmlspecialchars($status) ?>
                                </span>
                            </div>
                        </a>

                        <!-- Details & Action Button -->
                        <div class="p-3 flex-1 flex flex-col justify-between space-y-2">
                            <div>
                                <h3 class="font-bold text-sm text-slate-100 group-hover:text-brand-400 transition-colors line-clamp-1" title="<?= htmlspecialchars($title) ?>">
                                    <a href="<?= $cardUrl ?>">
                                        <?= htmlspecialchars($title) ?>
                                    </a>
                                </h3>
                                <p class="text-[11px] text-slate-400 line-clamp-1 mt-0.5">
                                    <?= htmlspecialchars($genresText) ?>
                                </p>
                            </div>

                            <!-- Read Button -->
                            <div class="pt-2 border-t border-dark-800/80">
                                <a href="<?= $cardUrl ?>" 
                                   class="w-full flex items-center justify-center gap-1.5 py-1.5 rounded-lg bg-dark-850 hover:bg-brand-600 text-slate-300 hover:text-white transition-colors text-xs font-semibold">
                                    <i class="fa-solid fa-book-open text-[11px] text-brand-400 group-hover:text-white"></i>
                                    <span>Read Now</span>
                                </a>
                            </div>
                        </div>

                    </div>
                <?php endforeach; ?>
            </div>

            <!-- MODERN NUMERIC & ARROW PAGINATION (Browse Full MangaDex Library) -->
            <?php if ($totalPages > 1): ?>
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-8 pb-4 border-t border-dark-800">
                    <div class="text-xs text-slate-400">
                        Page <span class="text-white font-bold"><?= $page ?></span> of <span class="text-white font-bold"><?= number_format($totalPages) ?></span>
                        <span class="hidden sm:inline">&bull; (<?= number_format($totalItems) ?> total comics)</span>
                    </div>

                    <div class="flex items-center gap-1.5 flex-wrap justify-center">
                        <!-- Prev Page Button -->
                        <?php if ($page > 1): ?>
                            <a href="<?= getPageUrl($page - 1, $search, $genreSlug, $typeFilter) ?>" 
                               class="px-3 py-1.5 rounded-lg bg-dark-850 hover:bg-brand-600 border border-dark-700 text-slate-300 hover:text-white text-xs font-semibold transition-colors flex items-center gap-1.5 shadow-sm">
                                <i class="fa-solid fa-chevron-left text-[10px]"></i> Prev
                            </a>
                        <?php else: ?>
                            <button disabled class="px-3 py-1.5 rounded-lg bg-dark-900 border border-dark-800 text-slate-600 text-xs font-semibold cursor-not-allowed">
                                <i class="fa-solid fa-chevron-left text-[10px]"></i> Prev
                            </button>
                        <?php endif; ?>

                        <!-- Page Numbers Window -->
                        <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                        ?>

                        <?php if ($startPage > 1): ?>
                            <a href="<?= getPageUrl(1, $search, $genreSlug, $typeFilter) ?>" 
                               class="w-8 h-8 rounded-lg bg-dark-850 hover:bg-dark-700 border border-dark-700 text-slate-300 hover:text-white text-xs font-bold transition-colors flex items-center justify-center">
                                1
                            </a>
                            <?php if ($startPage > 2): ?>
                                <span class="px-1 text-slate-600 text-xs">&hellip;</span>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
                            <?php if ($p === $page): ?>
                                <span class="w-8 h-8 rounded-lg bg-brand-600 text-white border border-brand-500 text-xs font-black flex items-center justify-center shadow-md shadow-brand-600/30">
                                    <?= $p ?>
                                </span>
                            <?php else: ?>
                                <a href="<?= getPageUrl($p, $search, $genreSlug, $typeFilter) ?>" 
                                   class="w-8 h-8 rounded-lg bg-dark-850 hover:bg-dark-700 border border-dark-700 text-slate-300 hover:text-white text-xs font-bold transition-colors flex items-center justify-center">
                                    <?= $p ?>
                                </a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($endPage < $totalPages): ?>
                            <?php if ($endPage < $totalPages - 1): ?>
                                <span class="px-1 text-slate-600 text-xs">&hellip;</span>
                            <?php endif; ?>
                            <a href="<?= getPageUrl($totalPages, $search, $genreSlug, $typeFilter) ?>" 
                               class="w-8 h-8 rounded-lg bg-dark-850 hover:bg-dark-700 border border-dark-700 text-slate-300 hover:text-white text-xs font-bold transition-colors flex items-center justify-center">
                                <?= $totalPages ?>
                            </a>
                        <?php endif; ?>

                        <!-- Next Page Button -->
                        <?php if ($page < $totalPages): ?>
                            <a href="<?= getPageUrl($page + 1, $search, $genreSlug, $typeFilter) ?>" 
                               class="px-3 py-1.5 rounded-lg bg-dark-850 hover:bg-brand-600 border border-dark-700 text-slate-300 hover:text-white text-xs font-semibold transition-colors flex items-center gap-1.5 shadow-sm">
                                Next <i class="fa-solid fa-chevron-right text-[10px]"></i>
                            </a>
                        <?php else: ?>
                            <button disabled class="px-3 py-1.5 rounded-lg bg-dark-900 border border-dark-800 text-slate-600 text-xs font-semibold cursor-not-allowed">
                                Next <i class="fa-solid fa-chevron-right text-[10px]"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>

</div>

<script>
// Hydrate Continue Reading section from LocalStorage if not rendered by PHP
(function() {
    try {
        const sec = document.getElementById('continue-reading-section');
        const list = document.getElementById('continue-reading-list');
        if (!sec || !list) return;

        const allHist = JSON.parse(localStorage.getItem('manhwaflow_history') || localStorage.getItem('manhwaverse_history') || '{}');
        const items = Object.values(allHist);
        if (items.length === 0) return;

        // Sort descending by updated_at
        items.sort((a, b) => (b.updated_at || 0) - (a.updated_at || 0));

        let added = 0;
        items.slice(0, 6).forEach(item => {
            if (list.querySelector(`[data-series="${item.series_id}"]`)) return;

            const card = document.createElement('div');
            card.className = 'continue-reading-card bg-dark-900 border border-dark-800 hover:border-emerald-500/50 rounded-xl p-3 flex gap-3 items-center group transition-all shadow-md hover:shadow-emerald-950/20';
            card.setAttribute('data-series', item.series_id);
            card.innerHTML = `
                <a href="${item.read_url}" class="shrink-0 block">
                    <img src="${item.cover_image}" alt="${item.series_title}" class="w-12 h-16 rounded-lg object-cover border border-dark-750 group-hover:scale-105 transition-transform duration-200">
                </a>
                <div class="flex-1 min-w-0 space-y-1">
                    <h4 class="text-xs font-bold text-slate-100 group-hover:text-emerald-400 transition-colors truncate" title="${item.series_title}">
                        <a href="${item.read_url}">
                            ${item.series_title}
                        </a>
                    </h4>
                    <div class="flex items-center gap-1.5">
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                            Ch. ${item.chapter_number}
                        </span>
                    </div>
                    <a href="${item.read_url}" class="inline-flex items-center gap-1 text-[11px] text-brand-400 hover:text-white font-semibold transition-colors mt-0.5">
                        <i class="fa-solid fa-play text-[9px]"></i> Resume
                    </a>
                </div>
            `;
            list.appendChild(card);
            added++;
        });

        if (list.children.length > 0) {
            sec.classList.remove('hidden');
        }
    } catch(e) {}
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
