#!/usr/bin/env python3
"""Reproducible narrow preview from the archived 0.3.22 baseline, not main plugin source."""
import argparse, hashlib, io, zipfile
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2]
SOURCE=ROOT/'releases/hamyar-doctors-0.3.22.zip'
OUTPUT=ROOT/'releases/hamyar-doctors-0.3.51-stage6-preview.zip'
SOURCE_SHA='8caa90f14ec66f143612e517f1cf25ab8cb5ec5400cc950a7db1ed4666f37850'
def once(s,old,new):
    if s.count(old)!=1: raise SystemExit(f'Unexpected baseline: expected one occurrence: {old[:70]!r}')
    return s.replace(old,new,1)
def build():
    raw=SOURCE.read_bytes()
    if hashlib.sha256(raw).hexdigest()!=SOURCE_SHA: raise SystemExit('Baseline ZIP SHA mismatch')
    with zipfile.ZipFile(io.BytesIO(raw)) as z:
        if z.testzip() is not None: raise SystemExit('Corrupt baseline')
        entries={n:z.read(n) for n in z.namelist() if not n.endswith('/')}
    if not entries or any(not n.startswith('hamyar-doctors/') or '..' in Path(n).parts for n in entries): raise SystemExit('Unexpected archive layout')
    prefix='hamyar-doctors/'
    def edit(name, fn):
        k=prefix+name; entries[k]=fn(entries[k].decode('utf-8')).encode('utf-8')
    def main(s):
        s=once(s,' * Version: 0.3.22',' * Version: 0.3.51')
        return once(s,"define( 'HJ_DOCTORS_VERSION', '0.3.22' );","define( 'HJ_DOCTORS_VERSION', '0.3.51' );")
    def dashboard(s):
        s=once(s,'<p class="hj-dash__muted">مدارک فقط برای بررسی مدیریت نگه‌داری می‌شوند و عمومی نیستند. فرمت PDF، JPG یا PNG.</p>', '<p class="hj-dash__muted">بارگذاری مدرک جدید تا بررسی محل نگهداری توسط مدیر موقتاً غیرفعال است. مدارک قبلی بدون جابه‌جایی باقی مانده‌اند.</p>')
        return once(s,"\t\tpublic static function ajax_upload_doc() {\n\t\t\t$pid = self::guard( 'upload_doc', 20, HOUR_IN_SECONDS );", "\t\tpublic static function ajax_upload_doc() {\n\t\t\t$pid = self::guard( 'upload_doc', 20, HOUR_IN_SECONDS );\n\t\t\tself::fail( 'بارگذاری مدرک جدید تا بررسی محل نگهداری توسط مدیر موقتاً غیرفعال است.', 503 );")
    def rest(s):
        return once(s,"        if (self::ttl() <= 0) { return false; }\n\n        $c = get_transient($key);\n\n        if (is_array($c) && isset($c['v']) && array_key_exists('d', $c) && $c['v'] === self::ver()) {", "        if (self::ttl() <= 0) { return false; }\n\n        // Capture the generation before constructing an item on a cache miss.\n        $version = self::ver();\n\n        $c = get_transient($key);\n\n        if (is_array($c) && isset($c['v']) && array_key_exists('d', $c) && $c['v'] === $version) {")
    edit('hamyar-doctors.php',main); edit('includes/dashboard.php',dashboard); edit('includes/rest-api.php',rest)
    buf=io.BytesIO()
    with zipfile.ZipFile(buf,'w',zipfile.ZIP_DEFLATED,compresslevel=9) as out:
        for n in sorted(entries):
            info=zipfile.ZipInfo(n,date_time=(1980,1,1,0,0,0)); info.compress_type=zipfile.ZIP_DEFLATED; info.external_attr=0o644<<16
            out.writestr(info,entries[n])
    return buf.getvalue()
result=build()
parser=argparse.ArgumentParser();parser.add_argument('--check',action='store_true');args=parser.parse_args()
if args.check:
    if not OUTPUT.exists() or OUTPUT.read_bytes()!=result: raise SystemExit('Preview missing or differs')
else: OUTPUT.write_bytes(result)
print(('OK' if args.check else 'built'),OUTPUT.relative_to(ROOT),'sha256',hashlib.sha256(result).hexdigest())
