"""Starting, checking and reviving the local PHP server.

PHP's built-in server dies partway through a long run and takes every check
after it with it: one --full run reported fourteen failures in a row, all of
them "No connection could be made", and not one a fault in the code. Worse, a
suite killed mid-test never reaches its own tidy-up, so it leaves the
catalogue half-edited and the NEXT run fails on the mess somebody then goes
looking for a bug in.

Kept in its own file so the behaviour can be tested rather than hoped for —
see test_devserver.py. preflight.py imports it.
"""
import os
import subprocess
import time
import urllib.request

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))


class DevServer:
    """A server this process may have started, and may restart.

    Only ever our own: one that was already running belongs to somebody who
    is probably still looking at it, so it is used and never killed.
    """

    def __init__(self, php, base='http://localhost:8124', port=8124):
        self.php, self.base, self.port = php, base, port
        self.proc = None            # None means "not ours"

    def alive(self, timeout=8):
        try:
            with urllib.request.urlopen(self.base + '/', timeout=timeout) as r:
                return r.status == 200
        except Exception:
            return False

    def start(self, wait=12.0):
        """Start one of our own and wait for it to answer."""
        if not self.php:
            return False
        self.proc = subprocess.Popen(
            [self.php, '-S', 'localhost:%d' % self.port, 'router.php'],
            cwd=ROOT, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        deadline = time.monotonic() + wait
        while time.monotonic() < deadline:
            if self.alive(timeout=2):
                return True
            time.sleep(0.25)
        return False

    def ensure(self):
        """True when the site answers, restarting our own server if it died."""
        if self.alive():
            return True
        if self.proc is None:
            return False            # somebody else's, or never started
        return self.start()

    def ours(self):
        return self.proc is not None

    def stop(self):
        if self.proc is not None:
            self.proc.terminate()
            try:
                self.proc.wait(timeout=5)
            except Exception:
                self.proc.kill()
            self.proc = None
