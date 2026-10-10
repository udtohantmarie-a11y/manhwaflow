<?php
// bookmarks.php - User Saved Bookmarks (Account Required)
require_once __DIR__ . '/config/db.php';
$pdo = getPdo();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isLoggedIn = isset($_SESSION['user_id']);
$userId = $isLoggedIn ? intval($_SESSION['user_id']) : 0;

$userBookmarks = [];
if ($isLoggedIn) {
    $stmt = $pdo->prepare("SELECT * FROM user_bookmarks WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$userId]);
    $userBookmarks = $stmt->fetchAll();
}

$page_title = 'My Bookmarks';
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-8">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-dark-800">
        <div>
            <h1 class="text-2xl sm:text-3xl font-black text-white flex items-center gap-3">
                <i class="fa-solid fa-bookmark text-brand-500"></i> My Saved Webtoons
            </h1>
            <p class="text-xs text-slate-400 mt-1">
                <?= $isLoggedIn ? 'Synced directly to your account: <strong class="text-white">' . htmlspecialchars($_SESSION['username']) . '</strong>.' : 'Sign in to access and sync your reading list across all your devices.' ?>
            </p>
        </div>

        <?php if ($isLoggedIn && !empty($userBookmarks)): ?>
            <button id="btn-clear-bookmarks" class="text-xs text-rose-400 hover:text-rose-300 font-semibold px-3 py-1.5 rounded-lg bg-rose-500/10 border border-rose-500/20 hover:bg-rose-500/20 transition-all self-start sm:self-auto">
                <i class="fa-regular fa-trash-can mr-1"></i> Clear All Bookmarks
            </button>
        <?php else: ?>
            <button id="btn-clear-local-bookmarks" class="hidden text-xs text-rose-400 hover:text-rose-300 font-semibold px-3 py-1.5 rounded-lg bg-rose-500/10 border border-rose-500/20 hover:bg-rose-500/20 transition-all self-start sm:self-auto">
                <i class="fa-regular fa-trash-can mr-1"></i> Clear Local Bookmarks
            </button>
        <?php endif; ?>
    </div>

    <?php if (!$isLoggedIn): ?>
        <!-- GUEST HYBRID BOOKMARKS CONTAINER -->
        <div id="guest-bookmarks-wrapper" class="space-y-6">
            <!-- Sync Cloud Banner -->
            <div id="guest-sync-banner" class="hidden bg-dark-900 border border-brand-500/30 rounded-2xl p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-xl">
                <div class="flex items-center gap-3.5">
                    <div class="w-11 h-11 rounded-xl bg-brand-500/20 text-brand-400 flex items-center justify-center shrink-0 text-xl shadow-md shadow-brand-500/10">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                    </div>
                    <div>
                        <h4 class="text-xs sm:text-sm font-bold text-white flex items-center gap-2">
                            <span>Saved to This Device</span>
                            <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase tracking-wider bg-brand-500/20 text-brand-300 border border-brand-500/30">Guest Mode</span>
                        </h4>
                        <p class="text-[11px] text-slate-400 mt-0.5">Your bookmarks are stored locally. Sign in or create a free account to sync across devices!</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <a href="<?= BASE_URL ?>login.php?redirect=bookmarks.php" 
                       class="px-4 py-2 rounded-xl bg-brand-600 hover:bg-brand-500 text-white font-bold text-xs shadow-md transition-all flex items-center gap-1.5">
                        <i class="fa-solid fa-right-to-bracket text-[10px]"></i> Sign In &amp; Sync
                    </a>
                    <a href="<?= BASE_URL ?>register.php" 
                       class="px-4 py-2 rounded-xl bg-dark-800 hover:bg-dark-750 text-slate-300 hover:text-white font-semibold text-xs border border-dark-700 transition-colors">
                        Sign Up
                    </a>
                </div>
            </div>

            <!-- Empty State -->
            <div id="guest-empty-state" class="bg-dark-900 border border-dark-800 rounded-3xl p-8 sm:p-14 text-center max-w-lg mx-auto space-y-6 shadow-2xl">
                <div class="w-20 h-20 rounded-3xl bg-brand-600/10 border border-brand-500/30 text-brand-400 flex items-center justify-center mx-auto text-3xl shadow-lg shadow-brand-600/20">
                    <i class="fa-regular fa-bookmark"></i>
                </div>
                <div class="space-y-2">
                    <h3 class="text-xl sm:text-2xl font-black text-white">No Bookmarks Saved Yet</h3>
                    <p class="text-xs sm:text-sm text-slate-400 leading-relaxed">
                        Discover any series in the catalog and click "Bookmark" to save your favorites right here on your phone or PC.
                    </p>
                </div>
                <div class="flex flex-col sm:flex-row items-center justify-center gap-3 pt-2">
                    <a href="<?= BASE_URL ?>" 
                       class="w-full sm:w-auto px-6 py-3 rounded-xl bg-gradient-to-r from-brand-600 to-indigo-600 hover:from-brand-500 hover:to-indigo-500 text-white font-bold text-xs sm:text-sm shadow-lg shadow-brand-600/30 transition-all flex items-center justify-center gap-2">
                        <i class="fa-solid fa-compass text-xs"></i> Explore Catalog
                    </a>
                    <a href="<?= BASE_URL ?>login.php?redirect=bookmarks.php" 
                       class="w-full sm:w-auto px-6 py-3 rounded-xl bg-dark-800 hover:bg-dark-700 border border-dark-700 text-slate-200 hover:text-white font-semibold text-xs sm:text-sm transition-colors flex items-center justify-center gap-2">
                        <i class="fa-solid fa-right-to-bracket text-xs"></i> Sign In to Sync
                    </a>
                </div>
            </div>

            <!-- Guest Bookmarks Grid -->
            <div id="guest-bookmarks-grid" class="hidden grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4 sm:gap-6"></div>
        </div>

    <?php elseif (empty($userBookmarks)): ?>
        <!-- EMPTY STATE CONTAINER -->
        <div id="bookmarks-empty" class="bg-dark-900 border border-dark-800 rounded-2xl p-12 text-center max-w-md mx-auto space-y-4 shadow-xl">
            <div class="w-16 h-16 rounded-full bg-dark-800 text-slate-500 flex items-center justify-center mx-auto text-2xl">
                <i class="fa-regular fa-bookmark"></i>
            </div>
            <h3 class="text-lg font-bold text-white">No Bookmarks Saved Yet</h3>
            <p class="text-xs text-slate-400">Discover any series in the catalog and click "Bookmark" to track your favorites here.</p>
            <a href="<?= BASE_URL ?>" class="inline-block px-5 py-2.5 bg-brand-600 hover:bg-brand-500 text-white rounded-xl text-xs font-bold transition-all shadow-md shadow-brand-600/20">
                Explore Catalog
            </a>
        </div>

    <?php else: ?>
        <!-- BOOKMARKS GRID -->
        <div id="bookmarks-grid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4 sm:gap-6">
            <?php foreach ($userBookmarks as $item): ?>
                <?php 
                    $seriesUrl = BASE_URL . "manhwa.php?id=" . urlencode($item['series_id']);
                ?>
                <div id="bm-card-<?= htmlspecialchars($item['series_id']) ?>" class="group flex flex-col bg-dark-900 rounded-xl overflow-hidden border border-dark-800 hover:border-brand-500/60 shadow-lg transition-all duration-300 relative">
                    <!-- Delete Bookmark Button -->
                    <button onclick="removeBookmark('<?= htmlspecialchars($item['series_id']) ?>', '<?= htmlspecialchars(addslashes($item['title'])) ?>')" 
                            title="Remove from library" 
                            class="absolute top-2 right-2 z-20 w-7 h-7 rounded-full bg-black/80 hover:bg-rose-600 text-white flex items-center justify-center text-xs transition-colors">
                        <i class="fa-solid fa-xmark"></i>
                    </button>

                    <!-- Poster -->
                    <a href="<?= $seriesUrl ?>" class="relative aspect-[2/3] overflow-hidden bg-dark-950 block">
                        <img src="<?= htmlspecialchars($item['cover_image']) ?>" 
                             alt="<?= htmlspecialchars($item['title']) ?>" 
                             referrerpolicy="no-referrer"
                             onerror="this.onerror=null; this.src='https://cdn.asurascans.com/asura-images/covers/nano-machine.e31bdb.webp';"
                             class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                        <div class="absolute bottom-2 left-2">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-black/80 text-amber-400 border border-white/10 flex items-center gap-1">
                                <i class="fa-solid fa-star text-[9px]"></i> <?= number_format(floatval($item['rating'] ?? 4.8), 1) ?>
                            </span>
                        </div>
                    </a>

                    <!-- Meta -->
                    <div class="p-3 flex-1 flex flex-col justify-between space-y-2">
                        <h3 class="font-bold text-sm text-slate-100 group-hover:text-brand-400 transition-colors line-clamp-1" title="<?= htmlspecialchars($item['title']) ?>">
                            <a href="<?= $seriesUrl ?>"><?= htmlspecialchars($item['title']) ?></a>
                        </h3>
                        <div class="pt-2 border-t border-dark-800">
                            <a href="<?= $seriesUrl ?>" 
                               class="w-full flex items-center justify-center py-1.5 rounded-lg bg-dark-850 hover:bg-brand-600 text-slate-300 hover:text-white transition-colors text-xs font-semibold">
                                Continue Reading
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
// Account removal
async function removeBookmark(seriesId, title) {
    if (!confirm('Remove "' + title + '" from your saved library?')) return;

    try {
        const res = await fetch('<?= BASE_URL ?>api/bookmark.php?action=toggle', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ series_id: seriesId, title: title })
        });
        const data = await res.json();
        if (data.success) {
            const card = document.getElementById('bm-card-' + seriesId);
            if (card) card.remove();
            const badge = document.getElementById('bookmark-badge');
            if (badge) {
                badge.textContent = data.count;
                if (data.count === 0) badge.classList.add('hidden');
            }
            if (data.count === 0) {
                location.reload();
            }
        }
    } catch (e) {
        alert('Failed to update bookmark.');
    }
}

