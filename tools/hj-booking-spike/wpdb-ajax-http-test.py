#!/usr/bin/env python3
"""Run the actual ajax_save from a patched disposable plugin copy, never production."""
import hashlib
import http.cookiejar
import json
import os
import shutil
import signal
import subprocess
import time
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

if os.getenv('GITHUB_ACTIONS') != 'true' or os.getenv('HJ_DISPOSABLE_MARIADB_FIXTURE') != '1':
    raise SystemExit('Disposable CI only')
workspace = Path(os.environ['GITHUB_WORKSPACE'])
plugin = Path('/wordpress/wp-content/plugins/hamyar-doctors')
mu = Path('/wordpress/wp-content/mu-plugins/hj-wpdb-ajax-fixture.php')
meta = Path(os.environ['RUNNER_TEMP']) / 'hj-wpdb-ajax-meta.json'
BASE = 'http://127.0.0.1:8997'
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, hdrs, url): return None

def session(): return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()), NoRedirect())
def request(opener, path, data=None):
    body = urllib.parse.urlencode(data).encode() if data is not None else None
    req = urllib.request.Request(BASE + path, data=body, headers={'Host': 'example.invalid'})
    try: res = opener.open(req, timeout=30)
    except urllib.error.HTTPError as e: res = e
    return res.code, res.read().decode('utf-8', errors='replace')
def check(condition, label):
    if not condition: raise AssertionError(label)
    print('PASS:', label, flush=True)
def login(user):
    opener = session()
    check(request(opener, '/wp-login.php')[0] == 200, user + ' login form')
    code, _ = request(opener, '/wp-login.php', {'log': user, 'pwd': 'disposable-test-password', 'wp-submit': 'Log In', 'redirect_to': 'http://example.invalid/wp-admin/', 'testcookie': '1'})
    check(code == 302, user + ' login')
    code, body = request(opener, '/wp-admin/admin-ajax.php', {'action': 'hj_ci_dashboard_nonce'})
    check(code == 200 and json.loads(body)['success'], user + ' nonce')
    return opener, json.loads(body)['data']['nonce']
def state(opener, doctor):
    code, body = request(opener, '/wp-admin/admin-ajax.php', {'action': 'hj_ci_schedule_state', 'pid': str(doctor)})
    check(code == 200, 'authorized synthetic state read')
    return json.loads(body)['data']

server = None
try:
    if plugin.exists() or mu.exists(): raise RuntimeError('Refusing existing plugin or MU fixture path')
    shutil.copytree(workspace / 'plugins/hamyar-doctors', plugin, ignore=shutil.ignore_patterns('tests', '__pycache__'))
    dashboard = plugin / 'includes/dashboard.php'
    source = dashboard.read_text(encoding='utf-8')
    old = "self::set( $pid, 'working_hours', $rows );"
    replacement = "if ( hj_ci_port_save_hours( $pid, $rows )['status'] !== 'saved' ) { self::fail( 'Fixture schedule save failed.', 503 ); }"
    if source.count(old) != 1: raise RuntimeError('Expected one exact dashboard hours writer')
    dashboard.write_text(source.replace(old, replacement, 1), encoding='utf-8')
    subprocess.run(['php', 'tools/hj-booking-spike/wpdb-ajax-setup.php'], check=True)
    mu.parent.mkdir(exist_ok=True)
    mu.symlink_to(workspace / 'tools/hj-booking-spike/wpdb-ajax-fixture-mu.php')
    doctor_id = json.loads(meta.read_text(encoding='utf-8'))['doctor']
    server = subprocess.Popen(['php', '-S', '127.0.0.1:8997', '-t', '/wordpress'], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, start_new_session=True)
    for _ in range(80):
        try: request(session(), '/wp-login.php'); break
        except OSError: time.sleep(.25)
    else: raise RuntimeError('fixture HTTP server did not start')
    owner, owner_nonce = login('synthetic_doctor_a')
    other, other_nonce = login('synthetic_doctor_b')
    url = '/wp-admin/admin-ajax.php'
    common = {'action': 'hj_dash_save', 'pid': str(doctor_id), 'name_fa': 'Synthetic Doctor', 'hours[wednesday][start]': '09:00', 'hours[wednesday][end]': '12:00'}
    check(request(owner, url, dict(common, _hjn='bad'))[0] == 403, 'actual ajax_save rejects invalid nonce')
    check(request(other, url, dict(common, _hjn=other_nonce))[0] == 403, 'actual ajax_save denies another doctor')
    code, text = request(owner, url, dict(common, _hjn=owner_nonce))
    check(code == 200 and json.loads(text)['success'], 'actual ajax_save writes canonical schedule through wpdb port')
    saved = state(owner, doctor_id); row = saved['snapshot']
    check(int(row['revision']) == 1 and json.loads(row['rows_json'])[0]['start_time'] == '09:00:00' and row['rows_hash'] == hashlib.sha256(row['rows_json'].encode()).hexdigest(), 'first canonical revision and hash persisted')
    check(saved['display'][0]['start_time'] == '09:00:00', 'best-effort display mirror saved')
    changed = dict(common, _hjn=owner_nonce, **{'hours[wednesday][start]': '10:00', 'hours[wednesday][end]': '11:00', 'hj_ci_mirror_fail': '1'})
    code, text = request(owner, url, changed)
    check(code == 200 and json.loads(text)['success'], 'display mirror failure does not roll back canonical schedule')
    saved = state(owner, doctor_id); row = saved['snapshot']
    check(int(row['revision']) == 2 and json.loads(row['rows_json'])[0]['start_time'] == '10:00:00' and saved['display'][0]['start_time'] == '09:00:00', 'mirror failure leaves canonical revision independent')
    killed = dict(common, _hjn=owner_nonce, hj_ci_kill_before_commit='1')
    code, text = request(owner, url, killed)
    check(code == 503 and not json.loads(text)['success'], 'killed transaction does not claim successful save')
    saved = state(owner, doctor_id); row = saved['snapshot']
    check(int(row['revision']) == 2 and json.loads(row['rows_json'])[0]['start_time'] == '10:00:00', 'kill before commit preserved prior canonical revision')
finally:
    if server:
        os.killpg(server.pid, signal.SIGTERM)
        try: server.wait(timeout=10)
        except subprocess.TimeoutExpired:
            os.killpg(server.pid, signal.SIGKILL); server.wait()
    if mu.is_symlink(): mu.unlink()
    if plugin.is_dir(): shutil.rmtree(plugin)
    meta.unlink(missing_ok=True)
