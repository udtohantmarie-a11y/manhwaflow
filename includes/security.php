<?php
// includes/security.php - Core Security Helper & Hardening for ManhwaFlow

// 1. Configure and start secure PHP session
function startSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        $cookieParams = [
            'lifetime' => 0, // Until browser closes
            'path' => '/',
            'domain' => '',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'), // true if HTTPS
            'httponly' => true, // Prevents JavaScript access (Anti-XSS cookie theft)
            'samesite' => 'Lax' // Prevents CSRF on cross-site navigations
        ];
        session_set_cookie_params($cookieParams);
        session_start();
    }
}

// 2. CSRF (Cross-Site Request Forgery) Protection
function getCsrfToken() {
    if (session_status() === PHP_SESSION_NONE) {
        startSecureSession();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField() {
    $token = htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

function verifyCsrfToken($token) {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function requireCsrf() {
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!verifyCsrfToken($token)) {
        http_response_code(403);
        if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Security token invalid (CSRF detected). Please refresh the page.']);
        } else {
            die("<div style='background:#18181b;color:#f87171;padding:24px;font-family:sans-serif;max-width:500px;margin:50px auto;border-radius:12px;border:1px solid #dc2626;'>
                    <h3 style='margin-top:0;'>⚠️ Security Error (CSRF Token Mismatch)</h3>
                    <p style='color:#e2e8f0;font-size:14px;'>Security token has expired or is invalid. Please refresh the page and try again.</p>
                    <a href='javascript:history.back()' style='display:inline-block;margin-top:10px;padding:8px 16px;background:#3b82f6;color:white;text-decoration:none;border-radius:6px;font-size:13px;'>Go Back</a>
                 </div>");
        }
        exit;
    }
}

// 3. Open Redirect Prevention (Safe Redirect Validator)
function sanitizeRedirectUrl($url, $default = BASE_URL) {
    if (empty($url) || !is_string($url)) {
        return $default;
    }
    // Disallow absolute external URLs (e.g. http://, https://, //evil.com)
    if (preg_match('#^https?://#i', $url) || strpos($url, '//') === 0 || strpos($url, '\\') !== false) {
        return $default;
    }
    // Must start with / or be relative
    if (strpos($url, '/') === 0 || strpos($url, 'index.php') === 0) {
        return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    }
    return $default;
}

// 4. Rate Limiting for Login / Sensitive Actions (Brute-Force Protection)
function checkRateLimit($actionKey, $maxAttempts = 5, $decaySeconds = 300) {
    if (session_status() === PHP_SESSION_NONE) {
        startSecureSession();
    }
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $bucketKey = "rate_limit_{$actionKey}_" . md5($ip);
    
    $now = time();
    $data = $_SESSION[$bucketKey] ?? ['attempts' => 0, 'first_attempt' => $now, 'locked_until' => 0];

    // Check if locked
    if ($data['locked_until'] > $now) {
        $remainingSeconds = $data['locked_until'] - $now;
        $minutes = ceil($remainingSeconds / 60);
        return [
            'allowed' => false,
            'message' => "Too many attempts (Rate limit exceeded). Please try again in {$minutes} minute(s)."
        ];
    }

    // Reset bucket if decay window has passed
    if ($now - $data['first_attempt'] > $decaySeconds) {
        $data = ['attempts' => 0, 'first_attempt' => $now, 'locked_until' => 0];
    }

    return ['allowed' => true, 'data' => $data, 'bucketKey' => $bucketKey];
}

function recordFailedAttempt($actionKey, $maxAttempts = 5, $decaySeconds = 300) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $bucketKey = "rate_limit_{$actionKey}_" . md5($ip);
    $now = time();

    $data = $_SESSION[$bucketKey] ?? ['attempts' => 0, 'first_attempt' => $now, 'locked_until' => 0];
    $data['attempts']++;

    if ($data['attempts'] >= $maxAttempts) {
        $data['locked_until'] = $now + $decaySeconds;
    }

    $_SESSION[$bucketKey] = $data;
}

function clearRateLimit($actionKey) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $bucketKey = "rate_limit_{$actionKey}_" . md5($ip);
    unset($_SESSION[$bucketKey]);
}

