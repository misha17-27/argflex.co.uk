"""Carry the form token the way a browser does.

Every public form now posts a token signed for its own action, so the suites
that drive those forms have to carry one too.

The token is always SCRAPED from the page the form is on, never computed
here. A test that signed its own tokens would go on passing if the server
quietly stopped checking them, which is the one thing these tests exist to
notice.

Usage, inside a suite that already has an opener with a cookie jar:

    from formtoken import Tokens
    tok = Tokens(op, BASE)
    ...
    post('/checkout/', {**fields, '_form': tok.checkout()})
"""
import re
import urllib.request


class Tokens:
    def __init__(self, opener, base, product_slug='acetylene-hose'):
        self.op = opener
        self.base = base
        self.product = product_slug

    def _get(self, path):
        with self.op.open(self.base + path, timeout=40) as r:
            return r.read().decode('utf-8', 'replace')

    @staticmethod
    def _first(html):
        m = re.search(r'name="_form" value="([a-f0-9]{64})"', html)
        return m.group(1) if m else ''

    def checkout(self):
        return self._first(self._get('/checkout/'))

    def coupon(self):
        m = re.search(r'data-coupon-token="([a-f0-9]{64})"', self._get('/cart/'))
        return m.group(1) if m else ''

    def review(self, slug=None):
        return self._first(self._get('/product/%s/' % (slug or self.product)))

    def account(self, act, page='/my-account/'):
        """The token for one account action.

        The forms declare which action they are before their token, in that
        order, so the pair can be read off together — which matters because a
        signed-in /my-account/details/ carries two forms with two different
        tokens and the wrong one is refused.
        """
        html = self._get(page)
        m = re.search(
            r'name="act" value="%s">\s*<input type="hidden" name="_form" value="([a-f0-9]{64})"'
            % re.escape(act), html)
        return m.group(1) if m else self._first(html)

    def sign(self, url, fields):
        """Whatever this URL needs, worked out from the URL and the fields."""
        if url.startswith('/checkout/'):
            return self.checkout()
        if url.startswith('/coupon-check'):
            return self.coupon()
        if url.startswith('/review-send'):
            return self.review(fields.get('product'))
        if url.startswith('/my-account'):
            act = str(fields.get('act', ''))
            # Each action's form lives on one section, and the token is signed
            # for that action, so the right page has to be asked for it.
            page = {'address':  '/my-account/addresses/',
                    'details':  '/my-account/details/',
                    'password': '/my-account/details/',
                    'forgot':   '/my-account/?do=forgot',
                    'reset':    '/my-account/?do=reset'}.get(act, '/my-account/')
            return self.account(act, page)
        if url.startswith('/contact-send'):
            return self._first(self._get('/contacts/'))
        return ''
