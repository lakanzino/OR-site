#!/usr/bin/env python3
"""Public AJAX checks against the disposable CI database only."""
import json
import os
import subprocess
import time
import urllib.error
import urllib.parse
import urllib.request

if os.environ.get('GITHUB_ACTIONS') != 'true' or os.environ.get('HJ_DISPOSABLE_MARIADB_FIXTURE') != '1' or os.environ.get('HJ_TEST_PLUGIN') != 'review-marker-http-current':
    raise SystemExit('Disposable CI fixture only.')
BASE = 'http://127.0.0.1:8999'

def request(path, data=None):
    body = urllib.parse.urlencode(data).encode() if data is not None else None
    req = urllib.request.Request(BASE + path, data=body, headers={'Host': 'example.invalid'})
    try:
        res = urllib.request.urlopen(req, timeout=30)
    except urllib.error.HTTPError as exc:
        res = exc
    return res.code, res.read().decode('utf-8', errors='replace')

def check(value, message):
    if not value:
        raise AssertionError(message)
    print('PASS:', message, flush=True)

def run():
    with open(os.path.join(os.environ['RUNNER_TEMP'], 'review-marker-meta.json'), encoding='utf-8') as f:
        meta = json.load(f)
    endpoint = '/wp-admin/admin-ajax.php'
    params = {'action': 'hj_profile_load_reviews', 'nonce': meta['nonce'], 'post_id': meta['demo']}
    code, text = request(endpoint, params)
    check(code == 200, 'public AJAX succeeds')
    data = json.loads(text)
    comments = [r['comment'] for r in data['data']['reviews']]
    check(data['success'] and data['data']['count'] == 4 and '[نظر آزمایشی] Synthetic <b>approved</b> & text' in comments and 'Ordinary synthetic text' in comments and not any('Pending synthetic' in c for c in comments), 'public AJAX keeps label, text, status and non-demo content')
    code, text = request(endpoint, dict(params, post_id=meta['other']))
    comments = [r['comment'] for r in json.loads(text)['data']['reviews']]
    check(code == 200 and comments == ['[نظر آزمایشی HJ-12345678-1234-4567-8123-123456789abc] Unmarked physician'], 'unmarked doctor not reformatted')
    check(request(endpoint, dict(params, nonce='invalid'))[0] == 403, 'AJAX invalid nonce rejected')
    subprocess.run(['php', 'tools/hamyar-php-review/review-marker-verify-wp.php'], check=True)

if __name__ == '__main__':
    server = subprocess.Popen(['php', '-S', '127.0.0.1:8999', '-t', '/wordpress'], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    try:
        for _ in range(80):
            try:
                request('/wp-login.php'); break
            except OSError:
                time.sleep(0.25)
        else:
            raise RuntimeError('fixture server did not start')
        run()
    finally:
        server.terminate(); server.wait(timeout=10)
