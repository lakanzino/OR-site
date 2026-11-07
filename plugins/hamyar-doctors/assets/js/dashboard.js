/*
 * Hamyar Doctors — Section 6: dashboard behaviour for /my-profile/
 *
 * Plain ES5-ish JS, no dependencies. All server talks go through
 * admin-ajax.php with the HJ_DASH nonce; errors are rendered ONLY under the
 * matching field ([data-err="key"]) — never as a separate notice.
 */
(function () {
	'use strict';

	var root = document.getElementById('hj-dash');
	if (!root || typeof window.HJ_DASH === 'undefined') {
		return;
	}

	var form = document.getElementById('hj-dash-form');
	var cfg = window.HJ_DASH;

	function q(sel, ctx) { return (ctx || root).querySelector(sel); }
	function qa(sel, ctx) { return Array.prototype.slice.call((ctx || root).querySelectorAll(sel)); }

	function err(key, msg) {
		var el = q('[data-err="' + key + '"]');
		if (el) { el.textContent = msg || ''; }
	}
	function clearErrs() {
		qa('[data-err]').forEach(function (el) { el.textContent = ''; });
	}

	function post(fd) {
		fd.append('_hjn', cfg.nonce);
		return fetch(cfg.ajax, { method: 'POST', body: fd, credentials: 'same-origin' })
			.then(function (r) { return r.json().catch(function () { return null; }); })
			.then(function (res) {
				if (!res) { throw new Error('bad-json'); }
				return res;
			});
	}

	function applyState(d) {
		if (!d) { return; }
		var chip = q('[data-hj="status-chip"]');
		if (chip && d.status_label) {
			chip.textContent = d.status_label;
			chip.setAttribute('data-status', d.status || 'draft');
		}
		var bar = q('[data-hj="bar"]');
		if (bar && typeof d.completeness !== 'undefined') { bar.style.width = d.completeness + '%'; }
		var pct = q('[data-hj="pct"]');
		if (pct && d.pct_label) { pct.textContent = d.pct_label; }
		var miss = q('[data-hj="missing"]');
		if (miss) {
			if (d.missing && d.missing.length) {
				miss.textContent = 'برای ارسال، تکمیل کنید: ' + d.missing.join('، ');
				miss.hidden = false;
			} else {
				miss.textContent = '';
				miss.hidden = true;
			}
		}
		var sub = q('[data-hj="submit-review"]');
		if (sub) {
			sub.hidden = !(d.status === 'draft' || d.status === 'rejected');
			sub.disabled = !d.can_submit;
		}
		var pend = q('[data-hj="pending-note"]');
		if (pend) { pend.hidden = d.status !== 'pending'; }
		var link = q('[data-hj="public-link"]');
		if (link) {
			if (d.public_url) { link.href = d.public_url; link.hidden = false; }
			else { link.hidden = true; }
		}
		var head = q('[data-hj="head-name"]');
		if (head && d.name) { head.textContent = d.name; }
		if (d.url) {
			qa('[data-hj="avatar"]').forEach(function (img) { img.src = d.url; });
		}
	}

	/* ---------------- save ---------------- */

	if (form) {
		var schedulePending = false;
		var scheduleUnknown = false;
		var saveButton = form.querySelector('[data-hj="save"]');
		var unknownMessage = 'نتیجهٔ ذخیره مشخص نیست؛ دوباره ذخیره نکنید. تغییرات را یادداشت کنید و با مدیر سایت وضعیت را بررسی کنید؛ بازخوانی به‌تنهایی نتیجه را قطعی نمی‌کند.';
		function markScheduleUnknown() {
			schedulePending = false;
			scheduleUnknown = true;
			if (saveButton) { saveButton.disabled = true; }
			err('form', unknownMessage);
		}
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var revision = form.querySelector('input[name="hj_schedule_revision"]');
			if (revision && (schedulePending || scheduleUnknown)) { return; }
			clearErrs();
			var fd = new FormData(form);
			fd.append('action', 'hj_dash_save');
			if (revision) {
				// A lost response does not imply rollback. Never retry this form automatically.
				schedulePending = true;
				if (saveButton) { saveButton.disabled = true; }
				err('form', 'ذخیره در حال انجام است؛ لطفاً تا دریافت پاسخ ویرایش نکنید.');
				fd.append('_hjn', cfg.nonce);
				var submittedRevision = revision.value;
				var timer = window.setTimeout(markScheduleUnknown, 30000);
				var request;
				try { request = fetch(cfg.ajax, { method: 'POST', body: fd, credentials: 'same-origin' }); }
				catch (ex) { window.clearTimeout(timer); markScheduleUnknown(); return; }
				request.then(function (response) {
					return response.json().then(function (body) { return { status: response.status, body: body }; });
				}).then(function (reply) {
					if (scheduleUnknown) { return; } // A late response cannot unlock a timed-out form.
					var res = reply.body;
					if (reply.status === 422 && res && res.success === false && typeof res.message === 'string' && res.errors && typeof res.errors === 'object' && !Array.isArray(res.errors)) {
						window.clearTimeout(timer); schedulePending = false;
						if (saveButton) { saveButton.disabled = false; }
						Object.keys(res.errors).forEach(function (k) { err(k, res.errors[k]); });
						err('form', res.message);
						return;
					}
					var oldRevision = Number(submittedRevision);
					if (reply.status !== 200 || !res || res.success !== true || !res.data || !Number.isSafeInteger(oldRevision) || oldRevision < 0 || !Number.isSafeInteger(res.data.schedule_revision) || res.data.schedule_revision !== oldRevision + 1) {
						window.clearTimeout(timer); markScheduleUnknown(); return;
					}
					revision.value = String(res.data.schedule_revision);
					if (res.data.created) { window.location.reload(); return; }
					applyState(res.data);
					var hint = q('[data-hj="pid-hint"]');
					if (hint) { hint.hidden = true; }
					qa('[data-needs-pid]').forEach(function (el) { el.disabled = false; });
					window.clearTimeout(timer); schedulePending = false;
					if (saveButton) { saveButton.disabled = false; }
					err('form', '');
				}).catch(function () { window.clearTimeout(timer); markScheduleUnknown(); });
				return;
			}
			post(fd).then(function (res) {
				if (!res.success) {
					if (res.errors) {
						Object.keys(res.errors).forEach(function (k) { err(k, res.errors[k]); });
					}
					err('form', res.message || cfg.i18n.fail);
					return;
				}
				err('form', '');
				if (res.data && res.data.created) {
					window.location.reload();
					return;
				}
				applyState(res.data);
				var hint = q('[data-hj="pid-hint"]');
				if (hint) { hint.hidden = true; }
				qa('[data-needs-pid]').forEach(function (el) { el.disabled = false; });
			}).catch(function () { err('form', cfg.i18n.fail); });
		});
	}

	/* ---------------- live preview ---------------- */

	qa('input[data-pv], textarea[data-pv]').forEach(function (input) {
		var out = q('[data-pvout="' + input.getAttribute('data-pv') + '"]');
		if (!out) { return; }
		input.addEventListener('input', function () { out.textContent = input.value; });
	});

	/* ---------------- province → city ---------------- */

	var prov = q('#hj-dash-province');
	var city = q('#hj-dash-city');
	if (prov && city) {
		prov.addEventListener('change', function () {
			var pv = parseInt(prov.value, 10) || 0;
			var sel = parseInt(city.value, 10) || 0;
			qa('option', city).forEach(function (op) {
				var opv = parseInt(op.value, 10) || 0;
				var opp = parseInt(op.getAttribute('data-province'), 10) || 0;
				op.hidden = pv !== 0 && opv !== 0 && opp !== pv;
				if (op.hidden && opv === sel) { city.value = '0'; }
			});
		});
		prov.dispatchEvent(new Event('change'));
	}
	if (city) {
		city.addEventListener('change', function () {
			var op = city.selectedOptions && city.selectedOptions[0];
			var pv = q('[data-hj="pv-city"]');
			if (pv) { pv.textContent = op && op.value !== '0' ? op.textContent : ''; }
		});
	}

	/* ---------------- working hours toggles ---------------- */

	qa('[data-hj="closed"]').forEach(function (tog) {
		tog.addEventListener('change', function () {
			var row = tog.closest('.hj-dash__hrow');
			if (!row) { return; }
			qa('input[type="time"]', row).forEach(function (t) { t.disabled = tog.checked; });
		});
	});

	/* ---------------- specialty chips: type filter + max ---------------- */

	var typeSel = q('#hj-dash-type');
	var chips = qa('#hj-dash-specs .hj-dash__chip');
	function filterChips() {
		var tv = typeSel ? (parseInt(typeSel.value, 10) || 0) : 0;
		chips.forEach(function (ch) {
			var dt = parseInt(ch.getAttribute('data-type'), 10) || 0;
			ch.hidden = tv !== 0 && dt !== tv;
			if (ch.hidden) {
				var cb = q('input', ch);
				if (cb) { cb.checked = false; }
			}
		});
	}
	if (typeSel) {
		typeSel.addEventListener('change', filterChips);
		filterChips();
	}
	if (chips.length) {
		root.addEventListener('change', function (e) {
			var cb = e.target;
			if (!cb || cb.name !== 'specialty[]') { return; }
			var checked = qa('#hj-dash-specs input:checked');
			if (cb.checked && checked.length > cfg.maxSpec) {
				cb.checked = false;
				err('specialty', 'حداکثر ' + cfg.maxSpec + ' رشته.');
			} else {
				err('specialty', '');
			}
		});
	}

	/* ---------------- photo ---------------- */

	var photoIn = q('#hj-dash-photo');
	if (photoIn) {
		photoIn.addEventListener('change', function () {
			if (!photoIn.files || !photoIn.files[0]) { return; }
			err('photo', '');
			var fd = new FormData();
			fd.append('action', 'hj_dash_photo');
			fd.append('photo', photoIn.files[0]);
			fd.append('pid', q('input[name="pid"]', form) ? q('input[name="pid"]', form).value : '0');
			post(fd).then(function (res) {
				if (!res.success) { err('photo', res.message || cfg.i18n.fail); return; }
				applyState(res.data);
				var rm = q('[data-hj="photo-remove"]');
				if (rm) { rm.hidden = false; }
				photoIn.value = '';
			}).catch(function () { err('photo', cfg.i18n.fail); });
		});
	}
	var photoRm = q('[data-hj="photo-remove"]');
	if (photoRm) {
		photoRm.addEventListener('click', function () {
			err('photo', '');
			var fd = new FormData();
			fd.append('action', 'hj_dash_photo_remove');
			fd.append('pid', q('input[name="pid"]', form) ? q('input[name="pid"]', form).value : '0');
			post(fd).then(function (res) {
				if (!res.success) { err('photo', res.message || cfg.i18n.fail); return; }
				applyState(res.data);
				photoRm.hidden = true;
			}).catch(function () { err('photo', cfg.i18n.fail); });
		});
	}

	/* ---------------- documents ---------------- */

	var docIn = q('#hj-dash-doc');
	if (docIn) {
		docIn.addEventListener('change', function () {
			if (!docIn.files || !docIn.files[0]) { return; }
			err('doc', '');
			var fd = new FormData();
			fd.append('action', 'hj_dash_doc');
			fd.append('doc', docIn.files[0]);
			fd.append('pid', q('input[name="pid"]', form) ? q('input[name="pid"]', form).value : '0');
			post(fd).then(function (res) {
				if (!res.success) { err('doc', res.message || cfg.i18n.fail); return; }
				var wrap = q('[data-hj="docs"]');
				if (wrap && res.data && typeof res.data.docs_html !== 'undefined') { wrap.innerHTML = res.data.docs_html; }
				docIn.value = '';
			}).catch(function () { err('doc', cfg.i18n.fail); });
		});
	}
	root.addEventListener('click', function (e) {
		var del = e.target.closest ? e.target.closest('[data-doc-del]') : null;
		if (!del) { return; }
		e.preventDefault();
		err('doc', '');
		var fd = new FormData();
		fd.append('action', 'hj_dash_doc_delete');
		fd.append('doc', del.getAttribute('data-doc-del'));
		fd.append('pid', q('input[name="pid"]', form) ? q('input[name="pid"]', form).value : '0');
		post(fd).then(function (res) {
			if (!res.success) { err('doc', res.message || cfg.i18n.fail); return; }
			var wrap = q('[data-hj="docs"]');
			if (wrap && res.data && typeof res.data.docs_html !== 'undefined') { wrap.innerHTML = res.data.docs_html; }
		}).catch(function () { err('doc', cfg.i18n.fail); });
	});

	/* ---------------- submit for review ---------------- */

	var submitBtn = q('[data-hj="submit-review"]');
	if (submitBtn) {
		submitBtn.addEventListener('click', function () {
			err('submit', '');
			var fd = new FormData();
			fd.append('action', 'hj_dash_submit');
			fd.append('pid', q('input[name="pid"]', form) ? q('input[name="pid"]', form).value : '0');
			post(fd).then(function (res) {
				if (!res.success) {
					err('submit', res.message || cfg.i18n.fail);
					return;
				}
				applyState(res.data);
			}).catch(function () { err('submit', cfg.i18n.fail); });
		});
	}
}());
