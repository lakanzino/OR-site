#!/usr/bin/env python3
"""Build Word and a compact tree from docs/roadmap/SITE-ROADMAP.fa.md.

Requires Pillow, python-docx, arabic-reshaper and python-bidi. Output is written
outside the repository (pass an output directory); the Markdown is the source.
"""
import re
import sys
from pathlib import Path
from PIL import Image, ImageDraw, ImageFont
import arabic_reshaper
from bidi.algorithm import get_display
from docx import Document
from docx.shared import Inches, Pt, RGBColor
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.enum.text import WD_ALIGN_PARAGRAPH

ROOT = Path(__file__).resolve().parents[1]
text = (ROOT / 'docs/roadmap/SITE-ROADMAP.fa.md').read_text(encoding='utf8')
out = Path(sys.argv[1] if len(sys.argv) > 1 else '.')
out.mkdir(parents=True, exist_ok=True)

def plain(s):
    s = re.sub(r'\[([^\]]+)\]\(([^)]+)\)', r'\1 (\2)', s)
    return s.replace('`', '').replace('**', '')

def rtl(p):
    p.alignment = WD_ALIGN_PARAGRAPH.RIGHT
    e = OxmlElement('w:bidi'); e.set(qn('w:val'), '1'); p._p.get_or_add_pPr().append(e)
    return p

doc = Document()
for section in doc.sections:
    section.top_margin = section.bottom_margin = Inches(.6)
    section.left_margin = section.right_margin = Inches(.7)
normal = doc.styles['Normal']; normal.font.name = 'Arial'; normal.font.size = Pt(11)
normal._element.rPr.rFonts.set(qn('w:cs'), 'Arial')
for raw in text.splitlines():
    line = raw.strip()
    if not line or line.startswith('|---'): continue
    if line.startswith('|'):
        p = rtl(doc.add_paragraph()); p.add_run('  |  '.join(plain(c.strip()) for c in line.strip('|').split('|')))
    elif line.startswith('#'):
        level = len(line) - len(line.lstrip('#')); p = rtl(doc.add_paragraph()); r = p.add_run(plain(line[level:].strip()))
        r.bold = True; r.font.size = Pt(18 if level == 1 else 14 if level == 2 else 12)
        r.font.color.rgb = RGBColor.from_string('17523F')
    else:
        p = rtl(doc.add_paragraph()); p.add_run(plain(line.replace('- [x]', '☑').replace('- [ ]', '☐')))
doc.save(out / 'HAMYAR-ROADMAP-UPDATED.fa.docx')

# Tree labels/statuses are derived from the two numbered tables in the Markdown.
main_section = text.split('## مسیر اصلی به‌ترتیب', 1)[1].split('## شاخه‌های ارتقا', 1)[0]
up_section = text.split('## شاخه‌های ارتقا', 1)[1].split('## یادداشت', 1)[0]
def rows(section):
    result = []
    for line in section.splitlines():
        if not line.startswith('|'): continue
        cells = [x.strip() for x in line.strip('|').split('|')]
        if re.fullmatch('[۰-۹]+', cells[0]): result.append(cells)
    return result
main, upgrades = rows(main_section), rows(up_section)
if len(main) != 12 or len(upgrades) != 6: raise SystemExit('Expected 12 main and 6 future stages')
parents = [1, 2, 4, 5, 6, 11]
bold = ROOT / 'tools/fonts/Vazirmatn-Bold.ttf'; medium = ROOT / 'tools/fonts/Vazirmatn-Medium.ttf'
f = lambda path, size: ImageFont.truetype(str(path), size)
B, M, S = f(bold, 34), f(medium, 24), f(medium, 20)
im = Image.new('RGB', (1750, 3650), '#f7f9fc'); d = ImageDraw.Draw(im)
def fa(s): return get_display(arabic_reshaper.reshape(s))
def rt(x, y, s, font=M, color='#253c49'): d.text((x, y), fa(s), font=font, fill=color, anchor='ra')
def ct(x, y, s, font=M, color='#253c49'): d.text((x, y), fa(s), font=font, fill=color, anchor='ma')
def box(coords, fill, outline=None, width=1, radius=20): d.rounded_rectangle(coords, radius, fill=fill, outline=outline, width=width)
box((45, 40, 1705, 180), '#102638', radius=30)
ct(875, 52, 'نقشهٔ درختی زندهٔ همیار جراحی', f(bold,48), 'white')
ct(875, 120, 'مرحلهٔ ۵ در جریان  |  ورود و حساب', M, '#cbdde4')
box((55, 210, 1695, 405), '#ffffff', '#d8e3e8', 2, 24)
rt(1640, 225, 'پیشرفت این نوبت', B)
for i, line in enumerate(['مدیر یک نوع حساب: جراح یا عمومی را تأیید کرد.', 'آزمون بستهٔ ۰٫۳٫۲۲ با حساب ساختگی انجام شد.', 'OAuth واقعی و طرح تغییر نوع حساب بررسی نشده است.']):
    rt(1620, 280 + i*38, '✓   ' + line, S)
