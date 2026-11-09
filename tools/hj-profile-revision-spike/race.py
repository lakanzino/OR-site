#!/usr/bin/env python3
"""Run only against disposable CI; both workers reach the CAS before release."""
import json, os, pathlib, subprocess, tempfile, time
if os.environ.get('GITHUB_ACTIONS') != 'true' or os.environ.get('HJ_DISPOSABLE_MARIADB_FIXTURE') != '1':
    raise SystemExit('Refusing non-disposable environment')
script = str(pathlib.Path(__file__).with_name('fixture.php'))
setup = subprocess.run(['php', script, 'setup'], check=True, text=True, capture_output=True)
values = json.loads(setup.stdout.strip())
with tempfile.TemporaryDirectory(prefix='hj-profile-spike-') as temp:
    workers = [subprocess.Popen(['php',script,'worker',str(values['pid']),str(values['uid']),values['token'],temp,label],stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True) for label in ('a','b')]
    try:
        deadline = time.monotonic()+25
        while not all((pathlib.Path(temp)/('ready-'+label)).exists() for label in ('a','b')):
            if any(p.poll() is not None for p in workers) or time.monotonic()>=deadline:
                raise RuntimeError('both workers did not reach barrier')
            time.sleep(.01)
        (pathlib.Path(temp)/'release').touch()
        results=[]
        for p in workers:
            stdout,stderr=p.communicate(timeout=30)
            if p.returncode: raise RuntimeError(f'worker failure: {stderr} {stdout}')
            results.append(json.loads(stdout.strip())['status'])
        if sorted(results)!=['conflict','saved']: raise AssertionError(f'unexpected CAS results {results}')
    finally:
        for p in workers:
            if p.poll() is None: p.kill(); p.wait()
with tempfile.TemporaryDirectory(prefix='hj-profile-lost-') as temp:
    token_file=str(pathlib.Path(temp)/'generation')
    worker=subprocess.run(['php',script,'lost-worker',str(values['pid']),str(values['uid']),token_file],check=True,text=True,capture_output=True,timeout=30)
    if worker.stdout.strip() or worker.stderr.strip(): raise AssertionError('expected deliberately lost response')
    lost=subprocess.run(['php',script,'lost-verify',str(values['pid']),str(values['uid']),token_file],check=True,text=True,capture_output=True)
    print(lost.stdout.strip())
with tempfile.TemporaryDirectory(prefix='hj-profile-receipt-') as temp:
    token_file=str(pathlib.Path(temp)/'generation')
    worker=subprocess.run(['php',script,'receipt-worker',str(values['pid']),str(values['uid']),token_file],check=True,text=True,capture_output=True,timeout=30)
    if worker.stdout.strip() or worker.stderr.strip(): raise AssertionError('expected deliberately lost receipt response')
    recovered=subprocess.run(['php',script,'receipt-verify',str(values['pid']),str(values['uid']),token_file],check=True,text=True,capture_output=True)
    print(recovered.stdout.strip())
token=subprocess.run(['php',script,'receipt-race-token',str(values['pid']),str(values['uid'])],check=True,text=True,capture_output=True).stdout.strip()
with tempfile.TemporaryDirectory(prefix='hj-receipt-race-') as temp:
    workers=[subprocess.Popen(['php',script,'receipt-race-worker',str(values['pid']),str(values['uid']),token,temp,label],stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True) for label in ('a','b')]
    try:
        deadline=time.monotonic()+25
        while not all((pathlib.Path(temp)/('ready-'+label)).exists() for label in ('a','b')):
            if any(p.poll() is not None for p in workers) or time.monotonic()>=deadline: raise RuntimeError('receipt workers did not reach barrier')
            time.sleep(.01)
        (pathlib.Path(temp)/'release').touch()
        results=[]
        for p in workers:
            stdout,stderr=p.communicate(timeout=30)
            if p.returncode: raise RuntimeError(f'receipt worker failure: {stderr} {stdout}')
            results.append(json.loads(stdout.strip()))
        if sorted(r['status'] for r in results)!=['replayed','saved'] or results[0]['generation']!=results[1]['generation']:
            raise AssertionError(f'concurrent receipt mismatch {results}')
        print('PASS simultaneous identical requests share one receipt and generation')
    finally:
        for p in workers:
            if p.poll() is None: p.kill(); p.wait()
verify=subprocess.run(['php',script,'verify',str(values['pid']),str(values['uid'])],check=True,text=True,capture_output=True)
print(verify.stdout.strip())
