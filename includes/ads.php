<?php
// includes/ads.php - Clean & Non-Intrusive Monetization for ManhwaFlow

// Enable monetization with Monetag Direct Link
if (!defined('ADS_ENABLED')) {
    define('ADS_ENABLED', true);
}

// Your Monetag Direct Link
if (!defined('MONETAG_DIRECT_LINK')) {
    define('MONETAG_DIRECT_LINK', 'https://uplcm.com/4/11983803');
}

// Optional custom HTML/Script code from ad networks (e.g. Adsterra / Monetag Banner)
if (!defined('CUSTOM_BANNER_CODE')) {
    define('CUSTOM_BANNER_CODE', '');
}

/**
 * Render non-intrusive native sponsored card
 * @param string $placement - 'reader_bottom', 'manhwa_details', 'home_middle'
 */
function renderAdSlot($placement = 'reader_bottom') {
    if (!defined('ADS_ENABLED') || !ADS_ENABLED) {
        return;
    }
    $link = defined('MONETAG_DIRECT_LINK') ? MONETAG_DIRECT_LINK : '#';
    ?>
    <div class="ad-slot-wrapper my-8 max-w-2xl mx-auto px-4">
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-dark-900 via-dark-850 to-dark-900 border border-dark-750/90 hover:border-brand-500/60 transition-all p-4 sm:p-5 shadow-xl group">
            
            <!-- Subtle accent glow -->
            <div class="absolute -right-10 -top-10 w-28 h-28 bg-brand-600/10 rounded-full blur-2xl pointer-events-none"></div>

            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 relative z-10">
                <div class="flex items-center gap-3.5">
                    <div class="w-11 h-11 rounded-xl bg-gradient-to-tr from-brand-600 to-indigo-600 text-white flex items-center justify-center shrink-0 shadow-lg shadow-brand-600/25 group-hover:scale-105 transition-transform">
                        <i class="fa-solid fa-gift text-base"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] uppercase font-extrabold tracking-widest px-2 py-0.5 rounded bg-brand-500/20 text-brand-300 border border-brand-500/30">
                                Sponsored
                            </span>
                            <span class="text-[11px] text-slate-400">Special Partner Offer</span>
                        </div>
                        <h4 class="text-xs sm:text-sm font-bold text-white mt-1 group-hover:text-brand-300 transition-colors">
                            Trending Games, Webtoons &amp; Digital Rewards
                        </h4>
                    </div>
                </div>

                <a href="<?= htmlspecialchars($link) ?>" 
                   target="_blank" 
                   rel="noopener sponsored nofollow"
                   class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-gradient-to-r from-brand-600 to-indigo-600 hover:from-brand-500 hover:to-indigo-500 text-white font-bold text-xs shadow-lg shadow-brand-600/30 transition-all text-center flex items-center justify-center gap-2 shrink-0">
                    <span>Explore Offer</span>
                    <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                </a>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Render non-intrusive floating sticky bottom banner (Mobile & Desktop)
 */
