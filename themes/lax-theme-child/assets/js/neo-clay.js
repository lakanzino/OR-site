/* Progressive enhancement: without JS, all three guide cards remain visible. */
document.querySelectorAll('.hj-neo .neo-section').forEach(function (section) {
  var list = section.querySelector('.neo-tabs');
  if (!list) return;
  var tabs = Array.from(list.querySelectorAll('a[href^="#"]'));
  var panels = tabs.map(function (tab) { return document.getElementById(tab.getAttribute('href').slice(1)); });
  if (!tabs.length || panels.some(function (panel) { return !panel || !section.contains(panel); })) return;
  list.setAttribute('role', 'tablist');
  function activate(index, focus) {
    tabs.forEach(function (tab, i) {
      var active = i === index;
      tab.setAttribute('aria-selected', String(active));
      tab.tabIndex = active ? 0 : -1;
      panels[i].hidden = !active;
    });
    if (focus) tabs[index].focus();
  }
  tabs.forEach(function (tab, i) {
    tab.id = 'neo-tab-' + i;
    tab.setAttribute('role', 'tab');
    tab.setAttribute('aria-controls', panels[i].id);
    panels[i].setAttribute('role', 'tabpanel');
    panels[i].setAttribute('aria-labelledby', tab.id);
    tab.addEventListener('click', function (event) { event.preventDefault(); activate(i, false); });
    tab.addEventListener('keydown', function (event) {
      var next;
      if (event.key === 'ArrowLeft') next = (i + 1) % tabs.length;
      else if (event.key === 'ArrowRight') next = (i + tabs.length - 1) % tabs.length;
      else if (event.key === 'Home') next = 0;
      else if (event.key === 'End') next = tabs.length - 1;
      else return;
      event.preventDefault(); activate(next, true);
    });
  });
  activate(0, false);
});
