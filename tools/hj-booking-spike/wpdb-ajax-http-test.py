#!/usr/bin/env python3
"""Run the actual ajax_save from a patched disposable plugin copy, never production."""
import concurrent.futures
import hashlib
import http.client
import http.cookiejar
import json
import os
import shutil
import signal
import subprocess
import threading
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
def ajax(opener, payload):
    code, body = request(opener, '/wp-admin/admin-ajax.php', payload)
    if code != 200: raise AssertionError(f'Unexpected AJAX status {code}: {body[:500]}')
    data = json.loads(body)
    if not data['success']: raise AssertionError('Unexpected AJAX failure')
    return data['data']
def booking_nonce(opener): return ajax(opener, {'action': 'hj_ci_booking_nonce'})['nonce']
def key(n): return f'{n:032x}'
def booking_payload(doctor, nonce, start, keyvalue, fault=''):
    return {'action': 'hj_ci_create', 'doctor': str(doctor), 'nonce': nonce, 'date': '2030-01-02', 'start': start, 'key': keyvalue, 'phone': '09123456789', 'national': '1234567891', 'fault': fault}
def slots(opener, doctor, nonce):
    return ajax(opener, {'action': 'hj_ci_slots', 'doctor': str(doctor), 'nonce': nonce, 'date': '2030-01-02'})
def audit(opener, doctor): return ajax(opener, {'action': 'hj_ci_booking_audit', 'doctor': str(doctor)})
barriers = []
def barrier(name):
    prefix = Path(os.environ['RUNNER_TEMP']) / ('hj-wpdb-test-' + name)
    ready_path, release_path = Path(str(prefix) + '-ready'), Path(str(prefix) + '-released')
    ready_path.unlink(missing_ok=True); release_path.unlink(missing_ok=True)
    barriers.extend((ready_path, release_path))
    return ready_path, release_path
def wait_ready(paths):
    end = time.monotonic() + 18
    while not all(p.exists() for p in paths) and time.monotonic() < end: time.sleep(.01)
    if not all(p.exists() for p in paths): raise AssertionError('Missing fixture barriers: ' + ', '.join(p.name for p in paths if not p.exists()))
    check(True, 'all fixture lock-order barriers reached')

