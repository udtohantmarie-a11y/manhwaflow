// assets/js/main.js - Global App Scripts with Account-Based Bookmarks & Live Update Notifications

const APP_BASE = window.BASE_URL || (window.location.pathname.startsWith('/manhwa/') ? '/manhwa/' : '/');

document.addEventListener('DOMContentLoaded', () => {
    initMobileMenu();
    initBookmarkSystem();
    initNotificationSystem();
    initScrollToTop();
});

// 1. Mobile Menu Toggle
function initMobileMenu() {
    const toggleBtn = document.getElementById('mobile-toggle');
    const mobileMenu = document.getElementById('mobile-menu');
    if (toggleBtn && mobileMenu) {
        toggleBtn.addEventListener('click', () => {
            mobileMenu.classList.toggle('hidden');
        });
    }
}

// 2. Account-Based Bookmark Manager
function initBookmarkSystem() {
    const btn = document.getElementById('btn-toggle-bookmark');
    const badge = document.getElementById('bookmark-badge');

    // Helper to update badge
    const updateBadgeCount = (count) => {
        if (!badge) return;
        if (count > 0) {
            badge.textContent = count;
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }
    };

    // Helper to style button
    const setBtnUi = (isBookmarked) => {
        if (!btn) return;
        if (isBookmarked) {
            btn.classList.add('bg-brand-600', 'text-white', 'border-brand-500');
            btn.classList.remove('bg-dark-800', 'text-slate-200');
            btn.innerHTML = '<i class="fa-solid fa-bookmark mr-2 text-white"></i>Bookmarked';
        } else {
            btn.classList.remove('bg-brand-600', 'text-white', 'border-brand-500');
            btn.classList.add('bg-dark-800', 'text-slate-200');
            btn.innerHTML = '<i class="fa-regular fa-bookmark mr-2 text-brand-500"></i>Bookmark';
        }
    };

    // Check status if on manhwa details page
    if (btn) {
        const mId = btn.dataset.id;
        fetch(APP_BASE + 'api/bookmark.php?action=status&series_id=' + encodeURIComponent(mId))
            .then(res => res.json())
            .then(data => {
                setBtnUi(data.bookmarked);
                if (data.logged_in) {
                    updateBadgeCount(data.count);
                }
            })
            .catch(() => {});

        btn.addEventListener('click', async () => {
            const payload = {
                series_id: btn.dataset.id,
                title: btn.dataset.title,
                cover_image: btn.dataset.cover,
                rating: btn.dataset.rating,
                status: btn.dataset.status
            };

            btn.disabled = true;
            try {
                const res = await fetch(APP_BASE + 'api/bookmark.php?action=toggle', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();

                if (data.auth_required) {
                    showAuthRequiredModal(
                        'Sign In Required',
                        'Bookmarking is exclusive to registered members. Sign in or create a free account to bookmark this series and get live release alerts!'
                    );
                    return;
                }

                if (data.success) {
                    setBtnUi(data.bookmarked);
                    updateBadgeCount(data.count);
                    showToast(data.bookmarked ? 'Added to your Library' : 'Removed from Library', data.bookmarked ? 'fa-bookmark text-brand-400' : 'fa-check text-slate-400');
                }
            } catch (err) {
                alert('Could not update bookmark.');
            } finally {
                btn.disabled = false;
            }
        });
    } else {
        // Sync badge on other pages
        fetch(APP_BASE + 'api/bookmark.php?action=status')
            .then(res => res.json())
            .then(data => {
                if (data.logged_in) updateBadgeCount(data.count);
            })
            .catch(() => {});
    }
}

// 3. Live Notification System for Bookmarked Series Updates
function initNotificationSystem() {
    const btn = document.getElementById('btn-notifications');
    const dropdown = document.getElementById('notifications-dropdown');
    const badge = document.getElementById('notification-badge');
    const mobileBadge = document.getElementById('mobile-notification-badge');
    const list = document.getElementById('notifications-list');
    const markReadBtn = document.getElementById('btn-mark-all-read');
    const mobileNotifsBtn = document.getElementById('btn-mobile-notifs');

    if (!btn && !mobileNotifsBtn) return; // User not logged in

    let isDropdownOpen = false;

    // Toggle dropdown
    const toggleDropdown = () => {
        isDropdownOpen = !isDropdownOpen;
        if (dropdown) dropdown.classList.toggle('hidden', !isDropdownOpen);
        if (isDropdownOpen) {
            fetchNotifications(true);
        }
    };

    if (btn) btn.addEventListener('click', (e) => {
        e.stopPropagation();
        toggleDropdown();
    });

    if (mobileNotifsBtn) mobileNotifsBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        toggleDropdown();
    });

    // Close dropdown on outside click
    document.addEventListener('click', (e) => {
        if (dropdown && !dropdown.contains(e.target) && e.target !== btn && e.target !== mobileNotifsBtn) {
            dropdown.classList.add('hidden');
            isDropdownOpen = false;
        }
    });

    // Mark all read button
    if (markReadBtn) {
        markReadBtn.addEventListener('click', async () => {
            try {
                await fetch(APP_BASE + 'api/notifications.php?action=mark_read', { method: 'POST' });
                if (badge) badge.classList.add('hidden');
                if (mobileBadge) mobileBadge.classList.add('hidden');
                fetchNotifications(false);
            } catch (e) {}
        });
    }

    // Fetch notifications
    const fetchNotifications = async (isManualClick = false) => {
        try {
            const res = await fetch(APP_BASE + 'api/notifications.php?action=get');
            const data = await res.json();

            if (!data.logged_in) return;

            // Update badge counter
            const unread = data.unread_count || 0;
            if (badge) {
                if (unread > 0) {
                    badge.textContent = unread > 9 ? '9+' : unread;
                    badge.classList.remove('hidden');
                } else {
                    badge.classList.add('hidden');
                }
            }
            if (mobileBadge) {
                mobileBadge.classList.toggle('hidden', unread === 0);
            }

            // Check if we should pop a live toast for new updates
            if (!isManualClick && unread > 0 && !sessionStorage.getItem('manhwa_toast_shown')) {
                const latest = data.notifications[0];
                if (latest) {
                    showToast(`New Chapter: ${latest.title}`, 'fa-bell text-amber-400', latest.read_url);
                    sessionStorage.setItem('manhwa_toast_shown', '1');
                }
            }

            // Render list
            if (list) {
                if (!data.has_bookmarks) {
                    list.innerHTML = `
                        <div class="p-8 text-center text-xs text-slate-400 space-y-2">
                            <i class="fa-regular fa-bookmark text-2xl text-slate-500"></i>
                            <p class="font-semibold text-slate-300">No Bookmarks Yet</p>
                            <p class="text-[11px] text-slate-500">Bookmark series to receive real-time alerts whenever new chapters release!</p>
                        </div>
                    `;
                } else if (!data.notifications || data.notifications.length === 0) {
                    list.innerHTML = `
                        <div class="p-8 text-center text-xs text-slate-400 space-y-1">
                            <i class="fa-solid fa-circle-check text-2xl text-emerald-500/60 mb-1"></i>
                            <p class="font-semibold text-slate-300">All Caught Up!</p>
                            <p class="text-[11px] text-slate-500">No new chapters released for your bookmarked series right now.</p>
                        </div>
                    `;
                } else {
                    list.innerHTML = data.notifications.map(n => `
                        <a href="${n.read_url}" class="p-3.5 flex items-start gap-3 hover:bg-dark-800/80 transition-colors group block ${n.is_read == 0 ? 'bg-brand-950/20' : ''}">
                            <img src="${n.cover_image || APP_BASE + 'assets/img/placeholder.jpg'}" alt="${n.title}" class="w-10 h-14 object-cover rounded-md border border-dark-700 shrink-0">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between gap-1">
                                    <h5 class="text-xs font-bold text-white group-hover:text-brand-400 truncate transition-colors">${n.title}</h5>
                                    ${n.is_read == 0 ? '<span class="w-2 h-2 rounded-full bg-brand-500 shrink-0"></span>' : ''}
                                </div>
                                <p class="text-[11px] text-slate-300 mt-0.5 line-clamp-1">${n.message}</p>
                                <span class="text-[10px] text-slate-500 mt-1 block">${formatTimeAgo(n.created_at)}</span>
                            </div>
                        </a>
                    `).join('');
                }
            }
        } catch (e) {}
    };

    // Initial check
    fetchNotifications(false);

    // Poll every 60 seconds for live updates
    setInterval(() => fetchNotifications(false), 60000);
}

