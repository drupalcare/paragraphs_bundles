/**
 * @param Drupal
 * @param once
 * @file
 * Pattern Tide: visible control words and the photo drift for the Slideshow.
 *
 * PB prints the Previous and Next words inside core's `.visually-hidden`,
 * which is `position: absolute !important`, so CSS cannot bring them back on
 * screen. The script copies those translated words into
 * data-pb-pattern-label on each button and CSS prints them. When an editor
 * turns the visible Play and Pause words off, the script writes them with
 * Drupal.t() on PB's two icon spans so the control still reads as a word.
 *
 * It also measures the controls (Pause, Previous, Next) and writes their
 * width into --pb-pattern-tide-room, so on wide screens the caption keeps
 * clear of them on the same line. The drift runs only while PB's slideshow
 * plays on its own (a slide time and a Pause button exist), and is held
 * while it is paused or the tab is hidden, so moving content can always be
 * stopped (WCAG 2.2.2).
 *
 * The script reads PB's state and never changes it; PB's script is
 * untouched. On detach every attribute it wrote is removed.
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */

((Drupal, once) => {
  /** @type {WeakMap<HTMLElement, AbortController>} */
  const controllers = new WeakMap();

  /**
   * Writes an attribute and removes it again on abort.
   *
   * @param {HTMLElement} element
   *   The element.
   * @param {string} name
   *   The attribute name.
   * @param {string} value
   *   The value.
   * @param {AbortSignal} signal
   *   The wiring's abort signal.
   */
  const label = (element, name, value, signal) => {
    element.setAttribute(name, value);
    signal.addEventListener('abort', () => element.removeAttribute(name));
  };

  /**
   * Wires one Tide slideshow.
   *
   * @param {HTMLElement} root
   *   The pattern root.
   */
  const wire = (root) => {
    const stage = root.querySelector('.pb-slideshow__stage');
    if (!stage) {
      return;
    }
    const controller = new AbortController();
    controllers.set(root, controller);
    const { signal } = controller;

    const nav = stage.querySelector(':scope > .pb-slideshow__nav');
    const arrows = stage.querySelector(':scope > .pb-slideshow__arrows');

    // Previous and Next: PB's own translated words, made visible.
    arrows
      ?.querySelectorAll(':scope > :is(.pb-nav__prev, .pb-nav__next)')
      .forEach((button) => {
        const hidden = button.querySelector(':scope > .visually-hidden');
        const words = hidden ? hidden.textContent.trim() : '';
        if (words) {
          label(button, 'data-pb-pattern-label', words, signal);
        }
      });

    // Play and Pause: PB prints the words itself when the label option is
    // on; otherwise the words are new here and come from Drupal.t().
    const play = nav?.querySelector(':scope > .pb-nav__play') ?? null;
    if (play && !play.classList.contains('pb-nav__play--labelled')) {
      const playIcon = play.querySelector(':scope > .pb-nav__play-icon');
      const pauseIcon = play.querySelector(':scope > .pb-nav__pause-icon');
      if (playIcon) {
        label(playIcon, 'data-pb-pattern-label', Drupal.t('Play'), signal);
      }
      if (pauseIcon) {
        label(pauseIcon, 'data-pb-pattern-label', Drupal.t('Pause'), signal);
      }
    }

    // The room the controls take on the caption line.
    const measure = () => {
      const width =
        (nav ? nav.getBoundingClientRect().width : 0) +
        (arrows ? arrows.getBoundingClientRect().width : 0);
      root.style.setProperty('--pb-pattern-tide-room', `${Math.ceil(width)}px`);
      root.classList.add('pb-pattern-tide-is-measured');
    };
    if ('ResizeObserver' in window && (nav || arrows)) {
      const resize = new ResizeObserver(measure);
      [nav, arrows].forEach((element) => {
        if (element) {
          resize.observe(element);
        }
      });
      signal.addEventListener('abort', () => resize.disconnect());
      measure();
    }

    // The drift: only with autoplay, held while paused or hidden.
    const time = Number.parseInt(stage.dataset.slideTime, 10) || 0;
    if (time > 0 && play) {
      root.style.setProperty('--pb-pattern-time', `${time}ms`);
      root.classList.add('pb-pattern-tide-drifts');

      /** @type {Set<string>} */
      const holds = new Set();
      const hold = (reason, on) => {
        if (on) {
          holds.add(reason);
        } else {
          holds.delete(reason);
        }
        root.classList.toggle('pb-pattern-is-held', holds.size > 0);
      };

      const playObserver = new MutationObserver(() =>
        hold('paused', play.classList.contains('pb-is-paused')),
      );
      playObserver.observe(play, {
        attributes: true,
        attributeFilter: ['class'],
      });
      signal.addEventListener('abort', () => playObserver.disconnect());

      document.addEventListener(
        'visibilitychange',
        () => hold('hidden', document.hidden),
        { signal },
      );
      hold('paused', play.classList.contains('pb-is-paused'));
    }

    signal.addEventListener('abort', () => {
      root.style.removeProperty('--pb-pattern-tide-room');
      root.style.removeProperty('--pb-pattern-time');
      root.classList.remove(
        'pb-pattern-tide-is-measured',
        'pb-pattern-tide-drifts',
        'pb-pattern-is-held',
      );
    });
  };

  Drupal.behaviors.pbPatternSlideshowTide = {
    attach(context) {
      once(
        'pb-pattern-slideshow-tide',
        '.pb-pattern--slideshow-tide',
        context,
      ).forEach(wire);
    },
    detach(context, settings, trigger) {
      if (trigger !== 'unload') {
        return;
      }
      once
        .remove(
          'pb-pattern-slideshow-tide',
          '.pb-pattern--slideshow-tide',
          context,
        )
        .forEach((root) => {
          controllers.get(root)?.abort();
          controllers.delete(root);
        });
    },
  };
})(Drupal, once);
