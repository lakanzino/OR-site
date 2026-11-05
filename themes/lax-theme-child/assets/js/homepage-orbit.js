/* Fit only the requested one-line slogan; leave all other edited text alone. */
(() => {
  'use strict';
  const titles = document.querySelectorAll('.hj-static-home .hj-hero-title');
  titles.forEach((title) => {
    const slogan = title.querySelector('.hj-hero-slogan');
    if (!slogan) return;
    let lastWidth = 0;
    const fit = (force = false) => {
      const width = title.getBoundingClientRect().width;
      if (!width || (!force && Math.abs(width - lastWidth) < 0.5)) return;
      lastWidth = width;
      title.style.removeProperty('font-size');
      const styles = getComputedStyle(title);
      const base = parseFloat(styles.fontSize);
      const available = title.clientWidth - parseFloat(styles.paddingLeft) - parseFloat(styles.paddingRight) - 2;
      const needed = slogan.getBoundingClientRect().width;
      if (Number.isFinite(base) && needed > available && available > 0) {
        title.style.fontSize = `${(base * available / needed * 0.985).toFixed(2)}px`;
      }
    };
    fit(true);
    window.addEventListener('resize', () => fit(), { passive: true });
    if ('ResizeObserver' in window) new ResizeObserver(() => fit()).observe(title);
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(() => fit(true));
  });
})();
