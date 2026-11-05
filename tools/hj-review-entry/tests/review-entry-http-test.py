#!/usr/bin/env python3
"""Synthetic HTTP tests in disposable CI only."""
import html
import http.cookiejar
import json
import os
import re
import subprocess
import time
import urllib.error
import urllib.parse
import urllib.request

if os.environ.get('GITHUB_ACTIONS') != 'true' or os.environ.get('HJ_DISPOSABLE_MARIADB_FIXTURE') != '1' or os.environ.get('HJ_TEST_PLUGIN') not in ('review-entry-http-0.3.12', 'review-entry-http-current'):
    raise SystemExit('Disposable CI fixture only.')
BASE = 'http://127.0.0.1:8999'
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, hdrs, url):
        return None

def session():
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()), NoRedirect())

def request(opener, path, data=None):
    body = urllib.parse.urlencode(data).encode() if data is not None else None
    req = urllib.request.Request(BASE + path, data=body, headers={'Host': 'example.invalid', 'X-Forwarded-For': '203.0.113.99'})
    try: response = opener.open(req, timeout=30)
    except urllib.error.HTTPError as exc: response = exc
    return response.code, response.headers, response.read().decode('utf-8', errors='replace')

def check(condition, message):
    if not condition: raise AssertionError(message)
    print('PASS:', message, flush=True)

def login(opener, user, password):
    check(request(opener, '/wp-login.php')[0] == 200, user + ' login form')
    code, _, body = request(opener, '/wp-login.php', {'log': user, 'pwd': password, 'wp-submit': 'Log In', 'redirect_to': 'http://example.invalid/wp-admin/', 'testcookie': '1'})
    check(code == 302, user + ' login ' + str(code) + ' ' + body[-300:])

def run():
    with open(os.path.join(os.environ['RUNNER_TEMP'], 'review-entry-meta.json'), encoding='utf-8') as f: meta = json.load(f)
    admin, sub, anon = session(), session(), session()
    login(admin, 'fixture_admin', 'disposable-password-please-ignore'); login(sub, 'entry_subscriber', 'disposable-password')
    code, _, page = request(admin, '/wp-admin/tools.php?page=hj-review-entry')
    check(code == 200 and 'Synthetic unmarked surgeon' in page and 'Synthetic draft surgeon' not in page, 'form offers published surgeons only')
    m = re.search(r'name="_wpnonce"\s+value="([^"]+)"', page); check(bool(m), 'administrator nonce present')
    valid = {'action':'hj_review_entry', '_wpnonce': html.unescape(m.group(1)), 'doctor':str(meta['doctor']), 'name':'Synthetic Name', 'comment':'Synthetic comment', 'rating':'4', 'date':'2024-05-17'}
    endpoint = '/wp-admin/admin-post.php'
    check(request(sub, endpoint, valid)[0] == 403 and request(anon, endpoint, valid)[0] in (400,403), 'subscriber and anonymous cannot submit')
    check(request(admin, endpoint + '?action=hj_review_entry')[0] == 405, 'GET denied')
    check(request(admin, endpoint, dict(valid, _wpnonce='invalid'))[0] == 403, 'bad nonce denied')
    for override in ({'doctor':str(meta['draft'])}, {'doctor':'99999999'}, {'rating':'0'}, {'rating':'-4'}, {'rating':'6'}, {'date':'2024-02-30'}, {'name':' '}, {'comment':' '}):
        check(request(admin, endpoint, dict(valid, **override))[0] == 400, 'invalid input rejected: ' + repr(override))
    code, headers, body = request(admin, endpoint, valid)
    check(code == 302 and 'saved=1' in headers.get('Location', ''), 'first review saved: ' + str(code) + ' ' + body[-500:])
    second = dict(valid, name='Second Name', comment='Second comment', rating='5', date='2025-08-01')
    code, _, body = request(admin, endpoint, second)
    check(code == 302, 'second review saved despite unique IP placeholder: ' + str(code) + ' ' + body[-500:])
    def db(action): subprocess.run(['php', 'tools/hj-review-entry/tests/verify-wp.php', action], check=True)
    db('unknown-add'); check(request(admin, endpoint, valid)[0] == 400, 'unknown required column rejected'); db('unknown-drop')
    db('myisam'); check(request(admin, endpoint, valid)[0] == 400, 'non-transactional table rejected'); db('innodb')
    db('verify')

if __name__ == '__main__':
    server = subprocess.Popen(['php', '-S', '127.0.0.1:8999', '-t', '/wordpress'], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    try:
        for _ in range(80):
            try: request(session(), '/wp-login.php'); break
            except OSError: time.sleep(0.25)
        else: raise RuntimeError('fixture server did not start')
        run()
    finally:
        server.terminate(); server.wait(timeout=10)