function renderStickyBottomBanner() {
    if (!defined('ADS_ENABLED') || !ADS_ENABLED) {
        return;
    }
    $link = defined('MONETAG_DIRECT_LINK') ? MONETAG_DIRECT_LINK : '#';
    $customCode = defined('CUSTOM_BANNER_CODE') ? CUSTOM_BANNER_CODE : '';
    ?>
    <!-- Sticky Bottom Ad Banner (Non-Intrusive, No Popups) -->
    <div id="mf-sticky-bottom-banner" class="fixed bottom-3 left-1/2 -translate-x-1/2 w-[calc(100%-1.25rem)] sm:w-auto sm:min-w-[420px] max-w-lg z-40 transition-all duration-300 transform translate-y-0 pointer-events-auto">
        <div class="relative overflow-hidden rounded-2xl bg-dark-900/95 backdrop-blur-md border border-dark-750/90 hover:border-brand-500/50 p-2.5 sm:p-3 shadow-2xl flex items-center justify-between gap-3 text-slate-100">
            <!-- Subtle accent glow -->
            <div class="absolute -right-6 -bottom-6 w-20 h-20 bg-brand-500/15 rounded-full blur-xl pointer-events-none"></div>

            <?php if (!empty($customCode)): ?>
                <div class="flex-1 overflow-hidden flex justify-center items-center">
                    <?= $customCode ?>
                </div>
            <?php else: ?>
                <!-- Left: Partner / Offer Info -->
                <a href="<?= htmlspecialchars($link) ?>" target="_blank" rel="noopener sponsored nofollow" class="flex items-center gap-2.5 min-w-0 flex-1 group">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-brand-600 to-indigo-600 text-white flex items-center justify-center shrink-0 shadow-md shadow-brand-600/30 group-hover:scale-105 transition-transform">
                        <i class="fa-solid fa-fire text-amber-300 text-sm"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-1.5">
                            <span class="text-[9px] uppercase font-black px-1.5 py-0.5 rounded bg-amber-500/20 text-amber-300 border border-amber-500/30">
                                AD
                            </span>
                            <span class="text-[10px] text-slate-400 font-medium truncate">Sponsored Partner</span>
                        </div>
                        <p class="text-xs font-bold text-white group-hover:text-brand-300 transition-colors truncate">
                            Trending Games, Comics &amp; Rewards
                        </p>
                    </div>
                </a>

                <!-- Right: Action CTA -->
                <div class="flex items-center gap-2 shrink-0">
                    <a href="<?= htmlspecialchars($link) ?>" target="_blank" rel="noopener sponsored nofollow"
                       class="px-3.5 py-1.5 rounded-xl bg-gradient-to-r from-brand-600 to-indigo-600 hover:from-brand-500 hover:to-indigo-500 text-white font-bold text-[11px] shadow-md shadow-brand-600/30 transition-all flex items-center gap-1">
                        <span>Explore</span>
                        <i class="fa-solid fa-arrow-up-right-from-square text-[9px]"></i>
                    </a>
                </div>
            <?php endif; ?>

            <!-- Dismiss Button -->
            <button type="button" onclick="dismissStickyBottomBanner()" 
                    class="w-7 h-7 rounded-lg bg-dark-800/90 hover:bg-dark-700 text-slate-400 hover:text-white flex items-center justify-center text-xs transition-colors shrink-0"
                    title="Dismiss ad banner">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
    </div>

    <script>
    function dismissStickyBottomBanner() {
        const el = document.getElementById('mf-sticky-bottom-banner');
        if (el) {
            el.classList.add('translate-y-24', 'opacity-0');
            setTimeout(() => el.remove(), 350);
            try {
                sessionStorage.setItem('mf_sticky_banner_dismissed', '1');
            } catch(e) {}
        }
        const scrollControls = document.getElementById('floating-scroll-controls');
        if (scrollControls) {
            scrollControls.classList.remove('bottom-20', 'bottom-24');
            scrollControls.classList.add('bottom-6');
        }
    }
    (function() {
        try {
            if (sessionStorage.getItem('mf_sticky_banner_dismissed') === '1') {
                const el = document.getElementById('mf-sticky-bottom-banner');
                if (el) el.remove();
            } else {
                const scrollControls = document.getElementById('floating-scroll-controls');
                if (scrollControls) {
                    scrollControls.classList.remove('bottom-6');
                    scrollControls.classList.add('bottom-20');
                }
            }
        } catch(e) {}
    })();
    </script>
    <?php
}

/**
 * Render non-intrusive top banner ad
 */
function renderTopBannerAd() {
    if (!defined('ADS_ENABLED') || !ADS_ENABLED) {
        return;
    }
    $link = defined('MONETAG_DIRECT_LINK') ? MONETAG_DIRECT_LINK : '#';
    ?>
    <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 my-3">
        <div class="rounded-xl bg-dark-900/85 backdrop-blur-sm border border-dark-800 hover:border-brand-500/40 px-3 py-2 flex items-center justify-between gap-3 text-xs shadow-md transition-all">
            <div class="flex items-center gap-2 min-w-0">
                <span class="text-[9px] uppercase font-black px-1.5 py-0.5 rounded bg-brand-500/20 text-brand-300 border border-brand-500/30 shrink-0">AD</span>
                <span class="text-slate-300 font-semibold truncate">Partner Spotlight &bull; Exclusive Webtoons &amp; Digital Rewards</span>
            </div>
            <a href="<?= htmlspecialchars($link) ?>" target="_blank" rel="noopener sponsored nofollow"
               class="shrink-0 text-brand-400 hover:text-brand-300 font-bold text-xs flex items-center gap-1">
                <span>Explore Offer</span>
                <i class="fa-solid fa-arrow-right text-[10px]"></i>
            </a>
        </div>
    </div>
    <?php
}
