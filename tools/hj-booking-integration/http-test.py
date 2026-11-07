#!/usr/bin/env python3
"""Exercise unchanged repository plugin files on disposable WordPress."""
import concurrent.futures
import copy
import datetime
from zoneinfo import ZoneInfo
import hashlib
import http.client
import http.cookiejar
from html.parser import HTMLParser
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
import uuid
from pathlib import Path

if os.getenv('GITHUB_ACTIONS') != 'true' or os.getenv('HJ_DISPOSABLE_MARIADB_FIXTURE') != '1':
    raise SystemExit('Disposable CI only')
workspace = Path(os.environ['GITHUB_WORKSPACE'])
plugin = Path('/wordpress/wp-content/plugins/hamyar-doctors')
mu = Path('/wordpress/wp-content/mu-plugins/hj-wpdb-ajax-fixture.php')
meta = Path(os.environ['RUNNER_TEMP']) / 'hj-wpdb-ajax-meta.json'
BASE = 'http://127.0.0.1:8997'
local_now = datetime.datetime.now(ZoneInfo('Asia/Tehran'))
offset = (2 - local_now.weekday()) % 7
if offset < 2: offset += 7
date = (local_now + datetime.timedelta(days=offset)).date()
DAY = date.isoformat()
def moment(hour, minute):
    return datetime.datetime.combine(date, datetime.time(hour, minute), ZoneInfo('Asia/Tehran')).astimezone(datetime.timezone.utc).strftime('%Y-%m-%d %H:%M:%S')
S0900, S0935, S1010, S1045, S1120 = [moment(h, m) for h, m in [(9, 0), (9, 35), (10, 10), (10, 45), (11, 20)]]
PREPEND = str(workspace / 'tools/hj-booking-integration/prepend.php')
class Inputs(HTMLParser):
    def __init__(self):
        super().__init__(); self.inputs = {}; self.booking = None; self.links = []
    def handle_starttag(self, tag, attrs):
        attributes = dict(attrs)
        if tag == 'input' and 'name' in attributes: self.inputs[attributes['name']] = attributes
        if tag == 'section' and 'hj-booking' in attributes.get('class', '').split(): self.booking = attributes
        if tag == 'a' and 'href' in attributes: self.links.append(attributes['href'])

def rendered_form(opener):
    html = ajax(opener, {'action': 'hj_ci_render_dashboard'})['html']
    parsed = Inputs(); parsed.feed(html)
    return html, parsed.inputs

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
def login(user, opener=None):
    opener = opener or session()
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
    if payload.get('action') == 'hj_dash_save' and 'hj_schedule_revision' not in payload:
        current = state(opener, int(payload['pid']))['snapshot']
        payload = dict(payload, hj_schedule_revision=str(current['revision'] if current else 0))
    code, body = request(opener, '/wp-admin/admin-ajax.php', payload)
    if code != 200: raise AssertionError(f'Unexpected AJAX status {code}: {body[:500]}')
    data = json.loads(body)
    if not data['success']: raise AssertionError('Unexpected AJAX failure')
    return data['data']
DAYS = ('saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday')
def empty_week():
    return {f'hours[{day}][{key}]': value for day in DAYS for key, value in (('present','1'),('start',''),('end',''))}
def booking_nonce(opener): return ajax(opener, {'action': 'hj_ci_booking_nonce'})['nonce']
def key(n): return f'{n:032x}'
def booking_payload(doctor, nonce, start, keyvalue, fault=''):
    return {'action': 'hj_ci_create', 'doctor': str(doctor), 'nonce': nonce, 'date': DAY, 'start': start, 'key': keyvalue, 'phone': '09123456789', 'national': '1234567891', 'fault': fault}
