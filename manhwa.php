<?php
// manhwa.php - Manhwa Details & Chapter List
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/mangadex.php';
require_once __DIR__ . '/includes/ads.php';
$pdo = getPdo();

$rawId = $_GET['id'] ?? ($_GET['md_id'] ?? '');
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
$isLive = !empty($_GET['md_id']) || (is_string($rawId) && strlen($rawId) > 20);

$manhwa = null;
$genres = [];
$chapters = [];
$relatedManhwas = [];

if (str_starts_with($rawId, 'athrea_') || str_starts_with($rawId, 'asura_') || str_starts_with($rawId, 'anisa_')) {
    // --- MULTI-SOURCE WEBTOON LIVE MODE (ATHREA, ASURA, ANISA) ---
    require_once __DIR__ . '/includes/chapters_fallback.php';
    
    if (str_starts_with($rawId, 'athrea_')) {
        $sourceSlug = substr($rawId, 7);
        $sourceData = ChaptersFallback::getAthreaDetails($sourceSlug);
        $liveChapters = ChaptersFallback::getAthreaChapters($sourceSlug, 1000);
    } elseif (str_starts_with($rawId, 'asura_')) {
        $sourceSlug = substr($rawId, 6);
        $sourceData = ChaptersFallback::getAsuraDetails($sourceSlug);
        $liveChapters = ChaptersFallback::getAsuraChapters($sourceSlug, 1000);
    } else {
        $sourceSlug = substr($rawId, 6);
        $sourceData = ChaptersFallback::getAnisaDetails($sourceSlug);
        $liveChapters = ChaptersFallback::getAnisaChapters($sourceSlug, 1000);
    }

    if (!$sourceData) {
        die("Series not found. <a href='" . BASE_URL . "'>Return to Home</a>");
    }

    $cleanAuthor = $sourceData['author'] ?? 'Webtoon Studio';
    if (stripos($cleanAuthor, 'scans') !== false || stripos($cleanAuthor, 'asura') !== false || stripos($cleanAuthor, 'athrea') !== false || stripos($cleanAuthor, 'anisa') !== false) {
        $cleanAuthor = 'Webtoon Studio';
    }

    $coverUrl = $sourceData['cover_url'] ?? '';

    // Priority 1: Match against verified Exclusive Spotlight list
    $spotlight = ChaptersFallback::getExclusiveSpotlight();
    foreach ($spotlight as $sp) {
        if ($sp['id'] === $rawId || $sp['slug'] === $sourceSlug || strcasecmp($sp['title'], $sourceData['title']) === 0) {
            if (!empty($sp['cover_url']) && !str_contains($sp['cover_url'], 'anisascans.in')) {
                $coverUrl = $sp['cover_url'];
                break;
            }
        }
    }

    // Priority 2: If cover contains anisascans.in (blocked by Cloudflare 403) or is empty, resolve via MangaDex
    if (empty($coverUrl) || str_contains($coverUrl, 'anisascans.in')) {
        $cleanSearchTitle = preg_replace('/[’\']/u', '', $sourceData['title']);
        $cleanSearchTitle = trim(preg_replace('/\s+/', ' ', $cleanSearchTitle));
        $mdMatch = MangaDexAPI::search($cleanSearchTitle, 1);
        if (!empty($mdMatch[0]['cover_url'])) {
            $coverUrl = $mdMatch[0]['cover_url'];
        } else {
            $coverUrl = BASE_URL . 'assets/images/placeholder.svg';
        }
    }

    $manhwa = [
        'id' => $rawId,
        'title' => $sourceData['title'],
        'slug' => preg_replace('/[^a-z0-9]+/i', '-', strtolower($sourceData['title'])),
        'alt_title' => $sourceData['title'],
        'author' => $cleanAuthor,
        'artist' => $cleanAuthor,
        'status' => $sourceData['status'] ?? 'Ongoing',
        'type' => $sourceData['type'] ?? 'Manhwa',
        'rating' => $sourceData['rating'] ?? 4.9,
        'views' => rand(25000, 95000),
        'synopsis' => $sourceData['synopsis'] ?? 'Read online at ManhwaFlow.',
        'cover_image' => $coverUrl,
        'banner_image' => $coverUrl,
        'is_live' => true
    ];

    foreach ($sourceData['genres'] as $idx => $t) {
        if (stripos($t, 'scans') !== false || stripos($t, 'athrea') !== false || stripos($t, 'asura') !== false || stripos($t, 'anisa') !== false) {
            continue;
        }
        $genres[] = [
            'id' => $idx + 1,
            'name' => $t,
            'slug' => strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $t), '-'))
        ];
    }

    // Sort DESC for chapter list display
    usort($liveChapters, function($a, $b) {
        return $b['chapter_number'] <=> $a['chapter_number'];
    });

    foreach ($liveChapters as $lc) {
        $lc['read_url'] = BASE_URL . "reader.php?md_ch=" . urlencode($lc['id']) . "&md_manga=" . urlencode($rawId) . "&ch_num=" . $lc['chapter_number'];
        $lc['views'] = rand(1500, 8500);
        $chapters[] = $lc;
    }

    $firstChapter = !empty($chapters) ? end($chapters) : null;
    $latestChapter = !empty($chapters) ? $chapters[0] : null;

} elseif ($isLive) {
    // --- LIVE MANGADEX MODE ---
    $mdId = trim($rawId);
    $liveManga = MangaDexAPI::getMangaDetailsLive($mdId);

    if (!$liveManga) {
        die("Series not found. <a href='" . BASE_URL . "'>Return to Home</a>");
    }

    $manhwa = [
        'id' => $liveManga['id'],
        'title' => $liveManga['title'],
        'slug' => 'md-' . $liveManga['id'],
        'alt_title' => $liveManga['alt_title'],
        'author' => $liveManga['author'],
        'artist' => $liveManga['author'],
        'status' => $liveManga['status'],
        'type' => $liveManga['type'] ?? 'Manhwa',
        'rating' => $liveManga['rating'] ?? 4.8,
        'views' => $liveManga['views'] ?? rand(18000, 95000),
        'synopsis' => $liveManga['description'],
        'cover_image' => $liveManga['cover_url'],
        'banner_image' => $liveManga['cover_url'],
        'is_live' => true
    ];

    // Tags as Genres
    foreach ($liveManga['tags'] as $idx => $t) {
        $genres[] = [
            'id' => $idx + 1,
            'name' => $t,
            'slug' => strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $t), '-'))
        ];
    }

    // Chapters Live from MangaDex & Fallback Provider
    $liveChapters = MangaDexAPI::getChaptersLive($mdId, 1000, $liveManga['title']);
    // Sort DESC for chapter list display
    usort($liveChapters, function($a, $b) {
        return $b['chapter_number'] <=> $a['chapter_number'];
    });

    foreach ($liveChapters as $lc) {
        $lc['read_url'] = BASE_URL . "reader.php?md_ch=" . $lc['id'] . "&md_manga=" . $mdId . "&ch_num=" . $lc['chapter_number'];
        $lc['views'] = rand(1200, 6500);
        $chapters[] = $lc;
    }

    $firstChapter = !empty($chapters) ? end($chapters) : null;
    $latestChapter = !empty($chapters) ? $chapters[0] : null;

} else {
    // --- LOCAL DATABASE MODE ---
    $id = intval($rawId);
    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM `manhwas` WHERE id = ?");
        $stmt->execute([$id]);
    } elseif (!empty($slug)) {
        $stmt = $pdo->prepare("SELECT * FROM `manhwas` WHERE slug = ?");
        $stmt->execute([$slug]);
    } else {
        header("Location: " . BASE_URL);
        exit;
    }

    $manhwa = $stmt->fetch();
    if (!$manhwa) {
        die("Series not found. <a href='" . BASE_URL . "'>Return to Home</a>");
    }

    // Increment view count
    $pdo->prepare("UPDATE `manhwas` SET views = views + 1 WHERE id = ?")->execute([$manhwa['id']]);

    // Fetch Genres
    $genresStmt = $pdo->prepare("
        SELECT g.* FROM `genres` g
        JOIN `manhwa_genres` mg ON g.id = mg.genre_id
        WHERE mg.manhwa_id = ?
    ");
    $genresStmt->execute([$manhwa['id']]);
    $genres = $genresStmt->fetchAll();

    // Fetch Chapters
    $chaptersStmt = $pdo->prepare("
        SELECT * FROM `chapters` 
        WHERE manhwa_id = ? 
        ORDER BY chapter_number DESC
    ");
    $chaptersStmt->execute([$manhwa['id']]);
    $localChapters = $chaptersStmt->fetchAll();

    foreach ($localChapters as $lc) {
        $lc['read_url'] = BASE_URL . "reader.php?chapter_id=" . $lc['id'];
        $chapters[] = $lc;
    }

    $firstChapter = !empty($chapters) ? end($chapters) : null;
    $latestChapter = !empty($chapters) ? $chapters[0] : null;

    // Related Manhwa
    $relatedStmt = $pdo->prepare("
        SELECT DISTINCT m.* FROM manhwas m
        JOIN manhwa_genres mg ON m.id = mg.manhwa_id
        WHERE mg.genre_id IN (SELECT genre_id FROM manhwa_genres WHERE manhwa_id = ?)
        AND m.id != ?
        LIMIT 4
    ");
    $relatedStmt->execute([$manhwa['id'], $manhwa['id']]);
    $relatedManhwas = $relatedStmt->fetchAll();
}

// Check Reading History for this series
$userId = $_SESSION['user_id'] ?? null;
$guestCookie = $_COOKIE['guest_reader_token'] ?? '';
$lastReadChapter = null;

if ($userId) {
    try {
        $stmtHist = $pdo->prepare("
            SELECT * FROM `reading_history` 
            WHERE `user_id` = ? AND `series_id` = ? 
            LIMIT 1
        ");
        $stmtHist->execute([$userId, strval($manhwa['id'])]);
        $lastReadChapter = $stmtHist->fetch();
    } catch (PDOException $e) {}
} elseif (!empty($guestCookie)) {
    try {
        $stmtHist = $pdo->prepare("
            SELECT * FROM `reading_history` 
            WHERE `user_token` = ? AND `user_id` IS NULL AND `series_id` = ? 
            LIMIT 1
        ");
        $stmtHist->execute([$guestCookie, strval($manhwa['id'])]);
        $lastReadChapter = $stmtHist->fetch();
    } catch (PDOException $e) {}
}

$page_title = $manhwa['title'] . ' - Read Chapters Free';
require_once __DIR__ . '/includes/header.php';
?>

<div class="relative">
    <!-- Top Backdrop Banner -->
    <div class="h-64 sm:h-80 w-full overflow-hidden relative border-b border-dark-800">
        <img src="<?= htmlspecialchars($manhwa['banner_image']) ?>" 
             alt="<?= htmlspecialchars($manhwa['title']) ?>" 
             referrerpolicy="no-referrer"
             onerror="this.onerror=null; this.src='<?= BASE_URL ?>assets/images/placeholder.svg';"
             class="w-full h-full object-cover opacity-25 blur-md scale-105">
        <div class="absolute inset-0 bg-gradient-to-t from-dark-950 via-dark-950/70 to-transparent"></div>
    </div>

    <!-- Main Container -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 -mt-36 sm:-mt-48 relative z-10 pb-16">
        
        <!-- Breadcrumb -->
        <nav class="flex items-center gap-2 text-xs text-slate-400 mb-6">
            <a href="<?= BASE_URL ?>" class="hover:text-white transition-colors">Home</a>
            <span>/</span>
            <span class="text-slate-300 font-semibold truncate max-w-xs"><?= htmlspecialchars($manhwa['title']) ?></span>
        </nav>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            
            <!-- Left Column: Poster & Quick Action Meta -->
            <div class="lg:col-span-1 space-y-6">
                <!-- Cover Image -->
                <div class="aspect-[2/3] w-52 sm:w-64 lg:w-full mx-auto rounded-2xl overflow-hidden shadow-2xl border-2 border-dark-700 bg-dark-900 group">
                    <img src="<?= htmlspecialchars($manhwa['cover_image']) ?>" 
                         alt="<?= htmlspecialchars($manhwa['title']) ?>" 
                         referrerpolicy="no-referrer"
                         onerror="this.onerror=null; this.src='<?= BASE_URL ?>assets/images/placeholder.svg';"
                         class="w-full h-full object-cover">
                </div>

                <!-- Bookmark Action Button -->
                <button id="btn-toggle-bookmark" 
                        data-id="<?= $manhwa['id'] ?>"
                        data-title="<?= htmlspecialchars($manhwa['title']) ?>"
                        data-slug="<?= htmlspecialchars($manhwa['slug']) ?>"
                        data-cover="<?= htmlspecialchars($manhwa['cover_image']) ?>"
                        data-rating="<?= $manhwa['rating'] ?>"
                        data-status="<?= $manhwa['status'] ?>"
                        class="w-full py-3 px-4 rounded-xl bg-dark-800 hover:bg-dark-700 border border-dark-700 text-slate-200 text-sm font-bold shadow-md transition-all flex items-center justify-center">
                    <i class="fa-regular fa-bookmark mr-2 text-brand-500"></i> Bookmark
                </button>

                <!-- Information Box -->
                <div class="bg-dark-900/90 rounded-xl p-5 border border-dark-800 space-y-3.5 text-xs">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-300 border-b border-dark-800 pb-2">
                        Series Information
                    </h3>

                    <div class="flex justify-between items-center">
                        <span class="text-slate-400">Format:</span>
                        <span class="font-semibold text-brand-400"><?= htmlspecialchars($manhwa['type']) ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-slate-400">Status:</span>
                        <span class="font-semibold <?= $manhwa['status'] === 'Completed' ? 'text-emerald-400' : 'text-blue-400' ?>">
                            <?= htmlspecialchars($manhwa['status']) ?>
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-slate-400">Author:</span>
                        <span class="font-semibold text-slate-200"><?= htmlspecialchars($manhwa['author']) ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-slate-400">Artist:</span>
                        <span class="font-semibold text-slate-200"><?= htmlspecialchars($manhwa['artist']) ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-slate-400">Total Views:</span>
                        <span class="font-semibold text-slate-200"><?= is_numeric($manhwa['views']) ? number_format($manhwa['views']) : htmlspecialchars($manhwa['views']) ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-slate-400">Rating:</span>
                        <span class="font-bold text-amber-400 flex items-center gap-1">
                            <i class="fa-solid fa-star text-[10px]"></i> <?= number_format(floatval($manhwa['rating'] ?? 4.8), 1) ?> / 5.0
                        </span>
                    </div>
                </div>

            </div>

            <!-- Right Column: Title, Synopsis, Chapters -->
            <div class="lg:col-span-3 space-y-6">
                <!-- Title & Meta -->
                <div class="space-y-3">
                    <h1 class="text-2xl sm:text-4xl font-black text-white tracking-tight">
                        <?= htmlspecialchars($manhwa['title']) ?>
                    </h1>
                    <?php if (!empty($manhwa['alt_title'])): ?>
                        <p class="text-sm font-medium text-slate-400">
                            Alternative Title: <span class="text-slate-300"><?= htmlspecialchars($manhwa['alt_title']) ?></span>
                        </p>
                    <?php endif; ?>

                    <!-- Genre Badges -->
                    <div class="flex flex-wrap gap-2 pt-1">
                        <?php foreach ($genres as $g): ?>
                            <a href="<?= BASE_URL ?>index.php?genre=<?= urlencode($g['slug']) ?>" 
                                class="px-3 py-1 rounded-lg bg-dark-850 hover:bg-brand-600 hover:text-white border border-dark-700 text-xs font-semibold text-slate-300 transition-colors">
                                <?= htmlspecialchars($g['name']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Synopsis -->
                <div class="bg-dark-900 rounded-xl p-5 border border-dark-800 space-y-2">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400">Synopsis</h2>
                    <p class="text-slate-200 text-sm leading-relaxed whitespace-pre-line">
                        <?= htmlspecialchars($manhwa['synopsis']) ?>
                    </p>
                </div>

                <!-- Quick Action Buttons -->
                <div class="flex flex-wrap gap-3" id="quick-action-buttons">
                    <?php if ($lastReadChapter): ?>
                        <a href="<?= htmlspecialchars($lastReadChapter['read_url']) ?>" 
                           id="btn-resume-reading"
                           class="flex-1 min-w-[200px] py-3.5 px-5 rounded-xl bg-gradient-to-r from-emerald-600 via-emerald-500 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-black text-sm shadow-xl shadow-emerald-950/40 text-center transition-all flex items-center justify-center gap-2 border border-emerald-400/40">
                            <i class="fa-solid fa-bookmark text-emerald-200"></i>
                            <span>Continue Reading (Ch. <?= $lastReadChapter['chapter_number'] ?>)</span>
                        </a>
                    <?php else: ?>
                        <!-- Client-side Fallback Resume Button (Activated via LocalStorage if found) -->
                        <a href="#" 
                           id="btn-resume-reading"
                           style="display: none;"
                           class="flex-1 min-w-[200px] py-3.5 px-5 rounded-xl bg-gradient-to-r from-emerald-600 via-emerald-500 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-black text-sm shadow-xl shadow-emerald-950/40 text-center transition-all flex items-center justify-center gap-2 border border-emerald-400/40">
                            <i class="fa-solid fa-bookmark text-emerald-200"></i>
                            <span id="resume-reading-text">Continue Reading</span>
                        </a>
                    <?php endif; ?>

                    <?php if ($firstChapter): ?>
                        <a href="<?= $firstChapter['read_url'] ?>" 
                           class="flex-1 min-w-[160px] py-3 px-4 rounded-xl <?= $lastReadChapter ? 'bg-dark-850 hover:bg-dark-800 border border-dark-700 text-slate-300 hover:text-white' : 'bg-gradient-to-r from-brand-600 to-indigo-600 hover:from-brand-500 hover:to-indigo-500 text-white shadow-lg shadow-brand-600/20' ?> font-bold text-sm text-center transition-all flex items-center justify-center gap-2">
                            <i class="fa-solid fa-play"></i> First Chapter (Ch. <?= $firstChapter['chapter_number'] ?>)
                        </a>
                    <?php endif; ?>

                    <?php if ($latestChapter && $latestChapter['id'] !== ($firstChapter['id'] ?? null)): ?>
                        <a href="<?= $latestChapter['read_url'] ?>" 
                           class="flex-1 min-w-[160px] py-3 px-4 rounded-xl bg-dark-850 hover:bg-dark-800 border border-dark-700 text-slate-200 hover:text-white font-bold text-sm text-center transition-all flex items-center justify-center gap-2">
                            <i class="fa-solid fa-forward-step text-brand-400"></i> Latest Chapter (Ch. <?= $latestChapter['chapter_number'] ?>)
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Non-Intrusive Sponsored Slot -->
                <?php renderAdSlot('manhwa_details'); ?>

                <!-- Chapters Section -->
                <div class="bg-dark-900 rounded-xl border border-dark-800 overflow-hidden space-y-4 p-5">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-dark-800">
                        <div>
                            <h2 class="text-base font-bold text-white flex items-center gap-2">
                                <i class="fa-solid fa-list-ol text-brand-500"></i> Chapter List
                                <span class="px-2 py-0.5 rounded-full text-xs bg-dark-800 text-slate-400 font-normal">
                                    <?= count($chapters) ?> Chapters
                                </span>
                            </h2>
                        </div>

                        <!-- Chapter Filter Search -->
                        <div class="relative w-full sm:w-60">
                            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
                            <input type="text" id="chapter-filter" placeholder="Search Chapter #" 
                                   class="w-full bg-dark-850 border border-dark-700 rounded-lg pl-8 pr-3 py-1.5 text-xs text-slate-200 placeholder-slate-500 focus:outline-none focus:border-brand-500">
                        </div>
                    </div>

                    <!-- Chapter List Grid -->
                    <?php if (empty($chapters)): ?>
                        <div class="py-8 text-center text-slate-500 text-sm">
                            No chapters available for this series yet.
                        </div>
                    <?php else: ?>
                        <div id="chapter-list-container" class="space-y-2 max-h-[500px] overflow-y-auto pr-1">
                            <?php foreach ($chapters as $ch): ?>
                                <?php 
                                    $isLastRead = $lastReadChapter && (
                                        strval($ch['id']) === strval($lastReadChapter['chapter_id']) || 
                                        floatval($ch['chapter_number']) === floatval($lastReadChapter['chapter_number'])
                                    ); 
                                ?>
                                <a href="<?= $ch['read_url'] ?>" 
                                   data-ch="<?= $ch['chapter_number'] ?>"
                                   data-id="<?= htmlspecialchars($ch['id']) ?>"
                                   class="chapter-item flex items-center justify-between p-3 rounded-lg <?= $isLastRead ? 'bg-emerald-950/40 border border-emerald-500/60 shadow-lg shadow-emerald-950/40' : 'bg-dark-850/80 hover:bg-dark-800 border border-dark-800 hover:border-brand-500/40' ?> transition-all group">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-lg <?= $isLastRead ? 'bg-emerald-600 text-white' : 'bg-dark-800 text-slate-300 group-hover:text-brand-400 group-hover:bg-brand-500/10' ?> flex items-center justify-center text-xs font-bold transition-colors">
                                            #<?= $ch['chapter_number'] ?>
                                        </div>
                                        <div>
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <span class="text-sm font-semibold <?= $isLastRead ? 'text-emerald-300' : 'text-slate-200 group-hover:text-white' ?> transition-colors">
                                                    Chapter <?= $ch['chapter_number'] ?>
                                                    <?php if (!empty($ch['title'])): ?>
                                                        <span class="text-xs font-normal text-slate-400 ml-1.5">- <?= htmlspecialchars($ch['title']) ?></span>
                                                    <?php endif; ?>
                                                </span>
                                                <?php if ($isLastRead): ?>
                                                    <span class="last-read-badge px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 flex items-center gap-1">
                                                        <i class="fa-solid fa-clock-rotate-left text-[9px]"></i> Last Read
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-[11px] text-slate-500 mt-0.5">
                                                <span><?= date('M d, Y', strtotime($ch['created_at'])) ?></span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-3 text-xs text-slate-400">
                                        <span class="hidden sm:inline-flex items-center gap-1">
                                            <i class="fa-regular fa-eye text-[11px]"></i> <?= is_numeric($ch['views']) ? number_format($ch['views']) : htmlspecialchars($ch['views']) ?>
                                        </span>
                                        <i class="fa-solid fa-chevron-right text-xs <?= $isLastRead ? 'text-emerald-400' : 'text-slate-600 group-hover:text-brand-400' ?> group-hover:translate-x-1 transition-all"></i>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Related Manhwas -->
                <?php if (!empty($relatedManhwas)): ?>
                <div class="space-y-3 pt-4">
                    <h3 class="text-sm font-bold uppercase tracking-wider text-slate-400 flex items-center gap-2">
                        <i class="fa-solid fa-thumbs-up text-brand-500"></i> Related Series
                    </h3>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <?php foreach ($relatedManhwas as $rm): ?>
                            <a href="<?= BASE_URL ?>manhwa.php?id=<?= $rm['id'] ?>" class="group bg-dark-900 rounded-xl overflow-hidden border border-dark-800 hover:border-brand-500/40 p-2 block transition-all">
                                <div class="aspect-[2/3] rounded-lg overflow-hidden bg-dark-950 mb-2">
                                    <img src="<?= htmlspecialchars($rm['cover_image']) ?>" 
                                         alt="<?= htmlspecialchars($rm['title']) ?>" 
                                         referrerpolicy="no-referrer"
                                         onerror="this.onerror=null; this.src='<?= BASE_URL ?>assets/images/placeholder.svg';"
                                         class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                                </div>
                                <h4 class="font-bold text-xs text-slate-200 group-hover:text-brand-400 line-clamp-1">
                                    <?= htmlspecialchars($rm['title']) ?>
                                </h4>
                                <span class="text-[10px] text-amber-400 flex items-center gap-1 mt-0.5">
                                    <i class="fa-solid fa-star text-[9px]"></i> <?= number_format($rm['rating'], 1) ?>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

            </div>

        </div>

    </div>
</div>

<script>
// Real-time Chapter Filtering
document.getElementById('chapter-filter')?.addEventListener('input', function(e) {
    const query = e.target.value.toLowerCase().trim();
    const items = document.querySelectorAll('.chapter-item');
    items.forEach(item => {
        const chNum = item.getAttribute('data-ch') || '';
        const text = item.innerText.toLowerCase();
        if (text.includes(query) || chNum.includes(query)) {
            item.style.display = 'flex';
        } else {
            item.style.display = 'none';
        }
    });
});

// Client-side LocalStorage Reading History Fallback
(function() {
    try {
        const seriesId = <?= json_encode(strval($manhwa['id'])) ?>;
        const allHist = JSON.parse(localStorage.getItem('manhwaflow_history') || localStorage.getItem('manhwaverse_history') || '{}');
        const hist = allHist[seriesId];
        if (!hist) return;

        const resumeBtn = document.getElementById('btn-resume-reading');
        if (resumeBtn) {
            resumeBtn.href = hist.read_url;
            resumeBtn.style.display = 'flex';
            const textSpan = resumeBtn.querySelector('span');
            if (textSpan) {
                textSpan.textContent = `Continue Reading (Ch. ${hist.chapter_number})`;
            }
        }

        // Highlight matching chapter in list if not already highlighted
        if (!document.querySelector('.last-read-badge')) {
            const chItems = document.querySelectorAll('.chapter-item');
            chItems.forEach(item => {
                const itemCh = parseFloat(item.getAttribute('data-ch'));
                const itemId = item.getAttribute('data-id');
                if (itemCh === parseFloat(hist.chapter_number) || (itemId && itemId === hist.chapter_id)) {
                    item.classList.add('bg-emerald-950/40', 'border-emerald-500/60', 'shadow-lg');
                    const titleWrap = item.querySelector('.text-sm');
                    if (titleWrap && !item.querySelector('.last-read-badge')) {
                        const badge = document.createElement('span');
                        badge.className = 'last-read-badge px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 flex items-center gap-1';
                        badge.innerHTML = '<i class="fa-solid fa-clock-rotate-left text-[9px]"></i> Last Read';
                        titleWrap.parentNode.appendChild(badge);
                    }
                }
            });
        }
    } catch(e) {}
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