box((885, 440, 1660, 505), '#e6eef5', radius=15); ct(1272, 450, 'مسیر اصلی  |  مرحلهٔ ۱ تا ۱۲', B)
box((70, 440, 790, 505), '#f9eeee', radius=15); ct(430, 450, 'ارتقاهای آینده  |  ۱۳ تا ۱۸', B, '#76464c')
ct(430, 518, 'پس از اتمام مرحلهٔ ۱۲، به‌ترتیب', S, '#946770')
colors=[('#fff4d6','#c78b0e','#795313')]*8 + [('#fdebed','#c75460','#82323d')]*4
start, step, height = 565, 175, 137
for i, row in enumerate(main):
    num, title, status, desc = row; bg, border, dark = colors[i]; y=start+i*step
    if i < 11:
        d.line((1272,y+height,1272,y+step), fill=colors[i+1][1], width=5)
        d.polygon([(1263,y+step-10),(1281,y+step-10),(1272,y+step)],fill=colors[i+1][1])
    box((889,y+5,1664,y+height+5),'#e4ebef'); box((885,y,1660,y+height),bg,border,3)
    box((1580,y+16,1640,y+76),border,radius=17); ct(1610,y+20,num,f(bold,30),'white')
    rt(1562,y+12,title,B,dark); rt(1562,y+60,status,M,dark)
    # Keep long table detail in Word, not squeezed into a tree node.
for i, row in enumerate(upgrades):
    num,title,dependency,status=row; y=start+parents[i]*step+9
    d.line((885,y+56,790,y+56),fill='#d99aa3',width=3)
    box((70,y,790,y+120),'#fff5f5','#d99aa3',2,19)
    box((724,y+15,775,y+66),'#d99aa3',radius=15); ct(749,y+18,num,f(bold,30),'white')
    rt(710,y+14,title,f(bold,29),'#985b61'); rt(710,y+73,'وابسته به مرحلهٔ '+dependency+'  •  '+status,S,'#9a6e75')
box((70,2830,1680,2935),'#fff1f2','#e3acb1',3,23)
ct(875,2844,'پس از ۱۲: مرحله‌های ۱۳ تا ۱۸ به‌ترتیب',B,'#994c55')
ct(875,2890,'مرحلهٔ ۱۸: پرداخت اختیاری، خارج از نسخهٔ اولیه',S,'#8f6266')
box((70,2980,1680,3500),'#ffffff','#d9e2e8',2,22)
rt(1630,2996,'چک‌لیست خلاصهٔ مرحلهٔ ۵',B)
items=[(True,'تصمیم: حساب عمومی یا جراح؛ نه هر دو.'),
       (True,'رفتار نقش در بستهٔ ۰٫۳٫۲۲ روی دادهٔ ساختگی آزموده شد.'),
       (True,'فرم عضو برای جراح نیز در نمونه رندر شد.'),
       (False,'تمایز حساب تازه و عضو موجود با طرح امن.'),
       (False,'ورود مستقیم Google و callback واقعی.'),
       (False,'نقش‌های مدیر و منشی و مجوزها به‌طور کامل.'),
       (False,'سیاست تغییر نوع حساب و موارد مراحل پیشین.')]


for i,(done,line) in enumerate(items):
    y=3053+i*52;x=1630
    d.rounded_rectangle((x-25,y+4,x,y+29),5,fill='#239362' if done else 'white',outline='#239362' if done else '#b98b33',width=2)
    if done:d.line([(x-20,y+16),(x-15,y+22),(x-5,y+10)],fill='white',width=3)
    rt(1590,y,line,M)
rt(1630,3440,'سبز: موجود؛ زرد: در توسعه یا بررسی؛ قرمز: آماده‌نشده؛ تیک به معنی پایان کل مرحله نیست.',S,'#5b737c')
im.save(out / 'HAMYAR-TREE-UPDATED.fa.png', optimize=True)
print('Generated roadmap files in', out)
