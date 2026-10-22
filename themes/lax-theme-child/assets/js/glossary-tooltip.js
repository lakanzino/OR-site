/**
 * Hamyar Jarahi — Responsive Glossary Tooltip Popover & Instant Directory Filter.
 *
 * Zero dependencies, RTL-first, touch + hover + keyboard accessible.
 *
 * @package Lax_Theme_Child
 * @since   0.12.0
 */
(function () {
	'use strict';

	/* ======================================================================
	 * PART 1: In-Article Green Highlight Floating Tooltip Popover
	 * ==================================================================== */

	function initArticleTooltips() {
		var dataEl = document.getElementById('hj-glossary-article-data');
		if (!dataEl) {
			return;
		}

		var payload = null;
		try {
			payload = JSON.parse(dataEl.textContent || '{}');
		} catch (e) {
			return;
		}

		if (!payload || !payload.terms) {
			return;
		}

		var termsMap = payload.terms;
		var showLink = !!payload.showLink;

		var popover = document.createElement('div');
		popover.id = 'hj-glossary-popover';
		popover.className = 'hj-glossary-popover';
		popover.setAttribute('role', 'tooltip');
		popover.setAttribute('aria-hidden', 'true');
		popover.innerHTML =
			'<div class="hj-glossary-popover__arrow"></div>' +
			'<div class="hj-glossary-popover__inner">' +
				'<div class="hj-glossary-popover__head">' +
					'<div class="hj-glossary-popover__titles">' +
						'<strong class="hj-glossary-popover__term"></strong>' +
						'<span class="hj-glossary-popover__en"></span>' +
					'</div>' +
					'<button type="button" class="hj-glossary-popover__close" aria-label="بستن">×</button>' +
				'</div>' +
				'<p class="hj-glossary-popover__def"></p>' +
				'<div class="hj-glossary-popover__foot">' +
					'<a class="hj-glossary-popover__cat" href="#"></a>' +
					'<a class="hj-glossary-popover__more" href="#">مشاهده در واژه‌نامه ←</a>' +
				'</div>' +
			'</div>';
		document.body.appendChild(popover);

		var elTerm  = popover.querySelector('.hj-glossary-popover__term');
		var elEn    = popover.querySelector('.hj-glossary-popover__en');
		var elDef   = popover.querySelector('.hj-glossary-popover__def');
		var elFoot  = popover.querySelector('.hj-glossary-popover__foot');
		var elCat   = popover.querySelector('.hj-glossary-popover__cat');
		var elMore  = popover.querySelector('.hj-glossary-popover__more');
		var elClose = popover.querySelector('.hj-glossary-popover__close');

		var activeTrigger = null;
		var activeRectIdx = 0;
		var openTimer     = null;
		var closeTimer    = null;
		var rafPending    = false;

		function getTargetRect(el, clientX, clientY) {
			var rects = el.getClientRects();
			if (!rects || rects.length === 0) {
				return el.getBoundingClientRect();
			}
			if (rects.length === 1 || typeof clientX !== 'number') {
				return rects[Math.min(activeRectIdx, rects.length - 1)];
			}
			var bestIdx  = 0;
			var bestDist = Infinity;
			for (var i = 0; i < rects.length; i++) {
				var r  = rects[i];
				var dx = clientX < r.left ? r.left - clientX : (clientX > r.right ? clientX - r.right : 0);
				var dy = clientY < r.top ? r.top - clientY : (clientY > r.bottom ? clientY - r.bottom : 0);
				var d  = dx * dx + dy * dy;
				if (d < bestDist) {
					bestDist = d;
					bestIdx  = i;
				}
			}
			activeRectIdx = bestIdx;
			return rects[bestIdx];
		}

		function getSafeTopCeiling() {
			var ceiling = 12;
			var adminBar = document.getElementById('wpadminbar');
			if (adminBar) {
				var abRect = adminBar.getBoundingClientRect();
				if (abRect.bottom > 0 && abRect.height < 80) {
					ceiling = Math.max(ceiling, abRect.bottom + 8);
				}
			}
			var header = document.querySelector('.site-header, #masthead');
			if (header) {
				var hStyle = window.getComputedStyle(header);
				if (hStyle.position === 'sticky' || hStyle.position === 'fixed') {
					var hRect = header.getBoundingClientRect();
					if (hRect.bottom > 0 && hRect.height < 140) {
						ceiling = Math.max(ceiling, hRect.bottom + 10);
					}
				}
			}
			return ceiling;
		}

		function positionPopover(clientX, clientY) {
			if (!activeTrigger) {
				return;
			}
			var rect = getTargetRect(activeTrigger, clientX, clientY);
			var vw   = document.documentElement.clientWidth || window.innerWidth;
			var vh   = window.innerHeight;

			// Auto-close if trigger scrolled completely out of viewport.
			if (rect.bottom < 0 || rect.top > vh) {
				closePopover(true);
				return;
			}

			var popRect = popover.getBoundingClientRect();
			var popW    = popRect.width || Math.min(340, vw - 24);
			var popH    = popRect.height || 130;
			var gap     = 10;
			var margin  = 12;
			var ceiling = getSafeTopCeiling();

			var spaceAbove = rect.top - ceiling;
			var spaceBelow = vh - rect.bottom - margin;

			var placement = 'top';
			if (spaceAbove < popH + gap && spaceBelow > spaceAbove) {
				placement = 'bottom';
			}

			var top = placement === 'top' ? (rect.top - popH - gap) : (rect.bottom + gap);
			if (placement === 'bottom' && top + popH > vh - margin) {
				top = Math.max(ceiling, vh - popH - margin);
			}
			if (placement === 'top' && top < ceiling) {
				top = ceiling;
			}

			var wordCenterX = rect.left + rect.width / 2;
			var left        = wordCenterX - popW / 2;
			if (left < margin) {
				left = margin;
			}
			if (left + popW > vw - margin) {
				left = vw - margin - popW;
			}

			var arrowX = Math.max(18, Math.min(popW - 18, wordCenterX - left));

			popover.setAttribute('data-placement', placement);
			popover.style.left = Math.round(left) + 'px';
			popover.style.top  = Math.round(top) + 'px';
			popover.style.setProperty('--hj-arrow-x', Math.round(arrowX) + 'px');
		}

		function openPopover(triggerEl, clientX, clientY) {
			var tid  = triggerEl.getAttribute('data-hj-term');
			var data = tid ? termsMap[tid] : null;
			if (!data) {
				return;
			}

			clearTimeout(closeTimer);

			if (activeTrigger && activeTrigger !== triggerEl) {
				activeTrigger.classList.remove('is-active');
				activeTrigger.setAttribute('aria-expanded', 'false');
			}

			activeTrigger = triggerEl;
			activeRectIdx = 0;
			activeTrigger.classList.add('is-active');
			activeTrigger.setAttribute('aria-expanded', 'true');
			activeTrigger.setAttribute('aria-describedby', 'hj-glossary-popover');

			elTerm.textContent = data.t || '';
			if (data.e) {
				elEn.textContent = data.e;
				elEn.style.display = 'inline';
			} else {
				elEn.textContent = '';
				elEn.style.display = 'none';
			}
			elDef.textContent = data.d || '';

			var hasCat  = !!(data.c && data.cu);
			var hasMore = !!(showLink && data.u);

			if (hasCat) {
				elCat.textContent = data.c;
				elCat.href = data.cu;
				elCat.style.display = 'inline-block';
			} else {
				elCat.style.display = 'none';
			}

			if (hasMore) {
				elMore.href = data.u;
				elMore.style.display = 'inline';
			} else {
				elMore.style.display = 'none';
			}

			elFoot.style.display = (hasCat || hasMore) ? 'flex' : 'none';

			popover.setAttribute('aria-hidden', 'false');
			positionPopover(clientX, clientY);
			popover.classList.add('is-open');
			// Re-measure once visible in case height wrapped.
			positionPopover(clientX, clientY);
		}

		function closePopover(immediate) {
			clearTimeout(openTimer);
			clearTimeout(closeTimer);

			var doClose = function () {
				popover.classList.remove('is-open');
				popover.setAttribute('aria-hidden', 'true');
				if (activeTrigger) {
					activeTrigger.classList.remove('is-active');
					activeTrigger.setAttribute('aria-expanded', 'false');
					activeTrigger.removeAttribute('aria-describedby');
					activeTrigger = null;
				}
			};

			if (immediate) {
				doClose();
			} else {
				closeTimer = setTimeout(doClose, 145);
			}
		}

		// Delegate hover, click/tap, and keyboard events cleanly across the document.
		document.addEventListener('mouseover', function (e) {
			var termEl = e.target.closest ? e.target.closest('.hj-glossary-term') : null;
			if (termEl) {
				clearTimeout(closeTimer);
				if (activeTrigger === termEl && popover.classList.contains('is-open')) {
					return;
				}
				clearTimeout(openTimer);
				var cx = e.clientX;
				var cy = e.clientY;
				openTimer = setTimeout(function () {
					openPopover(termEl, cx, cy);
				}, 80);
				return;
			}
			if (e.target.closest && e.target.closest('#hj-glossary-popover')) {
				clearTimeout(closeTimer);
			}
		});

		document.addEventListener('mouseout', function (e) {
			var fromTerm = e.target.closest ? e.target.closest('.hj-glossary-term') : null;
			var fromPop  = e.target.closest ? e.target.closest('#hj-glossary-popover') : null;
			if (!fromTerm && !fromPop) {
				return;
			}
			var rel = e.relatedTarget;
			if (rel && rel.closest && (rel.closest('.hj-glossary-term') === activeTrigger || rel.closest('#hj-glossary-popover'))) {
				return;
			}
			clearTimeout(openTimer);
			closePopover(false);
		});

		document.addEventListener('click', function (e) {
			var termEl = e.target.closest ? e.target.closest('.hj-glossary-term') : null;
			if (termEl) {
				e.preventDefault();
				clearTimeout(openTimer);
				// On touch/click: toggle if already open on the same trigger (unless opened via hover < 250ms ago).
				if (activeTrigger === termEl && popover.classList.contains('is-open') && e.pointerType === 'touch') {
					closePopover(true);
				} else {
					openPopover(termEl, e.clientX, e.clientY);
				}
				return;
			}
			if (e.target.closest && e.target.closest('#hj-glossary-popover')) {
				return;
			}
			if (popover.classList.contains('is-open')) {
				closePopover(true);
			}
		});

		elClose.addEventListener('click', function (e) {
			e.preventDefault();
			closePopover(true);
		});

		document.addEventListener('focusin', function (e) {
			var termEl = e.target.closest ? e.target.closest('.hj-glossary-term') : null;
			if (termEl) {
				openPopover(termEl);
			}
		});

		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && popover.classList.contains('is-open')) {
				var prev = activeTrigger;
				closePopover(true);
				if (prev) {
					prev.focus();
				}
				return;
			}
			if ((e.key === 'Enter' || e.key === ' ') && document.activeElement && document.activeElement.classList.contains('hj-glossary-term')) {
				e.preventDefault();
				if (activeTrigger === document.activeElement && popover.classList.contains('is-open')) {
					closePopover(true);
				} else {
					openPopover(document.activeElement);
				}
			}
		});

		function onScrollOrResize() {
			if (!popover.classList.contains('is-open') || rafPending) {
				return;
			}
			rafPending = true;
			window.requestAnimationFrame(function () {
				rafPending = false;
				positionPopover();
			});
		}

		window.addEventListener('scroll', onScrollOrResize, { passive: true });
		window.addEventListener('resize', onScrollOrResize, { passive: true });
	}

	/* ======================================================================
	 * PART 2: Instant Live Search & Filter on `/glossary/` Directory
	 * ==================================================================== */

	function initGlossaryDirectory() {
		var hub = document.getElementById('hj-glossary-directory');
		if (!hub) {
			return;
		}

		var ajaxUrl     = hub.getAttribute('data-ajax-url') || '';
		var hubBaseUrl  = hub.getAttribute('data-hub-url') || window.location.pathname;
		var searchInput = document.getElementById('hj-glossary-search-input');
		var clearBtn    = document.getElementById('hj-glossary-search-clear');
		var gridEl      = document.getElementById('hj-glossary-grid');
		var pagEl       = document.getElementById('hj-glossary-pagination');
		var statusEl    = document.getElementById('hj-glossary-status-text');

		if (!ajaxUrl || !gridEl) {
			return;
		}

		var state = {
			gq:      hub.getAttribute('data-init-q') || '',
			gcat:    hub.getAttribute('data-init-cat') || '',
			gletter: hub.getAttribute('data-init-letter') || '',
			gpage:   parseInt(hub.getAttribute('data-init-page') || '1', 10) || 1
		};

		var debounceTimer = null;
		var activeController = null;

		function toFaDigits(val) {
			return String(val).replace(/\d/g, function (d) {
				return '۰۱۲۳۴۵۶۷۸۹'[parseInt(d, 10)];
			});
		}

		function escHtml(str) {
			return String(str || '')
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;');
		}

		function syncActiveControls() {
			if (clearBtn && searchInput) {
				clearBtn.classList.toggle('is-visible', searchInput.value.trim().length > 0);
			}
			hub.querySelectorAll('[data-gcat]').forEach(function (btn) {
				btn.classList.toggle('is-active', (btn.getAttribute('data-gcat') || '') === state.gcat);
			});
			hub.querySelectorAll('[data-gletter]').forEach(function (btn) {
				btn.classList.toggle('is-active', (btn.getAttribute('data-gletter') || '') === state.gletter);
			});
		}

		function updateBrowserUrl() {
			if (!window.history || !window.history.replaceState) {
				return;
			}
			var params = new URLSearchParams();
			if (state.gq) params.set('gq', state.gq);
			if (state.gcat) params.set('gcat', state.gcat);
			if (state.gletter) params.set('gletter', state.gletter);
			if (state.gpage > 1) params.set('gpage', String(state.gpage));
			var qs = params.toString();
			window.history.replaceState(null, '', hubBaseUrl + (qs ? '?' + qs : ''));
		}

		function renderResults(data) {
			var items = data.items || [];
			var total = data.total || 0;
			var pages = data.pages || 1;
			var page  = data.page || 1;

			if (statusEl) {
				statusEl.textContent = total > 0
					? 'نمایش ' + toFaDigits(items.length) + ' واژه از مجموع ' + toFaDigits(total.toLocaleString('en-US')) + ' نتیجه (صفحه‌ی ' + toFaDigits(page) + ' از ' + toFaDigits(pages) + ')'
					: 'هیچ واژه‌ای مطابق فیلتر انتخابی یافت نشد.';
			}

			if (items.length === 0) {
				gridEl.innerHTML =
					'<div class="hj-glossary-empty">' +
						'<strong>واژه‌ای با این مشخصات پیدا نشد.</strong>' +
						'<p style="margin:8px 0 0;">املای کوتاه‌تر را امتحان کنید یا فیلتر حرف و دسته‌بندی را روی «همه» بگذارید.</p>' +
					'</div>';
			} else {
				var html = '';
				for (var i = 0; i < items.length; i++) {
					var it = items[i];
					html += '<article class="hj-glossary-item">' +
						'<div class="hj-glossary-item__head">' +
							'<div class="hj-glossary-item__title-wrap">' +
								'<h2 class="hj-glossary-item__term">' + escHtml(it.term) + '</h2>' +
								(it.en_term ? '<span class="hj-glossary-item__en">' + escHtml(it.en_term) + '</span>' : '') +
							'</div>' +
							(it.group_name ? '<a class="hj-glossary-item__cat" data-gcat-jump="' + escHtml(it.group_slug) + '" href="' + escHtml(it.group_url) + '">' + escHtml(it.group_name) + '</a>' : '') +
						'</div>' +
						'<p class="hj-glossary-item__def">' + escHtml(it.definition) + '</p>' +
					'</article>';
				}
				gridEl.innerHTML = html;
			}

			if (pagEl) {
				if (pages <= 1) {
					pagEl.innerHTML = '';
				} else {
					var pHtml = '';
					if (page > 1) {
						pHtml += '<button type="button" class="hj-glossary-page-btn" data-gpage="' + (page - 1) + '">قبلی</button>';
					}
					for (var p = 1; p <= pages; p++) {
						if (p > 2 && p < pages - 1 && Math.abs(p - page) > 2) {
							if (Math.abs(p - page) === 3) {
								pHtml += '<span style="padding:0 4px;color:#64748b;">…</span>';
							}
							continue;
						}
						pHtml += '<button type="button" class="hj-glossary-page-btn' + (p === page ? ' is-active' : '') + '" data-gpage="' + p + '">' + toFaDigits(p) + '</button>';
					}
					if (page < pages) {
						pHtml += '<button type="button" class="hj-glossary-page-btn" data-gpage="' + (page + 1) + '">بعدی</button>';
					}
					pagEl.innerHTML = pHtml;
				}
			}
		}

		function fetchFiltered() {
			syncActiveControls();
			updateBrowserUrl();

			if (activeController && activeController.abort) {
				activeController.abort();
			}
			activeController = typeof AbortController !== 'undefined' ? new AbortController() : null;

			gridEl.classList.add('is-loading');

			var params = new URLSearchParams({
				action:  'hj_glossary_filter',
				gq:      state.gq,
				gcat:    state.gcat,
				gletter: state.gletter,
				gpage:   String(state.gpage)
			});

			fetch(ajaxUrl + '?' + params.toString(), activeController ? { signal: activeController.signal } : {})
				.then(function (r) { return r.json(); })
				.then(function (res) {
					gridEl.classList.remove('is-loading');
					if (res && res.success && res.data) {
						renderResults(res.data);
					}
				})
				.catch(function (err) {
					if (err && err.name === 'AbortError') {
						return;
					}
					gridEl.classList.remove('is-loading');
				});
		}

		if (searchInput) {
			searchInput.addEventListener('input', function () {
				var val = searchInput.value.trim();
				if (clearBtn) {
					clearBtn.classList.toggle('is-visible', val.length > 0);
				}
				clearTimeout(debounceTimer);
				debounceTimer = setTimeout(function () {
					state.gq = val;
					state.gpage = 1;
					fetchFiltered();
				}, 220);
			});
		}

		if (clearBtn && searchInput) {
			clearBtn.addEventListener('click', function () {
				searchInput.value = '';
				state.gq = '';
				state.gpage = 1;
				fetchFiltered();
				searchInput.focus();
			});
		}

		hub.addEventListener('click', function (e) {
			var catBtn = e.target.closest ? e.target.closest('[data-gcat]') : null;
			if (catBtn) {
				e.preventDefault();
				state.gcat = catBtn.getAttribute('data-gcat') || '';
				state.gpage = 1;
				fetchFiltered();
				return;
			}

			var jumpCat = e.target.closest ? e.target.closest('[data-gcat-jump]') : null;
			if (jumpCat) {
				e.preventDefault();
				state.gcat = jumpCat.getAttribute('data-gcat-jump') || '';
				state.gpage = 1;
				fetchFiltered();
				hub.scrollIntoView({ behavior: 'smooth', block: 'start' });
				return;
			}

			var letBtn = e.target.closest ? e.target.closest('[data-gletter]') : null;
			if (letBtn) {
				e.preventDefault();
				var clicked = letBtn.getAttribute('data-gletter') || '';
				state.gletter = (state.gletter === clicked) ? '' : clicked;
				state.gpage = 1;
				fetchFiltered();
				return;
			}

			var pageBtn = e.target.closest ? e.target.closest('[data-gpage]') : null;
			if (pageBtn) {
				e.preventDefault();
				state.gpage = parseInt(pageBtn.getAttribute('data-gpage') || '1', 10) || 1;
				fetchFiltered();
				hub.scrollIntoView({ behavior: 'smooth', block: 'start' });
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () {
			initArticleTooltips();
			initGlossaryDirectory();
		});
	} else {
		initArticleTooltips();
		initGlossaryDirectory();
	}
})();
