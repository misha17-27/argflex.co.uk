<?php
/**
 * Outgoing mail.
 *
 * Uses SMTP when a host is configured in the admin, and falls back to PHP's
 * mail() when it is not, so the site still works on a plain shared host.
 */
declare(strict_types=1);

/**
 * Send a message. Returns true on success; $error carries the reason when it
 * fails, so the caller can log it without guessing. HTML is sent as
 * multipart/alternative with a readable text part, because plenty of
 * mailboxes still prefer or only show the text one.
 */
function send_mail(string $to, string $subject, string $body, string $replyTo = '',
                   string &$error = '', bool $isHtml = false): bool
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
        return smtp_send($to, $encodedSubject, $body, $from, $encodedName, $replyTo, $error, $isHtml);
    }

    [$typeHeaders, $payload] = mail_body($body, $isHtml);

    $headers = "From: {$encodedName} <{$from}>\r\n";
    if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $headers .= "Reply-To: {$replyTo}\r\n";
    }
    $headers .= "MIME-Version: 1.0\r\n" . $typeHeaders;

    if (!@mail($to, $encodedSubject, $payload, $headers)) {
        $error = 'PHP mail() refused the message. Configure SMTP under Settings → Mail.';
        return false;
    }
    return true;
}

/** Minimal SMTP client: STARTTLS, implicit TLS or plain, with AUTH LOGIN. */
function smtp_send(string $to, string $subject, string $body, string $from,
                   string $fromName, string $replyTo, string &$error, bool $isHtml = false): bool
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
        [$typeHeaders, $payload] = mail_body($body, $isHtml);

        $headers = "From: {$fromName} <{$from}>\r\n"
                 . "To: {$to}\r\n"
                 . "Subject: {$subject}\r\n"
                 . ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL) ? "Reply-To: {$replyTo}\r\n" : '')
                 . "Date: " . date('r') . "\r\n"
                 . "MIME-Version: 1.0\r\n"
                 . $typeHeaders;

        // a lone dot on a line ends the data block, so escape it
        $data = preg_replace('/^\./m', '..', $payload) ?? $payload;

        $ok = $say('MAIL FROM:<' . $from . '>', '250')
           && $say('RCPT TO:<' . $to . '>', '250')
           && $say('DATA', '354')
           && $say($headers . "\r\n" . $data . "\r\n.", '250');
    }

    @fwrite($socket, "QUIT\r\n");
    @fclose($socket);
    return $ok;
}

/* ----------------------------------------------------------------- bodies */

/** Build the Content-Type headers and body for a plain or HTML message. */
function mail_body(string $body, bool $isHtml): array
{
    if (!$isHtml) {
        return ["Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n", $body];
    }

    $boundary = 'argflex-' . bin2hex(random_bytes(8));
    $payload  = "--{$boundary}\r\n"
              . "Content-Type: text/plain; charset=UTF-8\r\n"
              . "Content-Transfer-Encoding: 8bit\r\n\r\n"
              . html_to_text($body) . "\r\n\r\n"
              . "--{$boundary}\r\n"
              . "Content-Type: text/html; charset=UTF-8\r\n"
              . "Content-Transfer-Encoding: 8bit\r\n\r\n"
              . $body . "\r\n\r\n"
              . "--{$boundary}--\r\n";

    return ["Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n", $payload];
}

/** A readable plain-text version of an HTML email. */
function html_to_text(string $html): string
{
    $text = preg_replace('#<(script|style|head)[^>]*>.*?</\1>#is', '', $html) ?? $html;
    $text = preg_replace('#</(tr|p|div|h1|h2|h3|li|table)>#i', "\n", $text) ?? $text;
    $text = preg_replace('#<br\s*/?>#i', "\n", $text) ?? $text;
    $text = preg_replace('#</t[dh]>#i', "  ", $text) ?? $text;
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
    $text = preg_replace('/ ?\n ?/', "\n", $text) ?? $text;
    return trim(preg_replace('/\n{3,}/', "\n\n", $text) ?? $text);
}

/* ---------------------------------------------------------- notifications */

/**
 * Send one of the configured notifications, wrapped in the email template.
 *
 * The "to" on the Emails tab wins; $fallbackTo is used when it is blank, so
 * a shop notification defaults to the address on the same tab and a customer
 * one to the customer. Several comma-separated recipients are allowed.
 * Failures are logged rather than shown — the order or enquiry is already
 * stored, and there is nothing useful the visitor could do about it.
 */
function mail_notify(string $kind, string $fallbackTo, array $vars, string $bodyHtml, string $replyTo = ''): bool
{
    $conf = email_conf($kind);
    if (empty($conf['enabled'])) return false;

    $to = trim((string) $conf['to']) !== '' ? (string) $conf['to'] : $fallbackTo;
    if (trim($to) === '') return false;

    $subject = email_tokens((string) $conf['subject'], $vars);
    $heading = email_tokens((string) $conf['heading'], $vars);
    $html    = email_html($heading, $bodyHtml, $subject);

    $ok = true;
    foreach (array_filter(array_map('trim', explode(',', $to))) as $address) {
        $error = '';
        if (!send_mail($address, $subject, $html, $replyTo, $error, true)) {
            $ok = false;
            @file_put_contents(ROOT_DIR . '/storage/mail-errors.log',
                date('c') . "  {$kind}  {$address}  {$error}\n", FILE_APPEND | LOCK_EX);
        }
    }
    return $ok;
}

