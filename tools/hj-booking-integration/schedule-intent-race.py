#!/usr/bin/env python3
"""CI-only intent experiment, synthetic data and disposable MariaDB."""
import os, subprocess, time
from pathlib import Path
if os.getenv('GITHUB_ACTIONS') != 'true' or os.getenv('HJ_DISPOSABLE_MARIADB_FIXTURE') != '1':
    raise SystemExit('disposable CI only')
root=Path(os.environ['GITHUB_WORKSPACE']); prefix=Path(os.environ['RUNNER_TEMP'])/'hj-ci-schedule-intent'
cmd=['php','-d','auto_prepend_file='+str(root/'tools/hj-booking-integration/prepend.php'),str(root/'tools/hj-booking-integration/schedule-intent-fixture.php')]
def file(s): return Path(str(prefix)+s)
def run(mode, extra=None):
    p=subprocess.run(cmd+[mode],env=dict(os.environ,**(extra or {})),stdout=subprocess.PIPE,stderr=subprocess.STDOUT,text=True,timeout=30)
    if p.returncode: raise RuntimeError(mode+' failed: '+p.stdout[-2500:])
    print(p.stdout.strip(),flush=True); return p.stdout.strip()
def wait(path,process,timeout=15):
    end=time.monotonic()+timeout
    while not path.exists() and time.monotonic()<end:
        if process.poll() is not None: raise RuntimeError('worker exited: '+process.communicate()[0][-1500:])
        time.sleep(.01)
    if not path.exists(): raise RuntimeError('barrier timeout')
files=[file('-prepared'),file('-applied'),file('-cancelled'),file('-locked'),file('-release')]+[file('-before-'+str(i)) for i in range(1,9)]
for f in files: f.unlink(missing_ok=True)
setup=False; processes=[]
try:
    run('setup'); setup=True
    for mode,marker,check in [('prepare-lost','-prepared','check-prepared'),('apply-lost','-applied','check-completed')]:
        p=subprocess.Popen(cmd+[mode],stdout=subprocess.PIPE,stderr=subprocess.STDOUT,text=True); processes.append(p)
        wait(file(marker),p); p.kill(); output=p.communicate(timeout=5)[0]
        if p.returncode!=-9: raise RuntimeError('expected SIGKILL after commit: '+output[-1000:])
        run(check)
    first=subprocess.Popen(cmd+['race-prepare'],env=dict(os.environ,HJ_INTENT_WORKER='0'),stdout=subprocess.PIPE,stderr=subprocess.STDOUT,text=True); processes.append(first)
    wait(file('-locked'),first)
    workers=[first]
    for i in range(1,9):
        p=subprocess.Popen(cmd+['race-prepare'],env=dict(os.environ,HJ_INTENT_WORKER=str(i)),stdout=subprocess.PIPE,stderr=subprocess.STDOUT,text=True); processes.append(p); workers.append(p)
    try:
        for i in range(1,9): wait(file('-before-'+str(i)),first)
        if run('waiter')!='WAITING': raise RuntimeError('expected real mutex waiter')
    finally: file('-release').touch()
    outputs=[]
    for p in workers:
        out=p.communicate(timeout=25)[0].strip()
        if p.returncode: raise RuntimeError('worker failed: '+out[-1500:])
        outputs.append(out)
    if outputs.count('prepared')!=8 or outputs.count('blocked')!=1: raise RuntimeError('unexpected race results: '+str(outputs))
    print('PASS: duplicate intent preparations serialize and competing intent is blocked',flush=True)
    run('check-race')
    for f in [file('-locked'),file('-release')]+[file('-before-'+str(i)) for i in range(1,9)]: f.unlink(missing_ok=True)
    first=subprocess.Popen(cmd+['race-apply'],env=dict(os.environ,HJ_INTENT_WORKER='0'),stdout=subprocess.PIPE,stderr=subprocess.STDOUT,text=True); processes.append(first)
    wait(file('-locked'),first); workers=[first]
    for i in range(1,8):
        p=subprocess.Popen(cmd+['race-apply'],env=dict(os.environ,HJ_INTENT_WORKER=str(i)),stdout=subprocess.PIPE,stderr=subprocess.STDOUT,text=True); processes.append(p); workers.append(p)
    try:
        for i in range(1,8): wait(file('-before-'+str(i)),first)
        if run('waiter')!='WAITING': raise RuntimeError('expected real mutex waiter during apply')
    finally: file('-release').touch()
    outputs=[]
    for p in workers:
        out=p.communicate(timeout=25)[0].strip()
        if p.returncode: raise RuntimeError('apply worker failed: '+out[-1500:])
        outputs.append(out)
    if outputs.count('saved')!=1 or outputs.count('replayed')!=7: raise RuntimeError('unexpected apply race results: '+str(outputs))
    print('PASS: eight concurrent executions produce one save and seven replays after unknown',flush=True)
    run('check-apply-race'); run('cancel-seed')
    for case,check in [('cancel_first','check-cancel-first'),('apply_first','check-apply-first')]:
        for f in [file('-locked'),file('-release')]+[file('-before-'+str(i)) for i in range(1,9)]: f.unlink(missing_ok=True)
        holder=subprocess.Popen(cmd+['cancel-worker'],env=dict(os.environ,HJ_CANCEL_CASE=case,HJ_CANCEL_ROLE='holder'),stdout=subprocess.PIPE,stderr=subprocess.STDOUT,text=True); processes.append(holder)
        wait(file('-locked'),holder)
        waiter=subprocess.Popen(cmd+['cancel-worker'],env=dict(os.environ,HJ_CANCEL_CASE=case,HJ_CANCEL_ROLE='waiter'),stdout=subprocess.PIPE,stderr=subprocess.STDOUT,text=True); processes.append(waiter)
        try:
            wait(file('-before-1'),holder)
            if run('waiter')!='WAITING': raise RuntimeError('expected real mutex waiter during cancellation')
        finally: file('-release').touch()
        h=holder.communicate(timeout=25)[0].strip(); w=waiter.communicate(timeout=25)[0].strip()
        expected=('cancelled','blocked') if case=='cancel_first' else ('saved','already_completed')
        if holder.returncode or waiter.returncode or (h,w)!=expected: raise RuntimeError(case+' unexpected '+repr((h,w)))
        print('PASS: '+case+' serialized '+repr(expected),flush=True); run(check)
    lost=subprocess.Popen(cmd+['cancel-lost'],stdout=subprocess.PIPE,stderr=subprocess.STDOUT,text=True); processes.append(lost)
    wait(file('-cancelled'),lost); lost.kill(); output=lost.communicate(timeout=5)[0]
    if lost.returncode!=-9: raise RuntimeError('expected SIGKILL after cancel commit: '+output[-1000:])
    run('check-cancel-lost')
finally:
    file('-release').touch()
    for p in processes:
        if p.poll() is None: p.kill(); p.wait(timeout=5)
    if setup: run('cleanup')
    for f in files: f.unlink(missing_ok=True)
