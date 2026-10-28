/**
 * Accessible, progressively enhanced tabs for [data-hj-tabs].
 * Each tab points to an existing panel by aria-controls; no content is created.
 */
(function () {
	'use strict';

	function initTabs(list) {
		var tabs = Array.prototype.slice.call(list.querySelectorAll('[role="tab"][aria-controls]')).filter(function (tab) {
			return tab.closest('[role="tablist"]') === list && document.getElementById(tab.getAttribute('aria-controls'));
		});
		var enabled = tabs.filter(function (tab) {
			return !tab.disabled && tab.getAttribute('aria-disabled') !== 'true';
		});
		if (!enabled.length) return;

		function select(tab, moveFocus) {
			tabs.forEach(function (item) {
				var selected = item === tab;
				var panel = document.getElementById(item.getAttribute('aria-controls'));
				item.setAttribute('aria-selected', selected ? 'true' : 'false');
				item.setAttribute('tabindex', selected ? '0' : '-1');
				if (panel) {
					panel.hidden = !selected;
				}
			});
			if (moveFocus) tab.focus();
		}

		var initiallySelected = enabled.filter(function (tab) {
			return tab.getAttribute('aria-selected') === 'true';
		})[0] || enabled[0];
		select(initiallySelected, false);

		list.addEventListener('click', function (event) {
			var tab = event.target.closest('[role="tab"][aria-controls]');
			if (!tab || enabled.indexOf(tab) === -1) return;
			event.preventDefault();
			select(tab, false);
		});

		list.addEventListener('keydown', function (event) {
			var index = enabled.indexOf(event.target);
			if (index < 0) return;
			// Buttons activate natively; enhanced links and other tab elements
			// must also respond to Space (and non-native elements to Enter).
			if ((event.key === ' ' || event.key === 'Spacebar') && event.target.tagName !== 'BUTTON') {
				event.preventDefault();
				event.target.click();
				return;
			}
			if (event.key === 'Enter' && event.target.tagName !== 'BUTTON' && event.target.tagName !== 'A') {
				event.preventDefault();
				event.target.click();
				return;
			}
			var vertical = list.getAttribute('aria-orientation') === 'vertical';
			var rtl = window.getComputedStyle(list).direction === 'rtl';
			var next = index;
			if (event.key === 'Home') next = 0;
			else if (event.key === 'End') next = enabled.length - 1;
			else if (vertical && event.key === 'ArrowDown') next = (index + 1) % enabled.length;
			else if (vertical && event.key === 'ArrowUp') next = (index - 1 + enabled.length) % enabled.length;
			else if (!vertical && event.key === 'ArrowLeft') next = (index + (rtl ? 1 : -1) + enabled.length) % enabled.length;
			else if (!vertical && event.key === 'ArrowRight') next = (index + (rtl ? -1 : 1) + enabled.length) % enabled.length;
			else return;
			event.preventDefault();
			select(enabled[next], true);
		});
	}

	function init() {
		var lists = document.querySelectorAll('[data-hj-tabs][role="tablist"]');
		Array.prototype.forEach.call(lists, initTabs);
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
	else init();
})();
