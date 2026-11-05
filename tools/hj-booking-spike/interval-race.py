#!/usr/bin/env python3
"""Synchronized, independent WordPress/PHP processes, disposable CI only."""
import os
import subprocess
import time
from pathlib import Path

if os.getenv('GITHUB_ACTIONS') != 'true' or os.getenv('HJ_DISPOSABLE_MARIADB_FIXTURE') != '1':
    raise SystemExit('Disposable CI only')
go = Path('/tmp/hj-interval-go')
ready = [Path(f'/tmp/hj-interval-ready-{i}') for i in range(12)]
for kind in ('same', 'overlap'):
    for path in [go, *ready]:
        path.unlink(missing_ok=True)
    workers = []
    try:
        for i in range(len(ready)):
            env = dict(os.environ, HJ_SPIKE_WORKER=str(i), HJ_SPIKE_KIND=kind)
            workers.append(subprocess.Popen(['php', 'tools/hj-booking-spike/interval-fixture.php', 'race'], stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, env=env))
        deadline = time.monotonic() + 25
        while not all(p.exists() for p in ready) and time.monotonic() < deadline:
            if any(w.poll() is not None for w in workers):
                raise RuntimeError('Worker exited before barrier')
            time.sleep(.02)
        if not all(p.exists() for p in ready):
            raise RuntimeError('Workers did not reach barrier')
        go.touch()
        outputs = []
        for w in workers:
            stdout, stderr = w.communicate(timeout=35)
            if w.returncode:
                raise RuntimeError(f'Worker failed: {stderr}')
            outputs.append(stdout.strip())
        if outputs.count('created') != 1 or outputs.count('overlap') != len(ready) - 1:
            raise AssertionError(outputs)
        print(f'PASS: {kind} race: one winner out of twelve synchronized processes')
    finally:
        for w in workers:
            if w.poll() is None:
                w.kill()
                w.wait()
        for path in [go, *ready]:
            path.unlink(missing_ok=True)
