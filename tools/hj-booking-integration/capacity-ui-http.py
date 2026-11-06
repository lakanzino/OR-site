#!/usr/bin/env python3
"""Real WP shortcode/admin-post tests; strictly disposable CI."""
import copy, datetime, hashlib, http.cookiejar, json, os, re, signal, subprocess, time, urllib.error, urllib.parse, urllib.request
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
 def __init__(self): super().__init__(); self.fields={}; self.sections=[]; self.booking=None
 def handle_starttag(self,tag,attrs):
  a=dict(attrs)
  if tag=='input' and a.get('name'): self.fields[a['name']]=a.get('value','')
  if tag=='section':
   self.sections.append(a.get('class',''))
   if 'hj-booking' in a.get('class','').split(): self.booking=a
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
 fixture=json.loads(meta.read_text()); doc=fixture['doctor']; page=fixture['page']; dashboard=fixture['dashboard']; legacy=fixture['legacy']; weekday=fixture['weekday']; day=fixture['date']
 server=subprocess.Popen(['php','-d','auto_prepend_file='+str(prepend),'-S','127.0.0.1:8996','-t','/wordpress'],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL,start_new_session=True,env=dict(os.environ,PHP_CLI_SERVER_WORKERS='8'))
 for _ in range(80):
  try: request(session(),'/wp-login.php'); break
  except OSError: time.sleep(.25)
 else: raise RuntimeError('capacity UI server failed')
 owner=login('synthetic_doctor_a'); other=login('synthetic_doctor_b'); patient=login('synthetic_race_0'); patient2=login('synthetic_race_1'); secretary=login('synthetic_secretary'); admin=login('fixture_admin'); guest=session()
 check(state(owner,doc)=={'config':None,'snapshot':None},'no doctor schedule exists before first GET')
 def public_hours(doctor):
  page_html=ajax(guest,{'action':'hj_ci_render_patient_profile','doctor':str(doctor)})['html']
  return page_html.split('<div class="hj-card hj-appointment">',1)[1].split('</section>',1)[0]
 missing_hours=public_hours(doc)
 check('ساعات حضور در مطب' in missing_hours and '09:00 - 11:00' in missing_hours and 'ساعات بالا نمایشی است' in missing_hours and state(owner,doc)=={'config':None,'snapshot':None},'missing canonical schedule preserves labelled legacy display without writing')
 legacy_before=state(owner,legacy); legacy_hours=public_hours(legacy)
 check('12:00:30 - 13:00' in legacy_hours and '09:00 - 11:00' not in legacy_hours and state(owner,legacy)==legacy_before,'existing v1 canonical schedule displays without rewriting and retains seconds')
 html,fields=form(owner,page)
 check('برنامهٔ نوبت‌دهی مطب' in html and fields['revision']=='0' and fields['original_buffer']=='5','real shortcode shows blank state with expected configuration')
 render_file=Path(os.environ['RUNNER_TEMP'])/'hj-capacity-ui-render.html'; render_file.write_text(html,encoding='utf-8')
 try: subprocess.run(['node',str(root/'tools/hj-booking-integration/capacity-ui-browser.cjs')],check=True,env=dict(os.environ,HJ_CAPACITY_HTML=str(render_file)))
 finally: render_file.unlink(missing_ok=True)
 check(state(owner,doc)=={'config':None,'snapshot':None},'rendering settings does not create database rows')
 check(form(other,page)[0]=='' and form(patient,page)[0]=='','other doctor and patient cannot view owner settings')
 dash_html,dash_fields=form(owner,dashboard)
 check('hj-dash-form' in dash_html and 'hj-capacity-settings' in dash_html and 'hj_schedule_revision' not in dash_fields and not any(k.startswith('hours[') for k in dash_fields),'v2 dashboard renders separate capacity editor and no legacy schedule inputs')
 check('hj-capacity-settings' not in form(patient,dashboard)[0] and form(other,dashboard)[1].get('doctor_id') != str(doc),'patient and other doctor do not see owner capacity editor')
 dash_file=Path(os.environ['RUNNER_TEMP'])/'hj-capacity-dashboard-render.html'; dash_file.write_text(dash_html,encoding='utf-8')
 try: subprocess.run(['node',str(root/'tools/hj-booking-integration/capacity-ui-browser.cjs')],check=True,env=dict(os.environ,HJ_CAPACITY_HTML=str(dash_file),HJ_DASHBOARD_TEST='1'))
 finally: dash_file.unlink(missing_ok=True)
 payload=week({'action':'hj_capacity_week_save','doctor_id':str(doc),'page_id':str(page),'revision':fields['revision'],'original_duration':fields['original_duration'],'original_buffer':fields['original_buffer'],'original_enabled':fields['original_enabled'],'enabled':'1','hj_capacity_nonce':fields['hj_capacity_nonce']},weekday)
 post='/wp-admin/admin-post.php'
 bad=dict(payload,hj_capacity_nonce='bad')
 check(request(owner,post,bad)[0]==403 and request(other,post,payload)[0]==403 and request(patient,post,payload)[0]==403,'nonce, owner and role checks reject unauthorized saves')
 # The existing appointments list uses a transaction and creates the default
 # doctor mutex row on dashboard render; it does not create a schedule.
 before_invalid=state(owner,doc)
 check(before_invalid['snapshot'] is None and int(before_invalid['config']['enabled'])==0,'dashboard listing only initializes disabled doctor mutex row')
 invalid=dict(payload,**{f'week[{weekday}][capacity]':'11'})
 code,_,headers=request(owner,post,invalid)
 invalid_state=state(owner,doc)
 check(code==303 and 'hj_cap_status=invalid' in headers.get('Location','') and invalid_state==before_invalid,f'invalid capacity rejected before writing (HTTP {code}, Location {headers.get("Location", "")}, state {invalid_state})')
 code,_,headers=request(owner,post,payload)
 check(code==303 and 'hj_cap_status=saved' in headers.get('Location',''),'owner saves actual weekly form')
 current=state(owner,doc); snapshot=current['snapshot']; rows=json.loads(snapshot['rows_json'])
 check(int(snapshot['revision'])==1 and rows['version']==2 and rows['rows'][list(('saturday','sunday','monday','tuesday','wednesday','thursday','friday')).index(weekday)]['capacity']==3 and int(current['config']['enabled'])==1 and snapshot['rows_hash']==hashlib.sha256(snapshot['rows_json'].encode()).hexdigest(),'real form atomically persists v2 snapshot and activation')
 check('ساعات نوبت‌دهی مطب' in public_hours(doc) and '17:00 - 21:00' in public_hours(doc) and '09:00 - 11:00' not in public_hours(doc) and state(owner,doc)==current,'guest public profile displays canonical v2 hours instead of stale meta without writes')
 # Actual patient shortcode and experimental endpoint, no authentication required for aggregates.
 patient_html=ajax(guest,{'action':'hj_ci_render_patient_form','page':str(page)})['html']; parser=Inputs(); parser.feed(patient_html)
 check(parser.booking and 'hj-booking__calendar-days' in patient_html and '۱۴ روز آینده' in patient_html,'v2 patient shortcode renders calendar and fallback date')
 ui_nonce=parser.booking['data-nonce']; params={'action':'hj_booking_calendar','doctor_id':str(doc),'nonce':ui_nonce}
 code,body,headers=request(guest,'/wp-admin/admin-ajax.php',params); calendar=json.loads(body)
 check(code==200 and calendar['success'] and len(calendar['data']['days'])==14 and calendar['data']['days'][2]['remaining']==12 and calendar['data']['days'][2]['color']=='green' and 'no-cache' in headers.get('Cache-Control','') and all(set(x)=={'date','remaining','state','color'} for x in calendar['data']['days']),'guest calendar endpoint returns non-cacheable aggregate capacity only')
 code,body,_=request(guest,'/wp-admin/admin-ajax.php',dict(params,nonce='bad'))
 check(code==403 and json.loads(body)['data']['code']=='forbidden','calendar endpoint rejects invalid nonce')
 code,body,_=request(guest,'/wp-admin/admin-ajax.php',dict(params,doctor_id=str(legacy)))
 check(code==409 and json.loads(body)['data']['code']=='legacy_schedule','legacy calendar does not infer new capacity')
 patient_logged_html=ajax(patient,{'action':'hj_ci_render_patient_form','page':str(page)})['html']
 browser_fixture=Path(os.environ['RUNNER_TEMP'])/'hj-patient-calendar.json'
 browser_fixture.write_text(json.dumps({'guest':patient_html,'patient':patient_logged_html,'calendar':calendar['data'],'doctor':str(doc),'legacy':str(legacy)},ensure_ascii=False),encoding='utf-8')
 try: subprocess.run(['node',str(root/'tools/hj-booking-integration/patient-calendar-browser.cjs')],check=True,env=dict(os.environ,HJ_PATIENT_CALENDAR=str(browser_fixture)))
 finally: browser_fixture.unlink(missing_ok=True)

 # WordPress native doctor editor can still edit display-only hours; verify it
 # cannot modify the canonical schedule and communicates the distinction.
 code,editor_html,_=request(admin,'/wp-admin/post.php?post='+str(doc)+'&action=edit')
 editor=Inputs(); editor.feed(editor_html)
 check(code==200 and 'ساعات نمایشی مطب (مستقل از نوبت‌دهی)' in editor_html and 'برنامهٔ نوبت‌دهی را تغییر نمی‌دهد' in editor_html and editor.fields.get('hj_review_nonce'),'native doctor editor labels display-only hours and shows warning')
 fake_field=ajax(admin,{'action':'hj_ci_acf_hours_label'})
 check(fake_field['name']=='working_hours' and fake_field['key']=='field_hj_working_hours' and fake_field['type']=='repeater' and 'مستقل از نوبت‌دهی' in fake_field['label'] and 'برنامهٔ نوبت‌دهی را تغییر نمی‌دهد' in fake_field['instructions'],'simulated ACF field label is scoped and keeps key, name and type')
 update_nonce=ajax(admin,{'action':'hj_ci_review_editor_nonce','doctor':str(doc)})['post_nonce']
 code,_,_=request(admin,'/wp-admin/post.php',{'action':'editpost','post_ID':str(doc),'post_type':'doctor','post_status':'publish','post_title':'Synthetic office review','_wpnonce':update_nonce,'hj_review_nonce':editor.fields['hj_review_nonce'],'hj_review[name_fa]':'Synthetic reviewed profile','hj_hours_present':'1','hj_hours[monday][start]':'08:00','hj_hours[monday][end]':'10:00'})
 display=ajax(owner,{'action':'hj_ci_schedule_state','pid':str(doc)})['display']
 check(code==302 and state(owner,doc)==current and any(row.get('start_time')=='08:00:00' for row in display) and '17:00 - 21:00' in public_hours(doc) and '08:00 - 10:00' not in public_hours(doc),'native admin edit changes only display metadata, not canonical v2 or public booking hours')
 # Saving a profile is independent of schedules, including a v2 snapshot.
 dash_nonce=ajax(owner,{'action':'hj_ci_dashboard_nonce'})['nonce']
 profile={'action':'hj_dash_save','pid':str(doc),'name_fa':'Synthetic dashboard profile','_hjn':dash_nonce}
 mirror_before=ajax(owner,{'action':'hj_ci_schedule_state','pid':str(doc)})['display']
 profile_result=ajax(owner,profile)
 check(profile_result['pid']==doc and state(owner,doc)==current and ajax(owner,{'action':'hj_ci_schedule_state','pid':str(doc)})['display']==mirror_before,'v2 profile save does not modify schedule, configuration or display mirror')
 code,_,_=request(owner,'/wp-admin/admin-ajax.php',dict(profile,**{'hours[monday][start]':'09:00','hj_schedule_revision':'1'}))
 check(code==409 and state(owner,doc)==current,'stale legacy dashboard save rejected before changing v2 schedule')
 dash_html,dash_fields=form(owner,dashboard)
 dash_payload=week({'action':'hj_capacity_week_save','doctor_id':str(doc),'page_id':str(dashboard),'revision':dash_fields['revision'],'original_duration':dash_fields['original_duration'],'original_buffer':dash_fields['original_buffer'],'original_enabled':dash_fields['original_enabled'],'enabled':'1','hj_capacity_nonce':dash_fields['hj_capacity_nonce']},weekday)
 code,_,headers=request(owner,post,dict(dash_payload,hj_capacity_nonce='bad'))
 check(code==403 and state(owner,doc)==current,'dashboard embedded editor rejects invalid nonce')
 code,_,headers=request(owner,post,dash_payload)
 check(code==303 and 'hj_cap_status=saved' in headers.get('Location','') and int(state(owner,doc)['snapshot']['revision'])==2,'dashboard embedded editor saves through actual handler')
 current=state(owner,doc)
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
 check(ajax(guest,params)['days'][2]['remaining']==11,'guest calendar reflects an actual new patient booking')
 patient_form=Inputs(); patient_form.feed(patient_logged_html); private_nonce=patient_form.booking['data-nonce']
 ui_payload={'action':'hj_booking_create','nonce':private_nonce,'doctor_id':str(doc),'slot_start':slots['slots'][1],'revision':str(rev),'idempotency_key':'7af34012-9eac-41ac-9e56-e133217238ed','first_name':'نام مستقل','last_name':'بیمار آزمایشی','phone':'۰۹۱۲۳۴۵۶۷۸۹','national_code':'','service':'consultation','note':'توضیح آزمایشی'}
 code,body,_=request(patient,'/wp-admin/admin-ajax.php',dict(ui_payload,first_name='')); check(code==400 and json.loads(body)['data']['code']=='invalid_patient','actual v2 create rejects missing patient name')
 code,body,_=request(patient,'/wp-admin/admin-ajax.php',dict(ui_payload,national_code='1234567890')); check(code==400 and json.loads(body)['data']['code']=='invalid_national','actual v2 create rejects invalid optional national code')
 ui_result=ajax(patient,ui_payload); replay=ajax(patient,ui_payload)
 tracking=ui_result.get('tracking_code','')
 check(ui_result['status']=='created' and re.fullmatch(r'[A-F0-9]{32}',tracking) and 'id' not in ui_result and replay['status']=='replayed' and replay['tracking_code']==tracking,'actual v2 create and replay return the same opaque random tracking code without exposing internal id')
 code,body,_=request(patient,'/wp-admin/admin-ajax.php',dict(ui_payload,last_name='نام متفاوت'))
 check(code==409 and json.loads(body)['data']['code']=='idempotency_conflict','actual v2 create does not replay changed patient information')
 check(ajax(guest,params)['days'][2]['remaining']==10,'calendar capacity reflects v2 patient form booking without exposing personal details')
 patient2_form=Inputs(); patient2_form.feed(ajax(patient2,{'action':'hj_ci_render_patient_form','page':str(page)})['html'])
 second=dict(ui_payload,nonce=patient2_form.booking['data-nonce'],idempotency_key='2ff34012-9eac-41ac-9e56-e133217238ed',first_name='بیمار دوم',last_name='آزمایشی دوم',national_code='1234567891')
 second_result=ajax(patient2,second); second_code=second_result['tracking_code']
 check(second_result['status']=='created' and re.fullmatch(r'[A-F0-9]{32}',second_code) and second_code!=tracking,'different patient receives a distinct tracking code')
 audit_rows=ajax(owner,{'action':'hj_ci_booking_audit','doctor':str(doc)})
 target=next(row['id'] for row in audit_rows if row['request_key']==ui_payload['idempotency_key'].replace('-',''))

 # Exercise actual management authorization and rendering for old and new records.
 admin_url='/wp-admin/edit.php?post_type=doctor&page=hj-bookings'
 code,admin_html,_=request(admin,admin_url)
 check(code==200 and 'نام مستقل بیمار آزمایشی' in admin_html and 'توضیح آزمایشی' in admin_html and 'نام حساب ثبت‌کننده' in admin_html and tracking in admin_html and second_code in admin_html and 'نام بیمار در نوبت قدیمی ثبت نشده' in admin_html,'admin sees independent patient details and distinguishes legacy booking from account name')
 owner_html=ajax(owner,{'action':'hj_ci_render_management','doctor':str(doc)})['html']
 check('نام مستقل بیمار آزمایشی' in owner_html and 'نوع خدمت: مشاوره' in owner_html and 'توضیح آزمایشی' in owner_html and 'نام حساب ثبت‌کننده' in owner_html and tracking in owner_html and second_code in owner_html and 'نام بیمار در نوبت قدیمی ثبت نشده' in owner_html,'assigned doctor sees patient name, service and note alongside account name and legacy fallback')
 for outsider in (other,patient,patient2,secretary):
  private=ajax(outsider,{'action':'hj_ci_render_management','doctor':str(doc)})['html']
  check('نام مستقل' not in private and 'توضیح آزمایشی' not in private and '09123456789' not in private and tracking not in private,'unrelated user cannot view private details before assignment')
 patient_view=ajax(patient,{'action':'hj_ci_render_patient_bookings'})['html']
 other_patient_view=ajax(patient2,{'action':'hj_ci_render_patient_bookings'})['html']
 check('نام مستقل' not in patient_view and 'توضیح آزمایشی' not in patient_view and 'نام مستقل' not in other_patient_view and 'توضیح آزمایشی' not in other_patient_view and tracking in patient_view and second_code not in patient_view and second_code in other_patient_view and tracking not in other_patient_view,'patient lists select own tracking code without exposing private fields or another patient code')
 check('نام مستقل' not in public_hours(doc) and tracking not in public_hours(doc) and 'توضیح آزمایشی' not in json.dumps(ajax(guest,params),ensure_ascii=False) and tracking not in json.dumps(ajax(guest,params),ensure_ascii=False),'public profile and calendar do not expose new patient details or tracking codes')
 class Forms(HTMLParser):
  def __init__(self): super().__init__(); self.forms=[]; self.current=None
  def handle_starttag(self,tag,attrs):
   a=dict(attrs)
   if tag=='form': self.current={}
   elif tag=='input' and self.current is not None and a.get('name'): self.current[a['name']]=a.get('value','')
  def handle_endtag(self,tag):
   if tag=='form' and self.current is not None: self.forms.append(self.current); self.current=None
 parsed=Forms(); parsed.feed(admin_html)
 assignment=next(f for f in parsed.forms if f.get('action')=='hj_booking_assignment')
 assign=dict(assignment,doctor_id=str(doc),user_id=str(fixture['secretary']),mode='add')
 check(request(patient,post,assign)[0]==403 and request(admin,post,dict(assign,_wpnonce='bad'))[0]==403,'secretary assignment requires administrator and valid nonce')
 check(request(admin,post,assign)[0]==302,'administrator assigns synthetic secretary')
 secretary_html=ajax(secretary,{'action':'hj_ci_render_management','doctor':str(doc)})['html']
 check('نام مستقل بیمار آزمایشی' in secretary_html and 'توضیح آزمایشی' in secretary_html and 'مشاوره' in secretary_html,'assigned secretary sees only authorized doctor patient details')
 check('نام مستقل' not in ajax(secretary,{'action':'hj_ci_render_management','doctor':str(legacy)})['html'],'secretary cannot read unassigned doctor')
 check(request(admin,post,dict(assign,mode='remove'))[0]==302 and 'نام مستقل' not in ajax(secretary,{'action':'hj_ci_render_management','doctor':str(doc)})['html'],'revoking secretary access prevents subsequent read')
 # The CI-only adapter writes an intentionally hostile synthetic row to test escaping at the actual render boundary.
 admin_form=Inputs(); admin_form.feed(ajax(admin,{'action':'hj_ci_render_patient_form','page':str(page)})['html'])
 check(ajax(admin,{'action':'hj_ci_patient_extra_xss','nonce':admin_form.booking['data-nonce'],'id':str(target)})['updated'],'synthetic stored XSS fixture installed')
 escaped=ajax(owner,{'action':'hj_ci_render_management','doctor':str(doc)})['html']
 check('&lt;img src=x onerror=alert(1)&gt;' in escaped and '&lt;script&gt;alert(2)&lt;/script&gt;' in escaped and '<script>alert(2)</script>' not in escaped,'private patient name and note are escaped in authorized management view')


 html,fields=form(owner,page); blocked=week(dict(payload,enabled='0',revision=fields['revision'],original_duration=fields['original_duration'],original_buffer=fields['original_buffer'],original_enabled=fields['original_enabled'],hj_capacity_nonce=fields['hj_capacity_nonce']),weekday)
 before=state(owner,doc); code,_,headers=request(owner,post,blocked)
 check(code==303 and 'hj_cap_status=booked' in headers.get('Location','') and state(owner,doc)==before,'future booking blocks schedule and activation changes without mutation')
 pause={'action':'hj_capacity_pause','doctor_id':str(doc),'page_id':str(page),'hj_capacity_pause_nonce':fields['hj_capacity_pause_nonce']}
 check(request(owner,post,dict(pause,hj_capacity_pause_nonce='bad'))[0]==403 and request(other,post,pause)[0]==403 and request(patient,post,pause)[0]==403,'pause requires nonce and doctor ownership')
 audit_before=ajax(owner,{'action':'hj_ci_booking_audit','doctor':str(doc)})
 code,_,headers=request(owner,post,pause); paused=state(owner,doc)
 check(code==303 and 'hj_cap_status=paused' in headers.get('Location','') and int(paused['config']['enabled'])==0 and paused['snapshot']==before['snapshot'] and ajax(owner,{'action':'hj_ci_booking_audit','doctor':str(doc)})==audit_before,'pause disables new bookings without changing existing schedule or bookings')
 check(ajax(patient,{'action':'hj_ci_slots','doctor':str(doc),'nonce':patient_nonce,'date':day})['status']=='unavailable','public slot access is unavailable after pause')
 code,body,_=request(guest,'/wp-admin/admin-ajax.php',params)
 check(code==503 and json.loads(body)['data']['code']=='unavailable','calendar endpoint reports unavailable after pause')
 code,_,headers=request(owner,post,pause)
 check(code==303 and 'hj_cap_status=paused' in headers.get('Location','') and state(owner,doc)==paused,'repeated pause is idempotent')
 check('17:00 - 21:00' in public_hours(doc) and 'نوبت‌دهی داخلی برای این جراح در دسترس نیست' in public_hours(doc),'paused public profile retains hours but reports unavailable booking')
 # Synthetic-only snapshot edits test faithful seconds and fail-closed display.
 snapshot_data=json.loads(paused['snapshot']['rows_json'])
 for row in snapshot_data['rows']:
  if row['day_of_week']==weekday: row['start_time']='17:00:30'
 replace={'action':'hj_ci_replace_snapshot','doctor':str(doc),'_hjn':dash_nonce,'rows_json':json.dumps(snapshot_data,separators=(',',':'))}
 check(ajax(owner,replace)['status']=='updated','synthetic canonical snapshot with seconds installed')
 seconds_state=state(owner,doc)
 check('17:00:30 - 21:00' in public_hours(doc) and state(owner,doc)==seconds_state,'validated canonical display preserves nonzero seconds without modifying snapshot')
 check(ajax(owner,dict(replace,rows_json='{"version":99,"rows":[]}'))['status']=='updated','synthetic unsupported snapshot installed')
 corrupt_state=state(owner,doc); invalid_hours=public_hours(doc)
 check('در دسترس نیست' in invalid_hours and '09:00 - 11:00' not in invalid_hours and '17:00' not in invalid_hours and state(owner,doc)==corrupt_state,'unsupported snapshot does not fall back to stale hours or mutate data')
finally:
 if server:
  os.killpg(server.pid,signal.SIGTERM)
  try: server.wait(timeout=10)
  except subprocess.TimeoutExpired: os.killpg(server.pid,signal.SIGKILL); server.wait()
 if setup: subprocess.run(cmd+['cleanup'],check=True)
 if created_mu and mu.is_symlink(): mu.unlink()
 if created_plugin and plugin.is_symlink(): plugin.unlink()
