/**
 * Article layout helpers for Hamyar Jarahi (lax-theme-child).
 *
 * On phones and tablets the table of contents is stacked above the article,
 * so it starts collapsed (the title bar stays tappable) to keep the body text
 * close to the top of the screen. On desktop the TOC stays expanded and the
 * CSS keeps it in the narrow column beside the article. Everything here is
 * progressive: without JavaScript the TOC simply stays open.
 */
(function () {
	'use strict';

	var MOBILE_QUERY = '(max-width: 991px)';
	var mql = window.matchMedia ? window.matchMedia(MOBILE_QUERY) : null;

	function isCompact() {
		return mql ? mql.matches : window.innerWidth <= 991;
	}

	function setupToc() {
		var toc = document.querySelector('details[data-orsite-toc]');
		if (!toc) {
			return;
		}

		// Expanded on desktop, collapsed on small screens.
		toc.open = !isCompact();

		// Tapping a TOC link jumps to the section and folds the list away
		// so the reader lands on the text, not on the menu.
		toc.addEventListener('click', function (event) {
			var link = event.target.closest ? event.target.closest('a') : null;
			if (link && isCompact()) {
				toc.open = false;
			}
		});

		if (mql) {
			var onChange = function () {
				toc.open = !isCompact();
			};
			if (typeof mql.addEventListener === 'function') {
				mql.addEventListener('change', onChange);
			} else if (typeof mql.addListener === 'function') {
				mql.addListener(onChange);
			}
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', setupToc);
	} else {
		setupToc();
	}
})();
