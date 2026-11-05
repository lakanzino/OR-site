#!/usr/bin/env python3
"""Synthetic HTTP tests in isolated GitHub Actions CI MariaDB ONLY."""
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

if os.environ.get('GITHUB_ACTIONS') != 'true' or os.environ.get('HJ_DISPOSABLE_MARIADB_FIXTURE') != '1' or os.environ.get('HJ_TEST_PLUGIN') != 'vote-manager-http-0.3.12':
    raise SystemExit('Disposable CI fixture only.')
BASE = 'http://127.0.0.1:8999'

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, hdrs, url):
        return None

def session():
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()), NoRedirect())

def request(opener, path, data=None):
    if isinstance(data, dict):
        data = urllib.parse.urlencode(data).encode()
    req = urllib.request.Request(BASE + path, data=data, headers={'Host': 'example.invalid'})
    try:
        response = opener.open(req, timeout=30)
    except urllib.error.HTTPError as exc:
        response = exc
    return response.code, response.headers, response.read().decode('utf-8', errors='replace')

def check(condition, message):
    if not condition:
        raise AssertionError(message)
    print('PASS:', message, flush=True)

def login(opener, user, password):
    check(request(opener, '/wp-login.php')[0] == 200, user + ' login form')
    code, _, body = request(opener, '/wp-login.php', {'log': user, 'pwd': password, 'wp-submit': 'Log In', 'redirect_to': 'http://example.invalid/wp-admin/', 'testcookie': '1'})
    check(code == 302, user + ' login ' + str(code) + ' ' + body[-300:])

def form_for(page, vote):
    for form in re.findall(r'<form\b.*?</form>', page, re.I | re.S):
        fields = dict((html.unescape(k), html.unescape(v)) for k, v in re.findall(r'<input\b[^>]*name="([^"]+)"[^>]*value="([^"]*)"', form, re.I))
        if fields.get('vote') == str(vote):
            return fields
    raise AssertionError('vote form missing: ' + str(vote) + ' ' + page[-500:])

def db(action):
    subprocess.run(['php', 'tools/hj-vote-manager/tests/manager-http-verify-wp.php', action], check=True)

def run():
    meta = json.load(open(os.path.join(os.environ['RUNNER_TEMP'], 'manager-test-meta.json'), encoding='utf-8'))
    admin = session(); sub = session(); anon = session()
    login(admin, 'fixture_admin', 'disposable-password-please-ignore')
    login(sub, 'manager_subscriber', 'disposable-password')
    pagepath = '/wp-admin/tools.php?page=hj-vote-manager&doctor=' + str(meta['demo'])
    code, _, page = request(admin, pagepath)
    check(code == 200, 'administrator can view manager')
    a = form_for(page, 101); b = form_for(page, 102)
    check('Unmarked profile synthetic comment' not in page and 'Synthetic comment' in page, 'only selected demo vote shown; marker hidden in editor')
    valid = dict(a, state='rejected', rating='2', body='Synthetic revised', reason='Synthetic moderation reason')
    endpoint = '/wp-admin/admin-post.php'
    check(request(sub, endpoint, valid)[0] == 403, 'subscriber with admin nonce denied')
    check(request(anon, endpoint, valid)[0] in (400, 403), 'anonymous denied')
    check(request(admin, endpoint + '?action=hj_vote_manager_save')[0] == 405, 'GET mutation denied')
    check(request(admin, endpoint, dict(valid, _wpnonce='invalid'))[0] == 403, 'invalid nonce denied')
    check(request(admin, endpoint, dict(valid, reason=''))[0] == 400, 'reason required')
    check(request(admin, endpoint, dict(valid, doctor=str(meta['other'])))[0] == 400, 'unmarked doctor denied')
    check(request(admin, endpoint, dict(valid, body=''))[0] == 400, 'lab marker without body refused')
    check(request(admin, endpoint, dict(valid, state='approved', rating='5', body='Synthetic comment'))[0] == 400, 'no-op refused')
    db('initial')
    code, h, body = request(admin, endpoint, valid)
    check(code == 302 and 'saved=1' in h.get('Location', ''), 'administrator edits and rejects synthetic vote: ' + str(code) + ' ' + body[-400:])
    check(request(admin, endpoint, valid)[0] == 400, 'stale submitted snapshot refused')
    code, _, page = request(admin, pagepath)
    check(code == 200 and 'Synthetic moderation reason' in page and 'Synthetic revised' in page, 'history and edited text visible to administrator')
    update_b = dict(b, state='approved', rating='3', body='', reason='Approve vote without a comment')
    check(request(admin, endpoint, update_b)[0] == 302, 'null comment safely changed to empty')
    a2 = form_for(request(admin, pagepath)[2], 101)
    db('fail-audit-setup')
    failed = dict(a2, state='approved', rating='4', body='Should roll back', reason='force audit failure')
    check(request(admin, endpoint, failed)[0] == 400, 'audit write failure refuses save')
    db('final')

if __name__ == '__main__':
    server = subprocess.Popen(['php', '-S', '127.0.0.1:8999', '-t', '/wordpress'], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    try:
        opener = session()
        for _ in range(80):
            try:
                request(opener, '/wp-login.php'); break
            except OSError:
                time.sleep(0.25)
        else:
            raise RuntimeError('fixture server did not start')
        run()
    finally:
        server.terminate(); server.wait(timeout=10)
