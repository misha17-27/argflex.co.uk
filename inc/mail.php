<?php
/**
 * Outgoing mail.
 *
 * Uses SMTP when a host is configured in the admin, and falls back to PHP's
 * mail() when it is not, so the site still works on a plain shared host.
 */
declare(strict_types=1);

/**
 * Send a plain-text message. Returns true on success; $error carries the
 * reason when it fails, so the caller can log it without guessing.
 */
function send_mail(string $to, string $subject, string $body, string $replyTo = '', string &$error = ''): bool
{
    $from     = (string) setting('mail_from');
    $fromName = (string) setting('mail_from_name');

    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $error = 'The destination address is not valid.';
        return false;
    }

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $encodedName    = '=?UTF-8?B?' . base64_encode($fromName) . '?=';

    if (trim((string) setting('smtp_host')) !== '') {
        return smtp_send($to, $encodedSubject, $body, $from, $encodedName, $replyTo, $error);
    }

    $headers = "From: {$encodedName} <{$from}>\r\n";
    if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $headers .= "Reply-To: {$replyTo}\r\n";
    }
    $headers .= "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n";

    if (!@mail($to, $encodedSubject, $body, $headers)) {
        $error = 'PHP mail() refused the message. Configure SMTP under Settings → Mail.';
        return false;
    }
    return true;
}

/** Minimal SMTP client: STARTTLS, implicit TLS or plain, with AUTH LOGIN. */
function smtp_send(string $to, string $subject, string $body, string $from,
                   string $fromName, string $replyTo, string &$error): bool
{
    $host   = (string) setting('smtp_host');
    $port   = (int) setting('smtp_port') ?: 587;
    $user   = (string) setting('smtp_user');
    $pass   = (string) setting('smtp_pass');
    $secure = strtolower((string) setting('smtp_secure'));

    $target = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $socket = @stream_socket_client($target, $errno, $errstr, 15);
    if (!$socket) {
        $error = "Could not reach {$host}:{$port} — {$errstr}";
        return false;
    }
    stream_set_timeout($socket, 15);

    $read = function () use ($socket): string {
        $out = '';
        while (($line = fgets($socket, 515)) !== false) {
            $out .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') break;
        }
        return $out;
    };
    $say = function (string $cmd, string $expect) use ($socket, $read, &$error): bool {
        if ($cmd !== '') fwrite($socket, $cmd . "\r\n");
        $reply = $read();
        if (!str_starts_with($reply, $expect)) {
            $error = 'SMTP said: ' . trim($reply) . ($cmd !== '' ? ' (after ' . strtok($cmd, ' ') . ')' : '');
            return false;
        }
        return true;
    };

    $helo = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $ok = $say('', '220')
       && $say('EHLO ' . $helo, '250');

    if ($ok && $secure === 'tls') {
        $ok = $say('STARTTLS', '220')
           && @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)
           && $say('EHLO ' . $helo, '250');
        if (!$ok && $error === '') $error = 'STARTTLS failed.';
    }

    if ($ok && $user !== '') {
        $ok = $say('AUTH LOGIN', '334')
           && $say(base64_encode($user), '334')
           && $say(base64_encode($pass), '235');
    }

    if ($ok) {
        $headers = "From: {$fromName} <{$from}>\r\n"
                 . "To: {$to}\r\n"
                 . "Subject: {$subject}\r\n"
                 . ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL) ? "Reply-To: {$replyTo}\r\n" : '')
                 . "Date: " . date('r') . "\r\n"
                 . "MIME-Version: 1.0\r\n"
                 . "Content-Type: text/plain; charset=UTF-8\r\n"
                 . "Content-Transfer-Encoding: 8bit\r\n";

        // a lone dot on a line ends the data block, so escape it
        $data = preg_replace('/^\./m', '..', $body) ?? $body;

        $ok = $say('MAIL FROM:<' . $from . '>', '250')
           && $say('RCPT TO:<' . $to . '>', '250')
           && $say('DATA', '354')
           && $say($headers . "\r\n" . $data . "\r\n.", '250');
    }

    @fwrite($socket, "QUIT\r\n");
    @fclose($socket);
    return $ok;
}
