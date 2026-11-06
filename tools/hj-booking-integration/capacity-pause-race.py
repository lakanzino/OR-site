#!/usr/bin/env python3
"""Both deterministic pause/create lock orders on disposable MariaDB only."""
import os, subprocess, sys, time
from pathlib import Path
if os.getenv('GITHUB_ACTIONS')!='true' or os.getenv('HJ_DISPOSABLE_MARIADB_FIXTURE')!='1': raise SystemExit('Disposable CI only')
root=Path(os.environ['GITHUB_WORKSPACE']); prefix=Path(os.environ['RUNNER_TEMP'])/'hj-capacity-v2'
cmd=['php','-d','auto_prepend_file='+str(root/'tools/hj-booking-integration/prepend-v2.php'),str(root/'tools/hj-booking-integration/capacity-v2-fixture.php')]
for round in ('pause','create'):
    race=Path(str(prefix)+'-pause-'+round); paths=[Path(str(race)+s) for s in ('-before','-locked','-release')]
    for p in paths: p.unlink(missing_ok=True)
    held=waiter=None
    try:
        base=dict(os.environ,HJ_PAUSE_ROUND=round)
        held=subprocess.Popen(cmd+['pause-worker'],stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True,env=dict(base,HJ_PAUSE_ROLE='holder',HJ_PAUSE_ACTOR=round))
        end=time.monotonic()+10
        while not paths[1].exists() and time.monotonic()<end:
            if held.poll() is not None: raise RuntimeError('holder exited: '+held.communicate()[1])
            time.sleep(.01)
        if not paths[1].exists(): raise RuntimeError('holder failed to lock')
        other='create' if round=='pause' else 'pause'
        waiter=subprocess.Popen(cmd+['pause-worker'],stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True,env=dict(base,HJ_PAUSE_ROLE='waiter',HJ_PAUSE_ACTOR=other))
        try:
            observed=subprocess.run(cmd+['pause-waiter'],capture_output=True,text=True,timeout=4,env=base)
            if observed.returncode or observed.stdout.strip()!='WAITING': raise RuntimeError('MariaDB waiter not observed: '+observed.stderr)
            print('PASS: MariaDB wait observed with '+round+' holding the doctor mutex',flush=True)
        finally: paths[2].touch()
        out1,err1=held.communicate(timeout=20); out2,err2=waiter.communicate(timeout=20)
        expected=('paused','unavailable') if round=='pause' else ('created','paused')
        if held.returncode or waiter.returncode or (out1.strip(),out2.strip())!=expected: raise RuntimeError(f'Unexpected {round} race outcomes: {out1!r} {out2!r} {err1!r} {err2!r}')
        print('PASS: '+round+'-first order returned '+', '.join(expected),flush=True)
        subprocess.run(cmd+['pause-check'],check=True,env=base)
    finally:
        paths[2].touch()
        for process in (held,waiter):
            if process and process.poll() is None: process.kill(); process.wait()
        for p in paths: p.unlink(missing_ok=True)
