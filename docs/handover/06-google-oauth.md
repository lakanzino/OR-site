# 06 — راهنمای ورود با گوگل

======================================================================

(نام منوها از حافظه است و ممکن است در نسخهٔ جدید گوگل/Nextend کمی فرق کند.)



1. Google Cloud Console → یک Project بساز.

2. APIs & Services → **OAuth consent screen**:

   - User type: External

   - نام برنامه، ایمیل پشتیبانی، دامنهٔ `hamyarjarahi.ir`

   - Scopes: فقط `openid`، `email`، `profile`

   - وضعیت را به **In production** ببر (در حالت Testing فقط کاربران تست‌شده وارد می‌شوند و توکن‌ها زود منقضی می‌شوند).

3. Credentials → Create Credentials → **OAuth client ID** → Web application.

   - Authorized JavaScript origins: `https://hamyarjarahi.ir`

   - **Authorized redirect URI** (دقیقاً همان که Nextend نشان می‌دهد؛ نمونه):

     `https://hamyarjarahi.ir/wp-login.php?loginSocial=google`

   - هیچ تفاوتی در http/https، www، اسلش یا حروف مجاز نیست.

4. Client ID و Client Secret را در **Nextend Social Login → Providers → Google** وارد و Verify Settings کن. Secret را در Drive/چت قرار نده.

5. در Nextend، اگر بعد از ورود به /my-profile/ نرفت، «Fixed redirect url» را روی `https://hamyarjarahi.ir/my-profile/` بگذار (افزونه خودش هم ریدایرکت می‌کند).

6. تست: Incognito → `https://hamyarjarahi.ir/?hj_google_start=1`.

7. hj_auth_status: نقش `doctor` برای کاربر جدید و ثبت metaهای `hj_role_pending`, `hj_auth_provider`.

8. اگر نقش پزشک داده نشد: نام قلاب‌های Nextend در بخش ۵ از حافظه نوشته شده (`nsl_register_new_user`, `nsl_login`, `nsl_login_redirect_url`, `nsl_registration_redirect_url`). نتیجهٔ hj_auth_status را بفرست. فقط همین بخش اثر می‌پذیرد.
