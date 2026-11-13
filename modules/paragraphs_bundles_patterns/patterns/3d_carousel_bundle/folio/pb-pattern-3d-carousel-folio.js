/**
 * @file
 * Pattern Folio: the "Page 2 of 6" words under the ring.
 *
 * PB's 3D carousel prints only the current number in its counter and has no
 * slide-changed event. The script watches the ring's cards for
 * `pb-is-active`, as Lantern and Orbit dock do, and writes the translated
 * words (Drupal.t) into data-pb-pattern-page on PB's counter; CSS prints
 * them in place of the bare number. The counter's accessible name and live
 * announcement stay PB's own. PB's script is untouched.
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */

((Drupal, once) => {
  /** @type {WeakMap<HTMLElement, AbortController>} */
  const controllers = new WeakMap();

  /**
   * Wires one Folio.
   *
   * @param {HTMLElement} root
   *   The pattern root.
   */
  const wire = (root) => {
    const ring = root.querySelector('.pb-3d-carousel__ring');
    const number = root.querySelector(
      '.pb-3d-carousel__options-inner > .pb-slideshow__number',
    );
    if (!ring || !number) {
      return;
    }
    const controller = new AbortController();
    controllers.set(root, controller);
    const { signal } = controller;

    const write = () => {
      const cells = Array.from(
        ring.querySelectorAll(':scope > .pb-3d-carousel__slide'),
      );
      if (!cells.length) {
        return;
      }
      const index = Math.max(
        0,
        cells.findIndex((cell) => cell.classList.contains('pb-is-active')),
      );
      number.setAttribute(
        'data-pb-pattern-page',
        Drupal.t('Page @current of @total', {
          '@current': index + 1,
          '@total': cells.length,
        }),
      );
    };

    const observer = new MutationObserver((records) => {
      if (records.some((record) => record.target.parentElement === ring)) {
        write();
      }
    });
    observer.observe(ring, {
      attributes: true,
      attributeFilter: ['class'],
      subtree: true,
    });

    signal.addEventListener('abort', () => {
      observer.disconnect();
      number.removeAttribute('data-pb-pattern-page');
    });
    write();
  };

  Drupal.behaviors.pbPattern3dCarouselFolio = {
    attach(context) {
      once(
        'pb-pattern-3d-carousel-folio',
        '.pb-pattern--3d-carousel-folio',
        context,
      ).forEach(wire);
    },
    detach(context, settings, trigger) {
      if (trigger !== 'unload') {
        return;
      }
      once
        .remove(
          'pb-pattern-3d-carousel-folio',
          '.pb-pattern--3d-carousel-folio',
          context,
        )
        .forEach((root) => {
          controllers.get(root)?.abort();
          controllers.delete(root);
        });
    },
  };
})(Drupal, once);