server = None
ready = None
released = None
created_plugin = False
created_mu = False
try:
    if plugin.exists() or mu.exists(): raise RuntimeError('Refusing existing plugin or MU fixture path')
    shutil.copytree(workspace / 'plugins/hamyar-doctors', plugin, ignore=shutil.ignore_patterns('tests', '__pycache__'))
    created_plugin = True
    dashboard = plugin / 'includes/dashboard.php'
    source = dashboard.read_text(encoding='utf-8')
    old = "self::set( $pid, 'working_hours', $rows );"
    replacement = "if ( hj_ci_port_save_hours( $pid, $rows )['status'] !== 'saved' ) { self::fail( 'Fixture schedule save failed.', 503 ); }"
    if source.count(old) != 1: raise RuntimeError('Expected one exact dashboard hours writer')
    dashboard.write_text(source.replace(old, replacement, 1), encoding='utf-8')
    subprocess.run(['php', 'tools/hj-booking-spike/wpdb-ajax-setup.php'], check=True)
    mu.parent.mkdir(exist_ok=True)
    mu.symlink_to(workspace / 'tools/hj-booking-spike/wpdb-ajax-fixture-mu.php')
    created_mu = True
    doctor_id = json.loads(meta.read_text(encoding='utf-8'))['doctor']
    server = subprocess.Popen(['php', '-S', '127.0.0.1:8997', '-t', '/wordpress'], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, start_new_session=True, env=dict(os.environ, PHP_CLI_SERVER_WORKERS='16'))
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
    def concurrent_save(hour):
        payload = dict(common, _hjn=owner_nonce, **{'hours[wednesday][start]': f'{hour:02d}:00', 'hours[wednesday][end]': '16:30', 'hj_ci_mirror_fail': '1'})
        return request(owner, url, payload)
    with concurrent.futures.ThreadPoolExecutor(max_workers=8) as pool:
        responses = list(pool.map(concurrent_save, range(8, 16)))
    check(all(code == 200 and json.loads(body)['success'] for code, body in responses), 'eight concurrent actual AJAX schedule saves succeeded')
    row = state(owner, doctor_id)['snapshot']
    check(int(row['revision']) == 10 and row['rows_hash'] == hashlib.sha256(row['rows_json'].encode()).hexdigest() and json.loads(row['rows_json'])[0]['start_time'] in [f'{h:02d}:00:00' for h in range(8, 16)], 'concurrent wpdb saves serialized without lost revisions or torn snapshot')

    # Set display to 10–11, then write canonical 9–12 without touching display.
    display = dict(common, _hjn=owner_nonce, **{'hours[wednesday][start]': '10:00', 'hours[wednesday][end]': '11:00'})
    check(ajax(owner, display) is not None, 'actual dashboard saves display baseline')
    open_hours = dict(common, _hjn=owner_nonce, hj_ci_mirror_fail='1')
    check(ajax(owner, open_hours) is not None, 'actual dashboard publishes test schedule')
    saved = state(owner, doctor_id); open_revision = int(saved['snapshot']['revision'])
    check(saved['display'][0]['start_time'] == '10:00:00' and json.loads(saved['snapshot']['rows_json'])[0]['start_time'] == '09:00:00', 'display intentionally differs from canonical snapshot')
    patients = [login(f'synthetic_race_{i}')[0] for i in range(8)]
    nonces = [booking_nonce(p) for p in patients]
    first_slots = slots(patients[0], doctor_id, nonces[0])
    check(first_slots['status'] == 'ok' and first_slots['revision'] == open_revision and '2030-01-02 05:30:00' in first_slots['slots'], 'actual snapshot supplies slot excluded by stale display')

    # The server commits and kills its own DB connection, signals the client,
    # then waits. Close the actual HTTP socket before releasing its response.
    lost_key = key(31001)
    marker = Path(os.environ['RUNNER_TEMP']) / ('hj-booking-commit-' + lost_key)
    ready, released = Path(str(marker) + '-ready'), Path(str(marker) + '-released')
    ready.unlink(missing_ok=True); released.unlink(missing_ok=True)
    cookie_handler = next(h for h in patients[0].handlers if isinstance(h, urllib.request.HTTPCookieProcessor))
    cookie = '; '.join(f'{c.name}={c.value}' for c in cookie_handler.cookiejar if c.name.startswith('wordpress_logged_in_'))
    check(bool(cookie), 'synthetic patient authentication cookie exists')
    lost = booking_payload(doctor_id, nonces[0], '2030-01-02 05:30:00', lost_key, 'disconnect_after_commit')
    conn = http.client.HTTPConnection('127.0.0.1', 8997, timeout=30)
    try:
        conn.request('POST', url, urllib.parse.urlencode(lost), {'Host': 'example.invalid', 'Cookie': cookie, 'Content-Type': 'application/x-www-form-urlencoded'})
        deadline = time.monotonic() + 25
        while not ready.exists() and time.monotonic() < deadline: time.sleep(.02)
        check(ready.exists(), 'COMMIT succeeded and DB connection killed before response')
        conn.close()  # no response is consumed; caller must treat outcome as unknown
    finally:
        conn.close(); released.touch()
    retry_payload = dict(lost, fault='')
    replay = ajax(patients[0], retry_payload)
    check(replay['status'] == 'replayed' and replay['id'] > 0, 'same-key retry resolves unknown commit without duplicate')
    with concurrent.futures.ThreadPoolExecutor(max_workers=4) as pool:
        retries = list(pool.map(lambda _: ajax(patients[0], retry_payload), range(4)))
    check(all(r['status'] == 'replayed' and r['id'] == replay['id'] for r in retries), 'concurrent same-key retries return the same committed booking')
    conflict = ajax(patients[0], dict(retry_payload, start='2030-01-02 06:05:00'))
    check(conflict['status'] == 'idempotency_conflict', 'same key cannot be reused for changed request')
    cross_patient = dict(retry_payload, nonce=nonces[1])
    check(ajax(patients[1], cross_patient)['status'] == 'idempotency_conflict', 'another patient cannot replay a booking key')
    before = audit(owner, doctor_id)
    check(len(before) == 1 and before[0]['request_key'] == lost_key and before[0]['details_id'] is not None, 'lost response left exactly one booking and atomic details')

    killed_key = key(31002)
    killed_booking = booking_payload(doctor_id, nonces[1], '2030-01-02 06:05:00', killed_key, 'kill_before_commit')
    check(ajax(patients[1], killed_booking)['status'] == 'unknown', 'booking database connection killed before commit yields unknown')
    check(len(audit(owner, doctor_id)) == 1, 'killed precommit booking and details rolled back')
    after_kill = ajax(patients[1], dict(killed_booking, fault=''))
    check(after_kill['status'] == 'created', 'retry after precommit kill creates one booking')

    # Nine simultaneous HTTP requests: actual ajax_save closes Wednesday, eight
    # distinct authenticated patients compete on two slots near the boundary.
    gate = threading.Barrier(9)
    race_payloads = [booking_payload(doctor_id, nonces[i], '2030-01-02 07:15:00' if i % 2 == 0 else '2030-01-02 07:50:00', key(32000+i)) for i in range(8)]
    def race_patient(i):
        gate.wait(timeout=15)
        return ajax(patients[i], race_payloads[i])
    def race_doctor():
        gate.wait(timeout=15)
        return ajax(owner, dict(common, _hjn=owner_nonce, hj_ci_mirror_fail='1', **{'hours[wednesday][closed]': '1'}))
    with concurrent.futures.ThreadPoolExecutor(max_workers=9) as pool:
        futures = [pool.submit(race_patient, i) for i in range(8)]
        closing = pool.submit(race_doctor)
        results = [f.result(timeout=45) for f in futures]
        check(closing.result(timeout=45) is not None, 'actual ajax_save closed hours concurrently with eight patients')
    check(all(r['status'] in ('created', 'invalid_slot') for r in results), 'concurrent patients receive only valid terminal outcomes')
    created = [r for r in results if r['status'] == 'created']
    check(len(created) <= 2 and len({r['id'] for r in created}) == len(created) and all(r['revision'] == open_revision for r in created), 'only open-revision bookings win, with no duplicated slots')
    closed_state = state(owner, doctor_id); closed_revision = int(closed_state['snapshot']['revision'])
    check(closed_revision == open_revision + 1 and json.loads(closed_state['snapshot']['rows_json'])[0]['is_closed'] == 1, 'closed snapshot published after race')
    closed_slots = slots(patients[0], doctor_id, nonces[0])
    check(closed_slots['status'] == 'ok' and closed_slots['revision'] == closed_revision and not closed_slots['slots'], 'slot reader uses closed snapshot despite stale open display')
    for i in range(8):
        payload = booking_payload(doctor_id, nonces[i], '2030-01-02 07:15:00', key(33000+i))
        check(ajax(patients[i], payload)['status'] == 'invalid_slot', 'patient cannot book after closure')
    # A retry remains stable even if the doctor closed hours after commit.
    check(ajax(patients[0], retry_payload)['id'] == replay['id'], 'committed retry survives later schedule closure')
    final = audit(owner, doctor_id)
    raced_rows = [r for r in final if r['request_key'] in {payload['key'] for payload in race_payloads}]
    check(len({r['starts_at'] for r in raced_rows}) == len(raced_rows), 'competing patients cannot duplicate a boundary slot')
    expected_ids = {replay['id'], after_kill['id'], *(r['id'] for r in created)}
    check(len(final) == len(expected_ids) and {int(r['id']) for r in final} == expected_ids and len({r['request_key'] for r in final}) == len(final) and all(int(r['revision']) == open_revision and r['details_id'] is not None for r in final), 'no lost or duplicate bookings; each is linked to open revision and details')

    # Deterministic closure-first ordering: doctor owns the DB mutex before
    # eight distinct patient requests start; at least one reaches the lock while held.
    ajax(owner, open_hours)
    hold_token = key(34000)
    save_ready, save_release = barrier('save-hold-' + hold_token)
    waiting = [barrier('booking-wait-' + key(34100+i))[0] for i in range(8)]
    with concurrent.futures.ThreadPoolExecutor(max_workers=9) as pool:
        closer = pool.submit(ajax, owner, dict(common, _hjn=owner_nonce, hj_ci_mirror_fail='1', hj_ci_hold_lock=hold_token, **{'hours[wednesday][closed]': '1'}))
        wait_ready([save_ready])
        patient_gate = threading.Barrier(8)
        def waiting_patient(i):
            patient_gate.wait(timeout=15)
            return ajax(patients[i], booking_payload(doctor_id, nonces[i], '2030-01-02 06:40:00', key(34100+i), 'signal_before_lock'))
        future_patients = [pool.submit(waiting_patient, i) for i in range(8)]
        try:
            deadline = time.monotonic() + 18
            while not any(p.exists() for p in waiting) and time.monotonic() < deadline: time.sleep(.01)
            check(any(p.exists() for p in waiting), 'at least one concurrent patient reached the held mutex')
        finally: save_release.touch()
        check(closer.result(timeout=40) is not None, 'held doctor closure committed')
        after_close = [f.result(timeout=40) for f in future_patients]
    check(all(r['status'] == 'invalid_slot' for r in after_close), 'eight waiting patients cannot book after closure obtains lock first')
    check(len(audit(owner, doctor_id)) == len(expected_ids), 'closure-first race leaves no phantom bookings')

    # Deterministic booking-first ordering: a patient owns the mutex, doctor
    # reaches the same lock, then the patient commits before closure proceeds.
    ajax(owner, open_hours)
    before_rev = int(state(owner, doctor_id)['snapshot']['revision'])
    first_key = key(35000)
    booking_ready, booking_release = barrier('booking-hold-' + first_key)
    doctor_ready, _ = barrier('save-wait-' + first_key)
    first_payload = booking_payload(doctor_id, nonces[2], '2030-01-02 06:40:00', first_key, 'hold_after_lock')
    with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
        first = pool.submit(ajax, patients[2], first_payload)
        wait_ready([booking_ready])
        closer = pool.submit(ajax, owner, dict(common, _hjn=owner_nonce, hj_ci_mirror_fail='1', hj_ci_signal_lock=first_key, **{'hours[wednesday][closed]': '1'}))
        try: wait_ready([doctor_ready])
        finally: booking_release.touch()
        prior = first.result(timeout=40)
        check(closer.result(timeout=40) is not None, 'doctor closure completed after waiting booking')
    check(prior['status'] == 'created' and prior['revision'] == before_rev, 'booking holding lock commits against open snapshot before closure')
    check(ajax(patients[2], dict(first_payload, fault=''))['id'] == prior['id'], 'booking-first retry survives closure')
    check(ajax(patients[3], booking_payload(doctor_id, nonces[3], '2030-01-02 07:15:00', key(35001)))['status'] == 'invalid_slot', 'new booking after ordered closure fails')
    final_ordered = audit(owner, doctor_id)
    check(len(final_ordered) == len(expected_ids) + 1 and sum(int(r['id']) == prior['id'] and int(r['revision']) == before_rev and r['details_id'] is not None for r in final_ordered) == 1, 'ordered race commits exactly one open-revision booking and its details')

    # Real InnoDB lock timeout: three bounded attempts, no blind replay;
    # release the dashboard writer, then the same idempotency key reconciles.
    timeout_token = key(36000)
    timeout_ready, timeout_release = barrier('save-hold-' + timeout_token)
    with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
        holder = pool.submit(ajax, owner, dict(open_hours, hj_ci_hold_lock=timeout_token))
        wait_ready([timeout_ready])
        started = time.monotonic()
        try:
            busy = ajax(patients[0], retry_payload)
            elapsed = time.monotonic() - started
            check(busy['status'] == 'busy' and elapsed >= 2.5, 'wpdb booking retry is bounded on real lock timeout')
        finally: timeout_release.touch()
        check(holder.result(timeout=40) is not None, 'dashboard lock released after timeout')
    check(ajax(patients[0], retry_payload)['id'] == replay['id'], 'same key reconciles after lock timeout and release')
    check(len(audit(owner, doctor_id)) == len(expected_ids) + 1, 'timeout retries caused no extra booking')

    # Minimal integrated status/cancel/rebook path, all on the same mutex.
    owner_booking_nonce = booking_nonce(owner)
    status_payload = {'action': 'hj_ci_status', 'doctor': str(doctor_id), 'nonce': owner_booking_nonce, 'id': str(after_kill['id']), 'status': 'confirmed'}
    check(request(patients[1], url, dict(status_payload, nonce=nonces[1]))[0] == 403, 'patient cannot change booking status')
    check(ajax(owner, status_payload)['status'] == 'updated', 'doctor confirms booking under shared mutex')
    check(ajax(owner, dict(status_payload, status='cancelled'))['status'] == 'updated', 'doctor cancels booking under shared mutex')
    rebook_key = key(37000)
    rebook = ajax(patients[4], booking_payload(doctor_id, nonces[4], '2030-01-02 06:05:00', rebook_key))
    check(rebook['status'] == 'created' and rebook['id'] != after_kill['id'], 'patient rebooks cancelled interval')
    check(ajax(patients[5], booking_payload(doctor_id, nonces[5], '2030-01-02 06:05:00', key(37001)))['status'] == 'invalid_slot', 'rebooked interval cannot be taken twice')
    last = audit(owner, doctor_id)
    check(len(last) == len(expected_ids) + 2 and sum(int(r['id']) == after_kill['id'] and r['status'] == 'cancelled' for r in last) == 1 and sum(int(r['id']) == rebook['id'] and r['status'] == 'pending' and r['details_id'] is not None for r in last) == 1, 'cancel and rebook retain distinct consistent records')
finally:
    if server:
        os.killpg(server.pid, signal.SIGTERM)
        try: server.wait(timeout=10)
        except subprocess.TimeoutExpired:
            os.killpg(server.pid, signal.SIGKILL); server.wait()
    for path in barriers: path.unlink(missing_ok=True)
    if ready is not None: ready.unlink(missing_ok=True)
    if released is not None: released.unlink(missing_ok=True)
    if created_mu and mu.is_symlink(): mu.unlink()
    if created_plugin:
        subprocess.run(['php', 'tools/hj-booking-spike/wpdb-ajax-cleanup.php'], check=True)
        shutil.rmtree(plugin)
        meta.unlink(missing_ok=True)
