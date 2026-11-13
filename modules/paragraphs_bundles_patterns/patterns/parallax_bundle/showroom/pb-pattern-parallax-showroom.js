/**
 * @file
 * Pattern Showroom for the Parallax: writes the scroll values its CSS reads.
 *
 * Writes --pb-pattern-enter and --pb-pattern-progress (0 to 1) on each section.
 * It only reads positions and never touches PB's own parallax script.
 */

((Drupal, once) => {
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
  const clamp = (value) => String(Math.min(1, Math.max(0, value)).toFixed(3));

  /**
   * Starts the scroll values for one Pattern on the page.
   *
   * @param {HTMLElement} root
   *   The Pattern's root element.
   */
  const start = (root) => {
    const sections = root.querySelectorAll('.pb-parallax-section');
    const controller = new AbortController();
    let ticking = false;

    const update = () => {
      ticking = false;
      if (!root.isConnected) {
        controller.abort();
        return;
      }
      const windowHeight = window.innerHeight;
      sections.forEach((section) => {
        const rect = section.getBoundingClientRect();
        const enter = reduced.matches
          ? 1
          : (windowHeight - rect.top) / (windowHeight * 0.75);
        const progress = reduced.matches
          ? 1
          : (windowHeight - rect.top) / (windowHeight + rect.height);
        section.style.setProperty('--pb-pattern-enter', clamp(enter));
        section.style.setProperty('--pb-pattern-progress', clamp(progress));
      });
    };

    const queue = () => {
      if (!ticking) {
        ticking = true;
        window.requestAnimationFrame(update);
      }
    };

    const { signal } = controller;
    window.addEventListener('scroll', queue, { passive: true, signal });
    window.addEventListener('resize', queue, { passive: true, signal });
    update();
  };

  Drupal.behaviors.pbPatternParallaxShowroom = {
    attach(context) {
      once(
        'pb-pattern-parallax-showroom',
        '.pb-pattern--parallax-showroom',
        context,
      ).forEach(start);
    },
  };
})(Drupal, once);
