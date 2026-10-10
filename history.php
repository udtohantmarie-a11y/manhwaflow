<?php
// history.php - Reading History & Continue Reading Hub
require_once __DIR__ . '/config/db.php';
$pdo = getPdo();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$page_title = 'Reading History';
require_once __DIR__ . '/includes/header.php';

// Fetch history strictly isolated for this user or guest session
$userId = $_SESSION['user_id'] ?? null;
$guestCookie = $_COOKIE['guest_reader_token'] ?? '';

$historyItems = [];
if ($userId) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM `reading_history` 
            WHERE `user_id` = ? 
            ORDER BY `updated_at` DESC 
            LIMIT 50
        ");
        $stmt->execute([$userId]);
        $historyItems = $stmt->fetchAll();
    } catch (PDOException $e) {}
} elseif (!empty($guestCookie)) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM `reading_history` 
            WHERE `user_token` = ? AND `user_id` IS NULL 
            ORDER BY `updated_at` DESC 
            LIMIT 50
        ");
        $stmt->execute([$guestCookie]);
        $historyItems = $stmt->fetchAll();
    } catch (PDOException $e) {}
}
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-dark-800">
        <div>
            <h1 class="text-2xl sm:text-3xl font-black text-white flex items-center gap-3">
                <span class="w-3 h-8 rounded-full bg-emerald-500"></span>
                <i class="fa-solid fa-clock-rotate-left text-emerald-400"></i> Reading History
            </h1>
            <p class="text-xs sm:text-sm text-slate-400 mt-1">
                Jump right back into any chapter where you left off.
            </p>
        </div>

        <div class="flex items-center gap-3">
            <button onclick="clearAllHistory()" 
                    id="btn-clear-history"
                    class="px-4 py-2 rounded-xl bg-dark-850 hover:bg-rose-950/40 border border-dark-750 hover:border-rose-500/40 text-slate-300 hover:text-rose-400 text-xs font-bold transition-all flex items-center gap-2 <?= empty($historyItems) ? 'hidden' : '' ?>">
                <i class="fa-regular fa-trash-can"></i> Clear History
            </button>
            <a href="<?= BASE_URL ?>" class="px-4 py-2 rounded-xl bg-brand-600 hover:bg-brand-500 text-white text-xs font-bold transition-all shadow-md">
                Browse Comics
            </a>
        </div>
    </div>

    <!-- History Container -->
    <div id="history-container">
        
        <!-- Empty State -->
        <div id="empty-history-state" class="bg-dark-900 border border-dark-800 rounded-2xl p-12 text-center max-w-md mx-auto space-y-4 <?= !empty($historyItems) ? 'hidden' : '' ?>">
            <div class="w-16 h-16 rounded-full bg-dark-800 flex items-center justify-center mx-auto text-slate-500 text-2xl">
                <i class="fa-solid fa-book-open"></i>
            </div>
            <h3 class="text-lg font-bold text-white">No Reading History Yet</h3>
            <p class="text-xs text-slate-400 leading-relaxed">
                As you read chapters on ManhwaFlow, they will automatically be saved here so you never lose your spot.
            </p>
            <a href="<?= BASE_URL ?>" class="inline-block px-5 py-2.5 bg-brand-600 hover:bg-brand-500 text-white rounded-xl text-xs font-bold transition-all shadow-lg shadow-brand-600/30">
                Start Reading Comics
            </a>
        </div>

        <!-- History Items Grid -->
        <div id="history-grid" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-6 <?= empty($historyItems) ? 'hidden' : '' ?>">
            <?php foreach ($historyItems as $item): ?>
                <div class="history-card bg-dark-900 border border-dark-800/80 hover:border-emerald-500/50 rounded-2xl p-4 flex gap-4 items-start group transition-all shadow-lg hover:shadow-emerald-950/20"
                     data-series="<?= htmlspecialchars($item['series_id']) ?>">
                    
                    <!-- Cover Poster -->
                    <a href="<?= htmlspecialchars($item['read_url']) ?>" class="w-20 aspect-[2/3] rounded-xl overflow-hidden bg-dark-950 shrink-0 border border-dark-750 block">
                        <img src="<?= htmlspecialchars($item['cover_image']) ?>" 
                             alt="<?= htmlspecialchars($item['series_title']) ?>" 
                             referrerpolicy="no-referrer"
                             loading="lazy"
                             onerror="this.onerror=null; this.src='<?= BASE_URL ?>assets/images/placeholder.svg';"
                             class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                    </a>

                    <!-- Details -->
                    <div class="flex-1 min-w-0 flex flex-col justify-between h-full space-y-2">
                        <div>
                            <div class="flex items-start justify-between gap-1">
                                <h3 class="font-bold text-sm text-white group-hover:text-emerald-400 transition-colors line-clamp-2" title="<?= htmlspecialchars($item['series_title']) ?>">
                                    <a href="<?= BASE_URL ?>manhwa.php?md_id=<?= urlencode($item['series_id']) ?>">
                                        <?= htmlspecialchars($item['series_title']) ?>
                                    </a>
                                </h3>
                                <button onclick="removeHistoryItem('<?= htmlspecialchars($item['series_id']) ?>', this)" 
                                        class="text-slate-500 hover:text-rose-400 p-1 text-xs transition-colors shrink-0" 
                                        title="Remove from history">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </div>

                            <div class="flex items-center gap-2 mt-1.5 flex-wrap">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                    Stopped at Ch. <?= $item['chapter_number'] ?>
                                </span>
                            </div>

                            <p class="text-[11px] text-slate-500 mt-1">
                                <?= date('M d, Y h:i A', strtotime($item['updated_at'])) ?>
                            </p>
                        </div>

                        <!-- Resume Button -->
                        <div class="pt-1">
                            <a href="<?= htmlspecialchars($item['read_url']) ?>" 
                               class="w-full py-2 px-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs text-center transition-all flex items-center justify-center gap-1.5 shadow-md shadow-emerald-950/30">
                                <i class="fa-solid fa-play text-[10px]"></i> Resume Reading
                            </a>
                        </div>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>

    </div>

