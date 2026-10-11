<?php
// forgot-password.php - User Password Recovery via Email Verification Code
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/mailer.php';
startSecureSession();

$pdo = getPdo();

// Ensure password_resets table exists
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `password_resets` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `email` VARCHAR(100) NOT NULL,
            `code` VARCHAR(10) NOT NULL,
            `expires_at` DATETIME NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_email` (`email`),
            INDEX `idx_code` (`code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} catch (Exception $e) {}

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL);
    exit;
}

$error = '';
$success = '';

// Determine current step
$currentStep = $_SESSION['reset_step'] ?? 'email';
if (isset($_GET['step']) && in_array($_GET['step'], ['email', 'verify', 'reset'])) {
    if ($_GET['step'] === 'email') {
        unset($_SESSION['reset_email'], $_SESSION['reset_step'], $_SESSION['reset_verified'], $_SESSION['reset_sent_at']);
        $currentStep = 'email';
    } elseif ($_GET['step'] === 'verify' && !empty($_SESSION['reset_email'])) {
        $currentStep = 'verify';
    } elseif ($_GET['step'] === 'reset' && !empty($_SESSION['reset_verified'])) {
        $currentStep = 'reset';
    }
}

// -------------------------------------------------------------
// ACTION HANDLING
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Verify CSRF
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security session expired. Please refresh the page and try again.';
    } else {

        // STEP 1: SEND VERIFICATION CODE
        if ($action === 'send_code' || $action === 'resend_code') {
            $email = trim($_POST['email'] ?? ($_SESSION['reset_email'] ?? ''));

            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email address.';
            } else {
                // Check if account exists
                $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if (!$user) {
                    $error = 'No account found with this email address. Please check your spelling or sign up.';
                } else {
                    // Check resend rate limit (minimum 45 seconds between sends)
                    $lastSent = $_SESSION['reset_sent_at'] ?? 0;
                    if ($action === 'resend_code' && (time() - $lastSent < 45)) {
                        $remaining = 45 - (time() - $lastSent);
                        $error = "Please wait {$remaining} seconds before requesting a new verification code.";
                    } else {
                        // Generate secure 6-digit code
                        $code = strval(random_int(100000, 999999));

                        // Remove older codes for this email
                        try {
                            $del = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
                            $del->execute([$email]);

                            // Insert new code with 15-minute expiration
                            $ins = $pdo->prepare("
                                INSERT INTO password_resets (email, code, expires_at) 
                                VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))
                            ");
                            $ins->execute([$email, $code]);
                        } catch (Exception $e) {}

                        // Send verification email via Gmail SMTP
                        $sendRes = Mailer::sendPasswordResetCode($user['email'], $code, $user['username']);

                        if ($sendRes['success']) {
                            $_SESSION['reset_email'] = $email;
                            $_SESSION['reset_username'] = $user['username'];
                            $_SESSION['reset_step'] = 'verify';
                            $_SESSION['reset_sent_at'] = time();
                            unset($_SESSION['reset_verified']);
                            
                            $currentStep = 'verify';
                            $success = "Verification code has been sent to <strong>" . htmlspecialchars($email) . "</strong>. Please check your inbox or spam folder.";
                        } else {
                            $error = 'Failed to send verification email. Please check your internet connection or try again later.';
                        }
                    }
                }
            }
        }

        // STEP 2: VERIFY CODE
        elseif ($action === 'verify_code') {
            $code = trim($_POST['code'] ?? '');
            $email = $_SESSION['reset_email'] ?? '';

            if (empty($email)) {
                $currentStep = 'email';
                $error = 'Your session expired. Please enter your email address again.';
            } elseif (empty($code) || strlen($code) < 6) {
                $error = 'Please enter the 6-digit verification code sent to your email.';
            } else {
                // Check code in DB
                $stmt = $pdo->prepare("
                    SELECT id FROM password_resets 
                    WHERE email = ? AND code = ? AND expires_at > NOW() 
                    ORDER BY id DESC LIMIT 1
                ");
                $stmt->execute([$email, $code]);
                $resetRow = $stmt->fetch();

                if ($resetRow) {
                    $_SESSION['reset_verified'] = true;
                    $_SESSION['reset_step'] = 'reset';
                    $currentStep = 'reset';
                    $success = 'Verification code accepted! You can now create your new password.';
                } else {
                    $error = 'Invalid or expired verification code. Please make sure you entered all 6 digits correctly or click Resend.';
                }
            }
        }

        // STEP 3: RESET NEW PASSWORD
        elseif ($action === 'change_password') {
            $email = $_SESSION['reset_email'] ?? '';
            $isVerified = !empty($_SESSION['reset_verified']);
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (!$isVerified || empty($email)) {
                $currentStep = 'email';
                $error = 'Verification expired. Please restart the password reset process.';
            } elseif (strlen($newPassword) < 6) {
                $error = 'Your new password must be at least 6 characters long.';
            } elseif ($newPassword !== $confirmPassword) {
                $error = 'The passwords do not match. Please re-enter both passwords.';
            } else {
                // Update password in database
                $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
                $upd = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
                $upd->execute([$hashed, $email]);

                // Cleanup reset codes
                try {
                    $del = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
                    $del->execute([$email]);
                } catch (Exception $e) {}

                // Clear reset session
                unset(
                    $_SESSION['reset_email'],
                    $_SESSION['reset_username'],
                    $_SESSION['reset_step'],
                    $_SESSION['reset_verified'],
                    $_SESSION['reset_sent_at']
                );

                // Redirect to login with success notice
                header("Location: " . BASE_URL . "login.php?reset=success");
                exit;
            }
        }
    }
}

