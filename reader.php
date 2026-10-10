<?php
// reader.php - Dedicated Webtoon & Strip Reader
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/mangadex.php';
require_once __DIR__ . '/includes/ads.php';
$pdo = getPdo();

$chapterId = isset($_GET['chapter_id']) ? intval($_GET['chapter_id']) : 0;
$mdChapterId = trim($_GET['md_ch'] ?? '');
$mdMangaId = trim($_GET['md_manga'] ?? '');
$isLive = !empty($mdChapterId);

$chapter = null;
$pages = [];
$allChapters = [];
$prevChapter = null;
$nextChapter = null;
$backUrl = BASE_URL;

if ($isLive) {
    // --- LIVE MANGADEX STREAMING & FALLBACK ---
    if (str_starts_with($mdMangaId, 'athrea_')) {
        require_once __DIR__ . '/includes/chapters_fallback.php';
        $athreaSlug = substr($mdMangaId, 7);
        $mangaDetails = ChaptersFallback::getAthreaDetails($athreaSlug);
        $liveChapters = ChaptersFallback::getAthreaChapters($athreaSlug, 1000);
        $coverUrl = ChaptersFallback::resolveCover($mdMangaId, $mangaDetails['title'] ?? '', $mangaDetails['cover_url'] ?? '');

        $manga = [
            'id' => $mdMangaId,
            'title' => $mangaDetails['title'] ?? 'Webtoon Series',
            'cover_url' => $coverUrl
        ];
    } elseif (str_starts_with($mdMangaId, 'asura_')) {
        require_once __DIR__ . '/includes/chapters_fallback.php';
        $asuraSlug = substr($mdMangaId, 6);
        $mangaDetails = ChaptersFallback::getAsuraDetails($asuraSlug);
        $liveChapters = ChaptersFallback::getAsuraChapters($asuraSlug, 1000);
        $coverUrl = ChaptersFallback::resolveCover($mdMangaId, $mangaDetails['title'] ?? '', $mangaDetails['cover_url'] ?? '');

        $manga = [
            'id' => $mdMangaId,
            'title' => $mangaDetails['title'] ?? 'Webtoon Series',
            'cover_url' => $coverUrl
        ];
    } elseif (str_starts_with($mdMangaId, 'anisa_')) {
        require_once __DIR__ . '/includes/chapters_fallback.php';
        $anisaSlug = substr($mdMangaId, 6);
        $mangaDetails = ChaptersFallback::getAnisaDetails($anisaSlug);
        $liveChapters = ChaptersFallback::getAnisaChapters($anisaSlug, 1000);
        $coverUrl = ChaptersFallback::resolveCover($mdMangaId, $mangaDetails['title'] ?? '', $mangaDetails['cover_url'] ?? '');

        $manga = [
            'id' => $mdMangaId,
            'title' => $mangaDetails['title'] ?? 'Webtoon Series',
            'cover_url' => $coverUrl
        ];
    } else {
        $manga = MangaDexAPI::getMangaDetailsLive($mdMangaId);
        $liveChapters = MangaDexAPI::getChaptersLive($mdMangaId, 1000, $manga ? $manga['title'] : '');
    }

    $chNum = isset($_GET['ch_num']) ? floatval($_GET['ch_num']) : 1;
    $chTitle = '';

    // Find current chapter in list
    foreach ($liveChapters as $index => $lc) {
        if ($lc['id'] === $mdChapterId) {
            $chNum = $lc['chapter_number'];
            $chTitle = $lc['title'];
            // Chapters are sorted ASC: index - 1 is Prev, index + 1 is Next
            if (isset($liveChapters[$index - 1])) {
                $prevChapter = $liveChapters[$index - 1];
                $prevChapter['read_url'] = BASE_URL . "reader.php?md_ch=" . $prevChapter['id'] . "&md_manga=" . $mdMangaId . "&ch_num=" . $prevChapter['chapter_number'];
            }
            if (isset($liveChapters[$index + 1])) {
                $nextChapter = $liveChapters[$index + 1];
                $nextChapter['read_url'] = BASE_URL . "reader.php?md_ch=" . $nextChapter['id'] . "&md_manga=" . $mdMangaId . "&ch_num=" . $nextChapter['chapter_number'];
            }
            break;
        }
    }

    $chapter = [
        'id' => $mdChapterId,
        'chapter_number' => $chNum,
        'title' => $chTitle,
        'manhwa_title' => $manga ? $manga['title'] : 'Manhwa',
        'manhwa_id' => $mdMangaId,
        'cover_image' => $manga ? $manga['cover_url'] : ''
    ];
    $backUrl = BASE_URL . "manhwa.php?md_id=" . $mdMangaId;

    // Dropdown list (DESC order for convenience)
    $descChapters = $liveChapters;
    usort($descChapters, function($a, $b) {
        return $b['chapter_number'] <=> $a['chapter_number'];
    });
    foreach ($descChapters as $dc) {
        $dc['read_url'] = BASE_URL . "reader.php?md_ch=" . $dc['id'] . "&md_manga=" . $mdMangaId . "&ch_num=" . $dc['chapter_number'];
        $dc['is_current'] = ($dc['id'] === $mdChapterId);
        $allChapters[] = $dc;
    }

    // Fetch real pages from MangaDex @Home CDN
    $pageUrls = MangaDexAPI::getChapterPagesLive($mdChapterId, true);
    foreach ($pageUrls as $pIndex => $pUrl) {
        $imgSrc = $pUrl;
        if (strpos($pUrl, 'mangadex.network') !== false || strpos($pUrl, 'mangadex.org') !== false || strpos($pUrl, 'athreascans.com') !== false || strpos($pUrl, 'anisascans.in') !== false || strpos($pUrl, 'mgread.io') !== false || strpos($pUrl, 'asurascans.com') !== false) {
            $imgSrc = BASE_URL . 'api/image_proxy.php?url=' . urlencode($pUrl);
        }
        $pages[] = [
            'page_number' => $pIndex + 1,
            'image_url' => $imgSrc,
            'fallback_url' => $pUrl
        ];
    }

} else {
    // --- LOCAL DATABASE CHAPTER ---
    if ($chapterId <= 0) {
        header("Location: " . BASE_URL);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT c.*, m.title AS manhwa_title, m.slug AS manhwa_slug, m.id AS manhwa_id, m.cover_image
        FROM `chapters` c
        JOIN `manhwas` m ON c.manhwa_id = m.id
        WHERE c.id = ?
    ");
    $stmt->execute([$chapterId]);
    $chapter = $stmt->fetch();

    if (!$chapter) {
        die("Chapter not found. <a href='" . BASE_URL . "'>Return to Home</a>");
    }

    $backUrl = BASE_URL . "manhwa.php?id=" . $chapter['manhwa_id'];

    // Increment local views
    $pdo->prepare("UPDATE `chapters` SET views = views + 1 WHERE id = ?")->execute([$chapterId]);

    // Fetch local pages
    $pagesStmt = $pdo->prepare("
        SELECT * FROM `chapter_pages` 
        WHERE chapter_id = ? 
        ORDER BY page_number ASC
    ");
    $pagesStmt->execute([$chapterId]);
    $pages = $pagesStmt->fetchAll();

    // Fetch local chapters
    $allChaptersStmt = $pdo->prepare("
        SELECT id, chapter_number, title 
        FROM `chapters` 
        WHERE manhwa_id = ? 
        ORDER BY chapter_number DESC
    ");
    $allChaptersStmt->execute([$chapter['manhwa_id']]);
    $localChapters = $allChaptersStmt->fetchAll();

    foreach ($localChapters as $index => $ch) {
        $ch['read_url'] = BASE_URL . "reader.php?chapter_id=" . $ch['id'];
        $ch['is_current'] = ($ch['id'] == $chapterId);
        $allChapters[] = $ch;

        if ($ch['id'] == $chapterId) {
            if (isset($localChapters[$index - 1])) {
                $nextChapter = $localChapters[$index - 1];
                $nextChapter['read_url'] = BASE_URL . "reader.php?chapter_id=" . $nextChapter['id'];
            }
            if (isset($localChapters[$index + 1])) {
                $prevChapter = $localChapters[$index + 1];
                $prevChapter['read_url'] = BASE_URL . "reader.php?chapter_id=" . $prevChapter['id'];
            }
        }
    }
}

// Fetch Comments for this Chapter
$commentsStmt = $pdo->prepare("
    SELECT id, author_name, avatar, comment, likes, created_at 
    FROM chapter_comments 
    WHERE chapter_id = ? 
    ORDER BY created_at DESC
");
$commentsStmt->execute([strval($chapter['id'])]);
$chapterComments = $commentsStmt->fetchAll();

// Auto-record in reading history table
try {
    $userId = $_SESSION['user_id'] ?? null;
    $userToken = !empty($userId) ? 'user_' . $userId : ($_COOKIE['guest_reader_token'] ?? '');
    if (empty($userToken)) {
        $userToken = 'guest_' . bin2hex(random_bytes(16));
        setcookie('guest_reader_token', $userToken, time() + (86400 * 365), '/');
    }

    $currentReadUrl = $isLive
        ? (BASE_URL . "reader.php?md_ch=" . urlencode($chapter['id']) . "&md_manga=" . urlencode($chapter['manhwa_id']) . "&ch_num=" . urlencode($chapter['chapter_number']))
        : (BASE_URL . "reader.php?chapter_id=" . intval($chapter['id']));

    $stmtHist = $pdo->prepare("
        INSERT INTO `reading_history` 
            (`user_id`, `user_token`, `series_id`, `series_title`, `cover_image`, `chapter_id`, `chapter_number`, `chapter_title`, `read_url`, `scroll_percent`, `updated_at`)
        VALUES 
            (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())
        ON DUPLICATE KEY UPDATE 
            `user_id` = VALUES(`user_id`),
            `series_title` = VALUES(`series_title`),
            `cover_image` = VALUES(`cover_image`),
            `chapter_id` = VALUES(`chapter_id`),
            `chapter_number` = VALUES(`chapter_number`),
            `chapter_title` = VALUES(`chapter_title`),
            `read_url` = VALUES(`read_url`),
            `updated_at` = NOW()
    ");
    $stmtHist->execute([
        $userId,
        $userToken,
        strval($chapter['manhwa_id']),
        $chapter['manhwa_title'],
        ChaptersFallback::resolveCover(strval($chapter['manhwa_id']), $chapter['manhwa_title'], $chapter['cover_image']),
        strval($chapter['id']),
        floatval($chapter['chapter_number']),
        $chapter['title'] ?: ('Chapter ' . $chapter['chapter_number']),
        $currentReadUrl
    ]);
} catch (PDOException $e) {}

$page_title = $chapter['manhwa_title'] . ' - Chapter ' . $chapter['chapter_number'];
require_once __DIR__ . '/includes/header.php';
?>

<!-- Reading Progress Bar -->
<div id="reading-progress-container" class="fixed top-0 left-0 right-0 h-1 bg-dark-900 z-50 pointer-events-none transition-opacity duration-300">
    <div id="reading-progress" class="h-full bg-gradient-to-r from-brand-500 to-indigo-500 w-0"></div>
</div>

<!-- Floating One-Hand Quick Scroll Buttons (Mobile & Desktop) -->
<div id="floating-scroll-controls" class="fixed bottom-6 right-4 z-40 flex flex-col gap-2.5 transition-all duration-300">
    <button onclick="scrollReader('up')" 
            class="w-11 h-11 rounded-full bg-dark-900/90 backdrop-blur-md border border-dark-700 hover:border-brand-500 text-slate-300 hover:text-white shadow-2xl flex items-center justify-center active:scale-90 transition-all"
            title="Scroll Up">
        <i class="fa-solid fa-chevron-up text-sm text-brand-400"></i>
    </button>
    <button onclick="scrollReader('down')" 
            class="w-11 h-11 rounded-full bg-dark-900/90 backdrop-blur-md border border-dark-700 hover:border-brand-500 text-slate-300 hover:text-white shadow-2xl flex items-center justify-center active:scale-90 transition-all"
            title="Scroll Down">
        <i class="fa-solid fa-chevron-down text-sm text-brand-400"></i>
    </button>
</div>

<!-- Floating Exit Fullscreen Button (Pinned during Fullscreen mode) -->
<button id="btn-floating-exit-fullscreen" 
        onclick="toggleFullscreen()" 
        class="fixed top-4 right-4 z-50 px-3.5 py-2 rounded-full bg-black/85 hover:bg-black text-slate-200 hover:text-white border border-white/20 backdrop-blur-md shadow-2xl transition-all duration-300 hidden items-center gap-2 text-xs font-bold active:scale-95 group opacity-85 hover:opacity-100 cursor-pointer"
        title="Exit Fullscreen (or press icon again)">
    <i class="fa-solid fa-compress text-brand-400 group-hover:scale-110 transition-transform"></i>
    <span class="text-[11px] font-semibold">Exit Fullscreen</span>
</button>

<!-- Gestures & Controls Guide Modal -->
<div id="gesture-guide-modal" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4 hidden">
    <div class="bg-dark-900 border border-dark-800 rounded-2xl max-w-sm w-full p-6 space-y-5 shadow-2xl animate-fadeIn">
        <div class="flex items-center justify-between border-b border-dark-800 pb-3">
            <h3 class="font-bold text-white text-sm flex items-center gap-2">
                <i class="fa-solid fa-mobile-screen-button text-brand-500"></i> Reading Controls &amp; Gestures
            </h3>
            <button onclick="closeGestureGuide()" class="text-slate-400 hover:text-white">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="space-y-3 text-xs text-slate-300">
            <div class="p-3 rounded-xl bg-dark-850 border border-dark-750 flex items-start gap-3">
                <div class="w-8 h-8 rounded-lg bg-brand-600/20 text-brand-400 flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-hand-pointer"></i>
                </div>
                <div>
                    <h4 class="font-bold text-white">Screen Tap Zones</h4>
                    <p class="text-slate-400 text-[11px] mt-0.5">
                        &bull; <strong>Left side (35%):</strong> Tap to Scroll Up<br>
                        &bull; <strong>Right side (35%):</strong> Tap to Scroll Down<br>
                        &bull; <strong>Center area:</strong> Tap to Show / Hide Menu Bar
                    </p>
                </div>
            </div>
            <div class="p-3 rounded-xl bg-dark-850 border border-dark-750 flex items-start gap-3">
                <div class="w-8 h-8 rounded-lg bg-emerald-600/20 text-emerald-400 flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-expand"></i>
                </div>
                <div>
                    <h4 class="font-bold text-white">Dedicated Fullscreen Mode</h4>
                    <p class="text-slate-400 text-[11px] mt-0.5">
                        Tap the <i class="fa-solid fa-expand text-[10px] text-brand-400"></i> icon in the menu bar to enter locked Fullscreen. To exit, simply tap the floating <strong>Exit Fullscreen</strong> button!
                    </p>
                </div>
            </div>
            <div class="p-3 rounded-xl bg-dark-850 border border-dark-750 flex items-start gap-3">
                <div class="w-8 h-8 rounded-lg bg-indigo-600/20 text-indigo-400 flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-keyboard"></i>
                </div>
                <div>
                    <h4 class="font-bold text-white">Keyboard &amp; Remote Keys</h4>
                    <p class="text-slate-400 text-[11px] mt-0.5">
                        &bull; <strong>Arrow Up / PageUp:</strong> Scroll Up<br>
                        &bull; <strong>Arrow Down / Space:</strong> Scroll Down<br>
                        <span class="text-amber-400/90 text-[10px] block mt-1">
                            *Physical phone volume keys are restricted by Android/iOS system audio. Use <strong>Screen Tap</strong> or <strong>Floating Buttons</strong> for smooth 1-hand mobile reading.
                        </span>
                    </p>
                </div>
            </div>
            <div class="p-3 rounded-xl bg-dark-850 border border-dark-750 flex items-start gap-3">
                <div class="w-8 h-8 rounded-lg bg-indigo-600/20 text-indigo-400 flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-circle-chevron-down"></i>
                </div>
                <div>
                    <h4 class="font-bold text-white">Floating Thumb Buttons</h4>
                    <p class="text-slate-400 text-[11px] mt-0.5">
                        Tap the <strong>▲ and ▼ buttons</strong> for easy one-hand scrolling (hides when menu is hidden).
                    </p>
                </div>
            </div>
        </div>
        <button onclick="closeGestureGuide()" class="w-full py-2.5 rounded-xl bg-brand-600 hover:bg-brand-500 text-white font-bold text-xs transition-colors shadow-lg shadow-brand-600/30">
            Got it, Let's Read!
        </button>
    </div>
</div>

<div class="min-h-screen bg-black">

    <!-- Sticky / Floating Reader Control Bar -->
    <div id="reader-sticky-bar" class="fixed top-0 left-0 right-0 z-40 bg-dark-900/95 backdrop-blur-md border-b border-dark-800 shadow-md transition-all duration-300 transform translate-y-0">
        <div class="max-w-6xl mx-auto px-4 py-2.5 flex flex-wrap items-center justify-between gap-3 text-xs sm:text-sm">
            
            <!-- Left: Back to Manhwa details & Title -->
            <div class="flex items-center gap-3">
                <a href="<?= $backUrl ?>" 
                   class="px-2.5 py-1.5 rounded-lg bg-dark-800 hover:bg-dark-700 text-slate-300 hover:text-white transition-colors flex items-center gap-1.5">
                    <i class="fa-solid fa-arrow-left"></i>
                    <span class="hidden sm:inline">Back</span>
                </a>
                <div class="flex flex-col">
                    <a href="<?= $backUrl ?>" class="font-bold text-white hover:text-brand-400 transition-colors line-clamp-1 max-w-[150px] sm:max-w-xs">
                        <?= htmlspecialchars($chapter['manhwa_title']) ?>
                    </a>
                    <span class="text-[11px] text-slate-400">
                        Chapter <?= $chapter['chapter_number'] ?><?= !empty($chapter['title']) ? ' - ' . htmlspecialchars($chapter['title']) : '' ?>
                    </span>
                </div>
            </div>

            <!-- Center: Chapter Dropdown & Navigation -->
            <div class="flex items-center gap-1.5 sm:gap-2">
                <!-- Prev Button -->
                <?php if ($prevChapter): ?>
                    <a href="<?= $prevChapter['read_url'] ?>" 
                       id="btn-prev-chapter"
                       title="Previous Chapter (Left Arrow Key)"
                       class="px-2.5 py-1.5 rounded-lg bg-dark-800 hover:bg-brand-600 text-slate-300 hover:text-white transition-colors">
                        <i class="fa-solid fa-chevron-left"></i>
                    </a>
                <?php else: ?>
                    <button disabled class="px-2.5 py-1.5 rounded-lg bg-dark-850 text-slate-600 cursor-not-allowed">
                        <i class="fa-solid fa-chevron-left"></i>
                    </button>
                <?php endif; ?>

                <!-- Chapter Selector Dropdown -->
                <select id="chapter-select" 
                        onchange="if(this.value) window.location.href=this.value"
                        class="bg-dark-800 border border-dark-700 text-slate-200 rounded-lg px-2.5 py-1.5 text-xs font-semibold focus:outline-none focus:border-brand-500">
                    <?php foreach ($allChapters as $cOption): ?>
                        <option value="<?= $cOption['read_url'] ?>" <?= !empty($cOption['is_current']) ? 'selected' : '' ?>>
                            Ch. <?= $cOption['chapter_number'] ?><?= !empty($cOption['title']) ? ' - ' . htmlspecialchars($cOption['title']) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <!-- Next Button -->
                <?php if ($nextChapter): ?>
                    <a href="<?= $nextChapter['read_url'] ?>" 
                       id="btn-next-chapter"
                       title="Next Chapter (Right Arrow Key)"
                       class="px-2.5 py-1.5 rounded-lg bg-dark-800 hover:bg-brand-600 text-slate-300 hover:text-white transition-colors">
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <button disabled class="px-2.5 py-1.5 rounded-lg bg-dark-850 text-slate-600 cursor-not-allowed">
                        <i class="fa-solid fa-chevron-right"></i>
                    </button>
                <?php endif; ?>
            </div>

            <!-- Right: Reader Width Controls, Gestures & Fullscreen -->
            <div class="flex items-center gap-1.5 sm:gap-2">
                <!-- Controls & Gesture Guide Button -->
                <button onclick="openGestureGuide()" 
                        class="px-2.5 py-1.5 rounded-lg bg-dark-800 hover:bg-dark-700 text-brand-400 hover:text-white transition-colors flex items-center gap-1 text-xs"
                        title="Touch & Volume Gestures Guide">
                    <i class="fa-solid fa-mobile-screen-button"></i>
                    <span class="hidden sm:inline text-[11px] font-semibold text-slate-300">Controls</span>
                </button>

                <!-- Width Selector (Desktop) -->
                <div class="hidden md:flex items-center bg-dark-800 p-0.5 rounded-lg border border-dark-700 text-xs">
                    <button onclick="setReaderWidth('650px', this)" class="btn-width px-2 py-1 rounded text-slate-400 hover:text-white transition-colors" title="650px">S</button>
                    <button onclick="setReaderWidth('800px', this)" class="btn-width px-2 py-1 rounded bg-dark-700 text-brand-400 font-bold transition-colors" title="800px">M</button>
                    <button onclick="setReaderWidth('1000px', this)" class="btn-width px-2 py-1 rounded text-slate-400 hover:text-white transition-colors" title="1000px">L</button>
                    <button onclick="setReaderWidth('100%', this)" class="btn-width px-2 py-1 rounded text-slate-400 hover:text-white transition-colors" title="Full Width">Full</button>
                </div>

                <!-- Fullscreen -->
                <button onclick="toggleFullscreen()" class="px-2.5 py-1.5 rounded-lg bg-dark-800 hover:bg-dark-700 text-slate-300 hover:text-white transition-colors" title="Fullscreen (F)">
                    <i id="fullscreen-icon" class="fa-solid fa-expand"></i>
                </button>
            </div>

        </div>
    </div>

    <!-- MAIN READER CANVAS / STRIP -->
    <div id="reader-canvas-wrapper" class="w-full p-0 m-0 overflow-x-hidden bg-black pt-[54px] sm:pt-[56px]">
        
        <?php if (empty($pages)): ?>
            <div class="max-w-md mx-auto my-20 p-8 rounded-2xl bg-dark-900 border border-dark-800 text-center space-y-4">
                <div class="text-4xl text-slate-600"><i class="fa-solid fa-image"></i></div>
                <h3 class="text-lg font-bold text-white">No pages available for this chapter</h3>
                <p class="text-xs text-slate-400">This chapter has no published pages or is currently being processed.</p>
                <a href="<?= $backUrl ?>" class="inline-block px-4 py-2 bg-brand-600 text-white rounded-lg text-xs font-bold">
                    Return to Series
                </a>
            </div>
        <?php else: ?>
            <!-- Continuous Webtoon Strip Container -->
            <div id="webtoon-strip" class="webtoon-strip-container w-full mx-auto p-0 m-0 bg-black" style="max-width: 800px;">
                <?php foreach ($pages as $p): ?>
                    <div class="w-full bg-black leading-none p-0 m-0 text-[0px] select-none">
                        <img src="<?= htmlspecialchars($p['image_url']) ?>" 
                             data-fallback="<?= htmlspecialchars($p['fallback_url'] ?? $p['image_url']) ?>"
                             alt="Chapter <?= $chapter['chapter_number'] ?> - Page <?= $p['page_number'] ?>" 
                             referrerpolicy="no-referrer"
                             loading="lazy"
                             decoding="async"
                             onerror="handleImageFallback(this)"
                             class="w-full h-auto block p-0 m-0 border-0 outline-none select-none max-w-full">
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Bottom Chapter Navigation Card -->
        <div class="max-w-2xl mx-auto my-12 px-4">
            <div class="bg-dark-900 border border-dark-800 rounded-2xl p-6 sm:p-8 text-center space-y-5 shadow-2xl">
                <div class="w-12 h-12 rounded-full bg-brand-600/20 text-brand-400 flex items-center justify-center mx-auto text-xl">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                
                <div>
                    <h3 class="text-lg sm:text-xl font-bold text-white">
                        You've finished Chapter <?= $chapter['chapter_number'] ?>!
                    </h3>
                    <p class="text-xs text-slate-400 mt-1">
                        Thank you for reading <strong class="text-slate-200"><?= htmlspecialchars($chapter['manhwa_title']) ?></strong> on ManhwaFlow.
                    </p>
                </div>

                <div class="flex flex-col sm:flex-row items-center justify-center gap-3 pt-2">
                    <?php if ($nextChapter): ?>
                        <a href="<?= $nextChapter['read_url'] ?>" 
                           class="w-full sm:w-auto px-6 py-3 rounded-xl bg-gradient-to-r from-brand-600 to-indigo-600 hover:from-brand-500 hover:to-indigo-500 text-white font-bold text-sm shadow-lg shadow-brand-600/30 flex items-center justify-center gap-2 transition-all">
                            <span>Next Chapter (Ch. <?= $nextChapter['chapter_number'] ?>)</span>
                            <i class="fa-solid fa-arrow-right text-xs"></i>
                        </a>
                    <?php else: ?>
                        <div class="px-5 py-2.5 rounded-xl bg-dark-850 border border-dark-700 text-slate-400 text-xs font-semibold">
                            You're at the latest chapter!
                        </div>
                    <?php endif; ?>

                    <a href="<?= $backUrl ?>" 
                       class="w-full sm:w-auto px-5 py-3 rounded-xl bg-dark-850 hover:bg-dark-800 border border-dark-700 text-slate-300 hover:text-white font-semibold text-xs transition-colors flex items-center justify-center gap-2">
                        <i class="fa-solid fa-list-ul"></i> Chapter List
                    </a>
                </div>
            </div>
        </div>

        <!-- Non-Intrusive Bottom Ad Slot -->
        <?php renderAdSlot('reader_bottom'); ?>

        <!-- Chapter Discussion & Comments Section -->
        <div id="comments-section" class="max-w-2xl mx-auto my-8 px-4">
            <div class="bg-dark-900 border border-dark-800 rounded-2xl p-6 sm:p-8 space-y-6 shadow-2xl">
                <!-- Header -->
                <div class="flex items-center justify-between pb-4 border-b border-dark-800">
                    <div class="flex items-center gap-2.5">
                        <i class="fa-solid fa-comments text-brand-500 text-lg"></i>
                        <h3 class="text-base sm:text-lg font-bold text-white">Chapter Discussion</h3>
                        <span id="comment-counter" class="px-2 py-0.5 rounded-full text-xs bg-dark-800 border border-dark-700 text-slate-300 font-semibold">
                            <?= count($chapterComments) ?>
                        </span>
                    </div>
                    <span class="text-xs text-slate-500">Ch. <?= $chapter['chapter_number'] ?></span>
                </div>

                <!-- Input Box -->
                <?php if (isset($_SESSION['user_id'])): ?>
                    <form id="comment-form" onsubmit="submitComment(event)" class="space-y-3">
                        <input type="hidden" id="comment-ch-id" value="<?= htmlspecialchars($chapter['id']) ?>">
                        <input type="hidden" id="comment-series-id" value="<?= htmlspecialchars($chapter['manhwa_id']) ?>">
                        <div class="flex items-start gap-3">
                            <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-brand-600 to-indigo-600 text-white flex items-center justify-center font-bold text-xs shrink-0">
                                <?= strtoupper(substr($_SESSION['username'], 0, 1)) ?>
                            </div>
                            <div class="flex-1 space-y-2">
                                <textarea id="comment-input" rows="3" required placeholder="Leave your thoughts, theories, or reaction on this chapter..."
                                          class="w-full bg-dark-850 border border-dark-700 rounded-xl p-3 text-xs sm:text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-brand-500 transition-all resize-none"></textarea>
                                <div class="flex justify-between items-center">
                                    <span class="text-[11px] text-slate-500">Posting as <strong class="text-slate-300"><?= htmlspecialchars($_SESSION['username']) ?></strong></span>
                                    <button type="submit" id="btn-submit-comment"
                                            class="px-4 py-2 rounded-xl bg-brand-600 hover:bg-brand-500 text-white text-xs font-bold transition-all shadow-md shadow-brand-600/20 flex items-center gap-1.5">
                                        <i class="fa-solid fa-paper-plane text-[10px]"></i> Post Comment
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="p-4 rounded-xl bg-dark-850 border border-dark-750 flex flex-col sm:flex-row items-center justify-between gap-3 text-center sm:text-left">
                        <div class="space-y-0.5">
                            <p class="text-xs font-bold text-white">Join the Community Discussion</p>
                            <p class="text-[11px] text-slate-400">Sign in to share your thoughts and react to this chapter.</p>
                        </div>
                        <a href="<?= BASE_URL ?>login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI'] ?? '') ?>" 
                           class="px-4 py-2 rounded-lg bg-brand-600 hover:bg-brand-500 text-white text-xs font-bold transition-all shadow-md shrink-0">
                            Sign In to Comment
                        </a>
                    </div>
                <?php endif; ?>

                <!-- Comments List -->
                <div id="comments-list" class="space-y-3.5 pt-2">
                    <?php if (empty($chapterComments)): ?>
                        <div id="no-comments-msg" class="py-8 text-center text-slate-500 text-xs space-y-1">
                            <i class="fa-regular fa-comment-dots text-2xl mb-1 text-slate-600"></i>
                            <p>No comments on this chapter yet.</p>
                            <p class="text-slate-400 font-medium">Be the first to share your reaction!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($chapterComments as $cm): ?>
                            <div class="comment-item p-4 rounded-xl bg-dark-850/70 border border-dark-800 space-y-2">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-7 h-7 rounded-full bg-dark-750 border border-dark-700 text-brand-400 flex items-center justify-center text-xs font-bold">
                                            <?= strtoupper(substr($cm['author_name'], 0, 1)) ?>
                                        </div>
                                        <span class="text-xs font-bold text-white"><?= htmlspecialchars($cm['author_name']) ?></span>
                                        <span class="text-[10px] text-slate-500"><?= date('M d, Y', strtotime($cm['created_at'])) ?></span>
                                    </div>
                                    <button onclick="likeComment(<?= $cm['id'] ?>, this)" class="btn-like flex items-center gap-1.5 text-xs text-slate-400 hover:text-rose-400 transition-colors">
                                        <i class="fa-regular fa-heart"></i>
                                        <span class="like-count"><?= $cm['likes'] ?></span>
                                    </button>
                                </div>
                                <p class="text-xs sm:text-sm text-slate-200 leading-relaxed pl-9">
                                    <?= nl2br(htmlspecialchars($cm['comment'])) ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>

</div>

<script>
// ==========================================
// 1. Reading Progress & Smart Auto-Hide Controls
// ==========================================
let lastScrollY = window.scrollY;
let isHeaderHidden = false;
let scrollTicking = false;

window.addEventListener('scroll', () => {
    // 1. Reading Progress Bar
    const totalHeight = document.documentElement.scrollHeight - window.innerHeight;
    const progress = totalHeight > 0 ? (window.scrollY / totalHeight) * 100 : 0;
    const progressBar = document.getElementById('reading-progress');
    if (progressBar) {
        progressBar.style.width = Math.min(100, Math.max(0, progress)) + '%';
    }

    // 2. Smart Auto-Hide: Scroll down hides menu so ONLY images are visible; Scroll up reveals
    if (!scrollTicking) {
        window.requestAnimationFrame(() => {
            const currentScrollY = window.scrollY;
            const header = document.getElementById('reader-sticky-bar');
            const floatingControls = document.getElementById('floating-scroll-controls');

            if (currentScrollY > lastScrollY + 10 && currentScrollY > 40) {
                // Scrolling DOWN -> Hide controls for pure full-screen image immersion
                if (header && !isHeaderHidden) {
                    header.classList.add('-translate-y-full', 'pointer-events-none');
                    isHeaderHidden = true;
                }
                if (floatingControls) {
                    floatingControls.classList.add('opacity-0', 'pointer-events-none', 'translate-y-4');
                }
            } else if (currentScrollY < lastScrollY - 15 || currentScrollY <= 15) {
                // Scrolling UP or Top -> Reveal controls smoothly (unless locked in Fullscreen mode)
                if (isReaderFullscreen) {
                    lastScrollY = Math.max(0, currentScrollY);
                    scrollTicking = false;
                    return;
                }
                if (header && isHeaderHidden) {
                    header.classList.remove('-translate-y-full', 'pointer-events-none');
                    isHeaderHidden = false;
                }
                if (floatingControls) {
                    floatingControls.classList.remove('opacity-0', 'pointer-events-none', 'translate-y-4');
                }
            }

            lastScrollY = Math.max(0, currentScrollY);
            scrollTicking = false;
        });
        scrollTicking = true;
    }
}, { passive: true });

// ==========================================
// 2. Adjust Reader Width
// ==========================================
function setReaderWidth(width, btnEl) {
    const strip = document.getElementById('webtoon-strip');
    if (strip) {
        strip.style.maxWidth = width;
    }
    document.querySelectorAll('.btn-width').forEach(b => {
        b.classList.remove('bg-dark-700', 'text-brand-400', 'font-bold');
        b.classList.add('text-slate-400');
    });
    if (btnEl) {
        btnEl.classList.remove('text-slate-400');
        btnEl.classList.add('bg-dark-700', 'text-brand-400', 'font-bold');
    }
}

// ==========================================
// 3. Immersive Locked Fullscreen (Mobile & Desktop)
// ==========================================
let isReaderFullscreen = false;

function applyFullscreenUI(enable) {
    const body = document.body;
    const header = document.getElementById('reader-sticky-bar');
    const canvas = document.getElementById('reader-canvas-wrapper');
    const floatingBtn = document.getElementById('btn-floating-exit-fullscreen');
    const bottomAd = document.getElementById('mf-sticky-bottom-banner');
    const icon = document.getElementById('fullscreen-icon');

    if (enable) {
        body.classList.add('reader-fullscreen-active');
        if (header) {
            header.classList.add('-translate-y-full', 'pointer-events-none');
            isHeaderHidden = true;
        }
        if (canvas) {
            canvas.classList.remove('pt-[54px]', 'sm:pt-[56px]');
            canvas.classList.add('pt-0');
        }
        if (floatingBtn) {
            floatingBtn.classList.remove('hidden');
            floatingBtn.classList.add('flex');
        }
        if (bottomAd) {
            bottomAd.classList.add('hidden');
        }
        if (icon) {
            icon.classList.remove('fa-expand');
            icon.classList.add('fa-compress');
        }
    } else {
        body.classList.remove('reader-fullscreen-active');
        if (header) {
            header.classList.remove('-translate-y-full', 'pointer-events-none');
            isHeaderHidden = false;
        }
        if (canvas) {
            canvas.classList.remove('pt-0');
            canvas.classList.add('pt-[54px]', 'sm:pt-[56px]');
        }
        if (floatingBtn) {
            floatingBtn.classList.add('hidden');
            floatingBtn.classList.remove('flex');
        }
        if (bottomAd && sessionStorage.getItem('mf_sticky_banner_dismissed') !== '1') {
            bottomAd.classList.remove('hidden');
        }
        if (icon) {
            icon.classList.remove('fa-compress');
            icon.classList.add('fa-expand');
        }
    }
}

function toggleFullscreen() {
    isReaderFullscreen = !isReaderFullscreen;

    const docEl = document.documentElement;
    const isNativeFS = document.fullscreenElement || 
                       document.webkitFullscreenElement || 
                       document.mozFullScreenElement || 
                       document.msFullscreenElement;

    if (isReaderFullscreen) {
        try {
            localStorage.setItem('mf_reader_fullscreen', '1');
        } catch(e) {}
        applyFullscreenUI(true);

        // Native Browser Fullscreen (Android Chrome / PC)
        if (!isNativeFS) {
            try {
                if (docEl.requestFullscreen) {
                    docEl.requestFullscreen().catch(() => {});
                } else if (docEl.webkitRequestFullscreen) {
                    docEl.webkitRequestFullscreen();
                } else if (docEl.mozRequestFullScreen) {
                    docEl.mozRequestFullScreen();
                } else if (docEl.msRequestFullscreen) {
                    docEl.msRequestFullscreen();
                }
            } catch(e) {}
        }
    } else {
        try {
            localStorage.removeItem('mf_reader_fullscreen');
        } catch(e) {}
        applyFullscreenUI(false);

        // Exit Native Browser Fullscreen
        if (isNativeFS) {
            try {
                if (document.exitFullscreen) {
                    document.exitFullscreen().catch(() => {});
                } else if (document.webkitExitFullscreen) {
                    document.webkitExitFullscreen();
                } else if (document.mozCancelFullScreen) {
                    document.mozCancelFullScreen();
                } else if (document.msExitFullscreen) {
                    document.msExitFullscreen();
                }
            } catch(e) {}
        }
    }
}

// Automatically restore Fullscreen state when navigating to Next / Previous Chapter
(function initPersistedFullscreen() {
    try {
        if (localStorage.getItem('mf_reader_fullscreen') === '1') {
            isReaderFullscreen = true;
            applyFullscreenUI(true);

            // Re-engage native browser fullscreen on user's first tap or scroll
            const resumeNative = () => {
                if (isReaderFullscreen) {
                    const isNative = document.fullscreenElement || document.webkitFullscreenElement;
                    if (!isNative) {
                        const doc = document.documentElement;
                        if (doc.requestFullscreen) doc.requestFullscreen().catch(() => {});
                        else if (doc.webkitRequestFullscreen) doc.webkitRequestFullscreen();
                    }
                }
            };
            window.addEventListener('click', resumeNative, { once: true });
            window.addEventListener('touchstart', resumeNative, { once: true, passive: true });
            window.addEventListener('scroll', resumeNative, { once: true, passive: true });
        }
    } catch(e) {}
})();

// Update icon if native fullscreen state changes, but NEVER drop reader mode when typing comments
['fullscreenchange', 'webkitfullscreenchange', 'mozfullscreenchange', 'MSFullscreenChange'].forEach(evt => {
    document.addEventListener(evt, () => {
        const icon = document.getElementById('fullscreen-icon');
        const floatingBtn = document.getElementById('btn-floating-exit-fullscreen');
        if (isReaderFullscreen) {
            if (icon) {
                icon.classList.remove('fa-expand');
                icon.classList.add('fa-compress');
            }
            if (floatingBtn) {
                floatingBtn.classList.remove('hidden');
                floatingBtn.classList.add('flex');
            }
        }
    });
});
// ==========================================
// 4. Smooth Reader Scroll (Up & Down)
// ==========================================
function scrollReader(direction) {
    const scrollAmount = Math.max(350, Math.floor(window.innerHeight * 0.75));
    if (direction === 'up') {
        window.scrollBy({ top: -scrollAmount, behavior: 'smooth' });
    } else {
        window.scrollBy({ top: scrollAmount, behavior: 'smooth' });
    }
}

// Distraction-free menu bar toggle (Screen Tap)
function toggleReaderHeader() {
    const header = document.getElementById('reader-sticky-bar');
    const floatingControls = document.getElementById('floating-scroll-controls');
    const progressContainer = document.getElementById('reading-progress-container');
    if (!header) return;
    
    isHeaderHidden = !isHeaderHidden;
    if (isHeaderHidden) {
        header.classList.add('-translate-y-full', 'pointer-events-none');
        if (floatingControls) {
            floatingControls.classList.add('opacity-0', 'pointer-events-none', 'translate-y-6');
        }
        if (progressContainer) {
            progressContainer.classList.add('opacity-0');
        }
    } else {
        header.classList.remove('-translate-y-full', 'pointer-events-none');
        if (floatingControls) {
            floatingControls.classList.remove('opacity-0', 'pointer-events-none', 'translate-y-6');
        }
        if (progressContainer) {
            progressContainer.classList.remove('opacity-0');
        }
    }
}

// Gestures guide modal
function openGestureGuide() {
    const modal = document.getElementById('gesture-guide-modal');
    if (modal) modal.classList.remove('hidden');
}

function closeGestureGuide() {
    const modal = document.getElementById('gesture-guide-modal');
    if (modal) modal.classList.add('hidden');
}

// ==========================================
// 5. Screen Tap to Toggle Menu Bar (Immersive Reading)
// ==========================================
let touchStartX = 0;
let touchStartY = 0;
let touchStartTime = 0;

window.addEventListener('touchstart', (e) => {
    if (e.touches.length !== 1) return;
    touchStartX = e.touches[0].clientX;
    touchStartY = e.touches[0].clientY;
    touchStartTime = Date.now();
}, { passive: true });

window.addEventListener('touchend', (e) => {
    if (e.changedTouches.length !== 1) return;
    const touchEndX = e.changedTouches[0].clientX;
    const touchEndY = e.changedTouches[0].clientY;
    const duration = Date.now() - touchStartTime;
    const diffX = Math.abs(touchEndX - touchStartX);
    const diffY = Math.abs(touchEndY - touchStartY);

    // If dragged/swiped more than 15px or held longer than 350ms, it's a drag/swipe/scroll, NOT a tap
    if (diffX > 15 || diffY > 15 || duration > 350) return;

    // Do not trigger when tapping interactive controls (buttons, links, inputs, dropdowns)
    const target = e.target;
    if (target.closest('button, a, input, textarea, select, #comments-section, #reader-sticky-bar, #gesture-guide-modal, #floating-scroll-controls, .btn-like, form')) {
        return;
    }

    handleScreenTap(touchEndX);
}, { passive: true });

function handleScreenTap(clientX) {
    const screenWidth = window.innerWidth;
    const leftBoundary = screenWidth * 0.35;   // Left 35% -> Clickable Scroll Up
    const rightBoundary = screenWidth * 0.65;  // Right 35% -> Clickable Scroll Down

    if (clientX < leftBoundary) {
        scrollReader('up');
    } else if (clientX > rightBoundary) {
        scrollReader('down');
    } else {
        toggleReaderHeader(); // Center area -> Toggle Menu Bar & Controls
    }
}

// Desktop click on reader container to trigger tap zones
const webtoonContainer = document.getElementById('webtoon-strip');
if (webtoonContainer) {
    webtoonContainer.addEventListener('click', (e) => {
        if (e.pointerType === 'touch') return; // Handled by touch events
        if (e.target.closest('button, a, input, textarea, select, #comments-section, #reader-sticky-bar, #gesture-guide-modal, #floating-scroll-controls, form')) {
            return;
        }
        handleScreenTap(e.clientX);
    });
}

// ==========================================
// 6. Hardware Volume Keys & Keyboard Navigation
// ==========================================
function handleReaderKeys(e) {
    // Avoid triggering when user is in input or select
    if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) return;

    // A. Phone Hardware Volume Keys (Volume Up = Scroll Up, Volume Down = Scroll Down)
    const isVolumeUp = e.key === 'VolumeUp' || 
                       e.key === 'AudioVolumeUp' || 
                       e.code === 'AudioVolumeUp' || 
                       e.code === 'VolumeUp' || 
                       e.keyCode === 175 || 
                       e.keyCode === 24 ||
                       e.which === 175 ||
                       e.which === 24;

    const isVolumeDown = e.key === 'VolumeDown' || 
                         e.key === 'AudioVolumeDown' || 
                         e.code === 'AudioVolumeDown' || 
                         e.code === 'VolumeDown' || 
                         e.keyCode === 174 || 
                         e.keyCode === 25 ||
                         e.which === 174 ||
                         e.which === 25;

    if (isVolumeUp) {
        if (e.cancelable) e.preventDefault();
        e.stopPropagation();
        scrollReader('up');
        return;
    }

    if (isVolumeDown) {
        if (e.cancelable) e.preventDefault();
        e.stopPropagation();
        scrollReader('down');
        return;
    }

    // B. Desktop Keyboard Navigation
    if (e.key === 'ArrowLeft') {
        const prevBtn = document.getElementById('btn-prev-chapter');
        if (prevBtn) prevBtn.click();
    } else if (e.key === 'ArrowRight') {
        const nextBtn = document.getElementById('btn-next-chapter');
        if (nextBtn) nextBtn.click();
    } else if (e.key === 'ArrowUp' || e.key === 'PageUp') {
        if (e.cancelable) e.preventDefault();
        scrollReader('up');
    } else if (e.key === 'ArrowDown' || e.key === 'PageDown') {
        if (e.cancelable) e.preventDefault();
        scrollReader('down');
    } else if (e.key === ' ' && !e.shiftKey) {
        if (e.cancelable) e.preventDefault();
        scrollReader('down');
    } else if (e.key === ' ' && e.shiftKey) {
        if (e.cancelable) e.preventDefault();
        scrollReader('up');
    } else if (e.key.toLowerCase() === 'f') {
        toggleFullscreen();
    } else if (e.key.toLowerCase() === 'm') {
        toggleReaderHeader();
    }
}

window.addEventListener('keydown', handleReaderKeys, { capture: true, passive: false });
window.addEventListener('keyup', (e) => {
    const isVol = [174, 175, 24, 25].includes(e.keyCode) || 
                  ['AudioVolumeUp', 'AudioVolumeDown', 'VolumeUp', 'VolumeDown'].includes(e.key);
    if (isVol) {
        if (e.cancelable) e.preventDefault();
        e.stopPropagation();
    }
}, { capture: true, passive: false });

// Toast notification (Non-blocking, preserves Fullscreen mode)
function showReaderToast(message, type = 'brand') {
    const existing = document.getElementById('reader-toast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.id = 'reader-toast';
    let borderClass = 'border-brand-500/60 text-white';
    let iconClass = 'fa-coins text-amber-400 animate-bounce';
    if (type === 'emerald') {
        borderClass = 'border-emerald-500/60 text-emerald-200';
        iconClass = 'fa-circle-check text-emerald-400';
    } else if (type === 'rose') {
        borderClass = 'border-rose-500/60 text-rose-200';
        iconClass = 'fa-circle-exclamation text-rose-400';
    } else if (type === 'amber') {
        borderClass = 'border-amber-500/60 text-amber-200';
        iconClass = 'fa-triangle-exclamation text-amber-400';
    }

    toast.className = `fixed bottom-6 left-1/2 -translate-x-1/2 z-50 bg-dark-900/95 backdrop-blur-md border ${borderClass} px-5 py-3 rounded-2xl shadow-2xl flex items-center gap-3 text-xs sm:text-sm font-bold transition-all duration-300 transform translate-y-0`;
    toast.innerHTML = `<i class="fa-solid ${iconClass} text-base shrink-0"></i> <span>${message}</span>`;
    document.body.appendChild(toast);

    setTimeout(() => {
        toast.classList.add('opacity-0', 'translate-y-4');
        setTimeout(() => toast.remove(), 400);
    }, 3500);
}

// 5. Submit Chapter Comment
async function submitComment(e) {
    e.preventDefault();
    const input = document.getElementById('comment-input');
    const chId = document.getElementById('comment-ch-id')?.value;
    const sId = document.getElementById('comment-series-id')?.value;
    const btn = document.getElementById('btn-submit-comment');
    const text = input ? input.value.trim() : '';
    if (!text || !chId) return;

    if (btn) btn.disabled = true;

    try {
        const res = await fetch('<?= BASE_URL ?>api/comments.php?action=add', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ chapter_id: chId, series_id: sId, comment: text })
        });
        const data = await res.json();
        if (data.success && data.comment) {
            input.value = '';
            const noMsg = document.getElementById('no-comments-msg');
            if (noMsg) noMsg.remove();

            const list = document.getElementById('comments-list');
            const newCard = document.createElement('div');
            newCard.className = 'comment-item p-4 rounded-xl bg-dark-850/70 border border-dark-800 space-y-2 animate-fadeIn';
            newCard.innerHTML = `
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="w-7 h-7 rounded-full bg-brand-600/30 border border-brand-500/40 text-brand-400 flex items-center justify-center text-xs font-bold">
                            ${data.comment.author_name.charAt(0).toUpperCase()}
                        </div>
                        <span class="text-xs font-bold text-white">${data.comment.author_name}</span>
                        <span class="text-[10px] text-slate-500">Just now</span>
                    </div>
                    <button onclick="likeComment(${data.comment.id}, this)" class="btn-like flex items-center gap-1.5 text-xs text-slate-400 hover:text-rose-400 transition-colors">
                        <i class="fa-regular fa-heart"></i>
                        <span class="like-count">0</span>
                    </button>
                </div>
                <p class="text-xs sm:text-sm text-slate-200 leading-relaxed pl-9">
                    ${data.comment.comment}
                </p>
            `;
            if (list) list.prepend(newCard);

            const counter = document.getElementById('comment-counter');
            if (counter) counter.textContent = data.count;

            if (data.coins_awarded && data.coins_awarded > 0) {
                showReaderToast(`🎉 Tagumpay! +${data.coins_awarded} Flow Coins credited!`, 'emerald');
            } else {
                showReaderToast("💬 Na-post ang iyong comment!", 'emerald');
            }
        } else if (data.auth_required) {
            showReaderToast(data.message || 'Please log in to leave a comment.', 'rose');
            setTimeout(() => {
                window.location.href = '<?= BASE_URL ?>login.php?redirect=' + encodeURIComponent(window.location.href);
            }, 1500);
        } else {
            showReaderToast(data.message || 'Could not post comment.', 'rose');
        }
    } catch (err) {
        showReaderToast('Network error while posting comment.', 'rose');
    } finally {
        if (btn) btn.disabled = false;
    }
}

// 6. Like Comment
async function likeComment(commentId, btnEl) {
    if (!commentId || !btnEl) return;
    try {
        const res = await fetch('<?= BASE_URL ?>api/comments.php?action=like', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'comment_id=' + commentId
        });
        const data = await res.json();
        if (data.success) {
            const countEl = btnEl.querySelector('.like-count');
            if (countEl) countEl.textContent = data.likes;
            const icon = btnEl.querySelector('i');
            if (icon) {
                icon.classList.remove('fa-regular');
                icon.classList.add('fa-solid', 'text-rose-500');
            }
        }
    } catch (err) {}
}

// Reading history is automatically securely saved to the database per user session above.

// ==========================================
// 8. Auto-claim Chapter Reading Flow Coins (On Reading Completion)
// ==========================================
(function() {
    let claimed = false;
    const chapterId = <?= json_encode(strval($chapter['id'])) ?>;

    async function triggerChapterReward() {
        if (claimed) return;
        claimed = true;
        try {
            const res = await fetch('<?= BASE_URL ?>api/rewards.php?action=read_chapter', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'chapter_id=' + encodeURIComponent(chapterId)
            });
            const data = await res.json();
            if (data.success && data.awarded) {
                const toast = document.createElement('div');
                toast.className = 'fixed bottom-5 right-5 z-50 bg-dark-900 border border-amber-500/60 text-white px-4 py-2.5 rounded-2xl shadow-2xl flex items-center gap-2.5 text-xs font-bold transition-all';
                toast.innerHTML = `<i class="fa-solid fa-coins text-amber-400 text-sm animate-bounce"></i> <span>+${data.earned_coins} Flow Coins Earned!</span>`;
                document.body.appendChild(toast);
                setTimeout(() => {
                    toast.style.opacity = '0';
                    setTimeout(() => toast.remove(), 400);
                }, 3500);
            }
        } catch(e) {}
    }

    // Trigger when user scrolls to bottom navigation card or comments
    const targetElement = document.getElementById('comments-section') || document.querySelector('.webtoon-strip-container');
    if (targetElement && 'IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    triggerChapterReward();
                    observer.disconnect();
                }
            });
        }, { threshold: 0.1 });
        observer.observe(targetElement);
    } else {
        // Fallback on scroll
        window.addEventListener('scroll', () => {
            if ((window.innerHeight + window.scrollY) >= document.body.offsetHeight - 500) {
                triggerChapterReward();
            }
        }, { passive: true });
    }
})();

