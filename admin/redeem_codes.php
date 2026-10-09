<?php
// admin/redeem_codes.php - Admin Monthly Redeem Codes Generator & Facebook Promo Manager
require_once __DIR__ . '/auth_check.php';
$pdo = getPdo();

$msg = '';
$msgType = '';

// 1. Handle Create Code
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_code') {
    requireCsrf();
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $coins = intval($_POST['coins'] ?? 100);
    $maxUses = intval($_POST['max_uses'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $expiresAt = !empty($_POST['expires_at']) ? date('Y-m-d 23:59:59', strtotime($_POST['expires_at'])) : null;

    if (empty($code)) {
        $msg = 'Please enter a valid code name.';
        $msgType = 'rose';
    } elseif ($coins <= 0) {
        $msg = 'Coin reward must be greater than 0.';
        $msgType = 'rose';
    } else {
        // Check uniqueness
        $chk = $pdo->prepare("SELECT id FROM `redeem_codes` WHERE `code` = ?");
        $chk->execute([$code]);
        if ($chk->fetch()) {
            $msg = "The code '{$code}' already exists! Choose another name or edit the existing one.";
            $msgType = 'rose';
        } else {
            $ins = $pdo->prepare("
                INSERT INTO `redeem_codes` (`code`, `coins`, `description`, `max_uses`, `expires_at`, `is_active`) 
                VALUES (?, ?, ?, ?, ?, 1)
            ");
            $ins->execute([$code, $coins, $description, $maxUses, $expiresAt]);
            $msg = "Redeem code '{$code}' created successfully! You can now copy the Facebook post template below.";
            $msgType = 'emerald';
        }
    }
}

// 2. Handle Toggle Active Status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_code') {
    requireCsrf();
    $id = intval($_POST['code_id'] ?? 0);
    $toggle = $pdo->prepare("UPDATE `redeem_codes` SET `is_active` = IF(`is_active` = 1, 0, 1) WHERE `id` = ?");
    $toggle->execute([$id]);
    $msg = 'Code status successfully updated.';
    $msgType = 'emerald';
}

// 3. Handle Delete Code
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_code') {
    requireCsrf();
    $id = intval($_POST['code_id'] ?? 0);
    $del = $pdo->prepare("DELETE FROM `redeem_codes` WHERE `id` = ?");
    $del->execute([$id]);
    $msg = 'Redeem code removed.';
    $msgType = 'amber';
}

// Fetch all codes
$codesStmt = $pdo->query("SELECT * FROM `redeem_codes` ORDER BY `id` DESC");
$codes = $codesStmt->fetchAll();

// Metrics
$totalActive = 0;
$totalClaims = 0;
$totalCoinsAwarded = 0;
foreach ($codes as $c) {
    if ($c['is_active']) $totalActive++;
    $totalClaims += intval($c['used_count']);
    $totalCoinsAwarded += (intval($c['used_count']) * intval($c['coins']));
}

