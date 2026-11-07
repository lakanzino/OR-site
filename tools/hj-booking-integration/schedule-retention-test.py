#!/usr/bin/env python3
"""Run destructive counterexamples exclusively in an isolated CI fixture."""
import os, subprocess
from pathlib import Path
if os.getenv('GITHUB_ACTIONS')!='true' or os.getenv('HJ_DISPOSABLE_MARIADB_FIXTURE')!='1': raise SystemExit('disposable CI only')
tool=Path(os.environ['GITHUB_WORKSPACE'])/'tools/hj-booking-integration'
base=['php','-d','auto_prepend_file='+str(tool/'prepend.php')]
def run(args):
 p=subprocess.run(base+args,stdout=subprocess.PIPE,stderr=subprocess.STDOUT,text=True,timeout=40)
 if p.returncode: raise RuntimeError('retention fixture failed: '+p.stdout[-3000:])
 print(p.stdout.strip(),flush=True)
setup=False
try:
 run([str(tool/'schedule-intent-fixture.php'),'setup']); setup=True
 run([str(tool/'schedule-retention-fixture.php')])
finally:
 if setup: run([str(tool/'schedule-intent-fixture.php'),'cleanup'])
