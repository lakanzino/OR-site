#!/usr/bin/env python3
"""Real WP shortcode/admin-post tests; strictly disposable CI."""
import copy, datetime, hashlib, http.cookiejar, json, os, signal, subprocess, time, urllib.error, urllib.parse, urllib.request
from html.parser import HTMLParser
from pathlib import Path
from zoneinfo import ZoneInfo
if os.getenv('GITHUB_ACTIONS')!='true' or os.getenv('HJ_DISPOSABLE_MARIADB_FIXTURE')!='1': raise SystemExit('Disposable CI only')
root=Path(os.environ['GITHUB_WORKSPACE']); plugin=Path('/wordpress/wp-content/plugins/hamyar-doctors'); mu=Path('/wordpress/wp-content/mu-plugins/hj-wpdb-ajax-fixture.php'); meta=Path(os.environ['RUNNER_TEMP'])/'hj-capacity-ui-meta.json'; prepend=root/'tools/hj-booking-integration/prepend-v2.php'; base='http://127.0.0.1:8996'
cmd=['php','-d','auto_prepend_file='+str(prepend),str(root/'tools/hj-booking-integration/capacity-ui-fixture.php')]
class NoRedirect(urllib.request.HTTPRedirectHandler):
 def redirect_request(self, req, fp, code, msg, headers, newurl): return None
def session(): return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()),NoRedirect())
def request(client,path,data=None):
 body=urllib.parse.urlencode(data).encode() if data is not None else None
 try: response=client.open(urllib.request.Request(base+path,data=body,headers={'Host':'example.invalid'}),timeout=30)
 except urllib.error.HTTPError as e: response=e
 return response.code,response.read().decode('utf-8',errors='replace'),response.headers
def check(value,label):
 if not value: raise AssertionError(label)
 print('PASS: '+label,flush=True)
def ajax(client,data):
 code,text,_=request(client,'/wp-admin/admin-ajax.php',data)
 if code!=200: raise AssertionError(f'AJAX {code}: {text[:400]}')
 result=json.loads(text)
 if not result['success']: raise AssertionError(str(result))
 return result['data']
def login(name):
 s=session(); check(request(s,'/wp-login.php')[0]==200,name+' login page')
 status,_,_=request(s,'/wp-login.php',{'log':name,'pwd':'disposable-test-password','wp-submit':'Log In','testcookie':'1'})
 check(status==302,name+' login'); return s
class Inputs(HTMLParser):
 def __init__(self): super().__init__(); self.fields={}; self.sections=[]
 def handle_starttag(self,tag,attrs):
  a=dict(attrs)
  if tag=='input' and a.get('name'): self.fields[a['name']]=a.get('value','')
  if tag=='section': self.sections.append(a.get('class',''))
def form(client,page):
 html=ajax(client,{'action':'hj_ci_render_capacity_settings','page':str(page)})['html']; p=Inputs(); p.feed(html); return html,p.fields
def state(client,doctor): return ajax(client,{'action':'hj_ci_capacity_state','doctor':str(doctor)})
def week(payload,weekday):
 data=dict(payload)
 for name in ('saturday','sunday','monday','tuesday','wednesday','thursday','friday'):
  opened=name==weekday
  for k,v in {'closed':'0' if opened else '1','start':'17:00' if opened else '','end':'21:00' if opened else '','duration':'60' if opened else '30','buffer':'0','capacity':'3' if opened else '1'}.items(): data[f'week[{name}][{k}]']=v
 return data
