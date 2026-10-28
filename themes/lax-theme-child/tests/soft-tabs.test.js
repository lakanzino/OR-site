'use strict';

// Run with: node --test themes/lax-theme-child/tests/soft-tabs.test.js
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const script = fs.readFileSync(path.join(__dirname, '../assets/js/soft-tabs.js'), 'utf8');

function makeTabs(options = {}) {
	const panels = {};
	const document = {
		readyState: 'complete',
		activeElement: null,
		getElementById(id) { return panels[id] || null; },
		querySelectorAll() { return [list]; }
	};
	const list = {
		direction: options.direction || 'rtl',
		orientation: options.orientation || 'horizontal',
		listeners: {},
		getAttribute(name) { return name === 'aria-orientation' ? this.orientation : null; },
		querySelectorAll() { return tabs; },
		addEventListener(type, handler) { this.listeners[type] = handler; }
	};
	function tab(id, tagName, attrs = {}) {
		panels[id] = { hidden: false };
		return {
			tagName,
			disabled: !!attrs.disabled,
			attrs: { role: 'tab', 'aria-controls': id, ...attrs },
			getAttribute(name) { return this.attrs[name] ?? null; },
			setAttribute(name, value) { this.attrs[name] = String(value); },
			closest(selector) { return selector === '[role="tablist"]' ? list : this; },
			focus() { document.activeElement = this; },
			click() { list.listeners.click({ target: this, preventDefault() {} }); }
		};
	}
	const tabs = [
		tab('one', 'A', { 'aria-selected': 'true' }),
		tab('two', 'BUTTON', { disabled: true }),
		tab('three', 'A'),
		tab('four', 'DIV', { 'aria-disabled': 'true' })
	];
	vm.runInNewContext(script, { document, window: { getComputedStyle: () => ({ direction: list.direction }) } });
	function key(target, key) {
		let prevented = false;
		list.listeners.keydown({ target, key, preventDefault() { prevented = true; } });
		return prevented;
	}
	return { tabs, panels, document, key };
}

test('initial selection has one keyboard entry and only its panel is visible', () => {
	const { tabs, panels } = makeTabs();
	assert.deepEqual(tabs.map(t => t.getAttribute('tabindex')), ['0', '-1', '-1', '-1']);
	assert.deepEqual(Object.values(panels).map(p => p.hidden), [false, true, true, true]);
});

test('RTL arrows, Home and End skip disabled tabs and wrap focus', () => {
	const { tabs, panels, document, key } = makeTabs();
	assert.equal(key(tabs[0], 'ArrowLeft'), true);
	assert.equal(document.activeElement, tabs[2]);
	assert.equal(tabs[2].getAttribute('aria-selected'), 'true');
	assert.equal(panels.three.hidden, false);
	assert.equal(panels.one.hidden, true);
	key(tabs[2], 'ArrowLeft');
	assert.equal(document.activeElement, tabs[0]);
	key(tabs[0], 'End');
	assert.equal(document.activeElement, tabs[2]);
	key(tabs[2], 'Home');
	assert.equal(document.activeElement, tabs[0]);
});

test('LTR arrows and vertical arrows follow their respective directions', () => {
	const ltr = makeTabs({ direction: 'ltr' });
	ltr.key(ltr.tabs[0], 'ArrowRight');
	assert.equal(ltr.document.activeElement, ltr.tabs[2]);
	const vertical = makeTabs({ orientation: 'vertical' });
	assert.equal(vertical.key(vertical.tabs[0], 'ArrowLeft'), false);
	vertical.key(vertical.tabs[0], 'ArrowDown');
	assert.equal(vertical.document.activeElement, vertical.tabs[2]);
	vertical.key(vertical.tabs[2], 'ArrowUp');
	assert.equal(vertical.document.activeElement, vertical.tabs[0]);
});

test('Space activates a link tab; disabled tabs cannot be selected', () => {
	const { tabs, panels, key } = makeTabs();
	assert.equal(key(tabs[2], ' '), true);
	assert.equal(tabs[2].getAttribute('aria-selected'), 'true');
	assert.equal(panels.three.hidden, false);
	tabs[1].click();
	assert.equal(tabs[2].getAttribute('aria-selected'), 'true');
	assert.equal(tabs[1].getAttribute('aria-selected'), 'false');
});