document.getElementById('btn-clear-bookmarks')?.addEventListener('click', async () => {
    if (!confirm('Are you sure you want to remove all saved bookmarks from your account?')) return;
    try {
        const res = await fetch('<?= BASE_URL ?>api/bookmark.php?action=clear', { method: 'POST' });
        const data = await res.json();
        if (data.success) {
            location.reload();
        }
    } catch (e) {
        alert('Failed to clear bookmarks.');
    }
});

// Guest Local Bookmarks Renderer
function renderGuestBookmarksView() {
    const isUserLoggedIn = <?= json_encode($isLoggedIn) ?>;
    if (isUserLoggedIn) return;

    const list = typeof getLocalBookmarks === 'function' ? getLocalBookmarks() : [];
    const banner = document.getElementById('guest-sync-banner');
    const emptyState = document.getElementById('guest-empty-state');
    const grid = document.getElementById('guest-bookmarks-grid');
    const clearBtn = document.getElementById('btn-clear-local-bookmarks');

    if (!grid) return;

    if (list.length === 0) {
        if (banner) banner.classList.add('hidden');
        if (emptyState) emptyState.classList.remove('hidden');
        if (grid) grid.classList.add('hidden');
        if (clearBtn) clearBtn.classList.add('hidden');
        return;
    }

    if (banner) banner.classList.remove('hidden');
    if (emptyState) emptyState.classList.add('hidden');
    if (grid) grid.classList.remove('hidden');
    if (clearBtn) clearBtn.classList.remove('hidden');

    grid.innerHTML = '';
    list.forEach(item => {
        const sId = item.id || item.series_id;
        const sTitle = item.title || 'Unknown Series';
        const sCover = item.cover_image || item.cover || '';
        const sRating = Number(item.rating || 4.8).toFixed(1);
        const sUrl = '<?= BASE_URL ?>manhwa.php?id=' + encodeURIComponent(sId);

        const card = document.createElement('div');
        card.id = 'guest-card-' + sId;
        card.className = 'group flex flex-col bg-dark-900 rounded-xl overflow-hidden border border-dark-800 hover:border-brand-500/60 shadow-lg transition-all duration-300 relative';
        card.innerHTML = `
            <button onclick="removeGuestBookmark('${sId}', '${sTitle.replace(/'/g, "\\'")}')" 
                    title="Remove from library" 
                    class="absolute top-2 right-2 z-20 w-7 h-7 rounded-full bg-black/80 hover:bg-rose-600 text-white flex items-center justify-center text-xs transition-colors">
                <i class="fa-solid fa-xmark"></i>
            </button>
            <a href="${sUrl}" class="relative aspect-[2/3] overflow-hidden bg-dark-950 block">
                <img src="${sCover}" alt="${sTitle}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" onerror="this.src='https://placehold.co/400x600/18181b/ffffff?text=Cover'">
                <div class="absolute bottom-2 left-2">
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-black/80 text-amber-400 border border-white/10 flex items-center gap-1">
                        <i class="fa-solid fa-star text-[9px]"></i> ${sRating}
                    </span>
                </div>
            </a>
            <div class="p-3 flex-1 flex flex-col justify-between space-y-2">
                <h3 class="font-bold text-sm text-slate-100 group-hover:text-brand-400 transition-colors line-clamp-1" title="${sTitle}">
                    <a href="${sUrl}">${sTitle}</a>
                </h3>
                <div class="pt-2 border-t border-dark-800">
                    <a href="${sUrl}" class="w-full flex items-center justify-center py-1.5 rounded-lg bg-dark-850 hover:bg-brand-600 text-slate-300 hover:text-white transition-colors text-xs font-semibold">
                        Continue Reading
                    </a>
                </div>
            </div>
        `;
        grid.appendChild(card);
    });
}

function removeGuestBookmark(id, title) {
    if (!confirm('Remove "' + title + '" from your saved library?')) return;
    if (typeof toggleLocalBookmark === 'function') {
        const res = toggleLocalBookmark({ id: id });
        renderGuestBookmarksView();
        const badge = document.getElementById('bookmark-badge');
        if (badge) {
            badge.textContent = res.count;
            if (res.count === 0) badge.classList.add('hidden');
        }
    }
}

document.getElementById('btn-clear-local-bookmarks')?.addEventListener('click', () => {
    if (!confirm('Clear all bookmarks saved on this device?')) return;
    try {
        localStorage.removeItem('mf_bookmarks');
    } catch(e) {}
    renderGuestBookmarksView();
    const badge = document.getElementById('bookmark-badge');
    if (badge) badge.classList.add('hidden');
});

document.addEventListener('DOMContentLoaded', renderGuestBookmarksView);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
