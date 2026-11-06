#!/usr/bin/env python3
"""Synchronized capacity-three race, isolated WordPress/MariaDB prototype."""
import os, subprocess, time
from pathlib import Path
if os.getenv('GITHUB_ACTIONS') != 'true' or os.getenv('HJ_DISPOSABLE_MARIADB_FIXTURE') != '1': raise SystemExit('Disposable CI only')
prefix=Path(os.environ['RUNNER_TEMP'])/'hj-capacity-spike'; go=Path(str(prefix)+'-go'); ready=[Path(str(prefix)+'-ready-'+str(i)) for i in range(12)]
for f in [go,*ready]: f.unlink(missing_ok=True)
workers=[]
try:
    for i in range(len(ready)):
        workers.append(subprocess.Popen(['php','tools/hj-booking-spike/capacity-fixture.php','race'],stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True,env=dict(os.environ,HJ_CAP_WORKER=str(i))))
    deadline=time.monotonic()+25
    while not all(f.exists() for f in ready) and time.monotonic()<deadline:
        if any(w.poll() is not None for w in workers): raise RuntimeError('capacity worker exited before barrier')
        time.sleep(.02)
    if not all(f.exists() for f in ready): raise RuntimeError('capacity barrier incomplete')
    go.touch(); results=[]
    for w in workers:
        out,err=w.communicate(timeout=40)
        if w.returncode: raise RuntimeError('capacity worker failed: '+err[-1000:])
        results.append(out.strip())
    if results.count('created')!=3 or results.count('full')!=9: raise AssertionError(results)
    print('PASS: twelve concurrent synthetic requests: three created, nine full')
finally:
    for w in workers:
        if w.poll() is None: w.kill(); w.wait()
    for f in [go,*ready]: f.unlink(missing_ok=True)
