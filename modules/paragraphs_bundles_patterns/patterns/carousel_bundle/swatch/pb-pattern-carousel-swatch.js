/**
 * @file
 * Pattern Swatch: magnetic arrows and the ring around Pause.
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
  const fine = window.matchMedia('(hover: hover) and (pointer: fine)');
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');

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
   * Wires the arrows of one Swatch: they lean towards the pointer.
   *
   * @param {HTMLElement} root
   *   The pattern root.
   * @param {AbortSignal} signal
   *   Stops the listeners.
   */
  const wireArrows = (root, signal) => {
    root
      .querySelectorAll('.pb-carousel__options-bar .pb-ui-btn')
      .forEach((button) => {
        const reset = () => {
          button.style.setProperty('--pb-pattern-mx', '0px');
          button.style.setProperty('--pb-pattern-my', '0px');
        };
        button.addEventListener(
          'pointermove',
          (event) => {
            if (reduced.matches || !fine.matches) {
              return;
            }
            const box = button.getBoundingClientRect();
            const x =
              (event.clientX - box.left - box.width / 2) / (box.width / 2);
            const y =
              (event.clientY - box.top - box.height / 2) / (box.height / 2);
            button.style.setProperty(
              '--pb-pattern-mx',
              `${(x * 8).toFixed(1)}px`,
            );
            button.style.setProperty(
              '--pb-pattern-my',
              `${(y * 8).toFixed(1)}px`,
            );
          },
          { signal },
        );
        button.addEventListener('pointerleave', reset, { signal });
      });
  };

  /**
   * Wires one Swatch.
   *
   * @param {HTMLElement} root
   *   The pattern root.
   */
  const wire = (root) => {
    const controller = new AbortController();
    controllers.set(root, controller);
    const { signal } = controller;

    wireArrows(root, signal);

    const track = root.querySelector('.pb-carousel__slides');
    const play = root.querySelector('.pb-carousel__play-pause');
    if (!track || !play) {
      return;
    }

    root.style.setProperty(
      '--pb-pattern-time',
      `${Number.parseInt(track.dataset.slideTime, 10) || 0}ms`,
    );

    const gap = window.getComputedStyle(track).columnGap;
    if (gap && gap !== 'normal') {
      root.style.setProperty('--pb-pattern-gap', gap);
    }

    const observer = new MutationObserver((records) => {
      if (records.some((record) => record.target.parentElement === track)) {
        restartRing(root);
      }
    });
    observer.observe(track, {
      attributes: true,
      attributeFilter: ['class'],
      subtree: true,
    });
    signal.addEventListener('abort', () => observer.disconnect());
    restartRing(root);

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
      root.classList.remove(
        'pb-pattern-is-running',
        'pb-pattern-is-held',
        'pb-pattern-is-focus-held',
      );
    });
  };

  Drupal.behaviors.pbPatternCarouselSwatch = {
    attach(context) {
      once(
        'pb-pattern-carousel-swatch',
        '.pb-pattern--carousel-swatch',
        context,
      ).forEach(wire);
    },
    detach(context, settings, trigger) {
      if (trigger !== 'unload') {
        return;
      }
      once
        .remove(
          'pb-pattern-carousel-swatch',
          '.pb-pattern--carousel-swatch',
          context,
        )
        .forEach((root) => {
          controllers.get(root)?.abort();
          controllers.delete(root);
        });
    },
  };
})(Drupal, once);
