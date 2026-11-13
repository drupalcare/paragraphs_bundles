/**
 * @file
 * Pattern Lantern: slides the counter knob along its track.
 *
 * PB's 3D carousel has no slide-changed event. The script watches the ring's
 * cards for `pb-is-active`, as Orbit dock does, and writes the position of
 * the front card as a fraction from 0 to 1 into --pb-pattern-pos on the
 * controls; CSS turns that into the knob's place on the track. The knob's
 * number and its accessible name stay PB's own. PB's script is untouched.
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */

((Drupal, once) => {
  /** @type {WeakMap<HTMLElement, AbortController>} */
  const controllers = new WeakMap();

  /**
   * Wires one Lantern.
   *
   * @param {HTMLElement} root
   *   The pattern root.
   */
  const wire = (root) => {
    const ring = root.querySelector('.pb-3d-carousel__ring');
    const controls = root.querySelector('.pb-3d-carousel__options-inner');
    if (!ring || !controls) {
      return;
    }
    const controller = new AbortController();
    controllers.set(root, controller);
    const { signal } = controller;

    const place = () => {
      const cells = Array.from(
        ring.querySelectorAll(':scope > .pb-3d-carousel__slide'),
      );
      const index = Math.max(
        0,
        cells.findIndex((cell) => cell.classList.contains('pb-is-active')),
      );
      const position = cells.length > 1 ? index / (cells.length - 1) : 0;
      controls.style.setProperty('--pb-pattern-pos', String(position));
    };

    const observer = new MutationObserver((records) => {
      if (records.some((record) => record.target.parentElement === ring)) {
        place();
      }
    });
    observer.observe(ring, {
      attributes: true,
      attributeFilter: ['class'],
      subtree: true,
    });

    signal.addEventListener('abort', () => {
      observer.disconnect();
      controls.style.removeProperty('--pb-pattern-pos');
    });
    place();
  };

  Drupal.behaviors.pbPattern3dCarouselLantern = {
    attach(context) {
      once(
        'pb-pattern-3d-carousel-lantern',
        '.pb-pattern--3d-carousel-lantern',
        context,
      ).forEach(wire);
    },
    detach(context, settings, trigger) {
      if (trigger !== 'unload') {
        return;
      }
      once
        .remove(
          'pb-pattern-3d-carousel-lantern',
          '.pb-pattern--3d-carousel-lantern',
          context,
        )
        .forEach((root) => {
          controllers.get(root)?.abort();
          controllers.delete(root);
        });
    },
  };
})(Drupal, once);
