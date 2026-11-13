/**
 * @file
 * Pattern Orbit dock: total slide count and the ring around Pause.
 *
 * PB's 3D carousel has no slide-changed event and restarts its timer on every
 * manual move. The script reads PB's own state and never writes it: the ring's
 * fill is a CSS animation, and the class restarts it when the active slide
 * changes. PB's script is untouched.
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */

((Drupal, once) => {
  /** @type {WeakMap<HTMLElement, AbortController>} */
  const controllers = new WeakMap();

  /**
   * Restarts the CSS animation that draws the ring.
   *
   * @param {HTMLElement} root
   *   The pattern root.
   */
  const restartRing = (root) => {
    root.classList.remove('pb-pattern-is-running');
    root.getBoundingClientRect();
    root.classList.add('pb-pattern-is-running');
  };

  /**
   * Wires one Orbit dock.
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
        restartRing(root);
      }
    });
    observer.observe(ring, {
      attributes: true,
      attributeFilter: ['class'],
      subtree: true,
    });
    signal.addEventListener('abort', () => observer.disconnect());
    restartRing(root);

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
    });
  };

  Drupal.behaviors.pbPattern3dCarouselOrbitDock = {
    attach(context) {
      once(
        'pb-pattern-3d-carousel-orbit-dock',
        '.pb-pattern--3d-carousel-orbit-dock',
        context,
      ).forEach(wire);
    },
    detach(context, settings, trigger) {
      if (trigger !== 'unload') {
        return;
      }
      once
        .remove(
          'pb-pattern-3d-carousel-orbit-dock',
          '.pb-pattern--3d-carousel-orbit-dock',
          context,
        )
        .forEach((root) => {
          controllers.get(root)?.abort();
          controllers.delete(root);
        });
    },
  };
})(Drupal, once);
