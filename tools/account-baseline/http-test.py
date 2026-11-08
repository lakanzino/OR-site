"""Disposable real WordPress HTTP test; no external identity provider."""
import os, socket, re, http.cookiejar, urllib.request, urllib.parse
from html.parser import HTMLParser
if os.environ.get('GITHUB_ACTIONS') != 'true' or os.environ.get('HJ_DISPOSABLE_MARIADB_FIXTURE') != '1': raise SystemExit(1)
old = socket.getaddrinfo
socket.getaddrinfo = lambda host, *args, **kwargs: old('127.0.0.1' if host == 'example.invalid' else host, *args, **kwargs)
opener = urllib.request.build_opener(urllib.request.ProxyHandler({}), urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def request(path, data=None):
    url = path if path.startswith('http') else 'http://example.invalid' + path
    encoded = urllib.parse.urlencode(data).encode() if data is not None else None
    try:
        with opener.open(urllib.request.Request(url, data=encoded), timeout=15) as r: return r.status, r.read().decode(), r.url
    except urllib.error.HTTPError as e: return e.code, e.read().decode(), e.url
class Forms(HTMLParser):
    def __init__(self): super().__init__(); self.forms=[]; self.cur=None
    def handle_starttag(self, tag, attrs):
        a=dict(attrs)
        if tag=='form': self.cur={'action':a.get('action',''), 'inputs':{}}; self.forms.append(self.cur)
        if self.cur is not None and tag=='input' and 'name' in a: self.cur['inputs'][a['name']]=a.get('value','')
    def handle_endtag(self, tag):
        if tag=='form': self.cur=None
status,_,_=request('/wp-login.php', {'log':'member_fixture','pwd':'fixture-password-only','wp-submit':'Log In','redirect_to':'http://example.invalid/','testcookie':'1'})
assert status == 200, f'login failed {status}'
status,html,_=request('/?pagename=member-account')
assert status == 200 and 'hj_child_nickname_save' in html, 'patient form not rendered'
assert 'تنظیمات کامل حساب' not in html, 'account settings still points to WordPress profile'
p=Forms(); p.feed(html); forms={f['inputs'].get('action'):f for f in p.forms if f['inputs'].get('action')}
nick=forms['hj_child_nickname_save']; display=forms['hj_member_save']
status,_,_=request(nick['action'], {'action':'hj_child_nickname_save','_wpnonce':'invalid','nickname':'bad'})
assert status == 403, f'bad nonce accepted {status}'
status,_,_=request(nick['action'], {'action':'hj_child_nickname_save','_wpnonce':nick['inputs']['_wpnonce'],'nickname':'نام مستعار آزمایشی','user_id':'1'})
assert status == 200, f'nickname save failed {status}'
status,_,_=request(display['action'], {'action':'hj_member_save','_wpnonce':display['inputs']['_wpnonce'],'display_name':'نام نمایشی آزمایشی'})
assert status == 200, f'display save failed {status}'
_,html,_=request('/?pagename=member-account')
assert 'نام مستعار آزمایشی' in html and 'نام نمایشی آزمایشی' in html, 'saved values missing'
print('PASS real WordPress HTTP: account rendering, own nickname and display name save, nonce rejection')