// Mask email for display in Step 2 (e.g. sh***n@gmail.com)
$displayEmail = $_SESSION['reset_email'] ?? '';
$maskedEmail = '';
if (!empty($displayEmail)) {
    $parts = explode('@', $displayEmail);
    if (count($parts) === 2) {
        $name = $parts[0];
        $domain = $parts[1];
        if (strlen($name) <= 2) {
            $maskedName = substr($name, 0, 1) . '*';
        } else {
            $maskedName = substr($name, 0, 2) . str_repeat('*', max(3, strlen($name) - 3)) . substr($name, -1);
        }
        $maskedEmail = $maskedName . '@' . $domain;
    } else {
        $maskedEmail = $displayEmail;
    }
}

$page_title = 'Reset Password';
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-md mx-auto px-4 py-16 space-y-6">
    <!-- Header -->
    <div class="text-center space-y-2">
        <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-brand-600 to-indigo-600 text-white flex items-center justify-center mx-auto text-2xl shadow-xl shadow-brand-600/20">
            <i class="fa-solid fa-shield-halved"></i>
        </div>
        <h1 class="text-2xl sm:text-3xl font-black text-white">Reset Your Password</h1>
        <p class="text-xs text-slate-400">Follow the steps below to securely regain access to your account.</p>
    </div>

    <!-- Stepper Indicator -->
    <div class="flex items-center justify-between px-6 py-3 bg-dark-900 border border-dark-800 rounded-2xl text-xs font-bold">
        <div class="flex items-center gap-2 <?= $currentStep === 'email' ? 'text-brand-400' : 'text-slate-400' ?>">
            <span class="w-6 h-6 rounded-full flex items-center justify-center text-[11px] <?= $currentStep === 'email' ? 'bg-brand-500 text-white shadow-md shadow-brand-500/30' : ($currentStep !== 'email' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-dark-800 text-slate-500') ?>">
                <?= $currentStep !== 'email' ? '<i class="fa-solid fa-check text-[10px]"></i>' : '1' ?>
            </span>
            <span>Email</span>
        </div>
        <div class="h-0.5 w-8 bg-dark-800"></div>
        <div class="flex items-center gap-2 <?= $currentStep === 'verify' ? 'text-brand-400' : 'text-slate-400' ?>">
            <span class="w-6 h-6 rounded-full flex items-center justify-center text-[11px] <?= $currentStep === 'verify' ? 'bg-brand-500 text-white shadow-md shadow-brand-500/30' : ($currentStep === 'reset' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-dark-800 text-slate-500') ?>">
                <?= $currentStep === 'reset' ? '<i class="fa-solid fa-check text-[10px]"></i>' : '2' ?>
            </span>
            <span>Code</span>
        </div>
        <div class="h-0.5 w-8 bg-dark-800"></div>
        <div class="flex items-center gap-2 <?= $currentStep === 'reset' ? 'text-brand-400' : 'text-slate-400' ?>">
            <span class="w-6 h-6 rounded-full flex items-center justify-center text-[11px] <?= $currentStep === 'reset' ? 'bg-brand-500 text-white shadow-md shadow-brand-500/30' : 'bg-dark-800 text-slate-500' ?>">
                3
            </span>
            <span>New Password</span>
        </div>
    </div>

    <!-- Feedback Alerts -->
    <?php if (!empty($error)): ?>
        <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-400 text-xs sm:text-sm flex items-start gap-2.5 shadow-md">
            <i class="fa-solid fa-circle-exclamation mt-0.5 shrink-0"></i>
            <div><?= $error ?></div>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs sm:text-sm flex items-start gap-2.5 shadow-md">
            <i class="fa-solid fa-circle-check mt-0.5 shrink-0"></i>
            <div><?= $success ?></div>
        </div>
    <?php endif; ?>

    <!-- Main Card -->
    <div class="bg-dark-900 border border-dark-800 rounded-2xl p-6 sm:p-8 shadow-2xl space-y-5">

        <?php if ($currentStep === 'email'): ?>
            <!-- STEP 1: ENTER EMAIL -->
            <form action="" method="POST" class="space-y-4">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="send_code">

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5">
                        Account Email Address
                    </label>
                    <p class="text-[11px] text-slate-400 mb-2">
                        Enter the Gmail or registered email address associated with your ManhwaFlow account. We'll send you a 6-digit verification code.
                    </p>
                    <div class="relative">
                        <i class="fa-solid fa-envelope absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
                        <input type="email" 
                               name="email" 
                               required 
                               placeholder="your.email@gmail.com" 
                               value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
                               class="w-full bg-dark-850 border border-dark-700 rounded-xl pl-9 pr-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-brand-500">
                    </div>
                </div>

                <button type="submit" 
                        class="w-full py-3 rounded-xl bg-gradient-to-r from-brand-600 to-indigo-600 hover:from-brand-500 hover:to-indigo-500 text-white font-bold text-sm shadow-lg shadow-brand-600/30 transition-all flex items-center justify-center gap-2">
                    <i class="fa-solid fa-paper-plane text-xs"></i> Send Verification Code
                </button>
            </form>

        <?php elseif ($currentStep === 'verify'): ?>
            <!-- STEP 2: ENTER VERIFICATION CODE -->
            <form action="" method="POST" class="space-y-5">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="verify_code">

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5">
                        Enter 6-Digit Code
                    </label>
                    <p class="text-xs text-slate-400 mb-3">
                        A verification code was sent to <strong class="text-slate-200"><?= htmlspecialchars($maskedEmail) ?></strong>.
                    </p>
                    <div class="relative">
                        <input type="text" 
                               name="code" 
                               required 
                               maxlength="6"
                               pattern="\d{6}"
                               inputmode="numeric"
                               autocomplete="one-time-code"
                               placeholder="••••••" 
                               class="w-full bg-dark-850 border border-brand-500/50 rounded-xl py-3 text-center text-2xl font-mono font-bold tracking-[0.5em] text-brand-400 placeholder-slate-600 focus:outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500">
                    </div>
                    <p class="text-[11px] text-slate-500 text-center mt-2">
                        Code expires in 15 minutes. Check spam or junk if you don't see it.
                    </p>
                </div>

                <button type="submit" 
                        class="w-full py-3 rounded-xl bg-gradient-to-r from-brand-600 to-indigo-600 hover:from-brand-500 hover:to-indigo-500 text-white font-bold text-sm shadow-lg shadow-brand-600/30 transition-all flex items-center justify-center gap-2">
                    <i class="fa-solid fa-check text-xs"></i> Verify Code &amp; Continue
                </button>
            </form>

            <!-- Resend & Switch Email Actions -->
            <div class="pt-3 border-t border-dark-800 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
                <form action="" method="POST" id="form-resend" class="inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="resend_code">
                    <input type="hidden" name="email" value="<?= htmlspecialchars($displayEmail) ?>">
                    <button type="submit" 
                            id="btn-resend" 
                            class="text-brand-400 hover:text-brand-300 font-semibold transition-colors flex items-center gap-1.5">
                        <i class="fa-solid fa-rotate-right text-[11px]"></i> 
                        <span id="resend-text">Resend Code</span>
                    </button>
                </form>

                <a href="<?= BASE_URL ?>forgot-password.php?step=email" class="text-slate-400 hover:text-white transition-colors">
                    Use a different email
                </a>
            </div>

        <?php elseif ($currentStep === 'reset'): ?>
            <!-- STEP 3: CREATE NEW PASSWORD -->
            <form action="" method="POST" class="space-y-4">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="change_password">

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5">
                        New Password
                    </label>
                    <div class="relative">
                        <i class="fa-solid fa-lock absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
                        <input type="password" 
                               id="new_password"
                               name="new_password" 
                               required 
                               minlength="6"
                               placeholder="At least 6 characters" 
                               class="w-full bg-dark-850 border border-dark-700 rounded-xl pl-9 pr-10 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-brand-500">
                        <button type="button" 
                                onclick="togglePasswordVisibility('new_password', this)"
                                class="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-300 text-xs">
                            <i class="fa-regular fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5">
                        Confirm New Password
                    </label>
                    <div class="relative">
                        <i class="fa-solid fa-shield-check absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
                        <input type="password" 
                               id="confirm_password"
                               name="confirm_password" 
                               required 
                               minlength="6"
                               placeholder="Re-enter your password" 
                               class="w-full bg-dark-850 border border-dark-700 rounded-xl pl-9 pr-10 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-brand-500">
                        <button type="button" 
                                onclick="togglePasswordVisibility('confirm_password', this)"
                                class="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-300 text-xs">
                            <i class="fa-regular fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" 
                        class="w-full py-3 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-bold text-sm shadow-lg shadow-emerald-600/30 transition-all flex items-center justify-center gap-2">
                    <i class="fa-solid fa-key text-xs"></i> Save New Password
                </button>
            </form>
        <?php endif; ?>

        <!-- Back to Sign In Link -->
        <div class="pt-4 border-t border-dark-800 text-center text-xs text-slate-400">
            <a href="<?= BASE_URL ?>login.php" class="text-slate-300 hover:text-brand-400 font-semibold transition-colors flex items-center justify-center gap-1.5">
                <i class="fa-solid fa-arrow-left text-[10px]"></i> Back to Sign In
            </a>
        </div>
    </div>
