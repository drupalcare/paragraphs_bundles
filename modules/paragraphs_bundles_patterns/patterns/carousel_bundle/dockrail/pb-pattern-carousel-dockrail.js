/**
 * @file
 * Pattern Dockrail: page counter, and the ring around Pause.
 *
 * PB's carousel has no slide-changed event. The script reads PB's own state
 * (pb-is-active on the slides, the Pause button) and never writes it. The
 * ring's fill is a CSS animation; the class restarts it on a slide change.
 * Keyboard, dot and swipe moves do not restart PB's timer, so a double press
 * of Pause restarts it and the ring stays honest. PB's script is untouched.
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
   * Writes the page counter from PB's active slides.
   *
   * @param {HTMLElement} track
   *   The slides element.
   * @param {HTMLElement} counter
   *   The counter element.
   * @param {{perPage: number}} state
   *   Slides per page, kept across calls for a short last page.
   */
  const writeCounter = (track, counter, state) => {
    const slides = [...track.children].filter((el) =>
      el.classList.contains('pb-carousel__slide'),
    );
    const active = slides.filter((el) => el.classList.contains('pb-is-active'));
    if (!active.length) {
      return;
    }
    const first = slides.indexOf(active[0]);
    if (first === 0 || state.perPage === 0) {
      state.perPage = active.length;
    }
    const pages = Math.ceil(slides.length / state.perPage);
    const current = Math.min(Math.floor(first / state.perPage) + 1, pages);
    counter.textContent = `${current} / ${pages}`;
  };

  /**
   * Wires one Dockrail.
   *
   * @param {HTMLElement} root
   *   The pattern root.
   */
  const wire = (root) => {
    const track = root.querySelector('.pb-carousel__slides');
    const bar = root.querySelector('.pb-carousel__bottom-bar');
    const play = root.querySelector('.pb-carousel__play-pause');
    if (!track || !bar || !play) {
      return;
    }
    const controller = new AbortController();
    controllers.set(root, controller);
    const { signal } = controller;

    // Decorative copy: PB's announcer already tells screen readers the page.
    const counter = document.createElement('span');
    counter.className = 'pb-pattern-dockrail__counter';
    counter.setAttribute('aria-hidden', 'true');
    bar.appendChild(counter);

    root.style.setProperty(
      '--pb-pattern-time',
      `${Number.parseInt(track.dataset.slideTime, 10) || 0}ms`,
    );

    const gap = window.getComputedStyle(track).columnGap;
    if (gap && gap !== 'normal') {
      root.style.setProperty('--pb-pattern-gap', gap);
    }

    const state = { perPage: 0 };
    const observer = new MutationObserver((records) => {
      if (records.some((record) => record.target.parentElement === track)) {
        restartRing(root);
        writeCounter(track, counter, state);
      }
    });
    observer.observe(track, {
      attributes: true,
      attributeFilter: ['class'],
      subtree: true,
    });
    signal.addEventListener('abort', () => observer.disconnect());
    restartRing(root);
    writeCounter(track, counter, state);

    const viewport = root.querySelector('.pb-carousel__viewport');
    if (viewport) {
      const io = new IntersectionObserver(
        (entries) =>
          root.classList.toggle(
            'pb-pattern-is-held',
            !entries[0].isIntersecting,
          ),
        { threshold: 0.3 },
      );
      io.observe(viewport);
      signal.addEventListener('abort', () => io.disconnect());
    }

    document.addEventListener(
      'visibilitychange',
      () => root.classList.toggle('pb-pattern-is-held', document.hidden),
      { signal },
    );

    const activeIndex = () =>
      [...track.children].findIndex((el) =>
        el.classList.contains('pb-is-active'),
      );
    let before = null;
    const note = () => {
      before = activeIndex();
    };
    const settle = () => {
      setTimeout(() => {
        const moved = before !== null && activeIndex() !== before;
        before = null;
        if (
          moved &&
          !play.classList.contains('pb-is-paused') &&
          !root.querySelector('.pb-carousel__wrapper:hover')
        ) {
          play.click();
          play.click();
        }
      }, 0);
    };
    ['click', 'keydown', 'touchstart'].forEach((type) =>
      root.addEventListener(type, note, { capture: true, signal }),
    );
    ['click', 'keydown', 'touchend'].forEach((type) =>
      root.addEventListener(type, settle, { signal }),
    );

    // Keyboard focus inside the carousel holds PB's rotation until focus
    // leaves, so the line stops too. This mirrors PB's own rule in
    // paragraph-bundle-carousel.js: a real press of Play while focus is
    // inside lifts the hold until focus leaves; a synthetic click (the double
    // press above) does not.
    let focusInside = false;
    let playRequested = false;
    const syncFocusHold = () => {
      root.classList.toggle(
        'pb-pattern-is-focus-held',
        focusInside && !playRequested,
      );
    };
    root.addEventListener(
      'focusin',
      () => {
        if (!focusInside) {
          playRequested = false;
        }
        focusInside = true;
        syncFocusHold();
      },
      { signal },
    );
    root.addEventListener(
      'focusout',
      (event) => {
        if (event.relatedTarget && root.contains(event.relatedTarget)) {
          return;
        }
        focusInside = false;
        playRequested = false;
        syncFocusHold();
      },
      { signal },
    );
    play.addEventListener(
      'click',
      (event) => {
        const trusted = event.isTrusted;
        // Read PB's button after every click handler has run.
        setTimeout(() => {
          playRequested = trusted && !play.classList.contains('pb-is-paused');
          syncFocusHold();
        }, 0);
      },
      { signal },
    );

    signal.addEventListener('abort', () => {
      counter.remove();
      root.classList.remove(
        'pb-pattern-is-running',
        'pb-pattern-is-held',
        'pb-pattern-is-focus-held',
      );
    });
  };

  Drupal.behaviors.pbPatternCarouselDockrail = {
    attach(context) {
      once(
        'pb-pattern-carousel-dockrail',
        '.pb-pattern--carousel-dockrail',
        context,
      ).forEach(wire);
    },
    detach(context, settings, trigger) {
      if (trigger !== 'unload') {
        return;
      }
      once
        .remove(
          'pb-pattern-carousel-dockrail',
          '.pb-pattern--carousel-dockrail',
          context,
        )
        .forEach((root) => {
          controllers.get(root)?.abort();
          controllers.delete(root);
        });
    },
  };
})(Drupal, once);
