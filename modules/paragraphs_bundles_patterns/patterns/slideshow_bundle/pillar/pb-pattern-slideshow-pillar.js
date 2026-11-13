/**
 * @file
 * Pattern Pillar: tab names and the timer bar for the Slideshow.
 *
 * Each numbered button gets its slide's Slide Title as a visible name
 * (data-pb-pattern-label, printed by CSS) and an accessible name that starts
 * with that name, "Consulting, slide 1 of 4", so the visible name is part of
 * the accessible name (WCAG 2.5.3). The open tab's bar is a CSS animation
 * that this script restarts whenever PB restarts its own timer: a slide
 * change, leaving the slides with the pointer, resuming, and coming back into
 * view. It is held while PB's timer is stopped: paused, pointer over the
 * slides, off screen or in a hidden tab.
 *
 * PB's slideshow has no slide-changed event, so the script watches
 * `pb-is-active` with a MutationObserver, as Orbit dock does. It reads PB's
 * state and never changes it; PB's script is untouched. On detach every
 * attribute it wrote is restored.
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */

((Drupal, once) => {
  /** @type {WeakMap<HTMLElement, AbortController>} */
  const controllers = new WeakMap();

  /**
   * Pads a slide number to two digits.
   *
   * @param {number} number
   *   The slide number, from 1.
   *
   * @return {string}
   *   The number as "01".
   */
  const pad = (number) => String(number).padStart(2, '0');

  /**
   * Wires one Pillar slideshow.
   *
   * @param {HTMLElement} root
   *   The pattern root.
   */
  const wire = (root) => {
    const stage = root.querySelector('.pb-slideshow__stage');
    const nav = stage?.querySelector(':scope > .pb-slideshow__nav');
    const slidesBox = stage?.querySelector(':scope > .pb-slideshow__slides');
    if (!stage || !nav || !slidesBox) {
      return;
    }
    const buttons = Array.from(
      nav.querySelectorAll(':scope > .pb-slideshow__nav-btn'),
    );
    if (!buttons.length) {
      return;
    }
    const slides = Array.from(
      slidesBox.querySelectorAll(':scope > .pb-slideshow__slide'),
    );
    const controller = new AbortController();
    controllers.set(root, controller);
    const { signal } = controller;
    const total = buttons.length;

    // Names: the Slide Title, or the padded number when a slide has none.
    const names = buttons.map((button, index) => {
      const title = slides[index]?.querySelector(
        ':scope > .pb-slideshow__slide-inner > .pb-slideshow__title',
      );
      const name = title ? title.textContent.trim() : '';
      const original = button.getAttribute('aria-label');
      button.setAttribute('data-pb-pattern-num', pad(index + 1));
      button.setAttribute('data-pb-pattern-label', name || pad(index + 1));
      if (name) {
        button.setAttribute(
          'aria-label',
          Drupal.t('@name, slide @current of @total', {
            '@name': name,
            '@current': index + 1,
            '@total': total,
          }),
        );
      }
      signal.addEventListener('abort', () => {
        button.removeAttribute('data-pb-pattern-num');
        button.removeAttribute('data-pb-pattern-label');
        if (original === null) {
          button.removeAttribute('aria-label');
        } else {
          button.setAttribute('aria-label', original);
        }
      });
      return name || pad(index + 1);
    });

    const showCurrent = () => {
      const index = buttons.findIndex((button) =>
        button.classList.contains('pb-is-active'),
      );
      nav.setAttribute('data-pb-pattern-current', names[index] ?? names[0]);
    };

    const time = Number.parseInt(stage.dataset.slideTime, 10) || 0;
    root.style.setProperty('--pb-pattern-time', `${time}ms`);

    /** @type {Set<string>} */
    const holds = new Set();

    const restart = () => {
      if (!time) {
        return;
      }
      root.classList.remove('pb-pattern-is-running');
      root.getBoundingClientRect();
      root.classList.add('pb-pattern-is-running');
    };

    /**
     * Holds or releases the bar for one reason; the last release restarts it.
     *
     * @param {string} reason
     *   Why PB's timer is stopped.
     * @param {boolean} on
     *   TRUE to hold, FALSE to release.
     */
    const hold = (reason, on) => {
      const was = holds.size > 0;
      if (on) {
        holds.add(reason);
      } else {
        holds.delete(reason);
      }
      root.classList.toggle('pb-pattern-is-held', holds.size > 0);
      if (was && holds.size === 0) {
        restart();
      }
    };

    const slideObserver = new MutationObserver((records) => {
      if (records.some((record) => record.target.parentElement === slidesBox)) {
        showCurrent();
        restart();
      }
    });
    slideObserver.observe(slidesBox, {
      attributes: true,
      attributeFilter: ['class'],
      subtree: true,
    });
    signal.addEventListener('abort', () => slideObserver.disconnect());

    const play = nav.querySelector(':scope > .pb-nav__play');
    if (play) {
      const playObserver = new MutationObserver(() =>
        hold('paused', play.classList.contains('pb-is-paused')),
      );
      playObserver.observe(play, {
        attributes: true,
        attributeFilter: ['class'],
      });
      signal.addEventListener('abort', () => playObserver.disconnect());
      if (play.classList.contains('pb-is-paused')) {
        holds.add('paused');
      }
    }

    slidesBox.addEventListener('mouseenter', () => hold('pointer', true), {
      signal,
    });
    slidesBox.addEventListener('mouseleave', () => hold('pointer', false), {
      signal,
    });
    slidesBox.addEventListener(
      'touchend',
      () => {
        if (holds.size === 0) {
          restart();
        }
      },
      { signal },
    );

    // Matches PB's own visibility test for the stage (more than 20 percent).
    const io = new IntersectionObserver(
      (entries) => hold('offscreen', !(entries[0].intersectionRatio > 0.2)),
      { threshold: [0, 0.2, 0.5, 1] },
    );
    io.observe(stage);
    signal.addEventListener('abort', () => io.disconnect());

    document.addEventListener(
      'visibilitychange',
      () => hold('hidden', document.hidden),
      { signal },
    );

    signal.addEventListener('abort', () => {
      nav.removeAttribute('data-pb-pattern-current');
      root.style.removeProperty('--pb-pattern-time');
      root.classList.remove('pb-pattern-is-running', 'pb-pattern-is-held');
    });

    root.classList.toggle('pb-pattern-is-held', holds.size > 0);
    showCurrent();
    restart();
  };

  Drupal.behaviors.pbPatternSlideshowPillar = {
    attach(context) {
      once(
        'pb-pattern-slideshow-pillar',
        '.pb-pattern--slideshow-pillar',
        context,
      ).forEach(wire);
    },
    detach(context, settings, trigger) {
      if (trigger !== 'unload') {
        return;
      }
      once
        .remove(
          'pb-pattern-slideshow-pillar',
          '.pb-pattern--slideshow-pillar',
          context,
        )
        .forEach((root) => {
          controllers.get(root)?.abort();
          controllers.delete(root);
        });
    },
  };
})(Drupal, once);
