<?php
// includes/ads.php - Clean & Non-Intrusive Monetization for ManhwaFlow

// Naka-enable na ang ads gamit ang Monetag Direct Link
if (!defined('ADS_ENABLED')) {
    define('ADS_ENABLED', true);
}

// Ang iyong Monetag Direct Link
if (!defined('MONETAG_DIRECT_LINK')) {
    define('MONETAG_DIRECT_LINK', 'https://uplcm.com/4/11983803');
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