</div>

<script>
// Remove single item from history
async function removeHistoryItem(seriesId, btnEl) {
    if (!seriesId) return;
    const card = btnEl ? btnEl.closest('.history-card') : null;
    if (card) card.remove();

    // 1. Remove from LocalStorage
    try {
        const allHist = JSON.parse(localStorage.getItem('manhwaflow_history') || localStorage.getItem('manhwaverse_history') || '{}');
        delete allHist[seriesId];
        localStorage.setItem('manhwaflow_history', JSON.stringify(allHist));
    } catch(e) {}

    // 2. Remove from Database
    try {
        await fetch('<?= BASE_URL ?>api/history.php?action=remove', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'series_id=' + encodeURIComponent(seriesId)
        });
    } catch(e) {}

    // Check if empty
    checkEmptyState();
}

// Clear all history
async function clearAllHistory() {
    if (!confirm('Are you sure you want to clear your entire reading history?')) return;

    // 1. Clear LocalStorage
    localStorage.removeItem('manhwaflow_history');
    localStorage.removeItem('manhwaverse_history');

    // 2. Clear Database
    try {
        await fetch('<?= BASE_URL ?>api/history.php?action=clear', { method: 'POST' });
    } catch(e) {}

    // Remove UI cards
    const grid = document.getElementById('history-grid');
    if (grid) grid.innerHTML = '';
    checkEmptyState();
}

function checkEmptyState() {
    const grid = document.getElementById('history-grid');
    const emptyState = document.getElementById('empty-history-state');
    const clearBtn = document.getElementById('btn-clear-history');
    const hasItems = grid && grid.querySelectorAll('.history-card').length > 0;

    if (hasItems) {
        if (grid) grid.classList.remove('hidden');
        if (emptyState) emptyState.classList.add('hidden');
        if (clearBtn) clearBtn.classList.remove('hidden');
    } else {
        if (grid) grid.classList.add('hidden');
        if (emptyState) emptyState.classList.remove('hidden');
        if (clearBtn) clearBtn.classList.add('hidden');
    }
}

// Legacy localStorage cleanup to ensure privacy
try {
    localStorage.removeItem('manhwaflow_history');
    localStorage.removeItem('manhwaverse_history');
} catch(e) {}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

