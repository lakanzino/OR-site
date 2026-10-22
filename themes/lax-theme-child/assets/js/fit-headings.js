/**
 * Single-line heading auto-fitter for Hamyar Jarahi (lax-theme-child 0.10.1).
 *
 * Ensures every heading across desktop and mobile stays on one line and
 * scales its font-size down smoothly only when needed so 100% of the heading
 * text fits inside its container box without "..." truncation or overflow.
 */
(function () {
	'use strict';

	var SELECTOR = [
		'.hj-home-hero__title',
		'.hj-home .hj-home-section__head h2',
		'.hj-journey-card h3',
		'.hj-surgery-card h3',
		'.hj-glossary-strip h3',
		'.hj-contact-band h2',
		'.hj-home-empty h3',
		'.hj-faq-list summary',
		'.hj-section-bar__title',
		'.orsite-card-article__title',
		'.orsite-article__title',
		'.orsite-archive__title',
		'.orsite-hero__title',
		'.orsite-article__content h1',
		'.orsite-article__content h2',
		'.orsite-article__content h3',
		'.orsite-article__content h4',
		'.orsite-related__title',
		'.hj-page-links__title',
		'.orsite-empty h1',
		'.orsite-empty h2',
		'.orsite-toc summary',
		'.widget-title',
		'.hj-footer__heading'
	].join(',');

	var MIN_PX = 8.5;
	var rafId = 0;
	var sharedRange = null;

	function measureInlineWidth(node) {
		if (!document.createRange) {
			return node.scrollWidth || 0;
		}
		try {
			if (!sharedRange) {
				sharedRange = document.createRange();
			}
			sharedRange.selectNodeContents(node);
			var rect = sharedRange.getBoundingClientRect();
			return rect ? rect.width : 0;
		} catch (e) {
			return node.scrollWidth || 0;
		}
	}

	function fitOne(el) {
		if (!el || el.nodeType !== 1) {
			return;
		}

		el.style.fontSize = '';
		el.removeAttribute('data-hj-fitted');

		var clientW = el.clientWidth;
		if (!clientW || clientW <= 0) {
			return;
		}

		var cs = window.getComputedStyle(el);
		var padL = parseFloat(cs.paddingLeft) || 0;
		var padR = parseFloat(cs.paddingRight) || 0;
		var baseSize = parseFloat(cs.fontSize) || 16;

		var innerBoxW = clientW - padL - padR;
		var availW = innerBoxW - 2;
		if (el.tagName === 'SUMMARY') {
			availW -= 22;
		}
		if (availW <= 16) {
			return;
		}

		var target = el;
		if (
			el.children &&
			el.children.length === 1 &&
			el.firstElementChild &&
			el.firstElementChild.tagName === 'A'
		) {
			target = el.firstElementChild;
		}

		var textW = measureInlineWidth(target);
		var overflowsScroll =
			el.scrollWidth > clientW + 1 ||
			(target !== el && target.scrollWidth > target.clientWidth + 1);

		var fitsNaturally =
			el.tagName === 'SUMMARY'
				? textW <= availW && !overflowsScroll
				: textW <= innerBoxW + 0.5 && !overflowsScroll;

		if (fitsNaturally) {
			el.setAttribute('data-hj-fitted', '1');
			return;
		}

		var effectiveTextW = Math.max(textW, el.scrollWidth - padL - padR);
		if (effectiveTextW <= 0) {
			return;
		}

		var ratio = availW / effectiveTextW;
		if (ratio >= 1) {
			ratio = 0.96;
		}

		var nextSize = Math.max(MIN_PX, Math.floor(baseSize * ratio * 20) / 20);
		el.style.fontSize = nextSize + 'px';

		var guard = 0;
		while (guard < 8 && nextSize > MIN_PX) {
			var curTextW = measureInlineWidth(target);
			var curOverflows =
				curTextW > availW ||
				el.scrollWidth > el.clientWidth + 1 ||
				(target !== el && target.scrollWidth > target.clientWidth + 1);
			if (!curOverflows) {
				break;
			}
			nextSize = Math.max(MIN_PX, Math.floor(nextSize * 0.95 * 20) / 20);
			el.style.fontSize = nextSize + 'px';
			guard++;
		}

		el.setAttribute('data-hj-fitted', '1');
	}

	function fitAll() {
		rafId = 0;
		var nodes = document.querySelectorAll(SELECTOR);
		for (var i = 0; i < nodes.length; i++) {
			fitOne(nodes[i]);
		}
	}

	function scheduleFit() {
		if (rafId) {
			return;
		}
		if (window.requestAnimationFrame) {
			rafId = window.requestAnimationFrame(fitAll);
		} else {
			rafId = window.setTimeout(fitAll, 50);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', fitAll);
	} else {
		fitAll();
	}

	window.addEventListener('load', scheduleFit);
	window.addEventListener('resize', scheduleFit, { passive: true });
	window.addEventListener('orientationchange', scheduleFit, { passive: true });

	document.addEventListener(
		'toggle',
		function () {
			scheduleFit();
		},
		true
	);

	if (document.fonts && document.fonts.ready && typeof document.fonts.ready.then === 'function') {
		document.fonts.ready.then(scheduleFit);
	}

	if (typeof window.ResizeObserver === 'function' && document.documentElement) {
		var lastDocW = document.documentElement.clientWidth;
		var ro = new window.ResizeObserver(function () {
			var w = document.documentElement.clientWidth;
			if (w !== lastDocW) {
				lastDocW = w;
				scheduleFit();
			}
		});
		ro.observe(document.documentElement);
	}
})();
