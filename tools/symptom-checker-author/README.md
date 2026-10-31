# ابزار نویسندگی بستهٔ دانش (فاز ۳)

یک CLI، یک مسیر: **داده → بستهٔ دانش**. هیچ بیماری‌ای با ویرایش دستی موتور، UI یا فایل بسته اضافه نمی‌شود.

```text
knowledge-bundle.v1.json
        │  tools/symptom-checker-migrate/v1_to_v2.py
        ▼
knowledge-bundle.v2.base.json        (پایهٔ ثابت، ساخت‌شده، دست‌نخورده)
        │  author.py build-bundle  +
        ▼
docs/symptom-checker/authoring/      (workspace: sources, symptoms, questions, conditions)
        │
        ▼
knowledge-bundle.v2.json             (بستهٔ تحویل‌شدنی، ساخت‌شده، نه دست‌نویس)
```

پایه و بستهٔ نهایی هر دو خروجی ماشین‌اند: اگر کسی یکی را دستی عوض کند، `v1_to_v2.py --check` یا `author.py build-bundle --check` می‌گیرد.

## دستورها

```sh
A=tools/symptom-checker-author/author.py

python3 $A validate                    # همهٔ بررسی‌ها: schema، ارجاع، پوشش، متن راهنما، موتور واقعی
python3 $A overlap                     # جفت‌های هم‌پوشان + جداکنندهٔ قابل‌پرسش (--json برای ماشین)
python3 $A gen-scenarios               # یک شخصیت «typical» برای هر بیماری → docs/symptom-checker/scenarios/generated/
python3 $A report                      # خلاصهٔ پوشش نشانه‌ها و بیماری‌های بدون جداکننده (--out برای فایل)
python3 $A gaps --strict               # گپ و تداخل: ارجاع شکسته، پرسش/گزینهٔ مرده، برچسب تکراری، جفت بی‌جداکننده (--json، --out)
python3 $A build-bundle                # base + workspace → knowledge-bundle.v2.json (نسخهٔ minor بالا می‌رود)
python3 $A build-bundle --check        # آیا فایل کامیت‌شده همان چیزی است که امروز ساخته می‌شود؟
python3 $A add-condition <id> --name «نام فارسی»   # اسکلت پروفایل با فهرست نشانه‌های کاتالوگ
python3 $A add <sources|symptoms|questions|conditions> --file <path>   # افزودن یک فایل داده به workspace
python3 -m unittest discover -s tools/symptom-checker-author -p 'test_*.py'
```

هر دستور `--workspace <dir>` را می‌پذیرد (پیش‌فرض `docs/symptom-checker/authoring`). همین راه، مجموعهٔ پذیرش را جدا از workspace زنده نگه می‌دارد:

```sh
python3 $A --workspace docs/symptom-checker/authoring/examples/cholecystitis \
  build-bundle --out docs/symptom-checker/phase-3/built/knowledge-bundle.v2.cholecystitis-demo.json
```

## چه چیزی را رد می‌کند (کد خروج ≠ ۰)

| وضعیت | پیام |
| --- | --- |
| دو بیماری با نشانه‌های مشترک و **هیچ** جداکنندهٔ قابل‌پرسش | «هیچ جداکنندهٔ قابل‌پرسشی ندارد — به پرسش تازه نیاز دارد» |
| نشانهٔ تازه‌ای که هیچ گزینه‌ای آن را گزارش نمی‌کند | «هیچ گزینه‌ای ندارد که آن را ثبت کند — به پرسش تازه نیاز دارد» |
| پرسش یا گزینه‌ای بدون خط راهنما در `question-help.js` | «خط راهنما ندارد» (هر گزینه یک جمله) |
| شناسهٔ تکراری یا شناسه‌ای که در بستهٔ پایه هست | `duplicate` / `already exists in the base bundle` |
| پروفایل کمتر از ۳ ردیف یا بدون `typical` | خطای ساختاری |
| override روی بیماری ناموجود | خطای ارجاع |
| بسته‌ای که موتور واقعی نمی‌پذیرد | `engine: …` |

**دروازهٔ موتور:** پیش از هر نوشتن، بستهٔ ساخته‌شده به `symptom-checker/engine/src/load-bundle.js` داده می‌شود؛ اگر موتور ردش کند، CLI هم رد می‌کند. این سدّ سوم (کنار `validate_v2.py`) جلوی این را می‌گیرد که دو پیاده‌سازی از قواعد از هم دور بیفتند — در همین فاز دقیقاً همین اتفاق افتاد و `profile_ref` این‌طور پیدا شد.

## نسخه و تغییرات

- محتوای تازه ⇒ نسخهٔ **minor** بالا می‌رود (`0.2.0-draft` → `0.3.0-draft`) و یک ورودی در `change_notes` می‌نشیند؛ `--no-bump` برای ساخت آزمایشی.
- `-draft` تا تأیید تیم می‌ماند؛ محتوای تازه `review_status: pending_team_review` است.
- هیچ عدد احتمال، امتیاز یا درصدی نوشته نمی‌شود؛ هر ردیف پروفایل `source_refs` دارد.
- کلیدهایی که با `_` شروع شوند (مثل `_note`) در ساخت بسته حذف می‌شوند؛ یادداشت نویسنده وارد بسته نمی‌شود.

## تست‌ها چه چیزی را قفل می‌کنند

`test_author.py` (۱۷ آزمون): بازتولیدپذیری بستهٔ تحویل‌شدنی و بستهٔ نمونه، ایدمپوتنسی، رد شدن پرسش بدون راهنما، رد شدن دوقلوهای بدون جداکننده، خطا بودن شکاف نشانه، رد شناسهٔ تکراری، و اینکه مجموعهٔ کوله‌سیستیت فقط minor بالا می‌برد و شخصیت ساخته‌شده‌اش قاعدهٔ پوشش را به‌اشتباه فعال نمی‌کند.
