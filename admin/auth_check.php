<?php
// admin/auth_check.php - Strict Admin Authentication Middleware
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/security.php';

startSecureSession();

// 1. Check if user is logged in
if (empty($_SESSION['user_id'])) {
    $redirect = urlencode($_SERVER['REQUEST_URI'] ?? (BASE_URL . 'admin/index.php'));
    header("Location: " . BASE_URL . "login.php?redirect=" . $redirect);
    exit;
}

// 2. Check if user has admin role in session
$userId = intval($_SESSION['user_id']);
$userRole = $_SESSION['role'] ?? '';

if ($userRole !== 'admin') {
    // Double check database in case role was recently updated
    $pdo = getPdo();
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $dbRole = $stmt->fetchColumn();

    if ($dbRole === 'admin') {
        $_SESSION['role'] = 'admin';
    } else {
        http_response_code(403);
        die("<!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <title>403 Forbidden - Access Denied</title>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <script src='https://cdn.tailwindcss.com'></script>
        </head>
        <body class='bg-[#0b0c10] text-slate-300 min-h-screen flex items-center justify-center p-4'>
            <div class='max-w-md w-full bg-[#12141a] border border-rose-500/30 rounded-2xl p-6 text-center shadow-2xl space-y-4'>
                <div class='w-14 h-14 rounded-2xl bg-rose-500/10 text-rose-400 flex items-center justify-center mx-auto text-2xl font-bold'>
                    🚫
                </div>
                <h1 class='text-xl font-bold text-white'>403 - Access Denied</h1>
                <p class='text-xs text-slate-400 leading-relaxed'>
                    You do not have permission to access the ManhwaFlow Admin Control Center. This area is reserved for administrators only.
                </p>
                <div class='pt-2 flex justify-center gap-3'>
                    <a href='" . BASE_URL . "' class='px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-white text-xs font-semibold'>
                        Return to Home
                    </a>
                    <a href='" . BASE_URL . "logout.php' class='px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white text-xs font-semibold'>
                        Sign Out
                    </a>
                </div>
            </div>
        </body>
        </html>");
    }
}
