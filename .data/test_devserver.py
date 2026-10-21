"""Does the harness really survive the server dying?

The supervision was added after three --full runs were lost to it, and then
two clean runs went by without the server dying once — which proves the happy
path and nothing else. This kills the server on purpose and checks that the
run carries on, because an untested recovery is a guess.

Uses a port of its own so it cannot disturb a server somebody is working with.
"""
import os
import shutil
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from devserver import DevServer

PHP = os.environ.get('ARGFLEX_PHP') or (
    r'D:\argflex\php\php.exe' if os.path.exists(r'D:\argflex\php\php.exe') else shutil.which('php'))
PORT = 8137

ok = bad = 0


def check(label, cond, note=''):
    global ok, bad
    if cond:
        ok += 1
    else:
        bad += 1
    print('  %-50s %s %s' % (label, 'OK  ' if cond else 'FAIL', note))


if not PHP:
    print('  no php binary found; set ARGFLEX_PHP')
    sys.exit(2)

srv = DevServer(PHP, base='http://localhost:%d' % PORT, port=PORT)

print('NOTHING RUNNING ON THIS PORT')
check('the site does not answer', not srv.alive(timeout=2))
check('and there is nothing of ours to revive', not srv.ensure())
check('we do not claim a server we did not start', not srv.ours())

print('\nSTARTED BY US')
check('it starts and answers', srv.start())
check('and is ours to manage', srv.ours())
check('ensure() is content while it lives', srv.ensure())

print('\nKILLED MID-RUN — THE CASE THAT COST THREE RUNS')
srv.proc.kill()
srv.proc.wait(timeout=10)
check('the site really has gone', not srv.alive(timeout=2))
check('ensure() brings it back', srv.ensure(), '(this is the branch that was never exercised)')
check('and it answers again', srv.alive())

print('\nTIDYING UP')
srv.stop()
check('stop() leaves nothing behind', not srv.alive(timeout=2))
check('and it is no longer claimed as ours', not srv.ours())

print('\nA SERVER THAT IS NOT OURS IS LEFT ALONE')
other = DevServer(PHP, base='http://localhost:%d' % PORT, port=PORT)
other.start()                      # stand in for somebody else's
watcher = DevServer(PHP, base='http://localhost:%d' % PORT, port=PORT)
check('the watcher sees it', watcher.alive())
check('but does not claim it', not watcher.ours())
watcher.stop()
check('and stop() does not kill it', watcher.alive())
other.stop()

print('\n  %d passed, %d failed%s' % (ok, bad, '  <-- LOOK' if bad else ''))
sys.exit(1 if bad else 0)