$page_title = 'Manage Redeem Codes - Admin';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

    <!-- Top Navigation Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-dark-800">
        <div>
            <div class="flex items-center gap-2">
                <a href="<?= BASE_URL ?>admin/index.php" class="text-xs text-brand-400 hover:text-brand-300 font-bold flex items-center gap-1">
                    <i class="fa-solid fa-arrow-left"></i> Dashboard
                </a>
                <span class="text-slate-600">&bull;</span>
                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wider bg-blue-500/10 text-blue-400 border border-blue-500/20">
                    Facebook Codes Generator
                </span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-black text-white mt-1">
                Monthly Redeem Codes &amp; Social Giveaways
            </h1>
            <p class="text-xs text-slate-400">Generate redeem codes, set coin rewards, and copy ready-made Facebook announcement posts.</p>
        </div>

        <div class="flex items-center gap-2.5">
            <a href="<?= BASE_URL ?>rewards.php" target="_blank"
               class="px-3.5 py-2.5 rounded-xl bg-dark-850 hover:bg-dark-800 text-slate-300 hover:text-white border border-dark-750 font-bold text-xs transition-all flex items-center gap-1.5 shadow-sm">
                <i class="fa-solid fa-gift"></i> View User Rewards Hub
            </a>
            <a href="<?= BASE_URL ?>admin/payouts.php" 
               class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-bold text-xs shadow-md transition-all flex items-center gap-1.5">
                <i class="fa-solid fa-money-bill-wave"></i> GCash Payouts
            </a>
        </div>
    </div>

    <!-- Alert Message -->
    <?php if (!empty($msg)): ?>
        <div class="p-4 rounded-xl bg-<?= $msgType ?>-500/10 border border-<?= $msgType ?>-500/30 text-<?= $msgType ?>-400 text-xs font-bold flex items-center gap-2 shadow-lg animate-fadeIn">
            <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($msg) ?>
        </div>
    <?php endif; ?>

    <!-- Summary Metrics -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-dark-900 border border-dark-800 rounded-2xl p-5 space-y-1 shadow-lg">
            <span class="text-[11px] uppercase font-bold text-slate-400 tracking-wider">Active Redeem Codes</span>
            <div class="text-2xl sm:text-3xl font-black text-white"><?= number_format($totalActive) ?></div>
            <div class="text-[11px] text-blue-400 font-semibold">Available for users to claim</div>
        </div>

        <div class="bg-dark-900 border border-dark-800 rounded-2xl p-5 space-y-1 shadow-lg">
            <span class="text-[11px] uppercase font-bold text-slate-400 tracking-wider">Total Claims by Readers</span>
            <div class="text-2xl sm:text-3xl font-black text-white"><?= number_format($totalClaims) ?></div>
            <div class="text-[11px] text-emerald-400 font-semibold">Unique user code redemptions</div>
        </div>

        <div class="bg-dark-900 border border-dark-800 rounded-2xl p-5 space-y-1 shadow-lg">
            <span class="text-[11px] uppercase font-bold text-slate-400 tracking-wider">Total Bonus Coins Distributed</span>
            <div class="text-2xl sm:text-3xl font-black text-amber-400"><?= number_format($totalCoinsAwarded) ?></div>
            <div class="text-[11px] text-slate-400 font-medium">≈ ₱<?= number_format($totalCoinsAwarded / 200, 2) ?> reader rewards value</div>
        </div>
    </div>

    <!-- Generate New Code Card -->
    <div class="bg-dark-900 border border-brand-500/30 rounded-3xl p-6 sm:p-8 space-y-6 shadow-2xl relative overflow-hidden">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-4 border-b border-dark-800">
            <div>
                <h2 class="text-lg sm:text-xl font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-wand-magic-sparkles text-brand-400"></i> Generate New Monthly Redeem Code
                </h2>
                <p class="text-xs text-slate-400">Create a new gift voucher to post on your official Facebook page.</p>
            </div>
            <button type="button" onclick="autoGenerateCode()" 
                    class="text-xs font-bold px-3 py-1.5 rounded-lg bg-brand-500/10 hover:bg-brand-500/20 text-brand-300 border border-brand-500/30 transition-all self-start sm:self-auto flex items-center gap-1.5">
                <i class="fa-solid fa-shuffle"></i> Random Generator
            </button>
        </div>

        <form method="POST" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <input type="hidden" name="action" value="create_code">
            <?= csrfField() ?>

            <!-- Code String -->
            <div>
                <label class="block text-xs font-bold text-slate-300 mb-1.5">Redeem Code</label>
                <input type="text" name="code" id="gen-code" required placeholder="e.g. FLOWOCT2026"
                       class="w-full bg-dark-850 border border-dark-700 focus:border-brand-500 rounded-xl px-4 py-2.5 text-xs text-white font-mono uppercase tracking-wider focus:outline-none">
            </div>

            <!-- Reward Coins -->
            <div>
                <label class="block text-xs font-bold text-slate-300 mb-1.5">Coin Reward</label>
                <input type="number" name="coins" id="gen-coins" required min="10" step="10" value="150"
                       class="w-full bg-dark-850 border border-dark-700 focus:border-brand-500 rounded-xl px-4 py-2.5 text-xs text-white font-mono focus:outline-none">
            </div>

            <!-- Max Uses Limit -->
            <div>
                <label class="block text-xs font-bold text-slate-300 mb-1.5">Max Uses (0 = Unlimited)</label>
                <input type="number" name="max_uses" value="0" min="0"
                       class="w-full bg-dark-850 border border-dark-700 focus:border-brand-500 rounded-xl px-4 py-2.5 text-xs text-white font-mono focus:outline-none">
            </div>

            <!-- Expiration Date -->
            <div>
                <label class="block text-xs font-bold text-slate-300 mb-1.5">Expires On (Optional)</label>
                <input type="date" name="expires_at"
                       class="w-full bg-dark-850 border border-dark-700 focus:border-brand-500 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none">
            </div>

            <!-- Campaign Description -->
            <div class="sm:col-span-2 lg:col-span-3">
                <label class="block text-xs font-bold text-slate-300 mb-1.5">Description / Note</label>
                <input type="text" name="description" id="gen-desc" placeholder="e.g. October Monthly Facebook Community Code"
                       class="w-full bg-dark-850 border border-dark-700 focus:border-brand-500 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none">
            </div>

            <!-- Submit Button -->
            <div class="sm:col-span-2 lg:col-span-1 flex items-end">
                <button type="submit" 
                        class="w-full py-2.5 rounded-xl bg-gradient-to-r from-brand-600 to-indigo-600 hover:from-brand-500 hover:to-indigo-500 text-white font-bold text-xs shadow-lg shadow-brand-600/30 transition-all flex items-center justify-center gap-2">
                    <i class="fa-solid fa-plus"></i>
                    <span>Create &amp; Publish Code</span>
                </button>
            </div>
        </form>
    </div>

    <!-- Active Codes List Table -->
    <div class="bg-dark-900 border border-dark-800 rounded-2xl overflow-hidden shadow-xl space-y-4 p-5 sm:p-6">
        <div class="flex items-center justify-between pb-3 border-b border-dark-800">
            <div>
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-list-check text-blue-400"></i> Active &amp; Existing Redeem Codes
                </h3>
                <p class="text-xs text-slate-400">Click the Facebook button on any code to copy an instant social media post announcement.</p>
            </div>
            <span class="text-xs font-bold text-slate-400"><?= count($codes) ?> Codes Total</span>
        </div>

        <?php if (empty($codes)): ?>
            <div class="py-12 text-center text-slate-500 text-xs">
                No redeem codes have been generated yet. Use the form above to create your first monthly code!
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-300">
                    <thead class="bg-dark-850/80 uppercase text-[10px] text-slate-400 tracking-wider">
                        <tr>
                            <th class="py-3 px-4">Code</th>
                            <th class="py-3 px-4">Coins Reward</th>
                            <th class="py-3 px-4">Claims</th>
                            <th class="py-3 px-4">Expires</th>
                            <th class="py-3 px-4">Description</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-dark-800">
                        <?php foreach ($codes as $c): ?>
                            <?php 
                                $isExpired = (!empty($c['expires_at']) && strtotime($c['expires_at']) < time());
                                $isFull = (intval($c['max_uses']) > 0 && intval($c['used_count']) >= intval($c['max_uses']));
                            ?>
                            <tr class="hover:bg-dark-850/40 transition-colors">
                                <td class="py-3 px-4">
                                    <div class="font-mono font-black text-sm text-brand-300 flex items-center gap-2">
                                        <span><?= htmlspecialchars($c['code']) ?></span>
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="inline-flex items-center gap-1 font-bold text-amber-400">
                                        <i class="fa-solid fa-coins text-[10px]"></i> +<?= number_format($c['coins']) ?> Coins
                                    </span>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="font-semibold text-white">
                                        <?= number_format($c['used_count']) ?>
                                    </span>
                                    <span class="text-slate-500">
                                        / <?= intval($c['max_uses']) > 0 ? number_format($c['max_uses']) : 'Unlimited' ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-slate-400">
                                    <?= !empty($c['expires_at']) ? date('M d, Y', strtotime($c['expires_at'])) : '<span class="text-slate-600">Never</span>' ?>
                                </td>
                                <td class="py-3 px-4 text-slate-400 max-w-xs truncate" title="<?= htmlspecialchars($c['description'] ?? '') ?>">
                                    <?= htmlspecialchars($c['description'] ?: '—') ?>
                                </td>
                                <td class="py-3 px-4">
                                    <?php if ($isExpired): ?>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-500/10 text-rose-400 border border-rose-500/20">Expired</span>
                                    <?php elseif ($isFull): ?>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20">Full Limit</span>
                                    <?php elseif ($c['is_active']): ?>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">Active</span>
                                    <?php else: ?>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-500/10 text-slate-400 border border-slate-500/20">Paused</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <!-- Copy Facebook Post Template Button -->
                                        <button type="button" 
                                                onclick="copyFbTemplate('<?= htmlspecialchars(addslashes($c['code'])) ?>', <?= $c['coins'] ?>)"
                                                title="Copy Facebook post announcement"
                                                class="px-2.5 py-1.5 rounded-lg bg-[#1877F2]/20 hover:bg-[#1877F2] text-[#4da2ff] hover:text-white font-bold text-[11px] transition-all flex items-center gap-1 border border-blue-500/30">
                                            <i class="fa-brands fa-facebook-f text-[10px]"></i>
                                            <span>Copy FB Post</span>
                                        </button>

                                        <!-- Toggle Status -->
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="action" value="toggle_code">
                                            <input type="hidden" name="code_id" value="<?= $c['id'] ?>">
                                            <?= csrfField() ?>
                                            <button type="submit" 
                                                    title="<?= $c['is_active'] ? 'Pause code' : 'Activate code' ?>"
                                                    class="p-1.5 rounded-lg bg-dark-800 hover:bg-dark-750 text-slate-300 hover:text-white transition-colors">
                                                <i class="fa-solid <?= $c['is_active'] ? 'fa-pause' : 'fa-play' ?>"></i>
                                            </button>
                                        </form>

                                        <!-- Delete -->
                                        <form method="POST" class="inline" onsubmit="return confirm('Delete code <?= htmlspecialchars(addslashes($c['code'])) ?>?');">
                                            <input type="hidden" name="action" value="delete_code">
                                            <input type="hidden" name="code_id" value="<?= $c['id'] ?>">
                                            <?= csrfField() ?>
                                            <button type="submit" title="Delete code"
                                                    class="p-1.5 rounded-lg bg-rose-500/10 hover:bg-rose-500 text-rose-400 hover:text-white transition-colors">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- Facebook Toast Modal / Notification -->
