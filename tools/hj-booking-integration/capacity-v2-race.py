#!/usr/bin/env python3
"""Real booking-create race in a CI-only v2 schema experiment, never production."""
import os, subprocess, time
from pathlib import Path
if os.getenv('GITHUB_ACTIONS') != 'true' or os.getenv('HJ_DISPOSABLE_MARIADB_FIXTURE') != '1': raise SystemExit('Disposable CI only')
root=Path(os.environ['GITHUB_WORKSPACE']); prefix=Path(os.environ['RUNNER_TEMP'])/'hj-capacity-v2'
cmd=['php','-d','auto_prepend_file='+str(root/'tools/hj-booking-integration/prepend-v2.php'),str(root/'tools/hj-booking-integration/capacity-v2-fixture.php')]
off=['php','-d','auto_prepend_file='+str(root/'tools/hj-booking-integration/prepend.php'),str(root/'tools/hj-booking-integration/capacity-v2-fixture.php'),'offcheck']
files=[Path(str(prefix)+s) for s in ('-start-holder','-start-others','-locked','-release')]+[Path(str(prefix)+'-ready-'+str(i)) for i in range(12)]+[Path(str(prefix)+'-before-'+str(i)) for i in range(1,12)]
for f in files: f.unlink(missing_ok=True)
workers=[]; setup=False
try:
    subprocess.run(cmd+['setup'],check=True); setup=True
    subprocess.run(off,check=True)
    subprocess.run(cmd+['patient'],check=True)
    for i in range(12): workers.append(subprocess.Popen(cmd+['worker'],stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True,env=dict(os.environ,HJ_V2_WORKER=str(i))))
    until=time.monotonic()+25
    while not all(Path(str(prefix)+'-ready-'+str(i)).exists() for i in range(12)) and time.monotonic()<until:
        if any(w.poll() is not None for w in workers): raise RuntimeError('v2 worker failed before barrier')
        time.sleep(.01)
    if not all(Path(str(prefix)+'-ready-'+str(i)).exists() for i in range(12)): raise RuntimeError('v2 readiness timeout')
    Path(str(prefix)+'-start-holder').touch(); until=time.monotonic()+10
    while not Path(str(prefix)+'-locked').exists() and time.monotonic()<until: time.sleep(.01)
    if not Path(str(prefix)+'-locked').exists(): raise RuntimeError('v2 holder did not obtain mutex')
    Path(str(prefix)+'-start-others').touch()
    try:
        observed=subprocess.run(cmd+['waiter'],capture_output=True,text=True,timeout=4)
        if observed.returncode or observed.stdout.strip()!='WAITING': raise RuntimeError('v2 wait not observed: '+observed.stderr)
        print('PASS: actual v2 create waiter observed in MariaDB PROCESSLIST',flush=True)
    finally: Path(str(prefix)+'-release').touch()
    results=[]
    for w in workers:
        out,err=w.communicate(timeout=40)
        if w.returncode: raise RuntimeError('v2 create worker failed: '+err[-1000:])
        results.append(out.strip())
    if results.count('created')!=3 or results.count('invalid_slot')!=9: raise AssertionError(results)
    print('PASS: real create accepts exactly three of twelve simultaneous v2 requests',flush=True)
    replay=subprocess.run(cmd+['replay'],capture_output=True,text=True,check=True,env=dict(os.environ,HJ_V2_WORKER='0'))
    if replay.stdout.strip()!='replayed': raise RuntimeError('same request did not replay: '+replay.stdout+' '+replay.stderr)
    print('PASS: real v2 create replays the same request without duplicating it',flush=True)
    subprocess.run(cmd+['cancel'],check=True)
    retry=subprocess.run(cmd+['retry'],capture_output=True,text=True,check=True,env=dict(os.environ,HJ_V2_WORKER='12'))
    if retry.stdout.strip()!='created': raise RuntimeError('cancelled v2 slot not released: '+retry.stdout+' '+retry.stderr)
    print('PASS: real create reuses a cancelled v2 place',flush=True)
    subprocess.run(cmd+['verify'],check=True)
finally:
    Path(str(prefix)+'-release').touch()
    for w in workers:
        if w.poll() is None: w.kill(); w.wait()
    if setup: subprocess.run(cmd+['cleanup'],check=True)
    for f in files: f.unlink(missing_ok=True)
