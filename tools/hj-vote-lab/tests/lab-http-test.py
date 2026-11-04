#!/usr/bin/env python3
"""HTTP test using a NEW throwaway CI WordPress with local MariaDB; never run on a real site."""
import html
import http.cookiejar
import json
import os
import re
import subprocess
import sys
import time
import urllib.error
import urllib.parse
import urllib.request

if os.environ.get('GITHUB_ACTIONS') != 'true' or os.environ.get('HJ_DISPOSABLE_MARIADB_FIXTURE') != '1' or os.environ.get('HJ_TEST_PLUGIN') != 'vote-lab-http-0.3.12':
    raise SystemExit('Disposable CI fixture only.')
BASE = 'http://127.0.0.1:8999'

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, hdrs, url):
        return None

def session():
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()), NoRedirect())

def request(opener, path, data=None, headers=None):
    headers = dict(headers or {})
    headers['Host'] = 'example.invalid'
    if isinstance(data, dict):
        data = urllib.parse.urlencode(data).encode()
    req = urllib.request.Request(BASE + path, data=data, headers=headers)
    try:
        response = opener.open(req, timeout=30)
    except urllib.error.HTTPError as exc:
        response = exc
    return response.code, response.headers, response.read().decode('utf-8', errors='replace')

def check(value, message):
    if not value:
        raise AssertionError(message)
    print('PASS:', message, flush=True)

def login(opener, user, password):
    s, _, _ = request(opener, '/wp-login.php')
    check(s == 200, f'{user} login form {s}')
    s, _, body = request(opener, '/wp-login.php', {'log': user, 'pwd': password, 'wp-submit': 'Log In', 'redirect_to': 'http://example.invalid/wp-admin/', 'testcookie': '1'})
    check(s == 302, f'{user} login response {s}: {body[-400:]}')

def multipart(fields, csv):
    boundary = 'hjlabdisposablehttpboundary'
    chunks = []
    for name, value in fields.items():
        chunks.append((f'--{boundary}\r\nContent-Disposition: form-data; name="{name}"\r\n\r\n{value}\r\n').encode())
    chunks.append((f'--{boundary}\r\nContent-Disposition: form-data; name="csv"; filename="fake.csv"\r\nContent-Type: text/csv\r\n\r\n{csv}\r\n--{boundary}--\r\n').encode())
    return b''.join(chunks), {'Content-Type': f'multipart/form-data; boundary={boundary}'}

def run():
    admin = session()
    login(admin, 'fixture_admin', 'disposable-password-please-ignore')
    s, h, page = request(admin, '/wp-admin/tools.php?page=hj-vote-lab')
    check(s == 200 and 'Synthetic doctor HTTP' in page, f'admin lab page {s}: {page[-500:]}')
    nonce_match = re.search(r'name="_wpnonce" value="([^"]+)"', page)
    batch_match = re.search(r'name="batch" value="([^"]+)"', page)
    doctor_match = re.search(r'<option value="(\d+)"', page)
    link_match = re.search(r'href="([^"]*action=hj_vote_lab_report[^"]+)"', page)
    check(all((nonce_match, batch_match, doctor_match, link_match)), 'form tokens, demo choice and report link')
    nonce, batch, doctor = [m.group(1) for m in (nonce_match, batch_match, doctor_match)]
    link = urllib.parse.urlsplit(html.unescape(link_match.group(1)))
    report_path = link.path + '?' + link.query
    s, h, text = request(admin, report_path)
    report = json.loads(text)
    check(s == 200 and 'attachment' in h.get('Content-Disposition', '') and report['vote_table']['all_rows_count'] == 0 and report['schema']['comment_column_will_be_added_on_import'] is True and report['vote_table']['lab_marker_count'] is None, 'initial JSON attachment indicates missing comment column')
    fields = {'action': 'hj_vote_lab_import', '_wpnonce': nonce, 'batch': batch, 'doctor': doctor, 'state': 'approved', 'confirm_demo': '1', 'reviews': '1 | ignored because file selected'}
    s, _, _ = request(admin, '/wp-admin/admin-post.php', {'action': 'hj_vote_lab_import'})
    check(s == 403, 'missing nonce rejected')
    missing_confirmation = dict(fields); missing_confirmation.pop('confirm_demo')
    body, headers = multipart(missing_confirmation, 'rating,comment\r\n5,Synthetic HTTP private text')
    check(request(admin, '/wp-admin/admin-post.php', body, headers)[0] == 400, 'missing confirmation rejected')
    s, _, text = request(admin, report_path)
    check(json.loads(text)['schema']['comment_column_will_be_added_on_import'] is True, 'rejected request did not alter schema')
    body, headers = multipart(fields, 'rating,comment\r\n5,Synthetic HTTP private text\r\n4,Another synthetic text')
    s, h, out = request(admin, '/wp-admin/admin-post.php', body, headers)
    check(s == 302 and 'result=ok' in h.get('Location', ''), f'CSV import HTTP redirect {s}: {out[-500:]}')
    s, h, text = request(admin, report_path)
    report = json.loads(text)
    check(s == 200 and report['schema']['comment_column_will_be_added_on_import'] is False and report['vote_table']['all_rows_count'] == 2 and report['vote_table']['lab_marker_count'] == 2 and report['vote_table']['status_groups']['approved'] == 2 and report['bridge']['approved'] == 2 and report['public_review_reader']['returned_comments_count'] == 2, f'actual post-import aggregate: {text}')
    check('Synthetic HTTP private text' not in text and 'Synthetic doctor HTTP' not in text, 'report omits review text and name')
    s, _, _ = request(admin, '/wp-admin/admin-post.php', body, headers)
    check(s == 400, 'replayed batch rejected over HTTP')
    s, _, text = request(admin, report_path)
    check(json.loads(text)['vote_table']['all_rows_count'] == 2, 'replay inserted no additional rows')
    sub = session(); login(sub, 'lab_http_subscriber', 'disposable-password')
    check(request(sub, report_path)[0] == 403, 'subscriber report forbidden')
    check(request(sub, '/wp-admin/admin-post.php', body, headers)[0] == 403, 'subscriber import forbidden with stolen nonce')
    check(request(session(), report_path)[0] in (302, 400, 403), 'anonymous report forbidden')
    check(request(admin, '/wp-admin/admin-post.php?action=hj_vote_lab_import')[0] == 405, 'GET import forbidden')
    subprocess.run(['php', 'tools/hj-vote-lab/tests/lab-http-verify-wp.php'], check=True, timeout=30)
    print('DONE: disposable MariaDB HTTP vote lab fixture.', flush=True)

server = subprocess.Popen(['php', '-S', '127.0.0.1:8999', '-t', '/wordpress'], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
try:
    time.sleep(2)
    run()
except BaseException as exc:
    print(f'::error::{str(exc).replace(chr(10), " ")}', flush=True)
    raise
finally:
    server.terminate()
    try:
        server.wait(timeout=5)
    except subprocess.TimeoutExpired:
        server.kill()
        server.wait()
