#!/usr/bin/env python3
"""HTTP tests for the 0.3.22-derived preview. CI and synthetic files only."""
import http.cookiejar, json, os, pathlib, socket, subprocess, time, urllib.error, urllib.parse, urllib.request
if os.getenv('GITHUB_ACTIONS')!='true' or os.getenv('HJ_DISPOSABLE_MARIADB_FIXTURE')!='1' or os.getenv('HJ_SPIKE_VERSION')!='0.3.51': raise SystemExit('CI preview only')
ws=pathlib.Path(os.environ['GITHUB_WORKSPACE']); mu=pathlib.Path('/wordpress/wp-content/mu-plugins/hj-private-test.php')
mu.parent.mkdir(exist_ok=True)
if mu.exists(): raise RuntimeError('fixture path occupied')
fixture=json.loads(subprocess.run(['php',str(ws/'tools/hj-private-document-test/setup.php')],check=True,text=True,capture_output=True).stdout.strip()); pid=str(fixture['pid'])
mu.symlink_to(ws/'tools/hj-private-document-test/mu.php')
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,req,fp,code,msg,headers,newurl): return None
def session(): return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()),NoRedirect())
def req(opener,path,data=None,content_type=None):
    if isinstance(data,dict): data=urllib.parse.urlencode(data).encode()
    headers={'Host':'example.invalid'}
    if content_type: headers['Content-Type']=content_type
    try: r=opener.open(urllib.request.Request('http://127.0.0.1:8994'+path,data=data,headers=headers),timeout=15)
    except urllib.error.HTTPError as e: r=e
    return r.code,r.read().decode('utf-8',errors='replace')
def login(user):
    s=session(); assert req(s,'/wp-login.php')[0]==200
    assert req(s,'/wp-login.php',{'log':user,'pwd':'private-fixture-password','wp-submit':'Log In','testcookie':'1'})[0]==302
    return s
def ajax(s,data):
    code,body=req(s,'/wp-admin/admin-ajax.php',data); return code,json.loads(body)
def upload(s,nonce):
    boundary='----previewfixture'; parts=[]
    for k,v in {'action':'hj_dash_doc','pid':pid,'_hjn':nonce}.items():
        parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{v}\r\n'.encode())
    parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="doc"; filename="fixture.pdf"\r\nContent-Type: application/pdf\r\n\r\n'.encode()+b'%PDF-1.4\npreview only\n%%EOF\n\r\n')
    parts.append(f'--{boundary}--\r\n'.encode())
    code,body=req(s,'/wp-admin/admin-ajax.php',b''.join(parts),'multipart/form-data; boundary='+boundary); return code,json.loads(body)
server=subprocess.Popen(['php','-S','127.0.0.1:8994','-t','/wordpress'],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
try:
    deadline=time.monotonic()+10
    while True:
        if server.poll() is not None: raise RuntimeError('fixture server stopped')
        try:
            with socket.create_connection(('127.0.0.1',8994),timeout=1): break
        except OSError:
            if time.monotonic()>deadline: raise
            time.sleep(.05)
    owner=login('surgeon_fixture'); code,state=ajax(owner,{'action':'hj_ci_doc_info','pid':pid}); assert code==200
    info=state['data']; assert len(info['docs'])==1
    code,rendered=ajax(owner,{'action':'hj_ci_render_profile'}); assert code==200 and 'بارگذاری مدرک جدید تا بررسی محل نگهداری' in rendered['data']['html']
    assert upload(owner,'invalid')[0]==403
    code,result=upload(owner,info['nonce']); assert code==503 and not result['success'],(code,result)
    assert len(ajax(owner,{'action':'hj_ci_doc_info','pid':pid})[1]['data']['docs'])==1
    code,nonce=ajax(owner,{'action':'hj_ci_doc_nonce','pid':pid,'doc':'abc123'}); assert code==200
    link='/wp-admin/admin-ajax.php?'+urllib.parse.urlencode({'action':'hj_doc_download','pid':pid,'doc':'abc123','_wpnonce':nonce['data']['nonce']})
    code,body=req(owner,link); assert code==200 and 'synthetic legacy only' in body
    assert req(session(),link)[0]!=200
    stranger=login('normal_fixture'); code,nonce=ajax(stranger,{'action':'hj_ci_doc_nonce','pid':pid,'doc':'abc123'}); assert code==200
    link2='/wp-admin/admin-ajax.php?'+urllib.parse.urlencode({'action':'hj_doc_download','pid':pid,'doc':'abc123','_wpnonce':nonce['data']['nonce']})
    assert req(stranger,link2)[0]==403
    # This fixture's PHP server does not implement the .htaccess rule.
    assert req(session(),'/wp-content/uploads/hj-private/'+fixture['legacy'])[0]==200
    print('PASS baseline-derived preview: visible warning, upload blocked, legacy handler and counterexample preserved')
finally:
    server.terminate(); server.wait(timeout=10); mu.unlink(missing_ok=True)
