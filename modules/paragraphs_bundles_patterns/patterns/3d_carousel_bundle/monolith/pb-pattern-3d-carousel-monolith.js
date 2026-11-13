/**
 * @file
 * Pattern Monolith: total slide count and the draining fill on Pause.
 *
 * PB's 3D carousel has no slide-changed event and restarts its timer on every
 * move. The script reads PB's own state and never writes it, as Orbit dock
 * does: it writes the total for the "/ N" beside the big number, and restarts
 * the CSS animation that drains Pause each time the front card changes. PB's
 * script is untouched.
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */

((Drupal, once) => {
  /** @type {WeakMap<HTMLElement, AbortController>} */
  const controllers = new WeakMap();

  /**
   * Restarts the CSS animation that drains Pause.
   *
   * @param {HTMLElement} root
   *   The pattern root.
   */
  const restartDrain = (root) => {
    root.classList.remove('pb-pattern-is-running');
    root.getBoundingClientRect();
    root.classList.add('pb-pattern-is-running');
  };

  /**
   * Wires one Monolith.
   *
   * @param {HTMLElement} root
   *   The pattern root.
   */
  const wire = (root) => {
    const ring = root.querySelector('.pb-3d-carousel__ring');
    if (!ring) {
      return;
    }
    const controller = new AbortController();
    controllers.set(root, controller);
    const { signal } = controller;

    const slides = ring.querySelectorAll(':scope > .pb-3d-carousel__slide');
    const number = root.querySelector('.pb-slideshow__number');
    if (number) {
      number.setAttribute('data-pb-pattern-total', String(slides.length));
    }

    root.style.setProperty(
      '--pb-pattern-time',
      `${Number.parseInt(ring.dataset.rotationDelay, 10) || 0}ms`,
    );

    const observer = new MutationObserver((records) => {
      if (records.some((record) => record.target.parentElement === ring)) {
        restartDrain(root);
      }
    });
    observer.observe(ring, {
      attributes: true,
      attributeFilter: ['class'],
      subtree: true,
    });
    signal.addEventListener('abort', () => observer.disconnect());
    restartDrain(root);

    // Matches PB's own visibility test for the ring (20 percent).
    const io = new IntersectionObserver(
      (entries) =>
        root.classList.toggle('pb-pattern-is-held', !entries[0].isIntersecting),
      { threshold: 0.2 },
    );
    io.observe(ring);
    signal.addEventListener('abort', () => io.disconnect());

    document.addEventListener(
      'visibilitychange',
      () => root.classList.toggle('pb-pattern-is-held', document.hidden),
      { signal },
    );

    signal.addEventListener('abort', () => {
      root.classList.remove('pb-pattern-is-running', 'pb-pattern-is-held');
      root.style.removeProperty('--pb-pattern-time');
      number?.removeAttribute('data-pb-pattern-total');
    });
  };

  Drupal.behaviors.pbPattern3dCarouselMonolith = {
    attach(context) {
      once(
        'pb-pattern-3d-carousel-monolith',
        '.pb-pattern--3d-carousel-monolith',
        context,
      ).forEach(wire);
    },
    detach(context, settings, trigger) {
      if (trigger !== 'unload') {
        return;
      }
      once
        .remove(
          'pb-pattern-3d-carousel-monolith',
          '.pb-pattern--3d-carousel-monolith',
          context,
        )
        .forEach((root) => {
          controllers.get(root)?.abort();
          controllers.delete(root);
        });
    },
  };
})(Drupal, once);