def slots(opener, doctor, nonce):
    return ajax(opener, {'action': 'hj_ci_slots', 'doctor': str(doctor), 'nonce': nonce, 'date': DAY})
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
    plugin.symlink_to(workspace / 'plugins/hamyar-doctors', target_is_directory=True)
    created_plugin = True
    subprocess.run(['php', '-d', 'auto_prepend_file=' + PREPEND, 'tools/hj-booking-integration/setup.php'], check=True)
    # In a separate process without the opt-in constant, the shortcode is inert.
    subprocess.run(['php', '-r', "require '/wordpress/wp-load.php'; if (hj_booking_enabled() || do_shortcode('[hj_booking_form]') !== '') { exit(1); } $m=json_decode(file_get_contents(getenv('RUNNER_TEMP').'/hj-wpdb-ajax-meta.json'),true); $h=HJ_Profile_Page::render($m['doctor']); if (hj_booking_doctor_has_valid_schedule((int)$m['doctor']) || strpos($h,'https://example.invalid/external-booking')===false || strpos($h,'data-fixed=')!==false) { exit(1); } echo 'PASS: feature flag off preserves legacy external profile link and no booking form\\n';"], check=True)
    mu.parent.mkdir(exist_ok=True)
    mu.symlink_to(workspace / 'tools/hj-booking-integration/mu.php')
    created_mu = True
    fixture_meta = json.loads(meta.read_text(encoding='utf-8')); doctor_id = fixture_meta['doctor']; doctor2_id = fixture_meta['doctor2']; page_id = fixture_meta['page']; secretary_id = fixture_meta['secretary']
    server = subprocess.Popen(['php', '-d', 'auto_prepend_file=' + PREPEND, '-S', '127.0.0.1:8997', '-t', '/wordpress'], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, start_new_session=True, env=dict(os.environ, PHP_CLI_SERVER_WORKERS='16'))
    for _ in range(80):
        try: request(session(), '/wp-login.php'); break
        except OSError: time.sleep(.25)
    else: raise RuntimeError('fixture HTTP server did not start')
    owner, owner_nonce = login('synthetic_doctor_a')
    other, other_nonce = login('synthetic_doctor_b')
    url = '/wp-admin/admin-ajax.php'
    common = {**empty_week(), 'action': 'hj_dash_save', 'pid': str(doctor_id), 'name_fa': 'Synthetic Doctor', 'hours[wednesday][start]': '09:00', 'hours[wednesday][end]': '12:00', 'hj_schedule_precision':'seconds-v1'}
    check(request(owner, url, dict(common, _hjn='bad'))[0] == 403, 'actual ajax_save rejects invalid nonce')
    check(request(other, url, dict(common, _hjn=other_nonce))[0] == 403, 'actual ajax_save denies another doctor')
    html, inputs = rendered_form(owner)
    check(inputs['hj_schedule_revision']['value'] == '0' and inputs['hours[wednesday][start]']['value'] == '' and 'هنوز ثبت نشده' in html, 'new snapshot editor starts at revision zero, not stale display meta')
    check(request(owner, url, dict(common, _hjn=owner_nonce))[0] == 400, 'missing revision is rejected')
    for value in ('-1', '01', 'not-a-number', '999999999999999999999999'):
        check(request(owner, url, dict(common, _hjn=owner_nonce, hj_schedule_revision=value))[0] == 400, 'invalid revision is rejected')
    baseline = state(owner, doctor_id)
    stale_precision=dict(common, _hjn=owner_nonce, hj_schedule_revision='0'); stale_precision.pop('hj_schedule_precision')
    code, body=request(owner, url, stale_precision)
    check(code == 409 and 'بازخوانی' in json.loads(body)['message'] and state(owner, doctor_id)==baseline, 'old minute-only form is rejected before any write')
    invalid_cases = [
        {'hours[wednesday][start]': '24:00'},
        {'hours[wednesday][start]': '09:60'},
        {'hours[wednesday][start]': '09:00:70'},
        {'hours[wednesday][start]': 'bad', 'hours[wednesday][end]': 'bad'},
        {'hours[wednesday][start][bad]': '09:00', 'hours[wednesday][start]': None},
        {'hours[wednesday]': 'bad', 'hours[wednesday][present]': None, 'hours[wednesday][start]': None, 'hours[wednesday][end]': None},
    ]
    for case in invalid_cases:
        payload = dict(common, _hjn=owner_nonce, hj_schedule_revision='0', name_fa='Must not be saved', **case)
        payload = {k:v for k,v in payload.items() if v is not None}
        code, text = request(owner, url, payload)
        check(code == 422 and 'hours_wednesday' in json.loads(text).get('errors', {}), 'invalid booking hours rejected before first save')
        check(state(owner, doctor_id) == baseline, 'invalid hours preserve snapshot, mirror and profile name')
    valid = dict(common, _hjn=owner_nonce, hj_schedule_revision='0', **{'hours[wednesday][start]': '9:00:00', 'hours[wednesday][end]': '12:00:00'})
    code, text = request(owner, url, valid)
    check(code == 200 and json.loads(text)['success'], 'repository ajax_save accepts valid whole-minute time and writes canonical schedule')
    saved = state(owner, doctor_id); row = saved['snapshot']
    check(int(row['revision']) == 1 and json.loads(row['rows_json'])[0]['start_time'] == '09:00:00' and row['rows_hash'] == hashlib.sha256(row['rows_json'].encode()).hexdigest(), 'first canonical revision and hash persisted')
    check(saved['display'][0]['start_time'] == '09:00:00', 'best-effort display mirror saved')
    check(json.loads(text)['data']['schedule_revision'] == 1, 'save response includes next revision')
    payload = dict(common, _hjn=owner_nonce, hj_schedule_revision='1', name_fa='Must not be saved', **{'hours[wednesday][start]':'24:00', 'hours[wednesday][end]':'25:00'})
    code, body = request(owner, url, payload)
    check(code == 422 and 'hours_wednesday' in json.loads(body).get('errors', {}) and state(owner, doctor_id) == saved, 'invalid pair cannot clear or rewrite an existing schedule')
    stale = dict(common, _hjn=owner_nonce, hj_schedule_revision='0', name_fa='Stale profile overwrite')
    code, response = request(owner, url, stale)
    check(code == 409 and not json.loads(response)['success'] and 'بازخوانی' in json.loads(response)['message'], 'stale tab receives conflict and reload instruction')
    check(state(owner, doctor_id)['name'] == 'Synthetic Doctor', 'stale tab does not mutate other profile fields')
    changed = dict(common, _hjn=owner_nonce, hj_schedule_revision='1', **{'hours[wednesday][start]': '10:00', 'hours[wednesday][end]': '11:00', 'hj_ci_mirror_fail': '1'})
    code, text = request(owner, url, changed)
    check(code == 200 and json.loads(text)['success'], 'display mirror failure does not roll back canonical schedule')
    saved = state(owner, doctor_id); row = saved['snapshot']
    check(int(row['revision']) == 2 and json.loads(row['rows_json'])[0]['start_time'] == '10:00:00' and saved['display'][0]['start_time'] == '09:00:00', 'mirror failure leaves canonical revision independent')
    html, inputs = rendered_form(owner)
    check(inputs['hj_schedule_revision']['value'] == '2' and inputs['hours[wednesday][start]']['value'] == '10:00', 'editor renders canonical snapshot and revision despite stale meta')
    code, _ = request(owner, url, dict(common, _hjn=owner_nonce, hj_schedule_revision='1'))
    check(code == 409 and int(state(owner, doctor_id)['snapshot']['revision']) == 2, 'stale tab cannot republish stale display after mirror failure')
    killed = dict(common, _hjn=owner_nonce, hj_schedule_revision='2', hj_ci_kill_before_commit='1')
    code, text = request(owner, url, killed)
    check(code == 503 and not json.loads(text)['success'], 'killed transaction does not claim successful save')
    saved = state(owner, doctor_id); row = saved['snapshot']
    check(int(row['revision']) == 2 and json.loads(row['rows_json'])[0]['start_time'] == '10:00:00', 'kill before commit preserved prior canonical revision')
    def concurrent_save(hour):
        payload = dict(common, _hjn=owner_nonce, hj_schedule_revision='2', **{'hours[wednesday][start]': f'{hour:02d}:00', 'hours[wednesday][end]': '16:30', 'hj_ci_mirror_fail': '1'})
        return request(owner, url, payload)
    with concurrent.futures.ThreadPoolExecutor(max_workers=8) as pool:
        responses = list(pool.map(concurrent_save, range(8, 16)))
    check(sum(code == 200 and json.loads(body)['success'] for code, body in responses) == 1 and sum(code == 409 and not json.loads(body)['success'] for code, body in responses) == 7, 'eight simultaneous same-revision tabs produce one winner and seven conflicts')
    row = state(owner, doctor_id)['snapshot']
    check(int(row['revision']) == 3 and row['rows_hash'] == hashlib.sha256(row['rows_json'].encode()).hexdigest() and json.loads(row['rows_json'])[0]['start_time'] in [f'{h:02d}:00:00' for h in range(8, 16)], 'concurrent wpdb saves cannot overwrite the winning revision')

    # Set display to 10–11, then write canonical 9–12 without touching display.
    display = dict(common, _hjn=owner_nonce, **{'hours[wednesday][start]': '10:00', 'hours[wednesday][end]': '11:00'})
    check(ajax(owner, display) is not None, 'actual dashboard saves display baseline')
    open_hours = dict(common, _hjn=owner_nonce, hj_ci_mirror_fail='1')
    check(ajax(owner, open_hours) is not None, 'actual dashboard publishes test schedule')
    saved = state(owner, doctor_id); open_revision = int(saved['snapshot']['revision'])
    check(saved['display'][0]['start_time'] == '10:00:00' and json.loads(saved['snapshot']['rows_json'])[0]['start_time'] == '09:00:00', 'display intentionally differs from canonical snapshot')
    # Exercise actual rendered legacy form with seconds using a separate synthetic doctor.
    original = state(other, doctor2_id)
    second_rows = [{'day_of_week':'wednesday','is_closed':0,'start_time':'09:00:30','end_time':'12:00:45'}]
    check(ajax(other, {'action':'hj_ci_replace_snapshot','doctor':str(doctor2_id),'_hjn':other_nonce,'rows_json':json.dumps(second_rows)})['status']=='updated', 'inject synthetic nonzero seconds snapshot')
    seconds_before=state(other, doctor2_id); html, fields=rendered_form(other)
    check(fields['hours[wednesday][start]']['value']=='09:00:30' and fields['hours[wednesday][end]']['value']=='12:00:45' and fields['hours[wednesday][start]'].get('step')=='1' and fields['hours[wednesday][end]'].get('step')=='1' and fields['hj_schedule_precision']['value']=='seconds-v1', 'actual dashboard renders seconds with explicit precision marker')
    render=Path(os.environ['RUNNER_TEMP'])/'hj-legacy-seconds-form.html'; render.write_text(html,encoding='utf-8')
    try:
        browser=subprocess.run(['node',str(workspace/'tools/hj-booking-integration/dashboard-seconds-browser.cjs')],check=True,capture_output=True,text=True,timeout=40,env=dict(os.environ,HJ_SECONDS_HTML=str(render)))
        browser_values=json.loads(browser.stdout)
        browser_save=subprocess.run(['node',str(workspace/'tools/hj-booking-integration/dashboard-save-browser.cjs')],capture_output=True,text=True,timeout=60,env=dict(os.environ,HJ_SECONDS_HTML=str(render)))
        check(browser_save.returncode == 0 and 'PASS Chromium real dashboard form' in browser_save.stdout, 'Chromium guards pending and uncertain v1 save outcomes: ' + browser_save.stderr[-1500:])
    finally: render.unlink(missing_ok=True)
    check(browser_values['start']=='09:00:30' and browser_values['end']=='12:00:45' and browser_values['precision']=='seconds-v1','Chromium form retains seconds and marker')
    base2={**empty_week(), 'action':'hj_dash_save','pid':str(doctor2_id),'name_fa':'Synthetic Second Doctor','_hjn':other_nonce,'hj_schedule_revision':str(seconds_before['snapshot']['revision'])}
    stale2=dict(base2,**{'hours[wednesday][start]':'09:00','hours[wednesday][end]':'12:00'})
    code,body=request(other,url,stale2)
    check(code==409 and state(other,doctor2_id)==seconds_before, 'stale minute-only client cannot truncate second-precision schedule')
    incomplete=[]
    full=dict(base2,hj_schedule_precision='seconds-v1',**{'hours[wednesday][start]':'09:00:30','hours[wednesday][end]':'12:00:45'})
    incomplete.append({k:v for k,v in full.items() if not k.startswith('hours[')})
    incomplete.append({k:v for k,v in full.items() if not k.startswith('hours[wednesday]')})
    incomplete.append({k:v for k,v in full.items() if not k.endswith('[present]')})
    for field_name in ('hours[wednesday][present]', 'hours[wednesday][start]', 'hours[wednesday][end]'):
        incomplete.append({k:v for k,v in full.items() if k!=field_name})
    incomplete.append(dict(full,**{'hours[wednesday][closed]':'unexpected'}))
    incomplete.append(dict(full,**{'hours[wednesday][future]':'1'}))
    incomplete.append(dict(full,**{'hours[funday][present]':'1'}))
    for partial in incomplete:
        code,body=request(other,url,partial)
        check(code==422 and state(other,doctor2_id)==seconds_before, 'incomplete or unknown weekly fields fail before any write')
    bad2=dict(base2,hj_schedule_precision='seconds-v1',**{'hours[wednesday][start]':'09:00:70','hours[wednesday][end]':'12:00:45'})
    code,body=request(other,url,bad2)
    check(code==422 and state(other,doctor2_id)==seconds_before, 'invalid seconds leave schedule and profile unchanged')
    for end in ('09:00:30', '09:00:29'):
        reversed2=dict(base2,hj_schedule_precision='seconds-v1',**{'hours[wednesday][start]':'09:00:30','hours[wednesday][end]':end})
        code,body=request(other,url,reversed2)
        check(code==422 and state(other,doctor2_id)==seconds_before, 'equal or reverse second-level range is rejected without writes')
    good2=dict(base2,hj_schedule_precision=browser_values['precision'],**dict(browser_values['hours'], hj_ci_time_format='H:i'))
    code,body=request(other,url,good2); after=state(other,doctor2_id)
    check(code==200 and json.loads(body)['success'] and json.loads(after['snapshot']['rows_json'])==second_rows and int(after['snapshot']['revision'])==int(seconds_before['snapshot']['revision'])+1 and after['snapshot']['rows_hash']==hashlib.sha256(after['snapshot']['rows_json'].encode()).hexdigest(), 'actual dashboard save preserves seconds in canonical snapshot')
    check(after['display'][0]['start_time']=='09:00' and after['display'][0]['end_time']=='12:00', 'minute display filter never truncates canonical snapshot')
    same_minute=dict(base2,hj_schedule_precision='seconds-v1',hj_schedule_revision=str(after['snapshot']['revision']),**{'hours[wednesday][start]':'09:00:30','hours[wednesday][end]':'09:00:55','hours[thursday][closed]':'1','hours[thursday][start]':'bad'})
    code,body=request(other,url,same_minute); narrow=state(other,doctor2_id)
    narrow_rows=json.loads(narrow['snapshot']['rows_json'])
    check(code==200 and narrow_rows==[{'day_of_week':'wednesday','is_closed':0,'start_time':'09:00:30','end_time':'09:00:55'},{'day_of_week':'thursday','is_closed':1,'start_time':'','end_time':''}], 'same-minute second precision and closed day are saved correctly')
    closed_payload=dict({k:v for k,v in base2.items() if not k.startswith('hours[')},hj_schedule_precision='seconds-v1',hj_schedule_revision=str(narrow['snapshot']['revision']),**browser_values['closed_hours'])
    code,body=request(other,url,closed_payload); closed=state(other,doctor2_id)
    check(code==200 and json.loads(closed['snapshot']['rows_json'])==[{'day_of_week':'wednesday','is_closed':1,'start_time':'','end_time':''}], 'actual Chromium closed toggle submits marker and checkbox without disabled times')
    all_empty=dict(base2,hj_schedule_precision='seconds-v1',hj_schedule_revision=str(closed['snapshot']['revision']))
    code,body=request(other,url,all_empty); empty=state(other,doctor2_id)
    check(code==200 and json.loads(empty['snapshot']['rows_json'])==[], 'explicit complete blank week remains a valid empty schedule')
    closed_week={f'hours[{day}][{key}]':'1' for day in DAYS for key in ('present','closed')}
    all_closed=dict({k:v for k,v in base2.items() if not k.startswith('hours[')},hj_schedule_precision='seconds-v1',hj_schedule_revision=str(empty['snapshot']['revision']),**closed_week)
    code,body=request(other,url,all_closed); all_closed_state=state(other,doctor2_id)
    check(code==200 and len(json.loads(all_closed_state['snapshot']['rows_json']))==7 and all(row['is_closed']==1 for row in json.loads(all_closed_state['snapshot']['rows_json'])), 'all seven explicit closed days are accepted')
    check(ajax(other, {'action':'hj_ci_replace_snapshot','doctor':str(doctor2_id),'_hjn':other_nonce,'rows_json':original['snapshot']['rows_json']})['status']=='updated', 'restore other synthetic doctor schedule for later fixture checks')

    # Real authenticated browser -> WordPress AJAX -> MariaDB, using a dedicated synthetic doctor.
    browser_http=subprocess.run(['node',str(workspace/'tools/hj-booking-integration/dashboard-live-browser.cjs')],capture_output=True,text=True,timeout=80,env=dict(os.environ,HJ_BROWSER_DOCTOR=str(doctor2_id)))
    check(browser_http.returncode==0 and 'PASS real authenticated Chromium dashboard save' in browser_http.stdout, 'real browser dashboard save: '+browser_http.stderr[-1800:])
    check(ajax(other, {'action':'hj_ci_replace_snapshot','doctor':str(doctor2_id),'_hjn':other_nonce,'rows_json':original['snapshot']['rows_json']})['status']=='updated', 'restore synthetic schedule after browser save')
    doctor2_revision = int(state(other, doctor2_id)['snapshot']['revision'])
    patients = [login(f'synthetic_race_{i}')[0] for i in range(8)]
    nonces = [booking_nonce(p) for p in patients]
    first_slots = slots(patients[0], doctor_id, nonces[0])
    check(first_slots['status'] == 'ok' and first_slots['revision'] == open_revision and S0900 in first_slots['slots'], 'actual snapshot supplies slot excluded by stale display')
    invalid = booking_payload(doctor_id, nonces[0], S0900, key(31000))
    check(ajax(patients[0], dict(invalid, national='1234567890'))['status'] == 'invalid', 'production booking rejects invalid national checksum')
    check(ajax(patients[0], dict(invalid, phone='02112345678'))['status'] == 'invalid', 'production booking rejects invalid mobile')
    check(request(patients[0], url, dict(invalid, nonce='bad'))[0] == 403, 'booking test adapter rejects invalid nonce')

    # The server commits and kills its own DB connection, signals the client,
    # then waits. Close the actual HTTP socket before releasing its response.
    lost_key = key(31001)
    marker = Path(os.environ['RUNNER_TEMP']) / ('hj-booking-commit-' + lost_key)
    ready, released = Path(str(marker) + '-ready'), Path(str(marker) + '-released')
    ready.unlink(missing_ok=True); released.unlink(missing_ok=True)
    cookie_handler = next(h for h in patients[0].handlers if isinstance(h, urllib.request.HTTPCookieProcessor))
    cookie = '; '.join(f'{c.name}={c.value}' for c in cookie_handler.cookiejar if c.name.startswith('wordpress_logged_in_'))
    check(bool(cookie), 'synthetic patient authentication cookie exists')
    lost = booking_payload(doctor_id, nonces[0], S0900, lost_key, 'disconnect_after_commit')
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
    conflict = ajax(patients[0], dict(retry_payload, start=S0935))
    check(conflict['status'] == 'idempotency_conflict', 'same key cannot be reused for changed request')
    cross_patient = dict(retry_payload, nonce=nonces[1])
    check(ajax(patients[1], cross_patient)['status'] == 'idempotency_conflict', 'another patient cannot replay a booking key')
    before = audit(owner, doctor_id)
    check(len(before) == 1 and before[0]['request_key'] == lost_key and before[0]['details_id'] is not None, 'lost response left exactly one booking and atomic details')

    killed_key = key(31002)
    killed_booking = booking_payload(doctor_id, nonces[1], S0935, killed_key, 'kill_before_commit')
    check(ajax(patients[1], killed_booking)['status'] == 'unknown', 'booking database connection killed before commit yields unknown')
    check(len(audit(owner, doctor_id)) == 1, 'killed precommit booking and details rolled back')
    after_kill = ajax(patients[1], dict(killed_booking, fault=''))
    check(after_kill['status'] == 'created', 'retry after precommit kill creates one booking')

    # Nine simultaneous HTTP requests: actual ajax_save closes Wednesday, eight
    # distinct authenticated patients compete on two slots near the boundary.
    gate = threading.Barrier(9)
    race_payloads = [booking_payload(doctor_id, nonces[i], S1045 if i % 2 == 0 else S1120, key(32000+i)) for i in range(8)]
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
        payload = booking_payload(doctor_id, nonces[i], S1045, key(33000+i))
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
            return ajax(patients[i], booking_payload(doctor_id, nonces[i], S1010, key(34100+i), 'signal_before_lock'))
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
    first_payload = booking_payload(doctor_id, nonces[2], S1010, first_key, 'hold_after_lock')
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
    check(ajax(patients[3], booking_payload(doctor_id, nonces[3], S1045, key(35001)))['status'] == 'invalid_slot', 'new booking after ordered closure fails')
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
    rebook = ajax(patients[4], booking_payload(doctor_id, nonces[4], S0935, rebook_key))
    check(rebook['status'] == 'created' and rebook['id'] != after_kill['id'], 'patient rebooks cancelled interval')
    check(ajax(patients[5], booking_payload(doctor_id, nonces[5], S0935, key(37001)))['status'] == 'invalid_slot', 'rebooked interval cannot be taken twice')
    last = audit(owner, doctor_id)
    check(len(last) == len(expected_ids) + 2 and sum(int(r['id']) == after_kill['id'] and r['status'] == 'cancelled' for r in last) == 1 and sum(int(r['id']) == rebook['id'] and r['status'] == 'pending' and r['details_id'] is not None for r in last) == 1, 'cancel and rebook retain distinct consistent records')
    baseline = state(owner, doctor_id)
    check(ajax(owner, {'action': 'hj_ci_corrupt_snapshot', 'doctor': str(doctor_id)})['ok'], 'test adapter deliberately corrupted canonical hash')
    check(slots(patients[0], doctor_id, nonces[0])['status'] == 'invalid_schedule', 'production slot reader fails closed for corrupted snapshot')
    check(ajax(patients[5], booking_payload(doctor_id, nonces[5], S1120, key(38000)))['status'] == 'invalid_schedule', 'production create fails closed for corrupted snapshot')
    broken = state(owner, doctor_id)
    rejected = dict(open_hours, hj_schedule_revision=str(broken['snapshot']['revision']), name_fa='Do not overwrite')
    code, body = request(owner, url, rejected)
    check(code == 503 and 'جلوگیری از بازنویسی' in json.loads(body)['message'] and state(owner, doctor_id) == broken, 'dashboard refuses to overwrite corrupted snapshot or other profile fields')
    replace = {'action':'hj_ci_replace_snapshot', 'doctor':str(doctor_id), '_hjn':owner_nonce}
    check(ajax(owner, dict(replace, rows_json=baseline['snapshot']['rows_json']))['status'] == 'updated', 'CI-only fixture restores original synthetic snapshot, not production repair')
    check(state(owner, doctor_id) == baseline and slots(patients[0], doctor_id, nonces[0])['status'] == 'ok', 'restoring original bytes preserves revision, display, name and reader')
    future_shapes = ['{}', json.dumps({'version':2, 'weekly':[]}), json.dumps([{'day_of_week':'wednesday','is_closed':0,'start_time':'09:00','end_time':'12:00','capacity':3}])]
    for future in future_shapes:
        check(ajax(owner, dict(replace, rows_json=future))['status'] == 'updated', 'CI-only fixture seeds an unknown schedule shape')
        prior = state(owner, doctor_id)
        code, body = request(owner, url, dict(open_hours, hj_schedule_revision=str(prior['snapshot']['revision']), name_fa='Do not overwrite'))
        check(code == 503 and state(owner, doctor_id) == prior and slots(patients[0], doctor_id, nonces[0])['status'] == 'invalid_schedule', 'unknown snapshot shape fails closed and cannot be overwritten by old editor')
        check(ajax(owner, dict(replace, rows_json=baseline['snapshot']['rows_json']))['status'] == 'updated', 'CI-only fixture restores original schedule after unknown shape')
    check(state(owner, doctor_id) == baseline, 'future-format checks preserve all original synthetic schedule and display data')

    # Product endpoints, not the CI adapter. Another Wednesday keeps the old
    # lock-order fixture's appointments separate from this race.
    ui_day = (date + datetime.timedelta(days=7)).isoformat()
    def ui_moment(hour, minute):
        return datetime.datetime.combine(date + datetime.timedelta(days=7), datetime.time(hour, minute), ZoneInfo('Asia/Tehran')).astimezone(datetime.timezone.utc).strftime('%Y-%m-%d %H:%M:%S')
    ui_starts = [ui_moment(h, m) for h, m in [(9,0),(9,35),(10,10),(10,45),(11,20)]]
    def ui_form(opener):
        html = ajax(opener, {'action':'hj_ci_render_patient_form', 'page':str(page_id)})['html']
        parsed = Inputs(); parsed.feed(html)
        check(parsed.booking is not None, 'real patient shortcode renders')
        return parsed.booking
    def ui_request(opener, payload):
        code, body = request(opener, url, payload)
        return code, json.loads(body)
    guest = session(); guest_form = ui_form(guest); guest_nonce = guest_form['data-nonce']
    profile_html = ajax(guest, {'action':'hj_ci_render_patient_profile', 'doctor':str(doctor_id)})['html']
    profile = Inputs(); profile.feed(profile_html)
    check(profile.booking is not None and profile.booking['data-fixed'] == '1' and profile.booking['data-page'] == str(doctor_id), 'actual doctor profile includes fixed-doctor booking form')
    check(profile.links.count('#hj-booking') >= 4, 'all four profile booking buttons target the internal form for a configured doctor')
    check('https://example.invalid/external-booking' in profile.links and 'نوبت‌دهی خارج از سایت' in profile_html, 'existing external booking link is preserved and clearly distinguished')
    for kind, gate_doctor in fixture_meta['profile_gates'].items():
        html = ajax(guest, {'action':'hj_ci_render_patient_profile', 'doctor':str(gate_doctor)})['html']
        gate = Inputs(); gate.feed(html)
        check(gate.booking is None and '#hj-booking' not in gate.links and 'https://example.invalid/external-booking' in gate.links and 'نوبت‌دهی داخلی برای این جراح در دسترس نیست' in html, 'profile gate fails closed and preserves external link: ' + kind)
    slot_payload = {'action':'hj_booking_slots', 'nonce':guest_nonce, 'doctor_id':str(doctor_id), 'date':ui_day}
    code, available = ui_request(guest, slot_payload)
    check(code == 200 and available['success'] and ui_starts[0] in [x['start'] for x in available['data']['slots']], 'guest obtains real public slots from canonical snapshot')
    check(set(available['data']) == {'revision','slots'} and all(set(x) == {'start','label'} for x in available['data']['slots']), 'public response contains no patient records')
    revision = available['data']['revision']
    check(ui_request(guest, dict(slot_payload, nonce='bad'))[0] == 403, 'product slots rejects invalid nonce')
    intent_key = str(uuid.uuid4())
    intent_payload = {'action':'hj_booking_intent', 'nonce':guest_nonce, 'doctor_id':str(doctor_id), 'page_id':str(page_id), 'slot_start':ui_starts[0], 'revision':str(revision), 'idempotency_key':intent_key, 'hj_ci_provider':'1'}
    code, intent_response = ui_request(guest, intent_payload)
    check(code == 200 and intent_response['success'] and 'hj_booking_return=1' in intent_response['data']['login_url'], 'guest intent saved and selects public Google entry')
    check(ui_request(session(), dict(intent_payload, page_id='999999'))[0] == 400, 'booking intent rejects an invalid return page')
    cookie = next((c for h in guest.handlers if isinstance(h, urllib.request.HTTPCookieProcessor) for c in h.cookiejar if c.name == 'hj_booking_intent'), None)
    check(cookie is not None and cookie.has_nonstandard_attr('HttpOnly') and cookie.get_nonstandard_attr('SameSite') == 'Lax', 'short-lived signed intent cookie is HttpOnly and SameSite Lax')
    google_path = urllib.parse.urlsplit(intent_response['data']['login_url'])
    tampered = session(); tampered_cookie = copy.copy(cookie); tampered_cookie.value = tampered_cookie.value[:-1] + ('0' if tampered_cookie.value[-1] != '0' else '1')
    next(h for h in tampered.handlers if isinstance(h, urllib.request.HTTPCookieProcessor)).cookiejar.set_cookie(tampered_cookie)
    tamper_req = urllib.request.Request(BASE + google_path.path + '?' + google_path.query, headers={'Host':'example.invalid'})
    try: tamper_res = tampered.open(tamper_req, timeout=30)
    except urllib.error.HTTPError as e: tamper_res = e
    tamper_dest = urllib.parse.parse_qs(urllib.parse.urlsplit(tamper_res.headers.get('Location', '')).query).get('redirect', [''])[0]
    check(tamper_res.code == 302 and '#hj-booking' not in tamper_dest, 'tampered intent cannot set a booking return target')
    req = urllib.request.Request(BASE + google_path.path + '?' + google_path.query, headers={'Host':'example.invalid'})
    try: google_response = guest.open(req, timeout=30)
    except urllib.error.HTTPError as e: google_response = e
    location = google_response.headers.get('Location', '')
    target = urllib.parse.parse_qs(urllib.parse.urlsplit(location).query).get('redirect', [''])[0]
    check(google_response.code == 302 and target.startswith('http://example.invalid/') and '#hj-booking' in target and 'loginSocial=google' in location, 'Google provider stub receives server-derived same-site return, not arbitrary URL: ' + repr((google_response.code, target, location)))
    cookie_names = {c.name for h in guest.handlers if isinstance(h, urllib.request.HTTPCookieProcessor) for c in h.cookiejar}
    check('hj_member_intent' in cookie_names and 'hj_doc_intent' not in cookie_names, 'booking uses ordinary member intent and not surgeon intent')
    resumed, _ = login('synthetic_race_8', guest)
    resumed_form = ui_form(resumed); restored = json.loads(resumed_form['data-intent'])
    check(restored['doctor'] == doctor_id and restored['start'] == ui_starts[0] and restored['revision'] == revision and restored['key'] == intent_key and restored['local_date'] == ui_day, 'signed intent survives simulated login and returns exact selection')
    resume_slots = ui_request(resumed, dict(slot_payload, nonce=resumed_form['data-nonce']))[1]
    check(ui_starts[0] in [x['start'] for x in resume_slots['data']['slots']] and resume_slots['data']['revision'] == restored['revision'], 'restored selection can be revalidated against server')
    rival, _ = login('synthetic_race_9'); rival_nonce = ui_form(rival)['data-nonce']
    base = {'action':'hj_booking_create', 'doctor_id':str(doctor_id), 'slot_start':ui_starts[0], 'revision':str(revision), 'phone':'۰۹۱۲۳۴۵۶۷۸۹', 'national_code':'۱۲۳۴۵۶۷۸۹۱'}
    a = dict(base, nonce=resumed_form['data-nonce'], idempotency_key=intent_key)
    b = dict(base, nonce=rival_nonce, idempotency_key=str(uuid.uuid4()))
    code, failure = ui_request(resumed, dict(a, revision=str(revision-1)))
    check(code == 409 and failure['data']['code'] == 'revision_conflict' and 'ساعات کاری تغییر کرده' in failure['data']['message'], 'real AJAX rejects stale revision with Persian conflict')
    for field, value, expected in [('phone','02112345678','invalid_phone'), ('national_code','1234567890','invalid_national')]:
        code, failure = ui_request(resumed, dict(a, **{field:value}))
        check(code == 400 and failure['data']['code'] == expected and len(failure['data']['message']) > 10, 'real AJAX rejects invalid ' + field + ' with clear message')
    anonymous = session()
    check(ui_request(anonymous, dict(a, nonce=guest_nonce))[0] == 403, 'guest cannot create reservation with guest nonce')
    with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
        replies = list(pool.map(lambda x: ui_request(*x), [(resumed,a),(rival,b)]))
    check(sorted(code for code, _ in replies) == [200,409] and sum(x['success'] for _, x in replies) == 1 and any(x.get('data',{}).get('code') == 'invalid_slot' for _, x in replies), 'two patients race real AJAX for one slot: one success, one taken')
    winner_index = 0 if replies[0][0] == 200 else 1
    winner = [resumed,rival][winner_index]; winning_payload = [a,b][winner_index]; winning_id = replies[winner_index][1]['data']['id']
    code, replayed = ui_request(winner, winning_payload)
    check(code == 200 and replayed['data']['status'] == 'replayed' and replayed['data']['id'] == winning_id, 'same UUID replays exactly the same reservation')
    refreshed = ui_request(rival, dict(slot_payload, nonce=rival_nonce))[1]
    check(ui_starts[0] not in [x['start'] for x in refreshed['data']['slots']], 'occupied choice disappears on refreshed server availability')
    check(sum(int(row['id']) == winning_id for row in audit(owner, doctor_id)) == 1, 'idempotency replay inserted only one row')
    limit_patient, _ = login('synthetic_race_10'); limit_nonce = ui_form(limit_patient)['data-nonce']
    for index, start in enumerate(ui_starts[1:]):
        code, result = ui_request(limit_patient, dict(base, nonce=limit_nonce, slot_start=start, idempotency_key=str(uuid.uuid4())))
        check((code == 200 and result['success']) if index < 3 else (code == 429 and result['data']['code'] == 'pending_limit'), 'three-pending patient limit item ' + str(index+1))
    # One patient attempts a third and fourth pending booking under distinct
    # doctor mutexes; the patient row lock must serialize the global limit.
    cross_patient, _ = login('synthetic_race_11'); cross_nonce = ui_form(cross_patient)['data-nonce']
    cross_day = (date + datetime.timedelta(days=14)).isoformat()
    def cross_start(h, m): return datetime.datetime.combine(date + datetime.timedelta(days=14), datetime.time(h, m), ZoneInfo('Asia/Tehran')).astimezone(datetime.timezone.utc).strftime('%Y-%m-%d %H:%M:%S')
    for h, m in [(9,0), (9,35)]:
        code, result = ui_request(cross_patient, dict(base, nonce=cross_nonce, slot_start=cross_start(h,m), idempotency_key=str(uuid.uuid4())))
        check(code == 200 and result['success'], 'patient baseline pending before cross-doctor race')
    one = dict(base, nonce=cross_nonce, slot_start=cross_start(10,10), idempotency_key=str(uuid.uuid4()))
    two = dict(one, doctor_id=str(doctor2_id), revision=str(doctor2_revision), idempotency_key=str(uuid.uuid4()))
    with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
        cross_results = list(pool.map(lambda payload: ui_request(cross_patient, payload), [one,two]))
    check(sorted(code for code, _ in cross_results) == [200,429] and any(r['data'].get('code') == 'pending_limit' for _, r in cross_results), 'same patient racing different doctors cannot exceed three pending')
    check(ajax(owner, open_hours) is not None, 'doctor publishes a new schedule revision after list was shown')
    code, stale = ui_request(rival, dict(b, slot_start=cross_start(10,45), idempotency_key=str(uuid.uuid4())))
    check(code == 409 and stale['data']['code'] == 'revision_conflict', 'real AJAX detects actual intervening schedule save')
    code, replayed = ui_request(winner, winning_payload)
    check(code == 200 and replayed['data']['status'] == 'replayed' and replayed['data']['id'] == winning_id, 'previously committed UUID still replays after schedule revision changes')

    # Management endpoints, private list rendering, and deterministic overlapping
    # cancellation/rebooking transactions on a fresh future Wednesday.
    admin, _ = login('fixture_admin'); secretary, _ = login('synthetic_secretary')
    fake_acf = ajax(admin, {'action':'hj_ci_acf_hours_label'})
    check(fake_acf['label']=='ساعات کاری' and fake_acf['instructions']=='توسط داشبورد پر می‌شود.', 'legacy v1 leaves the simulated ACF field unchanged')
    code, editor_html = request(admin, '/wp-admin/post.php?post=' + str(doctor_id) + '&action=edit')
    check(code == 200 and 'مستقل از نوبت‌دهی' not in editor_html, 'legacy v1 admin editor does not show the v2 notice')
    patient_a, _ = login('synthetic_patient'); patient_b, _ = login('synthetic_patient_b')
    def extract_form(html, action):
        class FormParser(HTMLParser):
            def __init__(self): super().__init__(); self.form=None; self.forms=[]
            def handle_starttag(self, tag, attrs):
                attr=dict(attrs)
                if tag == 'form': self.form={}
                elif tag == 'input' and self.form is not None and 'name' in attr: self.form[attr['name']] = attr.get('value','')
            def handle_endtag(self, tag):
                if tag == 'form' and self.form is not None: self.forms.append(self.form); self.form=None
        parser=FormParser(); parser.feed(html)
        return next(f for f in parser.forms if f.get('action') == action)
    admin_url = '/wp-admin/edit.php?post_type=doctor&page=hj-bookings'
    code, admin_html = request(admin, admin_url)
    check(code == 200 and 'مدیریت نوبت‌ها' in admin_html, 'admin can open actual aggregate submenu')
    assignment = extract_form(admin_html, 'hj_booking_assignment')
    assign_payload = dict(assignment, doctor_id=str(doctor_id), user_id=str(secretary_id), mode='add')
    check(request(patient_a, '/wp-admin/admin-post.php', assign_payload)[0] == 403, 'patient cannot assign a secretary')
    check(request(admin, '/wp-admin/admin-post.php', dict(assign_payload, _wpnonce='bad'))[0] == 403, 'assignment requires nonce')
    check(request(admin, '/wp-admin/admin-post.php', assign_payload)[0] == 302, 'admin assigns secretary through real admin-post handler')
    secretary_view = ajax(secretary, {'action':'hj_ci_render_dashboard'})['html']
    check('لیست نوبت‌ها' in secretary_view and 'Synthetic Doctor' in secretary_view and 'Second synthetic booking doctor' not in secretary_view and 'hj-dash-form' not in secretary_view, 'secretary enters only assigned booking panel, not profile editor')
    mdate = date + datetime.timedelta(days=21)
    mday = mdate.isoformat()
    def mstart(hour, minute): return datetime.datetime.combine(mdate, datetime.time(hour, minute), ZoneInfo('Asia/Tehran')).astimezone(datetime.timezone.utc).strftime('%Y-%m-%d %H:%M:%S')
    a_nonce = ui_form(patient_a)['data-nonce']; b_nonce = ui_form(patient_b)['data-nonce']
    newest_revision = int(state(owner, doctor_id)['snapshot']['revision'])
    def create_payload(nonce, doctor, start, revision):
        return {'action':'hj_booking_create', 'nonce':nonce, 'doctor_id':str(doctor), 'slot_start':start, 'revision':str(revision), 'idempotency_key':str(uuid.uuid4()), 'phone':'09123456789', 'national_code':'1234567891'}
    first = create_payload(a_nonce, doctor_id, mstart(9,0), newest_revision)
    second = create_payload(b_nonce, doctor2_id, mstart(9,0), doctor2_revision)
    c1, r1 = ui_request(patient_a, first); c2, r2 = ui_request(patient_b, second)
    check(c1 == 200 and c2 == 200, 'two doctors have distinct synthetic patient appointments')
    id_a = r1['data']['id']; id_b = r2['data']['id']
    owner_html = ajax(owner, {'action':'hj_ci_render_dashboard'})['html']
    secretary_html = ajax(secretary, {'action':'hj_ci_render_dashboard'})['html']
    other_html = ajax(other, {'action':'hj_ci_render_dashboard'})['html']
    patient_html = ajax(patient_a, {'action':'hj_ci_render_patient_bookings'})['html']
    check('Synthetic Patient Alpha' in owner_html and 'Synthetic Patient Beta' not in owner_html and '09123456789' in owner_html, 'doctor dashboard shows own patient details only')
    check('Synthetic Patient Alpha' in secretary_html and 'Synthetic Patient Beta' not in secretary_html, 'secretary sees only assigned doctor patient details')
    check('Synthetic Patient Beta' in other_html and 'Synthetic Patient Alpha' not in other_html, 'other doctor sees only other doctor patient details')
    for opener, forbidden_doctor in [(secretary,doctor2_id),(other,doctor_id),(patient_b,doctor_id)]:
        denied_html = ajax(opener, {'action':'hj_ci_render_management', 'doctor':str(forbidden_doctor)})['html']
        check('Synthetic Patient Alpha' not in denied_html and 'Synthetic Patient Beta' not in denied_html and '09123456789' not in denied_html, 'forged doctor list access returns no private records')
    check('Synthetic Doctor' in patient_html and 'Second synthetic booking doctor' not in patient_html and '09123456789' not in patient_html and '1234567891' not in patient_html, 'patient list is own-only and does not expose contact details')
    legacy_account=ajax(patient_a, {'action':'hj_ci_render_member_account','page':str(fixture_meta['account'])})['html']
    check('ایمیل حساب' in legacy_account and 'id="hj-bookings"' not in legacy_account and 'Synthetic Doctor' not in legacy_account, 'v1 member account does not automatically change or expose bookings')
    code, future_html = request(patient_a, url + '?hj_b_filter=future', {'action':'hj_ci_render_patient_bookings'})
    code_today, today_html = request(patient_a, url + '?hj_b_filter=today', {'action':'hj_ci_render_patient_bookings'})
    check(code == 200 and code_today == 200 and 'Synthetic Doctor' in json.loads(future_html)['data']['html'] and 'Synthetic Doctor' not in json.loads(today_html)['data']['html'], 'patient today/future filters use local-date boundaries')
    code, all_admin = request(admin, admin_url)
    check(code == 200 and 'Synthetic Patient Alpha' in all_admin and 'Synthetic Patient Beta' in all_admin, 'admin aggregate shows bookings from both doctors')
    code, filtered = request(admin, admin_url + '&doctor_id=' + str(doctor2_id) + '&booking_status=pending&booking_date=' + mday)
    check(code == 200 and 'Synthetic Patient Beta' in filtered and 'Synthetic Patient Alpha' not in filtered, 'admin filters aggregate by doctor, status and local date')
    check(request(patient_a, admin_url)[0] in (302,403), 'patient cannot access administrator submenu')
    def status_payload(opener, doctor, appointment, scope, status):
        nonce = ajax(opener, {'action':'hj_ci_status_nonce','id':str(appointment)})['nonce']
        return {'action':'hj_booking_status','doctor_id':str(doctor),'booking_id':str(appointment),'scope':scope,'status':status,'_wpnonce':nonce}
    patient_cancel = status_payload(patient_a, doctor_id, id_a, 'patient', 'cancelled')
    stranger_cancel = status_payload(patient_b, doctor_id, id_a, 'patient', 'cancelled')
    wrong_doc_cancel = status_payload(other, doctor_id, id_a, 'manage', 'cancelled')
    secretary_other = status_payload(secretary, doctor2_id, id_b, 'manage', 'cancelled')
    status_url = '/wp-admin/admin-post.php'
    check(request(patient_b, status_url, stranger_cancel)[0] == 403, 'patient cannot cancel another patient booking')
    check(request(other, status_url, wrong_doc_cancel)[0] == 403, 'doctor cannot cancel another doctor booking')
    check(request(secretary, status_url, secretary_other)[0] == 403, 'secretary cannot cancel unassigned doctor booking')
    check(request(patient_a, status_url, dict(patient_cancel, _wpnonce='bad'))[0] == 403, 'status change rejects invalid nonce')
    early = status_payload(other, doctor2_id, id_b, 'manage', 'completed')
    check(request(other, status_url, early)[0] == 409, 'future booking cannot be completed prematurely and release capacity')
    def wait_inside_mutex_query(marker):
        connection = marker.read_text().strip()
        check(connection.isdigit() and int(connection) > 0, 'fixture recorded contender connection id')
        deadline = time.monotonic() + 2.0
        while time.monotonic() < deadline:
            result = ajax(admin, {'action':'hj_ci_mutex_query_in_progress', 'connection':connection})
            if result['in_progress']:
                check(True, 'MariaDB shows contender mutex INSERT still in progress while lock is held')
                return
            time.sleep(.02)
        raise AssertionError('contender did not reach MariaDB mutex INSERT while lock was held')
    # Cancellation holds the doctor mutex while a real create reaches it.
    token = key(40100); cancel_ready, cancel_release = barrier('status-hold-' + token); create_ready, _ = barrier('create-wait-' + token)
    rebook_payload = create_payload(b_nonce, doctor_id, mstart(9,0), newest_revision)
    with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
        cancel_future = pool.submit(request, patient_a, status_url, dict(patient_cancel, hj_ci_status_hold=token))
        wait_ready([cancel_ready])
        create_future = pool.submit(ui_request, patient_b, dict(rebook_payload, hj_ci_create_signal=token))
        try:
            wait_ready([create_ready]); wait_inside_mutex_query(create_ready)
        finally: cancel_release.touch()
        cancel_result, created_result = cancel_future.result(timeout=40), create_future.result(timeout=40)
    check(cancel_result[0] == 302 and created_result[0] == 200 and created_result[1]['success'], 'overlapping patient cancellation then waiter rebooks same slot after mutex release')
    rebook_id = created_result[1]['data']['id']
    rows = audit(owner, doctor_id)
    check(sum(int(r['id']) == id_a and r['status'] == 'cancelled' for r in rows) == 1 and sum(int(r['id']) == rebook_id and r['status'] == 'pending' for r in rows) == 1, 'cancel/rebook leaves one cancelled and one active reservation for interval')
    confirm = status_payload(secretary, doctor_id, rebook_id, 'manage', 'confirmed')
    check(request(secretary, status_url, confirm)[0] == 302, 'assigned secretary confirms booking')
    # Reverse acquisition order: creation sees the still-confirmed slot and
    # fails; secretary cancellation then frees it for a same-key retry.
    token2 = key(40101); create_hold, create_release = barrier('create-hold-' + token2); status_ready, _ = barrier('status-wait-' + token2)
    competing = create_payload(a_nonce, doctor_id, mstart(9,0), newest_revision)
    sec_cancel = status_payload(secretary, doctor_id, rebook_id, 'manage', 'cancelled')
    with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
        before_cancel = pool.submit(ui_request, patient_a, dict(competing, hj_ci_create_hold=token2))
        wait_ready([create_hold])
        cancel_after = pool.submit(request, secretary, status_url, dict(sec_cancel, hj_ci_status_signal=token2))
        try:
            wait_ready([status_ready]); wait_inside_mutex_query(status_ready)
        finally: create_release.touch()
        before_result, after_result = before_cancel.result(timeout=40), cancel_after.result(timeout=40)
    check(before_result[0] == 409 and before_result[1]['data']['code'] == 'invalid_slot' and after_result[0] == 302, 'booking-first overlap sees occupied interval before secretary cancellation')
    retry_code, retry_result = ui_request(patient_a, competing)
    check(retry_code == 200 and retry_result['success'], 'same request can reserve after secretary cancellation completes')
    check(ajax(other, {'action':'hj_ci_set_past','doctor':str(doctor2_id),'id':str(id_b)})['status'] == 'updated', 'CI fixture moves synthetic appointment to the past under mutex')
    check(request(other, status_url, early)[0] == 302, 'doctor marks past booking completed')
    check(request(other, status_url, status_payload(other, doctor2_id, id_b, 'manage', 'cancelled'))[0] == 409, 'terminal booking cannot be reopened or cancelled')
    absent_code, absent = ui_request(patient_b, create_payload(b_nonce, doctor2_id, mstart(9,35), doctor2_revision))
    check(absent_code == 200 and absent['success'], 'second synthetic past-status candidate created')
    absent_id = absent['data']['id']
    check(ajax(other, {'action':'hj_ci_set_past','doctor':str(doctor2_id),'id':str(absent_id)})['status'] == 'updated', 'CI fixture makes no-show candidate past under mutex')
    check(request(other, status_url, status_payload(other, doctor2_id, absent_id, 'manage', 'no_show'))[0] == 302, 'doctor marks past booking no-show')
    absent_view = ajax(patient_b, {'action':'hj_ci_render_patient_bookings'})['html']
    check('عدم حضور' in absent_view, 'patient sees localized no-show state')
    removed = dict(assign_payload, mode='remove')
    check(request(admin, status_url.replace('admin-post.php','admin-post.php'), removed)[0] == 302, 'admin revokes secretary assignment')
    check('Synthetic Patient Alpha' not in ajax(secretary, {'action':'hj_ci_render_dashboard'})['html'] and request(secretary, status_url, confirm)[0] == 403, 'revoked secretary cannot view or change doctor bookings')
    check(ajax(owner, {'action':'hj_ci_corrupt_snapshot', 'doctor':str(doctor_id)})['ok'], 'corrupt snapshot for public endpoint test')
    code, result = ui_request(anonymous, slot_payload)
    check(code == 503 and result['data']['code'] == 'invalid_schedule' and 'slots' not in result['data'], 'public endpoint fails closed on corrupt snapshot')
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
        subprocess.run(['php', '-d', 'auto_prepend_file=' + PREPEND, 'tools/hj-booking-spike/wpdb-ajax-cleanup.php'], check=True)
        plugin.unlink()
        meta.unlink(missing_ok=True)
