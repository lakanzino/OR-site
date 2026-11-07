#!/usr/bin/env python3
"""Patch only a temporary plugin copy, then test coordination in disposable CI."""
import os, shutil, subprocess, tempfile, time
from pathlib import Path
if os.getenv('GITHUB_ACTIONS')!='true' or os.getenv('HJ_DISPOSABLE_MARIADB_FIXTURE')!='1':
    raise SystemExit('disposable CI only')
root=Path(os.environ['GITHUB_WORKSPACE']); runner=Path(os.environ['RUNNER_TEMP'])
base=runner/'hj-ci-coordination'
files=[Path(str(base)+suffix) for suffix in ('-before','-locked','-release')]
needle='$result = $callback( $db, $config );'
replacement='''// CI-only guard injected into this temporary copy, never the source plugin.
            $ci_guarded = in_array( $operation, array('schedule','capacity_schedule','capacity_resume','configure'), true );
            if ( $ci_guarded ) {
                $ci_table = $GLOBALS['wpdb']->prefix . 'ci_coordination_intents';
                $ci_active = hj_booking_row( $db, $db->prepare( "SELECT doctor_id FROM `$ci_table` WHERE doctor_id=%d AND active=1", $doctor ) );
                $result = $ci_active ? array('status'=>'intent_pending') : $callback( $db, $config );
            } else {
                $result = $callback( $db, $config );
            }'''
with tempfile.TemporaryDirectory(prefix='hj-coordination-',dir=runner) as temp:
    plugin=Path(temp)/'plugin'; shutil.copytree(root/'plugins/hamyar-doctors',plugin)
    target=plugin/'includes/appointments.php'; source=target.read_text(encoding='utf8')
    if source.count(needle)!=1: raise RuntimeError('refuse to patch changed transaction implementation')
    target.write_text(source.replace(needle,replacement),encoding='utf8')
    env=dict(os.environ,HJ_COORD_PLUGIN=str(plugin))
    cmd=['php','-d','auto_prepend_file='+str(root/'tools/hj-booking-integration/prepend-v2.php'),str(root/'tools/hj-booking-integration/schedule-coordination-fixture.php')]
    def run(mode, extra=None):
        p=subprocess.run(cmd+[mode],env=dict(env,**(extra or {})),stdout=subprocess.PIPE,stderr=subprocess.STDOUT,text=True,timeout=30)
        if p.returncode: raise RuntimeError(mode+' failed: '+p.stdout[-2500:])
        if p.stdout: print(p.stdout.strip(),flush=True)
        return p.stdout.strip()
    def wait(path,holder):
        end=time.monotonic()+15
        while not path.exists() and time.monotonic()<end:
            if holder.poll() is not None: raise RuntimeError('holder exited: '+holder.communicate()[0][-1500:])
            time.sleep(.01)
        if not path.exists(): raise RuntimeError('barrier timeout')
    for f in files: f.unlink(missing_ok=True)
    setup=False; processes=[]
    try:
        run('setup'); setup=True; run('manual')
        for case in ('v1_prepare','v1_writer','v2_prepare','v2_writer'):
            for f in files: f.unlink(missing_ok=True)
            holder=subprocess.Popen(cmd+['worker'],env=dict(env,HJ_COORD_CASE=case,HJ_COORD_ROLE='holder'),stdout=subprocess.PIPE,stderr=subprocess.STDOUT,text=True); processes.append(holder)
            wait(files[1],holder)
            waiter=subprocess.Popen(cmd+['worker'],env=dict(env,HJ_COORD_CASE=case,HJ_COORD_ROLE='waiter'),stdout=subprocess.PIPE,stderr=subprocess.STDOUT,text=True); processes.append(waiter)
            try:
                wait(files[0],holder)
                if run('waiter')!='WAITING': raise RuntimeError('did not observe database mutex waiter')
            finally: files[2].touch()
            h=holder.communicate(timeout=20)[0].strip(); w=waiter.communicate(timeout=20)[0].strip()
            expected=('prepared','intent_pending') if case.endswith('_prepare') else ('saved','stale')
            if holder.returncode or waiter.returncode or (h,w)!=expected: raise RuntimeError(case+' unexpected: '+repr((h,w)))
            print('PASS: '+case+' serializes to '+repr(expected),flush=True)
            run('check',{'HJ_COORD_CASE':case})
        run('patient'); run('patient-v2'); run('missing-table')
    finally:
        files[2].touch()
        for p in processes:
            if p.poll() is None: p.kill(); p.wait(timeout=5)
        if setup: run('cleanup')
        for f in files: f.unlink(missing_ok=True)
