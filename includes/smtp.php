<?php
/**
 * Minimal SMTP mailer — no external libraries needed.
 * Sends HTML email via Hostinger SMTP (or any SMTP server).
 *
 * Configure in config.php:
 *   define('SMTP_HOST', 'smtp.hostinger.com');
 *   define('SMTP_PORT', 587);
 *   define('SMTP_USER', 'noreply@bosketsalimentos.com');
 *   define('SMTP_PASS', 'your-email-password');
 *   define('SMTP_FROM', 'noreply@bosketsalimentos.com');
 *   define('SMTP_NAME', "Bosket's Alimentos");
 */

/**
 * Send an HTML email via SMTP with STARTTLS.
 * Returns true on success, error string on failure.
 */
function smtp_send(string $to, string $subject, string $htmlBody): bool|string
{
    // Config with sensible defaults
    $host    = defined('SMTP_HOST') ? SMTP_HOST : 'smtp.hostinger.com';
    $port    = defined('SMTP_PORT') ? (int)SMTP_PORT : 587;
    $user    = defined('SMTP_USER') ? SMTP_USER : (defined('MAIL_FROM') ? MAIL_FROM : '');
    $pass    = defined('SMTP_PASS') ? SMTP_PASS : '';
    $from    = defined('SMTP_FROM') ? SMTP_FROM : $user;
    $name    = defined('SMTP_NAME') ? SMTP_NAME : (defined('SITE_NAME') ? SITE_NAME : "Bosket's Alimentos");

    if (!$user || !$pass) {
        // Fall back to PHP mail() if SMTP not configured
        $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n"
                 . "From: $name <$from>\r\nReply-To: $from\r\n"
                 . "X-Mailer: PHP/" . PHP_VERSION;
        return @mail($to, $subject, $htmlBody, $headers, '-f' . $from)
            ? true : 'PHP mail() failed';
    }

    // ── Open TCP connection ───────────────────────────────────────────────────
    $errno = 0; $errstr = '';
    $sock = @fsockopen($host, $port, $errno, $errstr, 15);
    if (!$sock) return "Cannot connect to $host:$port — $errstr ($errno)";

    $read = fn() => fgets($sock, 515);
    $cmd  = function(string $c) use ($sock, $read): string {
        fwrite($sock, $c . "\r\n");
        $resp = '';
        do { $line = fgets($sock, 515); $resp .= $line; }
        while ($line && strlen($line) >= 4 && $line[3] === '-');
        return $resp;
    };

    // ── SMTP handshake ────────────────────────────────────────────────────────
    $banner = $read(); // server greeting
    if (!str_starts_with($banner, '2')) { fclose($sock); return "SMTP banner error: $banner"; }

    $r = $cmd("EHLO " . gethostname());
    if (!str_starts_with($r, '2')) { fclose($sock); return "EHLO failed: $r"; }

    // STARTTLS
    $r = $cmd("STARTTLS");
    if (!str_starts_with($r, '2')) { fclose($sock); return "STARTTLS failed: $r"; }
    if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
        fclose($sock); return "TLS handshake failed";
    }

    // Re-EHLO after TLS
    $r = $cmd("EHLO " . gethostname());
    if (!str_starts_with($r, '2')) { fclose($sock); return "EHLO after TLS failed: $r"; }

    // AUTH LOGIN
    $r = $cmd("AUTH LOGIN");
    if (!str_starts_with($r, '3')) { fclose($sock); return "AUTH LOGIN failed: $r"; }
    $r = $cmd(base64_encode($user));
    if (!str_starts_with($r, '3')) { fclose($sock); return "SMTP username rejected: $r"; }
    $r = $cmd(base64_encode($pass));
    if (!str_starts_with($r, '2')) { fclose($sock); return "SMTP password rejected — check SMTP_PASS in config.php"; }

    // MAIL FROM
    $r = $cmd("MAIL FROM:<$from>");
    if (!str_starts_with($r, '2')) { fclose($sock); return "MAIL FROM failed: $r"; }

    // RCPT TO
    $r = $cmd("RCPT TO:<$to>");
    if (!str_starts_with($r, '2')) { fclose($sock); return "RCPT TO failed (recipient rejected): $r"; }

    // DATA
    $r = $cmd("DATA");
    if (!str_starts_with($r, '3')) { fclose($sock); return "DATA failed: $r"; }

    // ── Build message ─────────────────────────────────────────────────────────
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $boundary = md5(uniqid('', true));
    $msgId    = '<' . time() . '.' . rand(1000,9999) . '@' . parse_url('https://' . $from, PHP_URL_HOST) . '>';
    $headers  = "Date: " . date('r') . "\r\n"
              . "Message-ID: $msgId\r\n"
              . "From: =?UTF-8?B?" . base64_encode($name) . "?= <$from>\r\n"
              . "Reply-To: $from\r\n"
              . "To: <$to>\r\n"
              . "Subject: $encodedSubject\r\n"
              . "MIME-Version: 1.0\r\n"
              . "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n"
              . "X-Mailer: BosketsAlimentos/1.0\r\n"
              . "Precedence: bulk\r\n"
              . "Auto-Submitted: auto-generated\r\n";

    $plainText = strip_tags(str_replace(['<br>','<br/>','<br />','</p>','</div>'], "\n", $htmlBody));

    $body = "--$boundary\r\n"
          . "Content-Type: text/plain; charset=UTF-8\r\n"
          . "Content-Transfer-Encoding: base64\r\n\r\n"
          . chunk_split(base64_encode($plainText)) . "\r\n"
          . "--$boundary\r\n"
          . "Content-Type: text/html; charset=UTF-8\r\n"
          . "Content-Transfer-Encoding: base64\r\n\r\n"
          . chunk_split(base64_encode($htmlBody)) . "\r\n"
          . "--$boundary--\r\n";

    // Dot-stuff and send
    $message = $headers . "\r\n" . $body;
    $message = str_replace("\r\n.", "\r\n..", $message);
    fwrite($sock, $message . "\r\n.\r\n");

    $r = $read();
    if (!str_starts_with($r, '2')) { $cmd("QUIT"); fclose($sock); return "Message rejected: $r"; }

    $cmd("QUIT");
    fclose($sock);
    return true;
}
