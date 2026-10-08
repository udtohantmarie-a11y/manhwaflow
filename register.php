<?php
// register.php - User Registration
require_once __DIR__ . '/config/db.php';
$pdo = getPdo();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL);
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($username) || empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } elseif (strlen($username) < 3) {
        $error = 'Username must be at least 3 characters long.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        // Check if username or email already exists
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $checkStmt->execute([$username, $email]);
        if ($checkStmt->fetch()) {
            $error = 'An account with this username or email already exists.';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $insert = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'user')");
            $insert->execute([$username, $email, $hashed]);

            // Auto-login after registration
            $newId = $pdo->lastInsertId();
            $_SESSION['user_id'] = $newId;
            $_SESSION['username'] = $username;
            $_SESSION['email'] = $email;
            $_SESSION['role'] = 'user';
            $_SESSION['avatar'] = 'default.png';

            header("Location: " . BASE_URL . "?msg=welcome");
            exit;
        }
    }
}

$page_title = 'Create an Account';
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-md mx-auto px-4 py-12 space-y-6">
    <div class="text-center space-y-2">
        <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-brand-600 to-indigo-600 text-white flex items-center justify-center mx-auto text-xl shadow-lg shadow-brand-600/30">
            <i class="fa-solid fa-user-plus"></i>
        </div>
        <h1 class="text-2xl sm:text-3xl font-black text-white">Create Your Account</h1>
        <p class="text-xs text-slate-400">Join ManhwaFlow to save your reading list and sync preferences across devices.</p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-400 text-xs sm:text-sm flex items-center gap-2">
            <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="bg-dark-900 border border-dark-800 rounded-2xl p-6 sm:p-8 shadow-2xl space-y-5">
        <form action="" method="POST" class="space-y-4">
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5">
                    Username
                </label>
                <div class="relative">
                    <i class="fa-solid fa-user absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
                    <input type="text" name="username" required placeholder="e.g. shadow_monarch" 
                           value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>"
                           class="w-full bg-dark-850 border border-dark-700 rounded-xl pl-9 pr-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-brand-500">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5">
                    Email Address
                </label>
                <div class="relative">
                    <i class="fa-solid fa-envelope absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
                    <input type="email" name="email" required placeholder="your.email@example.com" 
                           value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
                           class="w-full bg-dark-850 border border-dark-700 rounded-xl pl-9 pr-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-brand-500">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5">
                    Password (at least 6 characters)
                </label>
                <div class="relative">
                    <i class="fa-solid fa-lock absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
                    <input type="password" name="password" required placeholder="••••••••" 
                           class="w-full bg-dark-850 border border-dark-700 rounded-xl pl-9 pr-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-brand-500">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5">
                    Confirm Password
                </label>
                <div class="relative">
                    <i class="fa-solid fa-check-double absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
                    <input type="password" name="confirm_password" required placeholder="••••••••" 
                           class="w-full bg-dark-850 border border-dark-700 rounded-xl pl-9 pr-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-brand-500">
                </div>
            </div>

            <button type="submit" 
                    class="w-full py-3 rounded-xl bg-gradient-to-r from-brand-600 to-indigo-600 hover:from-brand-500 hover:to-indigo-500 text-white font-bold text-sm shadow-lg shadow-brand-600/30 transition-all flex items-center justify-center gap-2">
                <i class="fa-solid fa-user-plus text-xs"></i> Create Account
            </button>
        </form>

        <div class="pt-4 border-t border-dark-800 text-center text-xs text-slate-400">
            Already have an account? 
            <a href="<?= BASE_URL ?>login.php" class="text-brand-400 hover:text-brand-300 font-bold ml-1">Sign In</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
