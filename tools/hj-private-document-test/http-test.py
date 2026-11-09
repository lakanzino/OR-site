#!/usr/bin/env python3
"""Exercise private document gate on a disposable WP and PHP web server only."""
import http.cookiejar, json, os, pathlib, socket, subprocess, time, urllib.error, urllib.parse, urllib.request
if os.getenv('GITHUB_ACTIONS')!='true' or os.getenv('HJ_DISPOSABLE_MARIADB_FIXTURE')!='1': raise SystemExit('CI only')
root=pathlib.Path(os.environ['RUNNER_TEMP']); ws=pathlib.Path(os.environ['GITHUB_WORKSPACE'])
private=root/'hj-doc-fixture-private'; private.mkdir(mode=0o700)
inside=pathlib.Path('/wordpress/wp-content/uploads/hj-private-test'); inside.mkdir(parents=True)
(root/'hj-doc-fixture-symlink').symlink_to(private, target_is_directory=True)
mu=pathlib.Path('/wordpress/wp-content/mu-plugins/hj-private-test.php'); mu.parent.mkdir(exist_ok=True)
if mu.exists(): raise RuntimeError('fixture MU path occupied')
setup=subprocess.run(['php',str(ws/'tools/hj-private-document-test/setup.php')],check=True,text=True,capture_output=True)
fixture=json.loads(setup.stdout.strip()); pid=str(fixture['pid'])
mu.symlink_to(ws/'tools/hj-private-document-test/mu.php')
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,req,fp,code,msg,headers,newurl): return None
def session(): return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()),NoRedirect())
def req(opener,path,data=None,content_type=None):
    if isinstance(data,dict): data=urllib.parse.urlencode(data).encode()
    headers={'Host':'example.invalid'}
    if content_type: headers['Content-Type']=content_type
    try: r=opener.open(urllib.request.Request('http://127.0.0.1:8993'+path,data=data,headers=headers),timeout=15)
    except urllib.error.HTTPError as e: r=e
    return r.code,r.read().decode('utf-8',errors='replace')
def login(user):
    s=session(); assert req(s,'/wp-login.php')[0]==200
    code,_=req(s,'/wp-login.php',{'log':user,'pwd':'private-fixture-password','wp-submit':'Log In','testcookie':'1'})
    assert code==302,(user,code); return s
def info(s):
    code,body=req(s,'/wp-admin/admin-ajax.php',{'action':'hj_ci_doc_info','pid':pid})
    assert code==200,(code,body); return json.loads(body)['data']
def dashboard_nonce(s):
    code,body=req(s,'/wp-admin/admin-ajax.php',{'action':'hj_ci_dash_nonce'})
    assert code==200; return json.loads(body)['data']['nonce']
def upload(s,nonce,fail_meta=False):
    boundary='----hjfixtureboundary'
    parts=[]
    fields={'action':'hj_dash_doc','pid':pid,'_hjn':nonce}
    if fail_meta: fields['hj_ci_fail_meta']='1'
    for k,v in fields.items():
        parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{v}\r\n'.encode())
    parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="doc"; filename="fixture.pdf"\r\nContent-Type: application/pdf\r\n\r\n'.encode()+b'%PDF-1.4\nsynthetic private fixture\n%%EOF\n\r\n')
    parts.append(f'--{boundary}--\r\n'.encode())
    code,body=req(s,'/wp-admin/admin-ajax.php',b''.join(parts),'multipart/form-data; boundary='+boundary)
    return code,json.loads(body)
def run(mode):
    env=dict(os.environ,HJ_DOC_FIXTURE_MODE=mode)
    server=subprocess.Popen(['php','-S','127.0.0.1:8993','-t','/wordpress'],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL,env=env)
    try:
        deadline=time.monotonic()+10
        while True:
            if server.poll() is not None: raise RuntimeError('fixture server stopped')
            try:
                with socket.create_connection(('127.0.0.1',8993),timeout=1): break
            except OSError:
                if time.monotonic()>deadline: raise
                time.sleep(.05)
        owner=login('surgeon_fixture'); before=info(owner); assert len(before['docs'])==1
        assert upload(owner,'bad-nonce')[0]==403
        stranger=login('normal_fixture'); assert upload(stranger,dashboard_nonce(stranger))[0]==403
        # Legacy file remains present and owner-readable; builtin PHP serves it directly.
        legacy_url='/wp-admin/admin-ajax.php?'+urllib.parse.urlencode({'action':'hj_doc_download','pid':pid,'doc':'abc123','_wpnonce':doc_nonce(owner,'abc123')})
        code,body=req(owner,legacy_url); assert code==200 and 'synthetic legacy only' in body
        assert req(session(),'/wp-content/uploads/hj-private/'+fixture['legacy'])[0]==200
        code,result=upload(owner,before['nonce'])
        if mode!='verified':
            assert code==(500 if mode=='profilelink' else 503) and not result['success'],(mode,code,result)
            assert len(info(owner)['docs'])==1 and not list(private.rglob('*.pdf')),(mode,'unexpected write')
            print('PASS fail-closed mode',mode,flush=True); return
        assert code==200 and result['success'],(code,result)
        docs=info(owner)['docs']; assert len(docs)==2
        new=docs[1]; assert new['storage']=='private-v2' and (private/new['file']).is_file()
        url='/wp-admin/admin-ajax.php?'+urllib.parse.urlencode({'action':'hj_doc_download','pid':pid,'doc':new['id'],'_wpnonce':doc_nonce(owner,new['id'])})
        code,body=req(owner,url); assert code==200 and 'synthetic private fixture' in body
        admin=login('fixture_admin'); adminurl='/wp-admin/admin-ajax.php?'+urllib.parse.urlencode({'action':'hj_doc_download','pid':pid,'doc':new['id'],'_wpnonce':doc_nonce(admin,new['id'])})
        assert req(admin,adminurl)[0]==200
        foreignurl='/wp-admin/admin-ajax.php?'+urllib.parse.urlencode({'action':'hj_doc_download','pid':pid,'doc':new['id'],'_wpnonce':doc_nonce(stranger,new['id'])})
        assert req(stranger,foreignurl)[0]==403 and req(session(),url)[0]!=200
        assert req(session(),'/wp-content/uploads/hj-private/'+new['file'])[0]==404
        assert (private/new['file']).read_bytes().startswith(b'%PDF-1.4')
        code,failed=upload(owner,before['nonce'],True)
        assert code==500 and not failed['success'],(code,failed)
        assert len(info(owner)['docs'])==2 and len(list(private.rglob('*.pdf')))==2,'failed metadata retains private orphan without false success'
        print('PASS private-v2 owner/admin access, stranger and guest denied; legacy preserved',flush=True)
    finally:
        server.terminate(); server.wait(timeout=10)
def doc_nonce(s,doc):
    code,body=req(s,'/wp-admin/admin-ajax.php',{'action':'hj_ci_doc_nonce','pid':pid,'doc':doc})
    assert code==200,(code,body); return json.loads(body)['data']['nonce']
try:
    for mode in ('missing','unverified','inside','symlink'): run(mode)
    profilelink=private/('p'+pid); profilelink.symlink_to(inside,target_is_directory=True)
    try: run('profilelink')
    finally: profilelink.unlink()
    run('verified')
finally:
    mu.unlink(missing_ok=True)
