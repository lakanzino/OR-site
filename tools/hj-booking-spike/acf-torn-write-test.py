#!/usr/bin/env python3
"""Counterexample: a mocked multirow writer can overlap the doctor lock."""
import os
import subprocess
import time
from pathlib import Path

if os.getenv('GITHUB_ACTIONS') != 'true' or os.getenv('HJ_DISPOSABLE_MARIADB_FIXTURE') != '1':
    raise SystemExit('Disposable CI only')
base = ['php', 'tools/hj-booking-spike/acf-torn-write-fixture.php']
paths = [Path('/tmp/hj-acf-torn-' + x) for x in ('profile', 'half', 'read', 'done')]
for p in paths: p.unlink(missing_ok=True)
subprocess.run(base + ['setup'], check=True)
workers = []
try:
    writer = subprocess.Popen(base + ['writer'], stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
    workers.append(writer)
    deadline = time.monotonic() + 10
    while not paths[1].exists() and time.monotonic() < deadline:
        if writer.poll() is not None: raise RuntimeError('writer exited before barrier')
        time.sleep(.02)
    if not paths[1].exists(): raise RuntimeError('writer did not reach barrier')
    booking = subprocess.Popen(base + ['book'], stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
    workers.append(booking)
    for process, expected in ((writer, 'writer_done'), (booking, 'created')):
        stdout, stderr = process.communicate(timeout=20)
        if process.returncode or stdout.strip() != expected: raise RuntimeError((stdout, stderr))
    subprocess.run(base + ['verify'], check=True)
finally:
    for process in workers:
        if process.poll() is None:
            process.kill()
            process.wait()
    for p in paths: p.unlink(missing_ok=True)
