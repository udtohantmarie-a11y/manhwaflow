<?php
// includes/footer.php - Corporate Business Footer
?>
    </main>

    <!-- Professional Business Footer -->
    <?php 
    $current_page = $current_page ?? basename($_SERVER['PHP_SELF'], '.php');
    if (empty($hide_main_footer) && $current_page !== 'reader'): 
    ?>
    <footer class="bg-dark-900 border-t border-dark-800 mt-20 pt-16 pb-12 text-slate-400 text-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-10 pb-12 border-b border-dark-800">
                
                <!-- Brand Overview -->
                <div class="md:col-span-2 space-y-4">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-brand-700 to-indigo-600 flex items-center justify-center text-white shadow-md shadow-brand-600/30 font-black text-sm tracking-tighter select-none border border-brand-400/30">
                            <span class="bg-gradient-to-br from-white via-slate-100 to-indigo-100 bg-clip-text text-transparent">MF</span>
                        </div>
                        <span class="text-xl font-extrabold text-white tracking-tight">Manhwa<span class="text-brand-500">Flow</span></span>
                    </div>
                    <p class="text-slate-400 text-xs sm:text-sm leading-relaxed max-w-sm">
                        ManhwaFlow is a premier digital reading platform delivering seamless high-definition webtoons and comic series worldwide. Optimized for continuous mobile and desktop vertical reading.
                    </p>
                    <div class="flex items-center gap-3 pt-2 text-slate-400 text-xs flex-wrap">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-dark-850 border border-dark-750 text-slate-300 font-medium">
                            <span class="w-2 h-2 rounded-full bg-emerald-400"></span> Global Content Network Active
                        </span>
                        <a href="https://www.facebook.com/profile.php?id=61594942004447" target="_blank" rel="noopener noreferrer"
                           class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-blue-500/10 hover:bg-blue-500/20 border border-blue-500/30 text-blue-400 hover:text-blue-300 font-semibold transition-all">
                            <i class="fa-brands fa-facebook-f text-xs"></i> Official Facebook Page
                        </a>
                    </div>
                </div>

                <!-- Column: Discover -->
                <div class="space-y-3">
                    <h4 class="text-xs font-bold text-white uppercase tracking-wider">Discover</h4>
                    <ul class="space-y-2 text-xs sm:text-sm">
                        <li><a href="<?= BASE_URL ?>" class="hover:text-brand-400 transition-colors">Latest Releases</a></li>
                        <li><a href="<?= BASE_URL ?>index.php#genres" class="hover:text-brand-400 transition-colors">Browse Genres</a></li>
                        <li><a href="<?= BASE_URL ?>bookmarks.php" class="hover:text-brand-400 transition-colors">My Library</a></li>
                        <li><a href="<?= BASE_URL ?>register.php" class="hover:text-brand-400 transition-colors">Create Account</a></li>
                    </ul>
                </div>

                <!-- Column: Company -->
                <div class="space-y-3">
                    <h4 class="text-xs font-bold text-white uppercase tracking-wider">Company</h4>
                    <ul class="space-y-2 text-xs sm:text-sm">
                        <li><a href="#" class="hover:text-brand-400 transition-colors">About Us</a></li>
                        <li><a href="#" class="hover:text-brand-400 transition-colors">Publishing Partners</a></li>
                        <li><a href="#" class="hover:text-brand-400 transition-colors">Careers</a></li>
                        <li><a href="#" class="hover:text-brand-400 transition-colors">Press &amp; Media</a></li>
                    </ul>
                </div>

                <!-- Column: Legal & Support -->
                <div class="space-y-3">
                    <h4 class="text-xs font-bold text-white uppercase tracking-wider">Legal &amp; Trust</h4>
                    <ul class="space-y-2 text-xs sm:text-sm">
                        <li><a href="#" class="hover:text-brand-400 transition-colors">Terms of Service</a></li>
                        <li><a href="#" class="hover:text-brand-400 transition-colors">Privacy Policy</a></li>
                        <li><a href="#" class="hover:text-brand-400 transition-colors">DMCA Notice</a></li>
                        <li><a href="#" class="hover:text-brand-400 transition-colors">Content Guidelines</a></li>
                    </ul>
                </div>

            </div>

            <!-- Bottom Copyright & Compliance -->
            <div class="pt-8 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-slate-400">
                <p>&copy; <?= date('Y') ?> ManhwaFlow Global Entertainment Inc. All rights reserved.</p>
                <div class="flex items-center gap-6 text-slate-400 text-xs">
                    <span class="hover:text-slate-300 transition-colors cursor-pointer">English (US)</span>
                    <span class="hover:text-slate-300 transition-colors cursor-pointer">Security</span>
                    <span class="hover:text-slate-300 transition-colors cursor-pointer">Support</span>
                </div>
            </div>
        </div>
    </footer>
    <?php endif; ?>

    <!-- Main JS -->
    <script src="<?= BASE_URL ?>assets/js/main.js"></script>

    <!-- PWA Service Worker & Install Support -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('<?= BASE_URL ?>sw.js')
                    .then((reg) => console.log('[PWA] Service Worker active:', reg.scope))
                    .catch(() => {});
            });
        }

        let deferredPrompt;
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            const installBtns = document.querySelectorAll('.btn-pwa-install');
            installBtns.forEach(btn => btn.classList.remove('hidden'));
        });

        async function triggerPWAInstall() {
            if (!deferredPrompt) return;
            deferredPrompt.prompt();
            const { outcome } = await deferredPrompt.userChoice;
            if (outcome === 'accepted') {
                const installBtns = document.querySelectorAll('.btn-pwa-install');
                installBtns.forEach(btn => btn.classList.add('hidden'));
            }
            deferredPrompt = null;
        }
    </script>
</body>
</html>
