#!/usr/bin/env python3
"""Concurrent schema creation using disposable WordPress/MariaDB only."""
import os, subprocess, time
from pathlib import Path
if os.getenv('GITHUB_ACTIONS') != 'true' or os.getenv('HJ_DISPOSABLE_MARIADB_FIXTURE') != '1': raise SystemExit('Disposable CI only')
root = Path(os.environ['GITHUB_WORKSPACE']); prefix = Path(os.environ['RUNNER_TEMP']) / 'hj-booking-migration-barrier'
for f in prefix.parent.glob(prefix.name + '-*'): f.unlink()
worker = root / 'tools/hj-booking-integration/migration-worker.php'
prepend = root / 'tools/hj-booking-integration/prepend.php'
processes = [subprocess.Popen(['php', '-d', 'auto_prepend_file=' + str(prepend), str(worker)], stdout=subprocess.PIPE, stderr=subprocess.STDOUT, text=True) for _ in range(8)]
try:
    end=time.monotonic()+20
    while len(list(prefix.parent.glob(prefix.name + '-[0-9]*'))) < len(processes) and time.monotonic()<end: time.sleep(.02)
    if len(list(prefix.parent.glob(prefix.name + '-[0-9]*'))) != len(processes): raise RuntimeError('migration workers did not reach barrier')
    (prefix.parent / (prefix.name + '-release')).touch()
    for process in processes:
        output,_=process.communicate(timeout=40)
        if process.returncode: raise RuntimeError('schema worker failed: ' + output[-1000:])
        print(output.strip(), flush=True)
    print('PASS: eight simultaneous migrations converge without deleting existing tables or data')
finally:
    for process in processes:
        if process.poll() is None: process.kill(); process.wait()
    for f in prefix.parent.glob(prefix.name + '-*'): f.unlink()