server=None; created_plugin=False; created_mu=False; setup=False
try:
 if plugin.exists() or mu.exists(): raise RuntimeError('fixture path exists')
 plugin.symlink_to(root/'plugins/hamyar-doctors',target_is_directory=True); created_plugin=True
 subprocess.run(cmd+['setup'],check=True); setup=True
 mu.parent.mkdir(exist_ok=True); mu.symlink_to(root/'tools/hj-booking-integration/mu.php'); created_mu=True
 fixture=json.loads(meta.read_text()); doc=fixture['doctor']; page=fixture['page']; weekday=fixture['weekday']; day=fixture['date']
 server=subprocess.Popen(['php','-d','auto_prepend_file='+str(prepend),'-S','127.0.0.1:8996','-t','/wordpress'],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL,start_new_session=True,env=dict(os.environ,PHP_CLI_SERVER_WORKERS='8'))
 for _ in range(80):
  try: request(session(),'/wp-login.php'); break
  except OSError: time.sleep(.25)
 else: raise RuntimeError('capacity UI server failed')
 owner=login('synthetic_doctor_a'); other=login('synthetic_doctor_b'); patient=login('synthetic_race_0')
 check(state(owner,doc)=={'config':None,'snapshot':None},'no doctor schedule exists before first GET')
 html,fields=form(owner,page)
 check('برنامهٔ نوبت‌دهی مطب' in html and fields['revision']=='0' and fields['original_buffer']=='5','real shortcode shows blank state with expected configuration')
 render_file=Path(os.environ['RUNNER_TEMP'])/'hj-capacity-ui-render.html'; render_file.write_text(html,encoding='utf-8')
 try: subprocess.run(['node',str(root/'tools/hj-booking-integration/capacity-ui-browser.cjs')],check=True,env=dict(os.environ,HJ_CAPACITY_HTML=str(render_file)))
 finally: render_file.unlink(missing_ok=True)
 check(state(owner,doc)=={'config':None,'snapshot':None},'rendering settings does not create database rows')
 check(form(other,page)[0]=='' and form(patient,page)[0]=='','other doctor and patient cannot view owner settings')
 payload=week({'action':'hj_capacity_week_save','doctor_id':str(doc),'page_id':str(page),'revision':fields['revision'],'original_duration':fields['original_duration'],'original_buffer':fields['original_buffer'],'original_enabled':fields['original_enabled'],'enabled':'1','hj_capacity_nonce':fields['hj_capacity_nonce']},weekday)
 post='/wp-admin/admin-post.php'
 bad=dict(payload,hj_capacity_nonce='bad')
 check(request(owner,post,bad)[0]==403 and request(other,post,payload)[0]==403 and request(patient,post,payload)[0]==403,'nonce, owner and role checks reject unauthorized saves')
 invalid=dict(payload,**{f'week[{weekday}][capacity]':'11'})
 code,_,headers=request(owner,post,invalid)
 check(code==303 and 'hj_cap_status=invalid' in headers.get('Location','') and state(owner,doc)=={'config':None,'snapshot':None},'invalid capacity rejected before writing')
 code,_,headers=request(owner,post,payload)
 check(code==303 and 'hj_cap_status=saved' in headers.get('Location',''),'owner saves actual weekly form')
 current=state(owner,doc); snapshot=current['snapshot']; rows=json.loads(snapshot['rows_json'])
 check(int(snapshot['revision'])==1 and rows['version']==2 and rows['rows'][list(('saturday','sunday','monday','tuesday','wednesday','thursday','friday')).index(weekday)]['capacity']==3 and int(current['config']['enabled'])==1 and snapshot['rows_hash']==hashlib.sha256(snapshot['rows_json'].encode()).hexdigest(),'real form atomically persists v2 snapshot and activation')
 code,_,headers=request(owner,post,payload)
 check(code==303 and 'hj_cap_status=conflict' in headers.get('Location','') and state(owner,doc)==current,'stale form rejected without writes')
 html,fields=form(owner,page); fresh=week(dict(payload,revision=fields['revision'],original_duration=fields['original_duration'],original_buffer=fields['original_buffer'],original_enabled=fields['original_enabled'],hj_capacity_nonce=fields['hj_capacity_nonce']),weekday)
 nonce=ajax(owner,{'action':'hj_ci_dashboard_nonce'})['nonce']
 check(ajax(owner,{'action':'hj_ci_capacity_configure','doctor':str(doc),'_hjn':nonce})['status']=='configured','real configuration changed under mutex in another tab')
 changed=state(owner,doc); code,_,headers=request(owner,post,fresh)
 check(code==303 and 'hj_cap_status=conflict' in headers.get('Location','') and state(owner,doc)==changed,'configuration conflict rejects stale form without reverting enabled state')
 # Refresh and re-enable; then create an actual synthetic booking.
 html,fields=form(owner,page); fresh=week(dict(payload,revision=fields['revision'],original_duration=fields['original_duration'],original_buffer=fields['original_buffer'],original_enabled=fields['original_enabled'],hj_capacity_nonce=fields['hj_capacity_nonce']),weekday)
 code,_,headers=request(owner,post,fresh); check(code==303 and 'hj_cap_status=saved' in headers.get('Location',''),'fresh form safely re-enables and saves')
 current=state(owner,doc); rev=int(current['snapshot']['revision']); patient_nonce=ajax(patient,{'action':'hj_ci_booking_nonce'})['nonce']
 slots=ajax(patient,{'action':'hj_ci_slots','doctor':str(doc),'nonce':patient_nonce,'date':day})
 check(slots['status']=='ok' and len(slots['slots'])==4,'saved form drives four real backend slots')
 created=ajax(patient,{'action':'hj_ci_create','doctor':str(doc),'nonce':patient_nonce,'start':slots['slots'][0],'key':'000000000000000000000000000010ab','phone':'09123456789','national':'1234567891'})
 check(created['status']=='created','actual patient booking created after form save')
 html,fields=form(owner,page); blocked=week(dict(payload,enabled='0',revision=fields['revision'],original_duration=fields['original_duration'],original_buffer=fields['original_buffer'],original_enabled=fields['original_enabled'],hj_capacity_nonce=fields['hj_capacity_nonce']),weekday)
 before=state(owner,doc); code,_,headers=request(owner,post,blocked)
 check(code==303 and 'hj_cap_status=booked' in headers.get('Location','') and state(owner,doc)==before,'future booking blocks schedule and activation changes without mutation')
 pause={'action':'hj_capacity_pause','doctor_id':str(doc),'page_id':str(page),'hj_capacity_pause_nonce':fields['hj_capacity_pause_nonce']}
 check(request(owner,post,dict(pause,hj_capacity_pause_nonce='bad'))[0]==403 and request(other,post,pause)[0]==403 and request(patient,post,pause)[0]==403,'pause requires nonce and doctor ownership')
 audit_before=ajax(owner,{'action':'hj_ci_booking_audit','doctor':str(doc)})
 code,_,headers=request(owner,post,pause); paused=state(owner,doc)
 check(code==303 and 'hj_cap_status=paused' in headers.get('Location','') and int(paused['config']['enabled'])==0 and paused['snapshot']==before['snapshot'] and ajax(owner,{'action':'hj_ci_booking_audit','doctor':str(doc)})==audit_before,'pause disables new bookings without changing existing schedule or bookings')
 check(ajax(patient,{'action':'hj_ci_slots','doctor':str(doc),'nonce':patient_nonce,'date':day})['status']=='unavailable','public slot access is unavailable after pause')
 code,_,headers=request(owner,post,pause)
 check(code==303 and 'hj_cap_status=paused' in headers.get('Location','') and state(owner,doc)==paused,'repeated pause is idempotent')
finally:
 if server:
  os.killpg(server.pid,signal.SIGTERM)
  try: server.wait(timeout=10)
  except subprocess.TimeoutExpired: os.killpg(server.pid,signal.SIGKILL); server.wait()
 if setup: subprocess.run(cmd+['cleanup'],check=True)
 if created_mu and mu.is_symlink(): mu.unlink()
 if created_plugin and plugin.is_symlink(): plugin.unlink()
