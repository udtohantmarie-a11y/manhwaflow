<?php
// includes/security.php - Core Security Helper & Hardening for ManhwaFlow

if (!defined('AUTH_SECRET_KEY')) {
    define('AUTH_SECRET_KEY', 'manhwaflow_secure_auth_secret_key_2026_x89q2');
}

// 1. Configure and start secure long-lived PHP session (Persistent Login until user logs out)
function startSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        $lifetime = 315360000; // 10 years (Permanent session)
        @ini_set('session.cookie_lifetime', $lifetime);
        @ini_set('session.gc_maxlifetime', $lifetime);

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        $cookieParams = [
            'lifetime' => $lifetime,
            'path' => '/',
            'domain' => '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax'
        ];
        session_set_cookie_params($cookieParams);
        session_start();
    }

    // Auto-restore login if server session expired or browser restarted
    if (empty($_SESSION['user_id'])) {
        restoreUserSessionFromToken();
    } elseif (empty($_COOKIE['mf_remember_token'])) {
        // Upgrade existing session to persistent token
        upgradeActiveSessionToPersistentToken();
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

function validateCsrfToken($token) {
    return verifyCsrfToken($token);
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

// 5. Persistent Authentication & Remember-Me Engine (Never Log Out until explicit user logout)

/**
 * Issue a long-lived persistent authentication token
 */
function issuePersistentLoginToken($user) {
    if (empty($user['id'])) return;

    $userId = intval($user['id']);
    $rawToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);
    $expiresAt = date('Y-m-d H:i:s', time() + 315360000); // 10 years

    $authSecret = defined('AUTH_SECRET_KEY') ? AUTH_SECRET_KEY : 'manhwaflow_secure_auth_secret_key_2026_x89q2';
    $passwordComponent = !empty($user['password']) ? $user['password'] : 'default_pwd';
    $signature = hash_hmac('sha256', "{$userId}:{$rawToken}:{$passwordComponent}", $authSecret);

    $cookiePayload = "{$userId}:{$rawToken}:{$signature}";
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    setcookie('mf_remember_token', $cookiePayload, [
        'expires' => time() + 315360000,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    $_COOKIE['mf_remember_token'] = $cookiePayload;

    try {
        if (!function_exists('getPdo')) {
            $dbFile = dirname(__DIR__) . '/config/db.php';
            if (file_exists($dbFile)) require_once $dbFile;
        }
        if (function_exists('getPdo')) {
            $pdo = getPdo();
            if ($pdo) {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS `user_tokens` (
                        `id` INT AUTO_INCREMENT PRIMARY KEY,
                        `user_id` INT NOT NULL,
                        `token_hash` VARCHAR(64) NOT NULL UNIQUE,
                        `expires_at` DATETIME NOT NULL,
                        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX `idx_user_tokens` (`user_id`, `token_hash`),
                        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                ");

                $stmt = $pdo->prepare("INSERT INTO `user_tokens` (`user_id`, `token_hash`, `expires_at`) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE `expires_at` = VALUES(`expires_at`)");
                $stmt->execute([$userId, $tokenHash, $expiresAt]);
            }
        }
    } catch (Exception $e) {}
}

/**
 * Restore user session from persistent token if session expired or browser restarted
 */
function restoreUserSessionFromToken() {
    if (!empty($_SESSION['user_id'])) {
        return;
    }

    if (empty($_COOKIE['mf_remember_token'])) {
        return;
    }

    $cookieValue = $_COOKIE['mf_remember_token'];
    $parts = explode(':', $cookieValue);
    if (count($parts) < 3) {
        return;
    }

    $userId = intval($parts[0]);
    $rawToken = $parts[1];
    $providedSignature = $parts[2];

    if ($userId <= 0 || empty($rawToken) || empty($providedSignature)) {
        return;
    }

    try {
        if (!function_exists('getPdo')) {
            $dbFile = dirname(__DIR__) . '/config/db.php';
            if (file_exists($dbFile)) require_once $dbFile;
        }

        if (!function_exists('getPdo')) return;
        $pdo = getPdo();
        if (!$pdo) return;

        $stmt = $pdo->prepare("SELECT * FROM `users` WHERE `id` = ? LIMIT 1");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
            setcookie('mf_remember_token', '', [
                'expires' => time() - 3600,
                'path' => '/',
                'secure' => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            unset($_COOKIE['mf_remember_token']);
            return;
        }

        $authSecret = defined('AUTH_SECRET_KEY') ? AUTH_SECRET_KEY : 'manhwaflow_secure_auth_secret_key_2026_x89q2';
        $passwordComponent = !empty($user['password']) ? $user['password'] : 'default_pwd';
        $expectedSignature = hash_hmac('sha256', "{$userId}:{$rawToken}:{$passwordComponent}", $authSecret);

        $isValid = hash_equals($expectedSignature, $providedSignature);

        if (!$isValid) {
            $tokenHash = hash('sha256', $rawToken);
            $tokStmt = $pdo->prepare("SELECT id FROM `user_tokens` WHERE `user_id` = ? AND `token_hash` = ? AND `expires_at` > NOW() LIMIT 1");
            $tokStmt->execute([$userId, $tokenHash]);
            if ($tokStmt->fetch()) {
                $isValid = true;
            }
        }

        if ($isValid) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['avatar'] = $user['avatar'];

            // Migrate guest reading history if any was logged before session restoration
            $guestToken = $_COOKIE['guest_reader_token'] ?? '';
            if (!empty($guestToken)) {
                try {
                    $pdo->prepare("
                        DELETE FROM `reading_history` 
                        WHERE `user_token` = ? AND `user_id` IS NULL AND `series_id` IN (
                            SELECT `series_id` FROM (SELECT `series_id` FROM `reading_history` WHERE `user_id` = ?) AS tmp
                        )
                    ")->execute([$guestToken, $user['id']]);

                    $pdo->prepare("UPDATE `reading_history` SET `user_id` = ?, `user_token` = ? WHERE `user_token` = ? AND `user_id` IS NULL")
                        ->execute([$user['id'], 'user_' . $user['id'], $guestToken]);
                } catch (Exception $ex) {}
                setcookie('guest_reader_token', '', time() - 3600, '/');
                unset($_COOKIE['guest_reader_token']);
            }
        } else {
            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
            setcookie('mf_remember_token', '', [
                'expires' => time() - 3600,
                'path' => '/',
                'secure' => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            unset($_COOKIE['mf_remember_token']);
        }
    } catch (Exception $e) {}
}

/**
 * Upgrade currently active session to persistent token
 */
function upgradeActiveSessionToPersistentToken() {
    if (empty($_SESSION['user_id'])) return;
    try {
        if (!function_exists('getPdo')) {
            $dbFile = dirname(__DIR__) . '/config/db.php';
            if (file_exists($dbFile)) require_once $dbFile;
        }
        if (function_exists('getPdo')) {
            $pdo = getPdo();
            if ($pdo) {
                $stmt = $pdo->prepare("SELECT * FROM `users` WHERE `id` = ? LIMIT 1");
                $stmt->execute([intval($_SESSION['user_id'])]);
                $currentUser = $stmt->fetch();
                if ($currentUser) {
                    issuePersistentLoginToken($currentUser);
                }
            }
        }
    } catch (Exception $e) {}
}

/**
 * Revoke persistent authentication token on explicit manual logout
 */
function revokePersistentLoginToken() {
    $cookieValue = $_COOKIE['mf_remember_token'] ?? '';
    if (!empty($cookieValue)) {
        $parts = explode(':', $cookieValue);
        $userId = intval($parts[0] ?? 0);
        $rawToken = $parts[1] ?? '';
        if ($userId > 0 && !empty($rawToken)) {
            try {
                if (!function_exists('getPdo')) {
                    $dbFile = dirname(__DIR__) . '/config/db.php';
                    if (file_exists($dbFile)) require_once $dbFile;
                }
                if (function_exists('getPdo')) {
                    $pdo = getPdo();
                    if ($pdo) {
                        $tokenHash = hash('sha256', $rawToken);
                        $pdo->prepare("DELETE FROM `user_tokens` WHERE `user_id` = ? AND `token_hash` = ?")
                            ->execute([$userId, $tokenHash]);
                    }
                }
            } catch (Exception $e) {}
        }
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    setcookie('mf_remember_token', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    unset($_COOKIE['mf_remember_token']);
}


