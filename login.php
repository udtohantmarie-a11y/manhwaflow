<?php
// login.php - Secure User Login
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/security.php';

startSecureSession();
$pdo = getPdo();

// If already logged in, redirect home
if (isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL);
    exit;
}

$error = '';
$rateStatus = checkRateLimit('login', 5, 300);

if (isset($_GET['error']) && $_GET['error'] === 'unauthorized') {
    $error = 'You must sign in as Administrator to access that page.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$rateStatus['allowed']) {
        $error = $rateStatus['message'];
    } else {
        requireCsrf();
        $loginInput = trim($_POST['username_or_email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($loginInput) || empty($password)) {
            $error = 'Please fill in all fields.';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM `users` WHERE `username` = ? OR `email` = ?");
            $stmt->execute([$loginInput, $loginInput]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Success: Regenerate session ID to prevent session fixation attacks
                session_regenerate_id(true);
                clearRateLimit('login');

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['avatar'] = $user['avatar'];

                // Open redirect prevention
                $redirectParam = $_GET['redirect'] ?? '';
                $safeRedirect = sanitizeRedirectUrl($redirectParam, ($user['role'] === 'admin' ? BASE_URL . 'admin/index.php' : BASE_URL));
                
                header("Location: " . $safeRedirect);
                exit;
            } else {
                recordFailedAttempt('login', 5, 300);
                $error = 'Invalid username/email or password. Please try again.';
            }
        }
    }
}

$page_title = 'Sign In';
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-md mx-auto px-4 py-16 space-y-6">
    <div class="text-center space-y-2">
        <div class="w-12 h-12 rounded-2xl bg-brand-600/20 text-brand-400 flex items-center justify-center mx-auto text-xl shadow-lg shadow-brand-600/20">
            <i class="fa-solid fa-right-to-bracket"></i>
        </div>
        <h1 class="text-2xl sm:text-3xl font-black text-white">Sign In to ManhwaFlow</h1>
        <p class="text-xs text-slate-400">Access your saved bookmarks, reading history, and Flow Coins rewards.</p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-400 text-xs sm:text-sm flex items-center gap-2">
            <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="bg-dark-900 border border-dark-800 rounded-2xl p-6 sm:p-8 shadow-2xl space-y-5">
        <form action="" method="POST" class="space-y-4">
            <?= csrfField() ?>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5">
                    Username or Email
                </label>
                <div class="relative">
                    <i class="fa-solid fa-user absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
                    <input type="text" name="username_or_email" required placeholder="Enter your username or email" 
                           value="<?= isset($_POST['username_or_email']) ? htmlspecialchars($_POST['username_or_email']) : '' ?>"
                           class="w-full bg-dark-850 border border-dark-700 rounded-xl pl-9 pr-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-brand-500">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5">
                    Password
                </label>
                <div class="relative">
                    <i class="fa-solid fa-lock absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
                    <input type="password" name="password" required placeholder="••••••••" 
                           class="w-full bg-dark-850 border border-dark-700 rounded-xl pl-9 pr-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-brand-500">
                </div>
            </div>

            <button type="submit" 
                    class="w-full py-3 rounded-xl bg-gradient-to-r from-brand-600 to-indigo-600 hover:from-brand-500 hover:to-indigo-500 text-white font-bold text-sm shadow-lg shadow-brand-600/30 transition-all flex items-center justify-center gap-2">
                <i class="fa-solid fa-arrow-right-to-bracket text-xs"></i> Sign In
            </button>
        </form>

        <div class="pt-4 border-t border-dark-800 text-center text-xs text-slate-400">
            <p>
                Don't have an account? 
                <a href="<?= BASE_URL ?>register.php" class="text-brand-400 hover:text-brand-300 font-bold ml-1">Sign Up</a>
            </p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
