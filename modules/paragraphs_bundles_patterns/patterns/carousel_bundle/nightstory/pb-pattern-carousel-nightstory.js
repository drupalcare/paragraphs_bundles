/**
 * @param Drupal
 * @param once
 * @file
 * Pattern Nightstory: timer value for the story bars, and the rising words.
 *
 * The bars are CSS animations restarted by PB's pb-is-active. Keyboard, dot
 * and swipe moves do not restart PB's timer, so a double press of Pause
 * restarts it and the bars stay honest. PB's script is untouched.
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */

((Drupal, once) => {
  /** @type {WeakMap<HTMLElement, AbortController>} */
  const controllers = new WeakMap();

  // Letters of right-to-left scripts, and the first letter of any script.
  const RTL_LETTER =
    /[\p{Script=Arabic}\p{Script=Hebrew}\p{Script=Syriac}\p{Script=Thaana}\p{Script=Nko}]/u;
  const FIRST_LETTER = /\p{L}/u;

  /**
   * Splits a title into word spans, keeping one readable copy for screen
   * readers. Running it twice changes nothing.
   *
   * @param {HTMLElement} title
   *   The title element.
   */
  const splitWords = (title) => {
    if (title.hasAttribute('data-pb-pattern-split')) {
      return;
    }
    title.setAttribute('data-pb-pattern-split', '');
    const all = title.textContent;
    // Word boxes run in the page's direction, so English on an Arabic page
    // printed back to front. The pieces follow the text's own direction.
    const first = all.match(FIRST_LETTER);
    if (first) {
      const textDir = RTL_LETTER.test(first[0]) ? 'rtl' : 'ltr';
      if (window.getComputedStyle(title).direction !== textDir) {
        title.setAttribute('dir', textDir);
      }
    }
    const walker = document.createTreeWalker(title, NodeFilter.SHOW_TEXT);
    const nodes = [];
    while (walker.nextNode()) {
      if (walker.currentNode.nodeValue.trim() !== '') {
        nodes.push(walker.currentNode);
      }
    }
    let index = 0;
    nodes.forEach((node) => {
      const fragment = document.createDocumentFragment();
      const hidden = document.createElement('span');
      hidden.className = 'visually-hidden';
      hidden.textContent = node.nodeValue;
      fragment.appendChild(hidden);
      node.nodeValue.split(/(\s+)/).forEach((part) => {
        if (part === '') {
          return;
        }
        if (/^\s+$/.test(part)) {
          const space = document.createElement('span');
          space.setAttribute('aria-hidden', 'true');
          space.textContent = ' ';
          fragment.appendChild(space);
          return;
        }
        const outer = document.createElement('span');
        outer.className = 'pb-pattern-word';
        outer.setAttribute('aria-hidden', 'true');
        outer.style.setProperty('--pb-pattern-i', String(index));
        index += 1;
        const inner = document.createElement('span');
        inner.textContent = part;
        outer.appendChild(inner);
        fragment.appendChild(outer);
      });
      node.replaceWith(fragment);
    });
  };

  /**
   * Wires one Nightstory.
   *
   * @param {HTMLElement} root
   *   The pattern root.
   */
  const wire = (root) => {
    root
      .querySelectorAll('.pb-carousel__slide .pb-content__title')
      .forEach(splitWords);

    const track = root.querySelector('.pb-carousel__slides');
    const play = root.querySelector('.pb-carousel__play-pause');
    if (!track || !play) {
      return;
    }
    const controller = new AbortController();
    controllers.set(root, controller);
    const { signal } = controller;

    root.style.setProperty(
      '--pb-pattern-time',
      `${Number.parseInt(track.dataset.slideTime, 10) || 0}ms`,
    );

    const gap = window.getComputedStyle(track).columnGap;
    if (gap && gap !== 'normal') {
      root.style.setProperty('--pb-pattern-gap', gap);
    }

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
      root.classList.remove('pb-pattern-is-held', 'pb-pattern-is-focus-held');
    });
  };

  Drupal.behaviors.pbPatternCarouselNightstory = {
    attach(context) {
      once(
        'pb-pattern-carousel-nightstory',
        '.pb-pattern--carousel-nightstory',
        context,
      ).forEach(wire);
    },
    detach(context, settings, trigger) {
      if (trigger !== 'unload') {
        return;
      }
      once
        .remove(
          'pb-pattern-carousel-nightstory',
          '.pb-pattern--carousel-nightstory',
          context,
        )
        .forEach((root) => {
          controllers.get(root)?.abort();
          controllers.delete(root);
        });
    },
  };
})(Drupal, once);
