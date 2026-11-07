#!/usr/bin/env python3
"""CI-only synthetic table-copy restore with SIGKILL before transaction commit."""
import os, subprocess, time
from pathlib import Path
if os.getenv('GITHUB_ACTIONS')!='true' or os.getenv('HJ_DISPOSABLE_MARIADB_FIXTURE')!='1':
    raise SystemExit('disposable CI only')
root=Path(os.environ['GITHUB_WORKSPACE']); marker=Path(os.environ['RUNNER_TEMP'])/'hj-ci-recovery-ready'
cmd=['php','-d','auto_prepend_file='+str(root/'tools/hj-booking-integration/prepend.php'),str(root/'tools/hj-booking-integration/capacity-recovery-fixture.php')]
def run(mode, test=None):
    env=dict(os.environ)
    if test: env['HJ_RECOVERY_TEST']=test
    p=subprocess.run(cmd+[mode],env=env,stdout=subprocess.PIPE,stderr=subprocess.STDOUT,text=True,timeout=25)
    if p.returncode: raise RuntimeError(mode+' failed: '+p.stdout[-2000:])
    print(p.stdout.strip(),flush=True)
    return p.stdout.strip()
setup=False; worker=None
marker.unlink(missing_ok=True)
try:
    run('setup'); setup=True
    run('tamper')
    if run('worker','reject')!='REJECTED': raise RuntimeError('incomplete backup accepted')
    run('repair-fixture')
    run('bad-schema')
    if run('worker','reject')!='REJECTED': raise RuntimeError('incompatible backup schema accepted')
    run('repair-schema'); run('dirty-target')
    if run('worker','reject')!='REJECTED': raise RuntimeError('nonempty restore target accepted')
    run('clean-target'); run('seed-later')
    worker=subprocess.Popen(cmd+['worker'],env=dict(os.environ,HJ_RECOVERY_TEST='interrupt'),stdout=subprocess.PIPE,stderr=subprocess.STDOUT,text=True)
    until=time.monotonic()+15
    while not marker.exists() and time.monotonic()<until:
        if worker.poll() is not None: raise RuntimeError('worker exited before checkpoint: '+worker.communicate()[0][-1000:])
        time.sleep(.01)
    if not marker.exists(): raise RuntimeError('restore checkpoint not reached')
    worker.kill(); out=worker.communicate(timeout=5)[0]
    if worker.returncode!=-9: raise RuntimeError('restore worker was not SIGKILLed: '+out[-1000:])
    run('check-empty')
    if run('worker','commit')!='COMMITTED': raise RuntimeError('restore retry did not commit')
    run('check')
finally:
    if worker is not None and worker.poll() is None: worker.kill(); worker.wait(timeout=5)
    if setup: run('cleanup')
    marker.unlink(missing_ok=True)
