#!/usr/bin/env python3
"""Exercise real plugin doctor mutex with test-only capacity writes; disposable CI."""
import os, subprocess, time
from pathlib import Path
if os.getenv('GITHUB_ACTIONS') != 'true' or os.getenv('HJ_DISPOSABLE_MARIADB_FIXTURE') != '1': raise SystemExit('Disposable CI only')
prefix=Path(os.environ['RUNNER_TEMP'])/'hj-capacity-real-lock'
cmd=['php','-d','auto_prepend_file='+os.environ['GITHUB_WORKSPACE']+'/tools/hj-booking-integration/prepend.php','tools/hj-booking-integration/capacity-lock-fixture.php']
files=[Path(str(prefix)+s) for s in ['-start-holder','-start-others','-locked','-release']]+[Path(str(prefix)+'-ready-'+str(i)) for i in range(12)]+[Path(str(prefix)+'-before-'+str(i)) for i in range(1,12)]
for f in files: f.unlink(missing_ok=True)
workers=[]; setup=False
try:
    subprocess.run(cmd+['setup'],check=True); setup=True
    for i in range(12): workers.append(subprocess.Popen(cmd+['worker'],stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True,env=dict(os.environ,HJ_CAP_REAL_WORKER=str(i))))
    until=time.monotonic()+25
    while not all(Path(str(prefix)+'-ready-'+str(i)).exists() for i in range(12)) and time.monotonic()<until:
        if any(w.poll() is not None for w in workers): raise RuntimeError('worker failed before start')
        time.sleep(.01)
    if not all(Path(str(prefix)+'-ready-'+str(i)).exists() for i in range(12)): raise RuntimeError('readiness timeout')
    Path(str(prefix)+'-start-holder').touch(); until=time.monotonic()+10
    while not Path(str(prefix)+'-locked').exists() and time.monotonic()<until: time.sleep(.01)
    if not Path(str(prefix)+'-locked').exists(): raise RuntimeError('holder did not lock')
    Path(str(prefix)+'-start-others').touch()
    try:
        observed=subprocess.run(cmd+['waiter'],capture_output=True,text=True,timeout=4)
        if observed.returncode or observed.stdout.strip()!='WAITING': raise RuntimeError('no real database waiter observed: '+observed.stderr)
        print('PASS: PROCESSLIST confirms a separate connection is waiting on the real doctor mutex')
    finally: Path(str(prefix)+'-release').touch()
    results=[]
    for w in workers:
        out,err=w.communicate(timeout=35)
        if w.returncode: raise RuntimeError('worker failed: '+err[-1000:])
        results.append(out.strip())
    if results.count('created')!=3 or results.count('full')!=9: raise AssertionError(results)
    print('PASS: twelve synthetic writers using the real plugin mutex: three created, nine full')
    subprocess.run(cmd+['cancel'],check=True)
    r=subprocess.run(cmd+['retry'],capture_output=True,text=True,check=True,env=dict(os.environ,HJ_CAP_REAL_WORKER='12'))
    if r.stdout.strip()!='created': raise RuntimeError('place not released after cancellation: '+r.stdout+' '+r.stderr)
    print('PASS: cancelled place can be reused under the same doctor mutex')
    subprocess.run(cmd+['verify'],check=True)
finally:
    Path(str(prefix)+'-release').touch()
    for w in workers:
        if w.poll() is None: w.kill(); w.wait()
    if setup: subprocess.run(cmd+['cleanup'],check=True)
    for f in files: f.unlink(missing_ok=True)
