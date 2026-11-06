#!/usr/bin/env python3
"""SIGKILL after completed DDL, with competing migrations waiting on GET_LOCK.
Only tests the disposable CI experiment; not a production migration/drain test.
"""
import os, subprocess, time
from pathlib import Path

if os.getenv('GITHUB_ACTIONS') != 'true' or os.getenv('HJ_DISPOSABLE_MARIADB_FIXTURE') != '1':
    raise SystemExit('disposable CI only')
root = Path(os.environ['GITHUB_WORKSPACE'])
base = Path(os.environ['RUNNER_TEMP']) / 'hj-ci-schema-race'
fixture = root / 'tools/hj-booking-integration/capacity-schema-race-fixture.php'
prepend = root / 'tools/hj-booking-integration/prepend.php'
cmd = ['php', '-d', 'auto_prepend_file=' + str(prepend), str(fixture)]
def run(mode):
    result = subprocess.run(cmd + [mode], stdout=subprocess.PIPE, stderr=subprocess.STDOUT, text=True, timeout=25)
    if result.returncode: raise RuntimeError(f'{mode} failed: {result.stdout[-2000:]}')
    if result.stdout: print(result.stdout.strip(), flush=True)
    return result.stdout
def wait_file(path, holder, timeout=15):
    deadline = time.monotonic() + timeout
    while time.monotonic() < deadline:
        if path.exists(): return
        if holder.poll() is not None:
            raise RuntimeError('holder exited early: ' + holder.communicate()[0][-2000:])
        time.sleep(.01)
    raise RuntimeError('holder checkpoint timed out')
setup = False
try:
    run('setup'); setup = True
    for checkpoint in ('column', 'table'):
        for f in base.parent.glob(base.name + '-before-*'): f.unlink()
        (base.parent / (base.name + '-holder-locked')).unlink(missing_ok=True)
        procs = []
        try:
            env = dict(os.environ, HJ_SCHEMA_ACTOR='holder', HJ_SCHEMA_CHECKPOINT=checkpoint)
            holder = subprocess.Popen(cmd + ['worker'], env=env, stdout=subprocess.PIPE, stderr=subprocess.STDOUT, text=True)
            procs.append(holder)
            wait_file(base.parent / (base.name + '-holder-locked'), holder)
            for actor in ('waiter1', 'waiter2', 'waiter3'):
                env = dict(os.environ, HJ_SCHEMA_ACTOR=actor, HJ_SCHEMA_CHECKPOINT=checkpoint)
                procs.append(subprocess.Popen(cmd + ['worker'], env=env, stdout=subprocess.PIPE, stderr=subprocess.STDOUT, text=True))
            for actor in ('waiter1', 'waiter2', 'waiter3'):
                wait_file(base.parent / (base.name + '-before-' + actor), holder)
            if run('waiter-check').strip() != 'WAITING': raise RuntimeError('did not observe an actual lock waiter')
            holder.kill(); output = holder.communicate(timeout=5)[0]
            if holder.returncode != -9: raise RuntimeError('holder was not killed by SIGKILL: ' + output[-1000:])
            for proc in procs[1:]:
                out = proc.communicate(timeout=15)[0]
                if proc.returncode or out.strip() != 'prepared': raise RuntimeError('concurrent waiter failed: ' + out[-2000:])
            print('PASS: SIGKILL after completed ' + checkpoint + ' DDL releases lock; three concurrent reruns finish', flush=True)
            run('check'); run('reset')
        finally:
            for proc in procs:
                if proc.poll() is None: proc.kill(); proc.wait(timeout=5)
finally:
    if setup: run('cleanup')
    for f in base.parent.glob(base.name + '-before-*'): f.unlink()
    (base.parent / (base.name + '-holder-locked')).unlink(missing_ok=True)
