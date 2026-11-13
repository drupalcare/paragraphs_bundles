/**
 * @param Drupal
 * @param once
 * @file
 * Pattern Mosaic for the Image Grid: the photo grows into the viewer.
 *
 * PB owns the viewer. This script only adds to it, so opening, closing, focus
 * and the focus trap stay PB's. PB writes the shown photo into the viewer as a
 * copy of its section, so the copy's paragraph class says which is showing.
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
   * Grows the viewer photo out of the tile it came from.
   *
   * @param {HTMLElement} content - PB's viewer content element.
   * @param {DOMRect} first - Where the tile's photo sits on screen.
   */
  const grow = (content, first) => {
    const image = content.querySelector('img');
    if (!image || first.width === 0 || typeof image.animate !== 'function') {
      return;
    }
    image
      .decode()
      .then(() => {
        const last = image.getBoundingClientRect();
        if (last.width === 0 || last.height === 0) {
          return;
        }
        const x = first.left - last.left;
        const y = first.top - last.top;
        const scaleX = first.width / last.width;
        const scaleY = first.height / last.height;
        image.animate(
          [
            {
              transformOrigin: '0 0',
              transform: `translate(${x}px, ${y}px) scale(${scaleX}, ${scaleY})`,
            },
            { transformOrigin: '0 0', transform: 'none' },
          ],
          { duration: 420, easing: 'cubic-bezier(0.22, 1, 0.36, 1)' },
        );
      })
      .catch(() => {
        // The photo could not be decoded: PB shows it without the grow.
      });
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
    if (!content) {
      return;
    }
    const sections = Array.from(
      wrapper.querySelectorAll(`:scope > ${SECTION}`),
    );
    if (sections.length < 2) {
      return;
    }

    const writeCounter = addCounter(lightbox, sections.length);

    // PB stops the click on the button, so this listens on the way down.
    wrapper.addEventListener(
      'click',
      (event) => {
        const trigger =
          event.target instanceof Element
            ? event.target.closest('.pb-grid__expand')
            : null;
        const photo = trigger
          ? trigger.closest(SECTION).querySelector('img')
          : null;
        if (
          !photo ||
          lightbox.getAttribute('aria-hidden') === 'false' ||
          window.matchMedia('(prefers-reduced-motion: reduce)').matches
        ) {
          return;
        }
        const first = photo.getBoundingClientRect();
        // PB opens the viewer in this same click; the frame after it is ready.
        requestAnimationFrame(() => grow(content, first));
      },
      true,
    );

    new MutationObserver(() => {
      writeCounter(shownIndex(content, sections));
    }).observe(content, { childList: true });
  };

  /**
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.pbPatternImageGridMosaic = {
    attach(context) {
      once(
        'pb-pattern-image-grid-mosaic',
        '.paragraphs-bundles.pb-image-grid.pb-pattern--image-grid-mosaic',
        context,
      ).forEach(init);
    },
  };
})(Drupal, once);