<div id="copy-toast" class="fixed bottom-6 right-6 z-50 bg-[#1877F2] text-white px-5 py-3.5 rounded-2xl shadow-2xl font-bold text-xs flex items-center gap-3 hidden animate-fadeIn">
    <i class="fa-brands fa-facebook text-lg"></i>
    <span>Facebook post template copied to clipboard! Paste it on your Facebook page.</span>
</div>

<script>
function autoGenerateCode() {
    const months = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'];
    const currentMonth = months[new Date().getMonth()];
    const randNum = Math.floor(1000 + Math.random() * 9000);
    const generated = `FLOW${currentMonth}${randNum}`;
    
    document.getElementById('gen-code').value = generated;
    document.getElementById('gen-coins').value = 200;
    document.getElementById('gen-desc').value = `${currentMonth} Monthly Community Giveaway`;
}

function copyFbTemplate(code, coins) {
    const siteUrl = '<?= BASE_URL ?>rewards.php';
    const text = `🎁 MANHWAFLOW EXCLUSIVE MONTHLY REWARD CODE! 🎁\n\nUse Code: ${code}\nReceive: +${coins} FREE Flow Coins directly into your account balance!\n\n👉 Redeem your coins here: ${window.location.origin}${siteUrl}\n\nRead your favorite webtoons, earn daily coins, and cash out via GCash or Maya! Like and Follow our page for next month's secret code! 🔥`;

    navigator.clipboard.writeText(text).then(() => {
        const toast = document.getElementById('copy-toast');
        if (toast) {
            toast.classList.remove('hidden');
            setTimeout(() => toast.classList.add('hidden'), 4000);
        }
    }).catch(() => {
        prompt('Copy your Facebook Post text below:', text);
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
