<?php
// admin/payouts.php - GCash & Load Payout Management
require_once __DIR__ . '/auth_check.php';
$pdo = getPdo();

// Handle Approve / Reject actions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $payoutId = intval($_POST['payout_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($payoutId > 0) {
        $stmt = $pdo->prepare("SELECT * FROM `payout_requests` WHERE `id` = ?");
        $stmt->execute([$payoutId]);
        $req = $stmt->fetch();

        if ($req && $req['status'] === 'pending') {
            if ($action === 'approve') {
                $up = $pdo->prepare("UPDATE `payout_requests` SET `status` = 'approved' WHERE `id` = ?");
                $up->execute([$payoutId]);
                $message = "Payout #{$payoutId} marked as PAID ✅";
                $messageType = "emerald";
            } elseif ($action === 'reject') {
                // Refund coins back to user
                $pdo->beginTransaction();
                try {
                    $refund = $pdo->prepare("UPDATE `user_rewards` SET `coins` = `coins` + ? WHERE `user_id` = ?");
                    $refund->execute([$req['coins_deducted'], $req['user_id']]);

                    $up = $pdo->prepare("UPDATE `payout_requests` SET `status` = 'rejected', `admin_note` = ? WHERE `id` = ?");
                    $up->execute(['Rejected by admin (Coins refunded)', $payoutId]);

                    $log = $pdo->prepare("INSERT INTO `reward_logs` (`user_id`, `action_type`, `coins`, `description`) VALUES (?, 'payout_refund', ?, ?)");
                    $log->execute([$req['user_id'], $req['coins_deducted'], "Refunded ₱{$req['amount_php']} payout request"]);

                    $pdo->commit();
                    $message = "Payout #{$payoutId} has been REJECTED and {$req['coins_deducted']} coins refunded to user.";
                    $messageType = "rose";
                } catch(Exception $e) {
                    $pdo->rollBack();
                    $message = "Error: " . $e->getMessage();
                    $messageType = "rose";
                }
            }
        }
    }
}

// Fetch all requests
$filter = $_GET['status'] ?? 'all';
$query = "
    SELECT p.*, u.username, u.email 
    FROM `payout_requests` p 
    JOIN `users` u ON p.user_id = u.id 
";
if ($filter !== 'all') {
    $query .= " WHERE p.status = " . $pdo->quote($filter);
}
$query .= " ORDER BY p.id DESC";

$requests = $pdo->query($query)->fetchAll();

// Counts
$pendingCount = $pdo->query("SELECT COUNT(*) FROM `payout_requests` WHERE `status` = 'pending'")->fetchColumn();
$totalPaid = $pdo->query("SELECT SUM(amount_php) FROM `payout_requests` WHERE `status` = 'approved'")->fetchColumn() ?: 0;