/** The customer block shared by the order emails. */
function email_address_block(array $c): string
{
    return '<p style="margin:0 0 6px"><b>' . e($c['name']) . '</b>'
         . ($c['company'] !== '' ? ' &mdash; ' . e($c['company']) : '') . '</p>'
         . '<p style="margin:0;color:#5b6880;line-height:1.7">'
         . e($c['email']) . '<br>' . e($c['phone']) . '<br>'
         . e($c['address']) . '<br>' . e($c['city']) . ', ' . e($c['postcode']) . '<br>'
         . e($c['country']) . '</p>';
}

/** Tell the shop and the customer about a new order. */
function send_order_emails(array $record): void
{
    $order = $record['order'];
    $c     = $record['customer'];
    $pay   = $record['payment'] ?? [];
    $vars  = ['reference' => $record['reference'], 'site' => SITE_NAME,
              'name' => $c['name'], 'total' => money((int) $order['total'])];

    $h3   = 'font-size:15px;margin:22px 0 8px';
    $addr = email_address_block($c);

    mail_notify('new_order', (string) setting('mail_to'), $vars,
        '<p style="margin:0 0 14px">Order <b>' . e($record['reference']) . '</b> came in at '
      . e(date('j M Y, H:i')) . '.</p>'
      . email_order_table($order)
      . '<h3 style="' . $h3 . '">Deliver to</h3>' . $addr
      . ($c['notes'] !== '' ? '<h3 style="' . $h3 . '">Order notes</h3><p style="margin:0">' . nl2br(e($c['notes'])) . '</p>' : '')
      . (!empty($pay['title']) ? '<p style="margin:22px 0 0"><b>Payment:</b> ' . e($pay['title']) . '</p>' : ''),
        $c['email']);

    mail_notify('order_placed', $c['email'], $vars,
        '<p style="margin:0 0 12px">Hello ' . e($c['name']) . ',</p>'
      . '<p style="margin:0 0 14px">Thank you &mdash; we have your order and will confirm stock and '
      . 'cut lengths shortly. Your reference is <b>' . e($record['reference']) . '</b>.</p>'
      . email_order_table($order)
      . (!empty($order['delivery_in']) ? '<p style="margin:14px 0 0;color:#5b6880">Delivery estimate: ' . e($order['delivery_in']) . '.</p>' : '')
      . (!empty($pay['title'])
            ? '<h3 style="' . $h3 . '">' . e($pay['title']) . '</h3><p style="margin:0">'
              . e(($pay['instructions'] ?? '') !== '' ? $pay['instructions'] : ($pay['description'] ?? '')) . '</p>'
            : '')
      . '<h3 style="' . $h3 . '">Deliver to</h3>' . $addr,
        (string) setting('mail_to'));
}

/** Tell the customer their order moved to a new status. */
function send_status_email(array $record, string $status, string $note = ''): bool
{
    $c    = $record['customer'] ?? [];
    $to   = (string) ($c['email'] ?? '');
    if ($to === '') return false;

    $label = ORDER_STATUSES[$status] ?? $status;
    $vars  = ['reference' => $record['reference'], 'site' => SITE_NAME,
              'name' => $c['name'] ?? '', 'status' => lower($label)];

    return mail_notify('order_status', $to, $vars,
        '<p style="margin:0 0 12px">Hello ' . e($c['name'] ?? '') . ',</p>'
      . '<p style="margin:0 0 14px">Order <b>' . e($record['reference']) . '</b> is now <b>'
      . e(lower($label)) . '</b>.</p>'
      . ($note !== '' ? '<p style="margin:0 0 14px">' . nl2br(e($note)) . '</p>' : '')
      . email_order_table($record['order']),
        (string) setting('mail_to'));
}

/** Tell the shop about an enquiry, and acknowledge it to the sender. */
function send_enquiry_emails(array $fields): void
{
    $vars = ['site' => SITE_NAME, 'name' => $fields['name'], 'email' => $fields['email']];
    $rows = '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse">';
    foreach (['Name' => $fields['name'], 'Email' => $fields['email'],
              'Phone' => $fields['phone'], 'Product' => $fields['product']] as $label => $value) {
        if (trim((string) $value) === '') continue;
        $rows .= '<tr><td style="padding:5px 14px 5px 0;color:#5b6880;font-size:13.5px;white-space:nowrap">'
              . e($label) . '</td><td style="padding:5px 0;font-size:14px">' . e((string) $value) . '</td></tr>';
    }
    $rows .= '</table>';

    mail_notify('enquiry', (string) setting('mail_to'), $vars,
        $rows . '<h3 style="font-size:15px;margin:22px 0 8px">Message</h3>'
      . '<p style="margin:0">' . nl2br(e($fields['message'])) . '</p>',
        $fields['email']);

    if (filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) {
        mail_notify('enquiry_ack', $fields['email'], $vars,
            '<p style="margin:0 0 12px">Hello ' . e($fields['name']) . ',</p>'
          . '<p style="margin:0 0 14px">Thank you for getting in touch. We read every message and '
          . 'normally reply the same working day.</p>'
          . '<h3 style="font-size:15px;margin:22px 0 8px">What you sent us</h3>'
          . '<p style="margin:0;color:#5b6880">' . nl2br(e($fields['message'])) . '</p>',
            (string) setting('mail_to'));
    }
}
