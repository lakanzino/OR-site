=== Lax Theme ===
Contributors: mohammadrezalak
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Tags: rtl-language-support, custom-menu, custom-logo, featured-images, threaded-comments, translation-ready, blog, accessibility-ready

قالب والد سبک و راست‌چین «Lax Theme» (کلاسیک) — اسکلت خنثی و قابل‌توسعه با قالب فرزند.

== Description ==

Lax Theme is a lightweight, RTL-first classic parent theme reviewed against WordPress Theme
Review requirements and Google page-experience guidelines. Project-specific features belong in
a child theme whose style.css declares `Template: lax-theme`. (Renamed from the former
Sarzamin Aryan parent theme; history in CHANGELOG.md.)

* No external requests: Vazirmatn font bundled (SIL OFL 1.1).
* Logical CSS properties; no double-flip RTL hacks.
* Accessible: skip link, screen-reader text, visible focus, aria-controlled menu/search toggles.
* Performance: 3 small stylesheets, one deferred script, font preload, no jQuery.

== Installation ==

1. Upload `lax-theme.zip` via Appearance → Themes → Add New → Upload Theme (or extract into wp-content/themes/ with cPanel File Manager).
2. Create/upload a child theme with `Template: lax-theme` in its style.css header and activate the child (do not rename this parent folder afterwards).
3. Go to Appearance → Customize → Theme Options to set the footer text.

== Changelog ==

See CHANGELOG.md.

== Credits ==

* Based on Quantum Pedia 1.0.0 by M.r.lak, A-Del, R.LOR (GPLv2+), reviewed and rebuilt as Sarzamin Aryan by محمدرضا لک, renamed to Lax Theme in 1.0.0.
* Vazirmatn font by Saber Rastikerdar — SIL Open Font License 1.1 — https://github.com/rastikerdar/vazirmatn
