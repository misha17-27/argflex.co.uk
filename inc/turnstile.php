<?php
/**
 * Cloudflare Turnstile, shared by the public forms and the admin login.
 * With no keys configured every check passes, so forms keep working.
 */
declare(strict_types=1);

function turnstile_enabled(): bool
{
    return trim((string) setting('turnstile_site')) !== ''
        && trim((string) setting('turnstile_secret')) !== '';
}

/** The widget markup, plus the script tag the first time it is called. */
function turnstile_widget(string $theme = 'light'): string
{
    if (!turnstile_enabled()) return '';

    static $scriptAdded = false;
    $html = '<div class="cf-turnstile" data-sitekey="' . e((string) setting('turnstile_site'))
          . '" data-theme="' . e($theme) . '"></div>';
    if (!$scriptAdded) {
        $scriptAdded = true;
        $html .= '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
    }
    return $html;
}

/** Verify the token a form posted back. True when Turnstile is switched off. */
function turnstile_verify(?string $token): bool
{
    if (!turnstile_enabled()) return true;
    $token = trim((string) $token);
    if ($token === '') return false;

    $payload = http_build_query([
        'secret'   => (string) setting('turnstile_secret'),
        'response' => $token,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
    ]);

    $context = stream_context_create(['http' => [
        'method'        => 'POST',
        'header'        => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content'       => $payload,
        'timeout'       => 10,
        'ignore_errors' => true,
    ]]);

    $raw = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $context);
    if ($raw === false) return false;

    $result = json_decode($raw, true);
    return is_array($result) && !empty($result['success']);
}
