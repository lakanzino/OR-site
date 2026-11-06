#!/usr/bin/env python3
"""Concurrent schema creation and repeat with synthetic data in disposable CI only."""
import os, subprocess, time
from pathlib import Path
if os.getenv('GITHUB_ACTIONS') != 'true' or os.getenv('HJ_DISPOSABLE_MARIADB_FIXTURE') != '1': raise SystemExit('Disposable CI only')
root = Path(os.environ['GITHUB_WORKSPACE']); prefix = Path(os.environ['RUNNER_TEMP']) / 'hj-booking-migration-barrier'
worker = root / 'tools/hj-booking-integration/migration-worker.php'
prepend = root / 'tools/hj-booking-integration/prepend.php'
sentinel = root / 'tools/hj-booking-integration/migration-sentinel.php'
def run_race():
    for f in prefix.parent.glob(prefix.name + '-*'): f.unlink()
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
    finally:
        for process in processes:
            if process.poll() is None: process.kill(); process.wait()
        for f in prefix.parent.glob(prefix.name + '-*'): f.unlink()
def snapshot(mode):
    return subprocess.check_output(['php', '-d', 'auto_prepend_file=' + str(prepend), str(sentinel), mode], text=True).strip()
run_race()
before=snapshot('seed')
run_race()
after=snapshot('check')
if len(before) != 64 or before != after: raise AssertionError('simultaneous repeat changed schema or synthetic rows')
print('PASS: eight simultaneous fresh migrations and eight repeats preserve all synthetic rows and schema')
