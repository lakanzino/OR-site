/* Homepage mobile typography: avoid short wrapped lines without editing text. */
(() => {
  'use strict';
  const root = document.querySelector('main.hj-static-home');
  if (!root || !window.matchMedia || !document.createRange) return;
  const mobile = window.matchMedia('(max-width: 767px)');
  const selector = 'h1, h2, h3, h4, p';
  const originals = new WeakMap();
  const changed = new Set();
  let timer;

  function restore(el) {
    const old = originals.get(el);
    if (!old) return;
    if (old.value) el.style.setProperty('font-size', old.value, old.priority);
    else el.style.removeProperty('font-size');
    delete el.dataset.hjMobileWrap;
    originals.delete(el);
    changed.delete(el);
  }
  function eligible(el) {
    if (el.closest('form, [contenteditable="true"], [aria-live], .screen-reader-text') || el.querySelector('br, input, button, select, textarea, svg, img')) return false;
    // Preserve deliberate editorial line breaks and independently sized blocks.
    if (Array.from(el.querySelectorAll('*')).some(child => {
      const display = getComputedStyle(child).display;
      return display === 'block' || display === 'flex' || display === 'grid';
    })) return false;
    return el.getClientRects().length && getComputedStyle(el).whiteSpace !== 'nowrap';
  }
  function words(el) {
    const walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT);
    const nodes = [];
    let text = '', node;
    while ((node = walker.nextNode())) {
      if (node.parentElement.closest('[aria-hidden="true"], .screen-reader-text')) continue;
      nodes.push({node, start:text.length, end:text.length + node.data.length});
      text += node.data;
    }
    const tokens = [];
    for (const match of text.matchAll(/\S+/gu)) {
      const start = match.index, end = start + match[0].length;
      const a = nodes.find(n => start >= n.start && start < n.end);
      const b = nodes.find(n => end > n.start && end <= n.end);
      if (a && b) tokens.push({a:a.node, aOffset:start-a.start, b:b.node, bOffset:end-b.start});
    }
    return tokens;
  }
  function lineCounts(tokens, fontSize) {
    const lines = [];
    const range = document.createRange();
    for (const token of tokens) {
      range.setStart(token.a, token.aOffset); range.setEnd(token.b, token.bOffset);
      const rect = Array.from(range.getClientRects()).find(r => r.width > 0 && r.height > 0);
      if (!rect) continue;
      const y = rect.top + rect.height / 2;
      let line = lines.find(item => Math.abs(item.y - y) < fontSize * .65);
      if (!line) { line = {y, count:0}; lines.push(line); }
      line.count++;
    }
    return lines.sort((a,b) => a.y-b.y).map(line => line.count);
  }
  function fix(el) {
    if (!eligible(el)) return;
    const tokens = words(el);
    if (tokens.length < 3 || tokens.length > 200) return;
    const size = parseFloat(getComputedStyle(el).fontSize);
    if (!Number.isFinite(size) || size <= 0) return;
    const short = counts => counts.length > 1 && counts.some(count => count < 3);
    if (!short(lineCounts(tokens, size))) { el.dataset.hjMobileWrap = 'balanced'; return; }
    const original = {value:el.style.getPropertyValue('font-size'), priority:el.style.getPropertyPriority('font-size')};
    const floor = Math.min(size, Math.max(el.matches('p') ? 14 : 16, size * .82));
    let success = false;
    // Use small steps to keep the largest readable font that satisfies the rule.
    for (let test = size - .5; test >= floor; test -= .5) {
      el.style.setProperty('font-size', `${test}px`);
      if (!short(lineCounts(tokens, test))) { success = true; break; }
    }
    if (success) {
      originals.set(el, original); changed.add(el); el.dataset.hjMobileWrap = 'adjusted';
    } else {
      if (original.value) el.style.setProperty('font-size', original.value, original.priority);
      else el.style.removeProperty('font-size');
      el.dataset.hjMobileWrap = 'unresolved';
    }
  }
  function run() {
    for (const el of Array.from(changed)) restore(el);
    root.querySelectorAll('[data-hj-mobile-wrap]').forEach(el => delete el.dataset.hjMobileWrap);
    if (!mobile.matches) return;
    root.querySelectorAll(selector).forEach(fix);
  }
  function schedule() { clearTimeout(timer); timer = setTimeout(run, 100); }
  new MutationObserver(schedule).observe(root, {childList:true, subtree:true, characterData:true});
  window.addEventListener('resize', schedule, {passive:true});
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(schedule);
  schedule();
})();