$page_title = 'GCash & Load Payout Requests - Admin';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

    <!-- Top Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-dark-800">
        <div>
            <div class="flex items-center gap-2">
                <a href="<?= BASE_URL ?>admin/index.php" class="text-xs text-brand-400 hover:text-brand-300 font-bold">
                    &larr; Admin Dashboard
                </a>
            </div>
            <h1 class="text-2xl sm:text-3xl font-black text-white mt-1">
                GCash &amp; Load Payout Requests
            </h1>
            <p class="text-xs text-slate-400">Dito mo makikita ang mga nag-cashout na readers gamit ang kanilang naipong Flow Coins.</p>
        </div>

        <div class="flex items-center gap-3">
            <div class="bg-dark-900 border border-dark-800 rounded-xl px-4 py-2 text-center">
                <span class="text-[10px] text-slate-400 uppercase font-bold block">Pending Requests</span>
                <span class="text-xl font-black text-amber-400"><?= $pendingCount ?></span>
            </div>
            <div class="bg-dark-900 border border-dark-800 rounded-xl px-4 py-2 text-center">
                <span class="text-[10px] text-slate-400 uppercase font-bold block">Total Paid Out</span>
                <span class="text-xl font-black text-emerald-400">₱<?= number_format($totalPaid, 2) ?></span>
            </div>
        </div>
    </div>

    <?php if (!empty($message)): ?>
        <div class="p-4 rounded-xl bg-<?= $messageType ?>-500/10 border border-<?= $messageType ?>-500/30 text-<?= $messageType ?>-400 text-xs font-bold">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- Filter Pills -->
    <div class="flex items-center gap-2">
        <a href="<?= BASE_URL ?>admin/payouts.php?status=all" 
           class="px-3.5 py-1.5 rounded-full text-xs font-bold <?= $filter === 'all' ? 'bg-brand-600 text-white' : 'bg-dark-850 text-slate-400 hover:text-white' ?>">
            All (<?= count($requests) ?>)
        </a>
        <a href="<?= BASE_URL ?>admin/payouts.php?status=pending" 
           class="px-3.5 py-1.5 rounded-full text-xs font-bold <?= $filter === 'pending' ? 'bg-amber-600 text-white' : 'bg-dark-850 text-slate-400 hover:text-white' ?>">
            Pending Only (<?= $pendingCount ?>)
        </a>
        <a href="<?= BASE_URL ?>admin/payouts.php?status=approved" 
           class="px-3.5 py-1.5 rounded-full text-xs font-bold <?= $filter === 'approved' ? 'bg-emerald-600 text-white' : 'bg-dark-850 text-slate-400 hover:text-white' ?>">
            Paid
        </a>
    </div>

    <!-- Payout Requests Table -->
    <div class="bg-dark-900 border border-dark-800 rounded-2xl overflow-hidden shadow-2xl">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-dark-850 text-slate-400 border-b border-dark-800">
                    <tr>
                        <th class="p-3.5">ID</th>
                        <th class="p-3.5">User</th>
                        <th class="p-3.5">Amount</th>
                        <th class="p-3.5">Method</th>
                        <th class="p-3.5">Account Details</th>
                        <th class="p-3.5">Coins</th>
                        <th class="p-3.5">Status</th>
                        <th class="p-3.5">Date</th>
                        <th class="p-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-dark-800 text-slate-300">
                    <?php if (empty($requests)): ?>
                        <tr>
                            <td colspan="9" class="p-8 text-center text-slate-500">
                                Walang payout requests sa kasalukuyan.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($requests as $r): ?>
                        <tr class="hover:bg-dark-850/40 transition-colors">
                            <td class="p-3.5 font-mono text-slate-500">#<?= $r['id'] ?></td>
                            <td class="p-3.5">
                                <strong class="text-white block"><?= htmlspecialchars($r['username']) ?></strong>
                                <span class="text-[11px] text-slate-500"><?= htmlspecialchars($r['email']) ?></span>
                            </td>
                            <td class="p-3.5 font-black text-sm text-emerald-400">
                                ₱<?= number_format($r['amount_php'], 2) ?>
                            </td>
                            <td class="p-3.5 uppercase font-bold text-white">
                                <span class="px-2 py-0.5 rounded bg-dark-800 border border-dark-700">
                                    <?= htmlspecialchars($r['payout_method']) ?>
                                </span>
                            </td>
                            <td class="p-3.5">
                                <span class="text-white block font-semibold"><?= htmlspecialchars($r['account_name']) ?></span>
                                <span class="font-mono text-slate-400 select-all cursor-pointer bg-dark-950 px-2 py-0.5 rounded border border-dark-800" title="Click to copy">
                                    <?= htmlspecialchars($r['account_number']) ?>
                                </span>
                            </td>
                            <td class="p-3.5 font-mono text-amber-400">
                                -<?= number_format($r['coins_deducted']) ?>
                            </td>
                            <td class="p-3.5">
                                <?php if ($r['status'] === 'approved'): ?>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                        <i class="fa-solid fa-check mr-1"></i> Paid
                                    </span>
                                <?php elseif ($r['status'] === 'rejected'): ?>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-500/20 text-rose-300 border border-rose-500/30">
                                        <i class="fa-solid fa-xmark mr-1"></i> Rejected
                                    </span>
                                <?php else: ?>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30">
                                        <i class="fa-solid fa-clock mr-1"></i> Pending
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="p-3.5 text-slate-500">
                                <?= date('M j, Y g:i A', strtotime($r['created_at'])) ?>
                            </td>
                            <td class="p-3.5 text-right">
                                <?php if ($r['status'] === 'pending'): ?>
                                    <div class="flex items-center justify-end gap-2">
                                        <form method="POST" onsubmit="return confirm('Sigurado ka bang na-send mo na ang ₱<?= $r['amount_php'] ?> sa GCash ni <?= htmlspecialchars($r['account_name']) ?>?')">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="payout_id" value="<?= $r['id'] ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-[11px] shadow-sm transition-all">
                                                Mark Paid
                                            </button>
                                        </form>
                                        <form method="POST" onsubmit="return confirm('I-reject ang payout at ibalik ang coins sa user?')">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="payout_id" value="<?= $r['id'] ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button type="submit" class="px-2.5 py-1.5 rounded-lg bg-dark-800 hover:bg-rose-900/40 text-slate-400 hover:text-rose-400 font-semibold text-[11px] transition-all">
                                                Reject
                                            </button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <span class="text-slate-600 text-[11px]">Completed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

