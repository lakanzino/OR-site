#!/usr/bin/env python3
"""Actual ACF free + WordPress, disposable data only; simulate plugin file replacement."""
import http.cookiejar
import json
import os
from pathlib import Path
import signal
import subprocess
import time
import urllib.error
import urllib.parse
import urllib.request
import zipfile

if os.getenv('GITHUB_ACTIONS')!='true' or os.getenv('HJ_DISPOSABLE_MARIADB_FIXTURE')!='1': raise SystemExit('Disposable CI only')
workspace=Path(os.environ['GITHUB_WORKSPACE']); temp=Path(os.environ['RUNNER_TEMP']); acf=Path(os.environ['HJ_ACF_CI_PATH'])
plugins=Path('/wordpress/wp-content/plugins'); product=plugins/'hamyar-doctors'; acf_link=plugins/'advanced-custom-fields'
mu=Path('/wordpress/wp-content/mu-plugins/hj-acf-upgrade-ci.php'); olddir=temp/'hj-acf-upgrade-old'; base='http://127.0.0.1:8996'
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,req,fp,code,msg,hdrs,url): return None
opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()),NoRedirect())
def request(path,data=None):
    body=urllib.parse.urlencode(data).encode() if data is not None else None
    req=urllib.request.Request(base+path,data=body,headers={'Host':'example.invalid'})
    try: res=opener.open(req,timeout=30)
    except urllib.error.HTTPError as e: res=e
    return res.code,res.read().decode('utf-8',errors='replace')
def check(condition,label):
    if not condition: raise AssertionError(label)
    print('PASS:',label,flush=True)
def ajax(data):
    code,body=request('/wp-admin/admin-ajax.php',data)
    if code!=200: raise AssertionError(f'HTTP {code}: {body[:500]}')
    result=json.loads(body)
    if not result.get('success'): raise AssertionError('AJAX failed: '+body[:500])
    return result['data']
def start():
    worker=subprocess.Popen(['php','-S','127.0.0.1:8996','-t','/wordpress'],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL,start_new_session=True)
    for _ in range(80):
        if worker.poll() is not None: raise RuntimeError('ACF test server exited')
        try:
            if request('/wp-login.php')[0]==200: return worker
        except OSError: time.sleep(.25)
    os.killpg(worker.pid,signal.SIGTERM); worker.wait(); raise RuntimeError('ACF test server failed to start')
def stop(worker):
    if worker and worker.poll() is None:
        os.killpg(worker.pid,signal.SIGTERM)
        try: worker.wait(timeout=10)
        except subprocess.TimeoutExpired: os.killpg(worker.pid,signal.SIGKILL); worker.wait()
def cli(mode): subprocess.run(['php','tools/hj-booking-integration/acf-upgrade-setup.php',mode],check=True)
server=None; activated=False; installed_product=False; installed_acf=False; installed_mu=False
try:
    if any(p.exists() or p.is_symlink() for p in (product,acf_link,mu)) or olddir.exists() or not (acf/'acf.php').is_file(): raise RuntimeError('Refusing occupied or missing fixture paths')
    olddir.mkdir()
    with zipfile.ZipFile(workspace/'releases/hamyar-doctors-0.3.22.zip') as z: z.extractall(olddir)
    product.symlink_to(olddir/'hamyar-doctors',target_is_directory=True); installed_product=True
    acf_link.symlink_to(acf,target_is_directory=True); installed_acf=True
    mu.parent.mkdir(exist_ok=True); mu.symlink_to(workspace/'tools/hj-booking-integration/acf-upgrade-mu.php'); installed_mu=True
    cli('activate'); activated=True; cli('create')
    server=start()
    code,_=request('/wp-login.php',{'log':'synthetic_acf_upgrade','pwd':'disposable-test-password','wp-submit':'Log In','redirect_to':'http://example.invalid/wp-admin/','testcookie':'1'})
    check(code==302,'synthetic doctor login on installed 0.3.22')
    nonce=ajax({'action':'hj_ci_acf_nonce'})['nonce']; initial=ajax({'action':'hj_ci_acf_state'})
    check(initial['acf_version']=='6.8.11' and initial['plugin_version']=='0.3.22' and not initial['repeater_available'] and initial['hours_field_type']=='repeater' and not initial['profile'],'actual free ACF with local repeater metadata and old plugin')
    first={'action':'hj_dash_save','pid':'0','_hjn':nonce,'name_fa':'Synthetic ACF Before','hours[wednesday][start]':'09:00','hours[wednesday][end]':'12:00'}
    saved=ajax(first); check(saved['created'],'old plugin creates synthetic profile with ACF free')
    before=ajax({'action':'hj_ci_acf_state'}); pid=before['profile']
    print('OBSERVE old synthetic ACF hours:',json.dumps({'meta':before['hours_meta'],'acf':before['hours_acf']},ensure_ascii=False),flush=True)
    check(pid>0 and before['name']=='Synthetic ACF Before' and isinstance(before['hours_meta'],list),'old plugin saves name and raw synthetic working hours')
    stop(server); server=None; product.unlink(); product.symlink_to(workspace/'plugins/hamyar-doctors',target_is_directory=True)
    server=start(); after=ajax({'action':'hj_ci_acf_state'})
    check(after['acf_version']=='6.8.11' and after['plugin_version']=='0.3.49' and after['profile']==pid and after['name']==before['name'] and after['hours_meta']==before['hours_meta'],'file replacement retains old ACF profile fields and raw hours')
    updated=dict(first,pid=str(pid),name_fa='Synthetic ACF After'); response=ajax(updated)
    check(not response['created'],'new plugin edits existing synthetic profile')
    final=ajax({'action':'hj_ci_acf_state'}); html=ajax({'action':'hj_ci_acf_render'})['html']
    check(final['profile']==pid and final['name']=='Synthetic ACF After' and final['hours_meta']==before['hours_meta'],'new plugin saves name without losing existing hours under free ACF')
    check('09:00' in html and '12:00' in html,'dashboard renders the saved synthetic hours with real ACF free')
    print('PASS: actual ACF free old-to-new file-replacement smoke test',flush=True)
finally:
    stop(server)
    if activated: cli('restore')
    if installed_mu: mu.unlink()
    if installed_acf: acf_link.unlink()
    if installed_product: product.unlink()
    if olddir.exists():
        import shutil; shutil.rmtree(olddir)
