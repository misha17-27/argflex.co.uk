<?php
/**
 * The admin login's throttle and challenge, exercised against an account that
 * does not exist. No real credentials are involved anywhere in this file.
 */
declare(strict_types=1);
require dirname(__DIR__) . '/inc/config.php';
require_once ROOT_DIR . '/inc/security.php';
require_once ROOT_DIR . '/inc/turnstile.php';
require_once ROOT_DIR . '/admin/inc/auth.php';

@unlink(ROOT_DIR . '/storage/login-attempts.json');
$_SERVER['REMOTE_ADDR'] = '203.0.113.77';

$ok = $bad = 0;
function check(string $label, bool $cond, string $note = ''): void {
    global $ok, $bad;
    $cond ? $ok++ : $bad++;
    printf("  %-48s %s %s\n", $label, $cond ? 'OK  ' : 'FAIL', $note);
}

check('a fresh caller has no failures', failed_attempts() === 0);
check('and is not locked out', is_locked_out() === 0);
check('no challenge before any failure', !login_needs_challenge());

// an account that has never existed, with a password that is not one
attempt_login('nobody@example.invalid', 'not-a-password-' . bin2hex(random_bytes(4)));
check('one wrong guess is counted', failed_attempts() === 1, '(count=' . failed_attempts() . ')');
check('still not locked after one', is_locked_out() === 0);

for ($i = 0; $i < 7; $i++) {
    attempt_login('nobody@example.invalid', 'wrong-' . $i);
}
check('eight failures reach the limit', failed_attempts() === 8, '(count=' . failed_attempts() . ')');
check('and the caller is locked out', is_locked_out() > 0, '(' . is_locked_out() . 's left)');

echo "\n  THE CHALLENGE ARMS ON A FAILURE, NOT BEFORE\n";
echo "  login_needs_challenge() = turnstile_enabled() AND failed_attempts() > 0\n";
printf("    turnstile configured here : %s\n", turnstile_enabled() ? 'yes' : 'no — so it stays off locally');
printf("    failures standing         : %d\n", failed_attempts());
printf("    challenge shown           : %s\n", login_needs_challenge() ? 'yes' : 'no');

echo "\n  A DIFFERENT CALLER IS NOT PUNISHED FOR THIS ONE\n";
$_SERVER['REMOTE_ADDR'] = '198.51.100.4';
check('separate address, clean slate', failed_attempts() === 0);
check('and not locked out', is_locked_out() === 0);

echo "\n  WHAT THE OLD CODE GOT WRONG, BEHIND CLOUDFLARE\n";
$_SERVER['REMOTE_ADDR'] = '172.68.9.9';                  // a Cloudflare edge
$_SERVER['HTTP_CF_CONNECTING_IP'] = '203.0.113.200';     // the real attacker
$attacker = attempt_key();
$_SERVER['HTTP_CF_CONNECTING_IP'] = '203.0.113.201';     // a real customer
$customer = attempt_key();
check('two visitors behind one edge are told apart', $attacker !== $customer);
$old = hash('sha256', '172.68.9.9');
check('the old key would have merged them', $attacker !== $old && $customer !== $old);

@unlink(ROOT_DIR . '/storage/login-attempts.json');
printf("\n  %d passed, %d failed%s\n", $ok, $bad, $bad ? '  <-- LOOK' : '');
exit($bad ? 1 : 0);
