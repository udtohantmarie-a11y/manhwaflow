<?php
// includes/mailer.php - Native High-Reliability SMTP Mailer for ManhwaFlow

class Mailer {
    /**
     * Get Mailer Configuration
     */
    private static function getConfig() {
        $configFile = __DIR__ . '/../config/email.php';
        if (file_exists(__DIR__ . '/../config/email.local.php')) {
            $configFile = __DIR__ . '/../config/email.local.php';
        }
        
        $default = [
            'smtp_host'  => 'ssl://smtp.gmail.com',
            'smtp_port'  => 465,
            'smtp_user'  => 'manhwaflow.official@gmail.com',
            'smtp_pass'  => 'gzuaanqhebwtbbku',
            'from_email' => 'manhwaflow.official@gmail.com',
            'from_name'  => 'ManhwaFlow'
        ];

        if (file_exists($configFile)) {
            $loaded = include $configFile;
            if (is_array($loaded)) {
                return array_merge($default, $loaded);
            }
        }
        return $default;
    }

    /**
     * Send Password Reset Verification Email
     */
    public static function sendPasswordResetCode($toEmail, $code, $username = '') {
        $subject = "Your ManhwaFlow Password Reset Code: {$code}";
        $displayName = !empty($username) ? htmlspecialchars($username) : 'Reader';

        $htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset Code</title>
    <style>
        body { margin: 0; padding: 0; background-color: #0b0f19; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #f1f5f9; }
        .wrapper { width: 100%; max-width: 580px; margin: 0 auto; padding: 30px 15px; }
        .card { background-color: #111827; border: 1px solid #1f2937; border-radius: 20px; padding: 35px 30px; box-shadow: 0 20px 40px rgba(0,0,0,0.5); }
        .header { text-align: center; margin-bottom: 25px; }
        .logo-badge { display: inline-block; background: linear-gradient(135deg, #f97316 0%, #ec4899 50%, #8b5cf6 100%); padding: 10px 20px; border-radius: 12px; font-weight: 900; font-size: 20px; color: #ffffff; letter-spacing: -0.5px; text-decoration: none; }
        .title { font-size: 22px; font-weight: 800; color: #ffffff; margin: 20px 0 8px 0; text-align: center; }
        .subtitle { font-size: 14px; color: #94a3b8; text-align: center; line-height: 1.5; margin: 0 0 25px 0; }
        .code-box { background: #0f172a; border: 2px dashed #f97316; border-radius: 16px; padding: 20px; text-align: center; margin: 25px 0; }
        .code-label { font-size: 11px; text-transform: uppercase; letter-spacing: 2px; color: #94a3b8; font-weight: 700; margin-bottom: 8px; }
        .code-digits { font-family: 'Courier New', Courier, monospace; font-size: 38px; font-weight: 900; color: #f97316; letter-spacing: 8px; margin: 0; }
        .info-box { background: rgba(249, 115, 22, 0.08); border-left: 4px solid #f97316; padding: 12px 16px; border-radius: 8px; font-size: 13px; color: #cbd5e1; line-height: 1.5; margin-bottom: 25px; }
        .security-notice { font-size: 12px; color: #64748b; line-height: 1.6; border-top: 1px solid #1e293b; padding-top: 20px; margin-top: 20px; }
        .footer { text-align: center; font-size: 11px; color: #475569; margin-top: 25px; line-height: 1.6; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="card">
            <div class="header">
                <div class="logo-badge">MANHWALOW</div>
                <h1 class="title">Password Reset Verification</h1>
                <p class="subtitle">Hi <strong>{$displayName}</strong>, we received a request to change the password for your ManhwaFlow account.</p>
            </div>

            <div class="code-box">
                <div class="code-label">Your 6-Digit Verification Code</div>
                <div class="code-digits">{$code}</div>
            </div>

            <div class="info-box">
                ⏱️ This verification code is valid for <strong>15 minutes</strong>. Enter this code on the ManhwaFlow password reset page to choose your new password.
            </div>

            <div class="security-notice">
                🔒 <strong>Security Tip:</strong> Never share this code with anyone. If you did not request a password reset, you can safely ignore this email — your account remains secure.
            </div>
        </div>

        <div class="footer">
            &copy; 2026 ManhwaFlow. All rights reserved.<br>
            This is an automated security transmission. Replies to this address are not monitored.
        </div>
    </div>
</body>
</html>
HTML;

        return self::sendMail($toEmail, $subject, $htmlBody);
    }

    /**
     * Send email using SMTP (SSL/TLS) with native PHP sockets
     */
    public static function sendMail($toEmail, $subject, $htmlBody) {
        $config = self::getConfig();
        $user = $config['smtp_user'];
        $pass = $config['smtp_pass'];
        $fromEmail = $config['from_email'];
        $fromName = $config['from_name'];

        // 1. Try Primary SSL Port 465
        $res = self::sendViaSmtp('ssl://smtp.gmail.com', 465, $user, $pass, $fromEmail, $fromName, $toEmail, $subject, $htmlBody);
        if ($res['success']) {
            return $res;
        }

        // 2. Try Secondary TLS Port 587
        $resTls = self::sendViaSmtp('tcp://smtp.gmail.com', 587, $user, $pass, $fromEmail, $fromName, $toEmail, $subject, $htmlBody, true);
        if ($resTls['success']) {
            return $resTls;
        }

        // 3. Fallback: PHP native mail() function if server allows
        $headers  = "From: {$fromName} <{$fromEmail}>\r\n";
        $headers .= "Reply-To: {$fromEmail}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

        if (@mail($toEmail, $subject, $htmlBody, $headers)) {
            return ['success' => true, 'method' => 'native_mail'];
        }

        return [
            'success' => false, 
            'error' => 'Unable to dispatch email. Primary SMTP error: ' . ($res['error'] ?? 'Unknown error')
        ];
    }

    /**
     * Internal SMTP Protocol Communication
     */
    private static function sendViaSmtp($host, $port, $user, $pass, $fromEmail, $fromName, $toEmail, $subject, $htmlBody, $startTls = false) {
        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($host, $port, $errno, $errstr, 15);
        if (!$socket) {
            return ['success' => false, 'error' => "Cannot connect to {$host}:{$port} - {$errstr} ({$errno})"];
        }

        // Helper to read multiline SMTP response
        $readResponse = function() use ($socket) {
            $data = "";
            while ($line = fgets($socket, 512)) {
                $data .= $line;
                if (substr($line, 3, 1) === ' ') break;
            }
            return $data;
        };

        $banner = $readResponse();
        if (substr($banner, 0, 3) !== '220') {
            fclose($socket);
            return ['success' => false, 'error' => "Unexpected greeting: {$banner}"];
        }

        fputs($socket, "EHLO localhost\r\n");
        $ehlo = $readResponse();

        if ($startTls) {
            fputs($socket, "STARTTLS\r\n");
            $tlsRes = $readResponse();
            if (substr($tlsRes, 0, 3) !== '220') {
                fclose($socket);
                return ['success' => false, 'error' => "STARTTLS failed: {$tlsRes}"];
            }

            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($socket);
                return ['success' => false, 'error' => "Crypto negotiation failed on TLS."];
            }

            fputs($socket, "EHLO localhost\r\n");
            $readResponse();
        }

        // Authenticate
        fputs($socket, "AUTH LOGIN\r\n");
        $authPrompt = $readResponse();
        if (substr($authPrompt, 0, 3) !== '334') {
            fclose($socket);
            return ['success' => false, 'error' => "AUTH LOGIN rejected: {$authPrompt}"];
        }

        fputs($socket, base64_encode($user) . "\r\n");
        $userPrompt = $readResponse();

        fputs($socket, base64_encode($pass) . "\r\n");
        $authResult = $readResponse();
        if (substr($authResult, 0, 3) !== '235') {
            fclose($socket);
            return ['success' => false, 'error' => "Authentication failed: {$authResult}"];
        }

        // Mail From
        fputs($socket, "MAIL FROM: <{$fromEmail}>\r\n");
        $fromRes = $readResponse();
        if (substr($fromRes, 0, 3) !== '250') {
            fclose($socket);
            return ['success' => false, 'error' => "MAIL FROM failed: {$fromRes}"];
        }

        // Rcpt To
        fputs($socket, "RCPT TO: <{$toEmail}>\r\n");
        $rcptRes = $readResponse();
        if (substr($rcptRes, 0, 3) !== '250') {
            fclose($socket);
            return ['success' => false, 'error' => "RCPT TO failed: {$rcptRes}"];
        }

        // Data
        fputs($socket, "DATA\r\n");
        $dataPrompt = $readResponse();
        if (substr($dataPrompt, 0, 3) !== '354') {
            fclose($socket);
            return ['success' => false, 'error' => "DATA prompt failed: {$dataPrompt}"];
        }

        $headers  = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$fromEmail}>\r\n";
        $headers .= "To: <{$toEmail}>\r\n";
        $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $headers .= "Date: " . date('r') . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "Content-Transfer-Encoding: 8bit\r\n\r\n";

        fputs($socket, $headers . $htmlBody . "\r\n.\r\n");
        $sendRes = $readResponse();

        fputs($socket, "QUIT\r\n");
        fclose($socket);

        if (substr($sendRes, 0, 3) === '250') {
            return ['success' => true, 'response' => trim($sendRes)];
        }

        return ['success' => false, 'error' => "Message rejected: {$sendRes}"];
    }
}
