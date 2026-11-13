/**
 * @param Drupal
 * @param once
 * @file
 * Pattern Reel for the Image Grid: the story bars in the viewer.
 *
 * PB owns the viewer. This script adds to it and drives PB's own Previous and
 * Next buttons, so opening, closing, focus and the focus trap stay PB's. PB
 * writes the shown photo into the viewer as a copy of its section, so the
 * copy's paragraph class says which photo is showing.
 */

((Drupal, once) => {
  const SECTION = '.pb-image-grid-section';

  /**
   * Finds where the shown photo sits among the sections.
   *
   * @param {HTMLElement} content - PB's viewer content element.
   * @param {HTMLElement[]} sections - The sections in order.
   *
   * @return {number} The index, or -1 when nothing is showing.
   */
  const shownIndex = (content, sections) => {
    const shown = content.firstElementChild;
    const match = shown ? shown.className.match(/paragraph--id--\d+/) : null;
    return match
      ? sections.findIndex((section) => section.classList.contains(match[0]))
      : -1;
  };

  /**
   * Adds the "3 of 12" line to the viewer.
   *
   * @param {HTMLElement} lightbox - PB's viewer.
   * @param {number} total - How many photos there are.
   *
   * @return {function(number): void} Writes the current number.
   */
  const addCounter = (lightbox, total) => {
    const counter = document.createElement('p');
    counter.className = 'pb-pattern-counter';
    counter.setAttribute('aria-live', 'polite');
    lightbox.append(counter);
    return (index) => {
      counter.textContent =
        index < 0
          ? ''
          : Drupal.t('@current of @total', {
              '@current': index + 1,
              '@total': total,
            });
    };
  };

  /**
   * Shows one photo by pressing PB's own Next or Previous button.
   *
   * PB keeps its position private and has no jump, but stepping keeps PB's
   * position, focus and return-focus exactly right. Photos passed on the way
   * have their address blanked for the moment of the step so they are not
   * downloaded for nothing; the last step loads normally.
   *
   * @param {number} target - The index to show.
   * @param {HTMLElement} content - PB's viewer content element.
   * @param {HTMLElement[]} sections - The sections in order.
   * @param {HTMLElement} next - PB's Next button.
   * @param {HTMLElement} prev - PB's Previous button.
   */
  const jumpTo = (target, content, sections, next, prev) => {
    const current = shownIndex(content, sections);
    if (current < 0 || current === target) {
      return;
    }
    const total = sections.length;
    const forward = (target - current + total) % total;
    const goForward = forward <= total - forward;
    const steps = goForward ? forward : total - forward;
    const button = goForward ? next : prev;
    const urls = new Map();
    for (let step = 1; step < steps; step += 1) {
      const passed =
        sections[(current + (goForward ? step : -step) + total) % total];
      urls.set(passed, passed.getAttribute('data-full-image-url') || '');
      passed.setAttribute('data-full-image-url', '');
    }
    try {
      for (let step = 0; step < steps; step += 1) {
        button.click();
      }
    } finally {
      urls.forEach((url, passed) => {
        passed.setAttribute('data-full-image-url', url);
      });
    }
  };

  /**
   * Sets up one pattern instance.
   *
   * @param {HTMLElement} root - The pattern's outer element.
   */
  const init = (root) => {
    const wrapper = root.querySelector('.pb-image-grid__wrapper');
    const lightbox = wrapper && wrapper.querySelector('.pb-grid__lightbox');
    const content =
      lightbox && lightbox.querySelector('.pb-grid__lightbox-content');
    const next = lightbox && lightbox.querySelector('.pb-modal__next');
    const prev = lightbox && lightbox.querySelector('.pb-modal__prev');
    if (!content || !next || !prev) {
      return;
    }
    const sections = Array.from(
      wrapper.querySelectorAll(`:scope > ${SECTION}`),
    );
    if (sections.length < 2) {
      return;
    }

    const writeCounter = addCounter(lightbox, sections.length);

    const rail = document.createElement('ul');
    rail.className = 'pb-pattern-rail';
    rail.setAttribute('aria-label', Drupal.t('Photos'));
    const buttons = sections.map((section, index) => {
      const item = document.createElement('li');
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'control-btn';
      button.setAttribute(
        'aria-label',
        Drupal.t('Show photo @number of @total', {
          '@number': index + 1,
          '@total': sections.length,
        }),
      );
      button.addEventListener('click', () => {
        jumpTo(index, content, sections, next, prev);
      });
      item.append(button);
      rail.append(item);
      return button;
    });
    lightbox.append(rail);

    new MutationObserver(() => {
      const index = shownIndex(content, sections);
      writeCounter(index);
      buttons.forEach((button, position) => {
        if (position === index) {
          button.setAttribute('aria-current', 'true');
        } else {
          button.removeAttribute('aria-current');
        }
      });
    }).observe(content, { childList: true });
  };

  /**
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.pbPatternImageGridReel = {
    attach(context) {
      once(
        'pb-pattern-image-grid-reel',
        '.paragraphs-bundles.pb-image-grid.pb-pattern--image-grid-reel',
        context,
      ).forEach(init);
    },
  };
})(Drupal, once);
