'use strict';

// Run with: node --test themes/lax-theme-child/tests/care-select.test.js
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const script = fs.readFileSync(path.join(__dirname, '../assets/js/care-select.js'), 'utf8');

class FakeEvent {
	constructor(type, props = {}) {
		this.type = type;
		Object.assign(this, props);
		this.defaultPrevented = false;
		this.stopped = false;
	}
	preventDefault() { this.defaultPrevented = true; }
	stopPropagation() { this.stopped = true; }
}

function fixture(options = {}) {
	const doc = { readyState: 'complete', listeners: {}, activeElement: null };
	class Element {
		constructor(tag) {
			this.tagName = tag.toUpperCase();
			this.children = [];
			this.attrs = {};
			this.listeners = {};
			this.parentNode = null;
			this._text = '';
			this.className = '';
			this.id = '';
			this.hidden = false;
			this.disabled = false;
			this.selectedIndex = 0;
			this.scrollTop = 0;
			this.clientHeight = 88;
			this.rect = { top: 300, bottom: 348 };
			this.classList = {
				contains: name => this.className.split(/\s+/).includes(name),
				toggle: (name, force) => {
					const present = this.classList.contains(name);
					const shouldAdd = force === undefined ? !present : force;
					const classes = this.className.split(/\s+/).filter(c => c && c !== name);
					if (shouldAdd) classes.push(name);
					this.className = classes.join(' ');
					return shouldAdd;
				},
				remove: name => this.classList.toggle(name, false)
			};
		}
		setAttribute(name, value) { this.attrs[name] = String(value); }
		getAttribute(name) { return this.attrs[name] ?? null; }
		removeAttribute(name) { delete this.attrs[name]; }
		appendChild(child) { child.parentNode = this; this.children.push(child); return child; }
		insertBefore(child, sibling) {
			child.parentNode = this;
			const index = this.children.indexOf(sibling);
			if (index < 0) this.children.push(child); else this.children.splice(index, 0, child);
		}
		get parentElement() { return this.parentNode instanceof Element ? this.parentNode : null; }
		get nextSibling() {
			if (!this.parentNode) return null;
			return this.parentNode.children[this.parentNode.children.indexOf(this) + 1] || null;
		}
		get options() { return this.children; }
		get textContent() { return this._text + this.children.map(child => child.textContent).join(''); }
		set textContent(value) { this._text = String(value); this.children = []; }
		get offsetTop() { return this.parentNode ? this.parentNode.children.indexOf(this) * 44 : 0; }
		get offsetHeight() { return 44; }
		getBoundingClientRect() { return this.rect; }
		addEventListener(type, fn) { (this.listeners[type] ||= []).push(fn); }
		dispatchEvent(event) {
			event.target ||= this;
			for (const listener of this.listeners[event.type] || []) listener(event);
			return !event.defaultPrevented;
		}
		focus() {
			doc.activeElement = this;
			doc.dispatchEvent(new FakeEvent('focusin', { target: this }));
		}
		contains(child) {
			while (child) { if (child === this) return true; child = child.parentNode; }
			return false;
		}
		closest(selector) {
			let current = this;
			while (current) {
				if (selector === '[role="option"]' && current.getAttribute('role') === 'option') return current;
				current = current.parentElement;
			}
			return null;
		}
	}
	doc.body = new Element('body');
	doc.createElement = tag => new Element(tag);
	doc.addEventListener = (type, fn) => { (doc.listeners[type] ||= []).push(fn); };
	doc.dispatchEvent = event => { for (const fn of doc.listeners[event.type] || []) fn(event); };
	function all(node = doc.body) { return [node, ...node.children.flatMap(child => all(child))]; }
	doc.getElementById = id => all().find(node => node.id === id) || null;
	doc.querySelector = selector => {
		const match = selector.match(/^label\[for="([^"]+)"\]$/);
		return match ? all().find(node => node.tagName === 'LABEL' && node.getAttribute('for') === match[1]) || null : null;
	};
	function field(id, names, disabled = []) {
		const container = new Element('div');
		const label = new Element('label');
		label.setAttribute('for', id);
		label.textContent = id === 'hj-care-stage' ? 'روز بعد از عمل' : 'نوع جراحی';
		const select = new Element('select');
		select.id = id;
		for (const [index, name] of names.entries()) {
			const choice = new Element('option');
			choice.textContent = name;
			choice.disabled = disabled.includes(index);
			select.appendChild(choice);
		}
		container.appendChild(label);
		container.appendChild(select);
		doc.body.appendChild(container);
		return { label, select, container };
	}
	const surgery = field('hj-care-surgery', ['جراحی معده', 'غیرفعال', 'عمل دوم', 'عمل سوم'], [1]);
	const stage = field('hj-care-stage', ['روزهای ۰ تا ۲', 'روزهای ۳ تا ۷']);
	if (!options.noScript) vm.runInNewContext(script, { document: doc, window: { innerHeight: 800 }, Event: FakeEvent, Date });
	function find(id) { return doc.getElementById(id); }
	function button(field) { return field.container.children.find(child => child.classList.contains('hj-care-select')).children[0]; }
	function fire(element, type, props = {}) {
		const event = new FakeEvent(type, { target: element, ...props });
		element.dispatchEvent(event);
		return event;
	}
	return { doc, surgery, stage, find, button, fire };
}

