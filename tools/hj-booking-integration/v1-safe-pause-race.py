#!/usr/bin/env python3
"""CI-only v1 pause experiment: patch a temporary plugin copy, not product code."""
import os, shutil, subprocess, tempfile, time
from pathlib import Path
if os.getenv('GITHUB_ACTIONS')!='true' or os.getenv('HJ_DISPOSABLE_MARIADB_FIXTURE')!='1':
    raise SystemExit('disposable CI only')
root=Path(os.environ['GITHUB_WORKSPACE']); runner=Path(os.environ['RUNNER_TEMP']); base=runner/'hj-ci-v1-pause'
files=[Path(str(base)+suffix) for suffix in ('-before','-locked','-release','-committed')]
needle='$result = $callback( $db, $config );'
replacement='''// CI-only guard injected into this temporary plugin copy.
            if ( in_array($operation, array('schedule','configure'), true) ) {
                $ci_table = $GLOBALS['wpdb']->prefix . 'ci_v1_pause_guard';
                $ci_guard = hj_booking_row($db, $db->prepare("SELECT paused,intent_active,epoch FROM `$ci_table` WHERE doctor_id=%d",$doctor));
                if ( !pause_guard_valid($ci_guard) || !in_array((string)$config['enabled'],array('0','1'),true) || (int)$config['enabled'] !== (1-(int)$ci_guard['paused']) ) {
                    $result = array('status'=>'guard_unavailable');
                } elseif ( (int)$ci_guard['paused']===1 || (int)$ci_guard['intent_active']===1 ) {
                    $result = array('status'=>'coordination_blocked');
                } else { $result = $callback($db, $config); }
            } else { $result = $callback($db, $config); }'''
with tempfile.TemporaryDirectory(prefix='hj-v1-pause-',dir=runner) as temp:
    plugin=Path(temp)/'plugin'; shutil.copytree(root/'plugins/hamyar-doctors',plugin)
    target=plugin/'includes/appointments.php'; source=target.read_text(encoding='utf8')
    if source.count(needle)!=1: raise RuntimeError('refuse to patch changed transaction implementation')
    target.write_text(source.replace(needle,replacement),encoding='utf8')
    env=dict(os.environ,HJ_V1_PAUSE_PLUGIN=str(plugin)); cmd=['php','-d','auto_prepend_file='+str(root/'tools/hj-booking-integration/prepend.php'),str(root/'tools/hj-booking-integration/v1-safe-pause-fixture.php')]
    def run(mode,extra=None):
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
        run('setup'); setup=True; run('manual'); run('seed-races')
        for case in ('pause_first','resume_first'):
            for f in files[:3]: f.unlink(missing_ok=True)
            holder=subprocess.Popen(cmd+['worker'],env=dict(env,HJ_V1_PAUSE_CASE=case,HJ_V1_PAUSE_ROLE='holder'),stdout=subprocess.PIPE,stderr=subprocess.STDOUT,text=True); processes.append(holder)
            wait(files[1],holder)
            waiter=subprocess.Popen(cmd+['worker'],env=dict(env,HJ_V1_PAUSE_CASE=case,HJ_V1_PAUSE_ROLE='waiter'),stdout=subprocess.PIPE,stderr=subprocess.STDOUT,text=True); processes.append(waiter)
            try:
                wait(files[0],holder)
                if run('waiter')!='WAITING': raise RuntimeError('no real mutex wait observed')
            finally: files[2].touch()
            h=holder.communicate(timeout=20)[0].strip(); w=waiter.communicate(timeout=20)[0].strip()
            expected=('paused','stale') if case=='pause_first' else ('resumed','paused')
            if holder.returncode or waiter.returncode or (h,w)!=expected: raise RuntimeError(case+' unexpected '+repr((h,w)))
            print('PASS: '+case+' serialized '+repr(expected),flush=True); run('check-race',{'HJ_V1_PAUSE_CASE':case})
        for case in ('stop_booking','booking_stop'):
            for f in files[:3]: f.unlink(missing_ok=True)
            holder=subprocess.Popen(cmd+['booking-worker'],env=dict(env,HJ_V1_PAUSE_CASE=case,HJ_V1_PAUSE_ROLE='holder'),stdout=subprocess.PIPE,stderr=subprocess.STDOUT,text=True); processes.append(holder)
            wait(files[1],holder)
            waiter=subprocess.Popen(cmd+['booking-worker'],env=dict(env,HJ_V1_PAUSE_CASE=case,HJ_V1_PAUSE_ROLE='waiter'),stdout=subprocess.PIPE,stderr=subprocess.STDOUT,text=True); processes.append(waiter)
            try:
                wait(files[0],holder)
                if run('waiter')!='WAITING': raise RuntimeError('no real booking mutex wait observed')
            finally: files[2].touch()
            h=holder.communicate(timeout=20)[0].strip(); w=waiter.communicate(timeout=20)[0].strip()
            expected=('paused','unavailable') if case=='stop_booking' else ('created','paused')
            if holder.returncode or waiter.returncode or (h,w)!=expected: raise RuntimeError(case+' unexpected '+repr((h,w)))
            print('PASS: '+case+' serialized '+repr(expected),flush=True); run('check-booking-race',{'HJ_V1_PAUSE_CASE':case})
        lost=subprocess.Popen(cmd+['lost'],env=env,stdout=subprocess.PIPE,stderr=subprocess.STDOUT,text=True); processes.append(lost)
        wait(files[3],lost); lost.kill(); out=lost.communicate(timeout=5)[0]
        if lost.returncode!=-9: raise RuntimeError('lost response process not killed: '+out[-1000:])
        run('after-lost'); run('fault'); run('missing')
    finally:
        files[2].touch()
        for p in processes:
            if p.poll() is None: p.kill(); p.wait(timeout=5)
        if setup: run('cleanup')
        for f in files: f.unlink(missing_ok=True)