</div>

<script>
function togglePasswordVisibility(fieldId, btnEl) {
    const input = document.getElementById(fieldId);
    if (!input) return;
    const isPassword = input.type === 'password';
    input.type = isPassword ? 'text' : 'password';
    const icon = btnEl.querySelector('i');
    if (icon) {
        icon.className = isPassword ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye';
    }
}

// Resend countdown handler
(function() {
    const btnResend = document.getElementById('btn-resend');
    const textResend = document.getElementById('resend-text');
    if (!btnResend || !textResend) return;

    const lastSentTime = <?= intval($_SESSION['reset_sent_at'] ?? 0) ?>;
    const cooldownDuration = 45; // seconds
    const now = Math.floor(Date.now() / 1000);
    const elapsed = now - lastSentTime;

    if (elapsed < cooldownDuration) {
        let remaining = cooldownDuration - elapsed;
        btnResend.disabled = true;
        btnResend.classList.add('opacity-50', 'cursor-not-allowed');

        const interval = setInterval(() => {
            remaining--;
            if (remaining <= 0) {
                clearInterval(interval);
                btnResend.disabled = false;
                btnResend.classList.remove('opacity-50', 'cursor-not-allowed');
                textResend.textContent = 'Resend Code';
            } else {
                textResend.textContent = `Resend Code (${remaining}s)`;
            }
        }, 1000);
    }
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