test('enhancement keeps the native selects as the value source and labels both comboboxes', () => {
	const f = fixture();
	for (const field of [f.surgery, f.stage]) {
		const button = f.button(field);
		assert.equal(field.select.hidden, true);
		assert.equal(button.getAttribute('role'), 'combobox');
		assert.equal(button.getAttribute('aria-expanded'), 'false');
		assert.equal(button.getAttribute('aria-labelledby'), field.label.id + ' ' + field.select.id + '-value');
		assert.equal(button.textContent, field.select.options[0].textContent);
		assert.equal(f.find(field.select.id + '-listbox').hidden, true);
	}
	const fallback = fixture({ noScript: true });
	assert.equal(fallback.surgery.select.hidden, false);
	assert.equal(fallback.surgery.container.children.length, 2);
});

test('keyboard navigation skips disabled entries and commits only on Enter', () => {
	const f = fixture();
	const button = f.button(f.surgery);
	const list = f.find('hj-care-surgery-listbox');
	let changes = 0;
	f.surgery.select.addEventListener('change', () => changes++);
	assert.equal(f.fire(button, 'keydown', { key: 'ArrowDown' }).defaultPrevented, true);
	assert.equal(list.hidden, false);
	assert.equal(button.getAttribute('aria-activedescendant'), 'hj-care-surgery-option-0');
	f.fire(button, 'keydown', { key: 'ArrowDown' });
	assert.equal(button.getAttribute('aria-activedescendant'), 'hj-care-surgery-option-2');
	assert.equal(f.surgery.select.selectedIndex, 0);
	f.fire(button, 'keydown', { key: 'End' });
	assert.equal(button.getAttribute('aria-activedescendant'), 'hj-care-surgery-option-3');
	f.fire(button, 'keydown', { key: 'Enter' });
	assert.equal(f.surgery.select.selectedIndex, 3);
	assert.equal(button.textContent, 'عمل سوم');
	assert.equal(changes, 1);
	assert.equal(button.getAttribute('aria-expanded'), 'false');
	assert.equal(f.doc.activeElement, button);
});

test('Escape cancels, Tab closes without trapping focus and label click focuses the control', () => {
	const f = fixture();
	const button = f.button(f.stage);
	f.fire(button, 'click');
	f.fire(button, 'keydown', { key: 'ArrowDown' });
	const escape = f.fire(button, 'keydown', { key: 'Escape' });
	assert.equal(escape.stopped, true);
	assert.equal(f.stage.select.selectedIndex, 0);
	assert.equal(button.getAttribute('aria-expanded'), 'false');
	f.fire(button, 'click');
	const tab = f.fire(button, 'keydown', { key: 'Tab' });
	assert.equal(tab.defaultPrevented, false);
	assert.equal(button.getAttribute('aria-expanded'), 'false');
	assert.equal(f.fire(f.stage.label, 'click').defaultPrevented, true);
	assert.equal(f.doc.activeElement, button);
});

test('pointer choice, outside clicks, external select changes and typeahead stay in sync', () => {
	const f = fixture();
	const button = f.button(f.surgery);
	const list = f.find('hj-care-surgery-listbox');
	f.fire(button, 'click');
	f.fire(list, 'click', { target: f.find('hj-care-surgery-option-2') });
	assert.equal(f.surgery.select.selectedIndex, 2);
	assert.equal(button.textContent, 'عمل دوم');
	f.surgery.select.selectedIndex = 3;
	f.fire(f.surgery.select, 'change');
	assert.equal(button.textContent, 'عمل سوم');
	f.fire(button, 'keydown', { key: 'ج' });
	assert.equal(button.getAttribute('aria-activedescendant'), 'hj-care-surgery-option-0');
	f.doc.dispatchEvent(new FakeEvent('pointerdown', { target: f.doc.body }));
	assert.equal(list.hidden, true);
	assert.equal(button.getAttribute('aria-expanded'), 'false');
});

test('the popup opens above the control when the bottom of the viewport is cramped', () => {
	const f = fixture();
	const button = f.button(f.surgery);
	button.rect = { top: 730, bottom: 778 };
	f.fire(button, 'click');
	assert.equal(button.parentNode.classList.contains('hj-care-select--above'), true);
	f.fire(button, 'click');
	assert.equal(button.parentNode.classList.contains('hj-care-select--above'), false);
});
