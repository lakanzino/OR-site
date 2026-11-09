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

# A separate, synthetic role is removed after the worker's authorization check.
rev=json.loads(subprocess.run(['php',script,'revoke-setup'],check=True,text=True,capture_output=True).stdout.strip())
with tempfile.TemporaryDirectory(prefix='hj-profile-revoke-') as temp:
    p=subprocess.Popen(['php',script,'revoke-worker',str(rev['pid']),str(rev['uid']),rev['token'],temp],stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True)
    try:
        deadline=time.monotonic()+25
        while not (pathlib.Path(temp)/'ready').exists():
            if p.poll() is not None or time.monotonic()>=deadline: raise RuntimeError('revocation worker did not reach barrier')
            time.sleep(.01)
        subprocess.run(['php',script,'revoke-role',str(rev['uid'])],check=True,text=True,capture_output=True)
        (pathlib.Path(temp)/'release').touch()
        stdout,stderr=p.communicate(timeout=30)
        if p.returncode: raise RuntimeError(f'revoked worker failure: {stderr} {stdout}')
        if json.loads(stdout)['status']!='saved': raise AssertionError('expected in-flight revocation counterexample')
    finally:
        if p.poll() is None: p.kill(); p.wait()
verified=subprocess.run(['php',script,'revoke-verify',str(rev['pid']),str(rev['uid'])],check=True,text=True,capture_output=True)
print(verified.stdout.strip())

# Contrast: a write holding the capability row lock serializes with stock WP set_role.
guard=json.loads(subprocess.run(['php',script,'guard-setup'],check=True,text=True,capture_output=True).stdout.strip())
with tempfile.TemporaryDirectory(prefix='hj-role-lock-') as temp:
    writer=subprocess.Popen(['php',script,'guard-worker',str(guard['uid']),guard['option'],temp],stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True)
    revoke=None
    try:
        deadline=time.monotonic()+20
        while not (pathlib.Path(temp)/'locked').exists():
            if writer.poll() is not None or time.monotonic()>=deadline: raise RuntimeError('role lock not acquired')
            time.sleep(.01)
        revoke=subprocess.Popen(['php',script,'guard-revoke',str(guard['uid']),temp],stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True)
        deadline=time.monotonic()+20
        while not (pathlib.Path(temp)/'revoke-ready').exists():
            if revoke.poll() is not None or time.monotonic()>=deadline: raise RuntimeError('role revocation did not start')
            time.sleep(.01)
        # Observe the actual UPDATE on the revocation connection, not merely a sleeping process.
        connection=(pathlib.Path(temp)/'revoke-ready').read_text().strip()
        if not connection.isdigit(): raise RuntimeError('invalid revocation connection id')
        deadline=time.monotonic()+10
        while True:
            if revoke.poll() is not None or time.monotonic()>=deadline: raise AssertionError('revocation UPDATE did not wait on locked row')
            probe=subprocess.run(['php',script,'guard-check',connection],check=True,text=True,capture_output=True,timeout=10)
            if probe.stdout.strip()=='waiting': break
        (pathlib.Path(temp)/'release').touch()
        out,err=writer.communicate(timeout=30)
        if writer.returncode or json.loads(out)['status']!='saved': raise RuntimeError(f'guarded writer failed {out} {err}')
        out,err=revoke.communicate(timeout=30)
        if revoke.returncode or out.strip()!='revoked': raise RuntimeError(f'revocation failed {out} {err}')
    finally:
        for p in (writer,revoke):
            if p is not None and p.poll() is None: p.kill(); p.wait()
verified=subprocess.run(['php',script,'guard-verify',str(guard['uid']),guard['option']],check=True,text=True,capture_output=True)
print(verified.stdout.strip())
