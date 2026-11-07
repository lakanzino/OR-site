#!/usr/bin/env python3
"""CI-only capacity receipt prototype; real MariaDB mutex, synthetic data."""
import os, subprocess, time
from pathlib import Path
if os.getenv('GITHUB_ACTIONS')!='true' or os.getenv('HJ_DISPOSABLE_MARIADB_FIXTURE')!='1': raise SystemExit('disposable CI only')
root=Path(os.environ['GITHUB_WORKSPACE']); prefix=Path(os.environ['RUNNER_TEMP'])/'hj-ci-capacity-idem'
cmd=['php','-d','auto_prepend_file='+str(root/'tools/hj-booking-integration/prepend-v2.php'),str(root/'tools/hj-booking-integration/capacity-idempotency-fixture.php')]
def file(s): return Path(str(prefix)+s)
def run(mode, env=None):
    result=subprocess.run(cmd+[mode],env=dict(os.environ,**(env or {})),stdout=subprocess.PIPE,stderr=subprocess.STDOUT,text=True,timeout=30)
    if result.returncode: raise RuntimeError(mode+' failed: '+result.stdout[-2500:])
    print(result.stdout.strip(),flush=True); return result.stdout.strip()
def wait(path,process,timeout=15):
    end=time.monotonic()+timeout
    while not path.exists() and time.monotonic()<end:
        if process.poll() is not None: raise RuntimeError('worker exited early: '+process.communicate()[0][-1500:])
        time.sleep(.01)
    if not path.exists(): raise RuntimeError('fixture barrier timeout')
setup=False; workers=[]; lost=None
for f in [file('-locked'),file('-release'),file('-committed')]+[file('-before-'+str(i)) for i in range(1,8)]: f.unlink(missing_ok=True)
try:
    run('setup'); setup=True; run('policy')
    workers.append(subprocess.Popen(cmd+['worker'],env=dict(os.environ,HJ_CAPIDEM_WORKER='0'),stdout=subprocess.PIPE,stderr=subprocess.STDOUT,text=True))
    wait(file('-locked'),workers[0])
    for i in range(1,8): workers.append(subprocess.Popen(cmd+['worker'],env=dict(os.environ,HJ_CAPIDEM_WORKER=str(i)),stdout=subprocess.PIPE,stderr=subprocess.STDOUT,text=True))
    try:
        for i in range(1,8): wait(file('-before-'+str(i)),workers[0])
        if run('waiter')!='WAITING': raise RuntimeError('mutex wait not observed')
    finally: file('-release').touch()
    outputs=[]
    for worker in workers:
        out=worker.communicate(timeout=25)[0].strip()
        if worker.returncode: raise RuntimeError('concurrent worker failed: '+out[-1500:])
        outputs.append(out)
    if outputs.count('saved')!=1 or outputs.count('replayed')!=7: raise RuntimeError('unexpected results: '+str(outputs))
    print('PASS: eight simultaneous identical requests yield one capacity save and seven historical replays',flush=True)
    run('after-race'); run('precommit')
    lost=subprocess.Popen(cmd+['lost'],stdout=subprocess.PIPE,stderr=subprocess.STDOUT,text=True)
    wait(file('-committed'),lost)
    lost.kill(); out=lost.communicate(timeout=5)[0]
    if lost.returncode!=-9: raise RuntimeError('committed response was not killed: '+out[-1000:])
    run('after-lost')
finally:
    file('-release').touch()
    for worker in workers:
        if worker.poll() is None: worker.kill(); worker.wait(timeout=5)
    if lost is not None and lost.poll() is None: lost.kill(); lost.wait(timeout=5)
    if setup: run('cleanup')
    for f in [file('-locked'),file('-release'),file('-committed')]+[file('-before-'+str(i)) for i in range(1,8)]: f.unlink(missing_ok=True)