// Helper: Relative Time
function formatTimeAgo(dateStr) {
    if (!dateStr) return 'Recently';
    const date = new Date(dateStr);
    const sec = Math.floor((new Date() - date) / 1000);
    if (sec < 60) return 'Just now';
    const min = Math.floor(sec / 60);
    if (min < 60) return `${min}m ago`;
    const hrs = Math.floor(min / 60);
    if (hrs < 24) return `${hrs}h ago`;
    return `${Math.floor(hrs / 24)}d ago`;
}

// 4. Modal: Sign In Required
function showAuthRequiredModal(title, message) {
    const existing = document.getElementById('auth-modal');
    if (existing) existing.remove();

    const currentUrl = window.location.href;
    const modal = document.createElement('div');
    modal.id = 'auth-modal';
    modal.className = 'fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-fadeIn';
    modal.innerHTML = `
        <div class="bg-dark-900 border border-dark-750 rounded-2xl p-6 sm:p-8 max-w-sm w-full text-center space-y-5 shadow-2xl relative">
            <button onclick="document.getElementById('auth-modal').remove()" class="absolute top-4 right-4 text-slate-400 hover:text-white text-base">
                <i class="fa-solid fa-xmark"></i>
            </button>
            <div class="w-14 h-14 rounded-2xl bg-brand-600/10 border border-brand-500/30 text-brand-400 flex items-center justify-center mx-auto text-2xl shadow-lg shadow-brand-600/20">
                <i class="fa-solid fa-lock"></i>
            </div>
            <div class="space-y-1.5">
                <h3 class="text-lg font-bold text-white">${title}</h3>
                <p class="text-xs text-slate-400 leading-relaxed">${message}</p>
            </div>
            <div class="space-y-2 pt-2">
                <a href="${APP_BASE}login.php?redirect=${encodeURIComponent(currentUrl)}" 
                   class="w-full py-2.5 rounded-xl bg-gradient-to-r from-brand-600 to-indigo-600 hover:from-brand-500 hover:to-indigo-500 text-white font-bold text-xs shadow-md shadow-brand-600/20 transition-all flex items-center justify-center gap-2">
                     <i class="fa-solid fa-arrow-right-to-bracket text-xs"></i> Sign In
                </a>
                <a href="${APP_BASE}register.php" 
                   class="w-full py-2.5 rounded-xl bg-dark-800 hover:bg-dark-750 border border-dark-700 text-slate-300 hover:text-white font-semibold text-xs transition-colors flex items-center justify-center gap-2">
                    <i class="fa-solid fa-user-plus text-xs"></i> Create Account
                </a>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
}

// 5. Toast Notifications (Bottom Right Alert)
function showToast(message, iconClass = 'fa-check text-brand-400', clickUrl = null) {
    const existing = document.getElementById('live-toast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.id = 'live-toast';
    toast.className = 'fixed bottom-6 right-6 z-50 max-w-sm bg-dark-900/95 border border-dark-700 rounded-xl p-3.5 shadow-2xl backdrop-blur-md flex items-center gap-3 animate-slideUp cursor-pointer';
    toast.innerHTML = `
        <div class="w-8 h-8 rounded-lg bg-dark-800 border border-dark-700 flex items-center justify-center text-sm shrink-0">
            <i class="fa-solid ${iconClass}"></i>
        </div>
        <div class="flex-1 min-w-0">
            <p class="text-xs font-semibold text-white truncate">${message}</p>
        </div>
        <button onclick="event.stopPropagation(); this.parentElement.remove();" class="text-slate-500 hover:text-slate-300 text-xs pl-2">
            <i class="fa-solid fa-xmark"></i>
        </button>
    `;

    if (clickUrl) {
        toast.addEventListener('click', () => { window.location.href = clickUrl; });
    }

    document.body.appendChild(toast);
    setTimeout(() => {
        if (toast && toast.parentElement) toast.remove();
    }, 5500);
}

// 6. Scroll to top button if exists
function initScrollToTop() {
    const btn = document.getElementById('btn-scroll-top');
    if (!btn) return;

    window.addEventListener('scroll', () => {
        if (window.scrollY > 400) {
            btn.classList.remove('opacity-0', 'pointer-events-none');
        } else {
            btn.classList.add('opacity-0', 'pointer-events-none');
        }
    });

    btn.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
}
