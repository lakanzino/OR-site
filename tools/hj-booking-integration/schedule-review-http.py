#!/usr/bin/env python3
"""Disposable fixture only: real WordPress auth, loopback HTTP and Chromium."""
import json, os, signal, subprocess, time, urllib.request
from pathlib import Path
if os.getenv('GITHUB_ACTIONS')!='true' or os.getenv('HJ_DISPOSABLE_MARIADB_FIXTURE')!='1': raise SystemExit('disposable CI only')
root=Path(os.environ['GITHUB_WORKSPACE']); tool=root/'tools/hj-booking-integration'; meta=Path(os.environ['RUNNER_TEMP'])/'hj-ci-schedule-intent.json'
cmd=['php','-d','auto_prepend_file='+str(tool/'prepend.php'),str(tool/'schedule-intent-fixture.php')]
def run(mode): return subprocess.run(cmd+[mode],check=True,capture_output=True,text=True,timeout=40).stdout.strip()
setup=False; server=None; server_log=Path(os.environ['RUNNER_TEMP'])/'hj-review-http-server.log'
try:
 print(run('setup'),flush=True); setup=True; print(run('http-seed'),flush=True); before=run('http-audit'); doctor=str(json.loads(meta.read_text())['doctor'])
 log=server_log.open('w')
 server=subprocess.Popen(['php','-S','127.0.0.1:8995','-t','/wordpress',str(tool/'schedule-review-http-router.php')],stdout=log,stderr=log,start_new_session=True)
 for _ in range(80):
  try:
   req=urllib.request.Request('http://127.0.0.1:8995/review.js',headers={'Host':'example.invalid:8995'})
   with urllib.request.urlopen(req,timeout=2) as r:
    if r.status==200: break
  except OSError: time.sleep(.25)
 else: raise RuntimeError('fixture server failed')
 subprocess.run(['node',str(tool/'schedule-review-http-browser.cjs')],check=True,timeout=90,env=dict(os.environ,HJ_REVIEW_DOCTOR=doctor))
 assert before==run('http-audit'), 'review must not modify snapshot or intent rows'
 print('PASS: snapshot and intent rows unchanged by browser HTTP review',flush=True)
except Exception:
 if server_log.exists(): print('Server diagnostic:',server_log.read_text()[-1000:],flush=True)
 raise
finally:
 if server is not None:
  os.killpg(server.pid,signal.SIGTERM); server.wait(timeout=10)
 if setup: print(run('cleanup'),flush=True)
 if server_log.exists(): server_log.unlink()