// ==========================================
// 9. Intelligent Image Error Fallback & Retry
// ==========================================
function handleImageFallback(img) {
    if (!img.dataset.retried) {
        img.dataset.retried = '1';
        const fallback = img.getAttribute('data-fallback');
        // If current image used proxy, attempt direct CDN url; if it was direct, attempt proxy
        if (img.src.includes('api/image_proxy.php')) {
            if (fallback && fallback !== img.src) {
                img.src = fallback;
                return;
            }
        } else {
            img.src = '<?= BASE_URL ?>api/image_proxy.php?url=' + encodeURIComponent(img.src);
            return;
        }
    }

    // If both direct and proxy failed, render a clean styled retry box
    img.style.display = 'none';
    const existingBox = img.parentNode.querySelector('.img-retry-box');
    if (!existingBox) {
        const errBox = document.createElement('div');
        errBox.className = 'img-retry-box w-full py-10 text-center text-xs text-slate-400 bg-dark-900 border border-dark-800 my-2 rounded-xl flex flex-col items-center justify-center gap-2.5';
        errBox.innerHTML = `
            <i class="fa-solid fa-triangle-exclamation text-amber-500 text-lg"></i>
            <span>Page failed to load</span>
            <button onclick="retryImgLoad(this)" class="px-4 py-1.5 rounded-lg bg-brand-600 hover:bg-brand-500 text-white font-bold text-xs transition-colors shadow-md">
                <i class="fa-solid fa-rotate-right mr-1"></i> Retry Page
            </button>
        `;
        img.parentNode.appendChild(errBox);
    }
}

function retryImgLoad(btn) {
    const parent = btn.closest('.img-retry-box').parentNode;
    const img = parent.querySelector('img');
    const errBox = btn.closest('.img-retry-box');
    if (img) {
        img.dataset.retried = '';
        img.style.display = 'block';
        img.src = img.src + (img.src.includes('?') ? '&' : '?') + 'r=' + Date.now();
    }
    if (errBox) errBox.remove();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

