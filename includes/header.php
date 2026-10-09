<?php
// includes/header.php
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../config/db.php';
}
require_once __DIR__ . '/security.php';
startSecureSession();

$current_page = basename($_SERVER['PHP_SELF'], '.php');
$headerCoins = 0;
if (isset($_SESSION['user_id'])) {
    try {
        $pdoHeader = getPdo();
        $stmtCoins = $pdoHeader->prepare("SELECT `coins` FROM `user_rewards` WHERE `user_id` = ?");
        $stmtCoins->execute([$_SESSION['user_id']]);
        $headerCoins = intval($stmtCoins->fetchColumn() ?: 0);
    } catch(Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="referrer" content="no-referrer">
    <title><?= isset($page_title) ? htmlspecialchars($page_title) . ' - ' . (defined('SITE_NAME') ? SITE_NAME : 'ManhwaFlow') : (defined('SITE_NAME') ? SITE_NAME : 'ManhwaFlow') . ' - Read Webtoons & Digital Comics Online' ?></title>
    <!-- Tailwind CSS CDN -->
    <script>
        // Suppress Tailwind Play CDN development notice in console
        const _warn = console.warn;
        console.warn = (...args) => {
            if (typeof args[0] === 'string' && args[0].includes('cdn.tailwindcss.com')) return;
            _warn(...args);
        };
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        dark: {
                            950: '#090a0f',
                            900: '#0f111a',
                            850: '#151824',
                            800: '#1c2030',
                            700: '#2a3047',
                            600: '#404968'
                        },
                        brand: {
                            500: '#8b5cf6',
                            600: '#7c3aed',
                            700: '#6d28d9',
                            glow: '#a855f7'
                        }
                    },
                    fontFamily: {
                        sans: ['Outfit', 'Inter', 'system-ui', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- PWA Meta Tags & Manifest -->
    <meta name="theme-color" content="#7c3aed">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="ManhwaFlow">
    <link rel="manifest" href="<?= BASE_URL ?>manifest.json">
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>assets/icons/icon.svg">
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>assets/icons/icon-192.png">
    <!-- Custom Styles -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/custom.css">
</head>
<body class="bg-dark-950 text-slate-100 min-h-screen flex flex-col font-sans antialiased selection:bg-brand-600 selection:text-white">

    <!-- Top Navigation Bar -->
    <header class="sticky top-0 z-50 bg-dark-900/90 backdrop-blur-md border-b border-dark-800 transition-all duration-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16 gap-4">
                
                <!-- Brand / Logo -->
                <a href="<?= BASE_URL ?>" class="flex items-center gap-2.5 group">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-brand-700 via-brand-600 to-indigo-500 flex items-center justify-center shadow-lg shadow-brand-600/30 group-hover:scale-105 transition-transform font-black text-white text-base tracking-tighter select-none border border-brand-400/30">
                        <span class="bg-gradient-to-br from-white via-slate-100 to-indigo-100 bg-clip-text text-transparent">MF</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-xl font-extrabold tracking-tight bg-gradient-to-r from-white via-slate-100 to-brand-500 bg-clip-text text-transparent">
                            Manhwa<span class="text-brand-500">Flow</span>
                        </span>
                        <span class="text-[10px] text-slate-400 font-medium tracking-wider -mt-1 uppercase">Webtoon Portal</span>
                    </div>
                </a>

                <!-- Search Bar (Desktop) -->
                <div class="hidden md:flex flex-1 max-w-md mx-4 relative">
                    <form action="<?= BASE_URL ?>index.php" method="GET" class="w-full relative">
                        <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                        <input type="text" name="q" value="<?= isset($_GET['q']) ? htmlspecialchars($_GET['q']) : '' ?>" 
                               placeholder="Search manga, manhwa, authors, genres..." 
                               class="w-full bg-dark-850 border border-dark-700 rounded-full pl-10 pr-4 py-2 text-sm text-slate-100 placeholder-slate-400 focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 transition-all">
                    </form>
                </div>

                <!-- Nav Menu Links -->
                <nav class="hidden md:flex items-center gap-1.5 lg:gap-3">
                    <a href="<?= BASE_URL ?>" class="px-3.5 py-2 rounded-lg text-sm font-medium transition-colors <?= $current_page === 'index' ? 'bg-dark-800 text-brand-500 font-semibold' : 'text-slate-300 hover:text-white hover:bg-dark-800/60' ?>">
                        <i class="fa-solid fa-house mr-1.5 text-xs"></i>Home
                    </a>
                    
                    <a href="<?= BASE_URL ?>index.php#genres" class="px-3.5 py-2 rounded-lg text-sm font-medium text-slate-300 hover:text-white hover:bg-dark-800/60 transition-colors">
                        <i class="fa-solid fa-layer-group mr-1.5 text-xs"></i>Genres
                    </a>

                    <a href="<?= BASE_URL ?>bookmarks.php" class="px-3.5 py-2 rounded-lg text-sm font-medium transition-colors relative <?= $current_page === 'bookmarks' ? 'bg-dark-800 text-brand-500 font-semibold' : 'text-slate-300 hover:text-white hover:bg-dark-800/60' ?>">
                        <i class="fa-solid fa-bookmark mr-1.5 text-xs"></i>Bookmarks
                        <span id="bookmark-badge" class="hidden ml-1 px-1.5 py-0.2 bg-brand-600 text-[10px] text-white rounded-full font-bold">0</span>
                    </a>

                    <a href="<?= BASE_URL ?>history.php" class="px-3.5 py-2 rounded-lg text-sm font-medium transition-colors relative <?= $current_page === 'history' ? 'bg-dark-800 text-brand-500 font-semibold' : 'text-slate-300 hover:text-white hover:bg-dark-800/60' ?>">
                        <i class="fa-solid fa-clock-rotate-left mr-1.5 text-xs"></i>History
                    </a>

                    <a href="<?= BASE_URL ?>rewards.php" class="px-3.5 py-2 rounded-lg text-sm font-medium transition-colors relative flex items-center gap-1.5 <?= $current_page === 'rewards' ? 'bg-amber-500/20 text-amber-300 font-semibold border border-amber-500/30' : 'text-amber-400 hover:text-amber-300 hover:bg-dark-800/60' ?>">
                        <i class="fa-solid fa-coins text-xs animate-bounce"></i>
                        <span>Rewards</span>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-black bg-amber-500/20 text-amber-300 border border-amber-500/30">
                                <?= number_format($headerCoins) ?>
                            </span>
                        <?php endif; ?>
                    </a>

                    <!-- Live Notifications (for Bookmarked Series) -->
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <div class="relative" id="notifications-wrapper">
                            <button id="btn-notifications" 
                                    class="relative p-2 rounded-lg bg-dark-850 hover:bg-dark-800 text-slate-300 hover:text-white transition-colors"
                                    title="Updates for Bookmarked Series">
                                <i class="fa-solid fa-bell text-sm"></i>
                                <span id="notification-badge" class="hidden absolute -top-1 -right-1 w-4 h-4 bg-brand-600 text-[10px] text-white rounded-full flex items-center justify-center font-bold">0</span>
                            </button>

                            <!-- Notifications Dropdown -->
                            <div id="notifications-dropdown" class="hidden absolute right-0 mt-2 w-80 sm:w-96 bg-dark-900 border border-dark-750 rounded-2xl shadow-2xl z-50 overflow-hidden divide-y divide-dark-800">
                                <div class="p-3.5 flex items-center justify-between bg-dark-850/80">
                                    <div class="flex items-center gap-2">
                                        <i class="fa-solid fa-bell text-brand-400 text-xs"></i>
                                        <h4 class="text-xs font-bold text-white uppercase tracking-wider">Bookmarked Updates</h4>
                                    </div>
                                    <button id="btn-mark-all-read" class="text-[11px] text-brand-400 hover:text-brand-300 font-semibold transition-colors">
                                        Mark all read
                                    </button>
                                </div>
                                <div id="notifications-list" class="max-h-80 overflow-y-auto divide-y divide-dark-800/60">
                                    <div class="p-6 text-center text-xs text-slate-500">
                                        Checking for updates...
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- User Account / Auth Section -->
                    <div class="h-5 w-px bg-dark-750 mx-1"></div>

                    <?php if (isset($_SESSION['user_id'])): ?>
                        <div class="flex items-center gap-2">
                            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                                <a href="<?= BASE_URL ?>admin/index.php" 
                                   class="px-2.5 py-1.5 rounded-lg bg-rose-500/15 hover:bg-rose-500 text-rose-400 hover:text-white border border-rose-500/30 text-xs font-bold transition-all flex items-center gap-1.5 shadow-sm" title="Pumunta sa Admin Panel">
                                    <i class="fa-solid fa-shield-halved"></i> Admin
                                </a>
                            <?php endif; ?>
                            <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-dark-850 border border-dark-700 text-xs">
                                <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                                <span class="font-bold text-white"><?= htmlspecialchars($_SESSION['username']) ?></span>
                            </div>
                            <a href="<?= BASE_URL ?>logout.php" title="Sign out" 
                               class="p-2 rounded-lg bg-dark-850 hover:bg-rose-900/40 text-slate-400 hover:text-rose-400 border border-dark-750 transition-colors text-xs">
                                <i class="fa-solid fa-right-from-bracket"></i>
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="flex items-center gap-1.5">
                            <a href="<?= BASE_URL ?>login.php" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold text-slate-300 hover:text-white hover:bg-dark-800 transition-colors">
                                Log In
                            </a>
                            <a href="<?= BASE_URL ?>register.php" class="px-4 py-1.5 rounded-lg text-xs font-bold bg-brand-600 hover:bg-brand-500 text-white shadow-md shadow-brand-600/20 transition-all">
                                Sign Up
                            </a>
                        </div>
                    <?php endif; ?>
                </nav>

                <!-- Mobile Menu Button -->
                <div class="flex items-center gap-2 md:hidden">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <button id="btn-mobile-notifs" class="p-2 text-slate-300 hover:text-white relative">
                            <i class="fa-solid fa-bell text-base"></i>
                            <span id="mobile-notification-badge" class="hidden absolute top-1 right-1 w-2.5 h-2.5 bg-brand-500 rounded-full"></span>
                        </button>
                    <?php endif; ?>
                    <a href="<?= BASE_URL ?>rewards.php" class="p-2 text-amber-400 hover:text-amber-300" title="Rewards & GCash">
                        <i class="fa-solid fa-coins text-base"></i>
                    </a>
                    <a href="<?= BASE_URL ?>history.php" class="p-2 text-slate-300 hover:text-white" title="Reading History">
                        <i class="fa-solid fa-clock-rotate-left text-base"></i>
                    </a>
                    <a href="<?= BASE_URL ?>bookmarks.php" class="p-2 text-slate-300 hover:text-white" title="Bookmarks">
                        <i class="fa-solid fa-bookmark text-base"></i>
                    </a>
                    <button id="mobile-toggle" type="button" class="p-2 text-slate-300 hover:text-white focus:outline-none">
                        <i class="fa-solid fa-bars text-lg"></i>
                    </button>
                </div>
            </div>

            <!-- Mobile Search & Menu (Collapsible) -->
            <div id="mobile-menu" class="hidden md:hidden pb-4 pt-2 border-t border-dark-800">
                <form action="<?= BASE_URL ?>index.php" method="GET" class="w-full relative mb-3">
                    <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                    <input type="text" name="q" placeholder="Search title, genre, author..." 
                           class="w-full bg-dark-850 border border-dark-700 rounded-lg pl-10 pr-4 py-2 text-sm text-slate-100 placeholder-slate-400">
                </form>
                <div class="flex flex-col gap-1">
                    <a href="<?= BASE_URL ?>" class="px-3 py-2 rounded-md text-sm text-slate-200 hover:bg-dark-800">Home</a>
                    <a href="<?= BASE_URL ?>index.php#genres" class="px-3 py-2 rounded-md text-sm text-slate-200 hover:bg-dark-800">Genres</a>
                    <a href="<?= BASE_URL ?>rewards.php" class="px-3 py-2 rounded-md text-sm text-amber-400 hover:bg-dark-800 flex items-center justify-between">
                        <span class="flex items-center gap-2"><i class="fa-solid fa-coins text-xs"></i> Rewards &amp; GCash</span>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-amber-500/20 text-amber-300 border border-amber-500/30">
                                <?= number_format($headerCoins) ?> Coins
                            </span>
                        <?php endif; ?>
                    </a>
                    <a href="<?= BASE_URL ?>history.php" class="px-3 py-2 rounded-md text-sm text-slate-200 hover:bg-dark-800 flex items-center justify-between">
                        <span>Reading History</span>
                        <i class="fa-solid fa-clock-rotate-left text-xs text-slate-500"></i>
                    </a>
                    <a href="<?= BASE_URL ?>bookmarks.php" class="px-3 py-2 rounded-md text-sm text-slate-200 hover:bg-dark-800 flex items-center justify-between">
                        <span>Bookmarks</span>
                        <i class="fa-solid fa-bookmark text-xs text-slate-500"></i>
                    </a>
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                        <a href="<?= BASE_URL ?>admin/index.php" class="px-3 py-2 rounded-md text-sm text-rose-400 bg-rose-950/20 border border-rose-500/30 font-bold flex items-center justify-between">
                            <span class="flex items-center gap-2"><i class="fa-solid fa-shield-halved text-xs"></i> Admin Dashboard</span>
                            <span class="px-2 py-0.5 rounded text-[10px] bg-rose-500/20 text-rose-300 font-bold">Manage</span>
                        </a>
                    <?php endif; ?>

                    <!-- PWA Install Button -->
                    <button type="button" onclick="triggerPWAInstall()" class="btn-pwa-install hidden w-full text-left px-3 py-2 rounded-lg text-xs font-bold text-white bg-gradient-to-r from-brand-600 via-brand-500 to-indigo-600 shadow-md flex items-center justify-between my-1">
                        <span class="flex items-center gap-2"><i class="fa-solid fa-download"></i> Install App to Home Screen</span>
                        <span class="text-[10px] bg-white/20 px-1.5 py-0.5 rounded uppercase font-black">Install</span>
                    </button>
                    
                    <div class="border-t border-dark-800 my-1 pt-1">
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <div class="px-3 py-1.5 text-xs text-slate-400 flex items-center justify-between">
                                <span>Signed in as: <strong class="text-white"><?= htmlspecialchars($_SESSION['username']) ?></strong></span>
                                <a href="<?= BASE_URL ?>logout.php" class="text-rose-400 font-bold">Sign Out</a>
                            </div>
                        <?php else: ?>
                            <div class="grid grid-cols-2 gap-2 pt-1 px-1">
                                <a href="<?= BASE_URL ?>login.php" class="text-center py-2 rounded-lg bg-dark-800 text-xs font-semibold text-white">Log In</a>
                                <a href="<?= BASE_URL ?>register.php" class="text-center py-2 rounded-lg bg-brand-600 text-xs font-bold text-white">Sign Up</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="flex-grow">

