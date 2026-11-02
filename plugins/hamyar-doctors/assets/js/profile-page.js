/**
 * صفحهٔ پروفایل پزشک — رفتارهای کوچک.
 *
 * ۱) جلوگیری از ارسال دوبارهٔ فرم (قفل دکمه پس از submit معتبر).
 * ۲) فعال‌کردن حالت چسبانِ نوار لنگرها با احترام به prefers-reduced-motion.
 * ۳) نمایش پیام وضعیت با اسکرول نرم به همان بخش.
 */
(function () {
	'use strict';

	var root = document.querySelector('.hj-doc');
	if (!root) {
		return;
	}

	/* ---------------------------------------------------------------
	 * ۱) قفل دکمهٔ ارسال
	 * --------------------------------------------------------------- */
	var form = root.querySelector('.hj-card--form form');
	if (form) {
		form.addEventListener('submit', function (event) {
			var button = form.querySelector('button[type="submit"]');
			if (!button) {
				return;
			}
			if (button.dataset.busy === '1') {
				// ارسال دوم را متوقف کن.
				event.preventDefault();
				return;
			}
			button.dataset.busy = '1';
			button.disabled = true;
			button.textContent = 'در حال ارسال…';
		});
	}

	/* ---------------------------------------------------------------
	 * ۲) اسکرول نرم به بخش‌ها (اگر CSS پشتیبانی نکند)
	 * --------------------------------------------------------------- */
	var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	if (!reduce) {
		root.querySelectorAll('a[href^="#hj-"]').forEach(function (link) {
			link.addEventListener('click', function (event) {
				var id = link.getAttribute('href').slice(1);
				var target = document.getElementById(id);
				if (!target) {
					return;
				}
				event.preventDefault();
				target.scrollIntoView({ behavior: 'smooth', block: 'start' });
				if (history.pushState) {
					history.pushState(null, '', '#' + id);
				}
			});
		});
	}

}());
