/**
 * @file
 * Pattern Loupe for the Image: the magnifying glass.
 *
 * The lens is decorative: aria-hidden and without pointer events. It only
 * switches on when the displayed photo has enough pixels to enlarge.
 */

((Drupal, once) => {
  const FINE_POINTER = '(hover: hover) and (pointer: fine)';
  const MIN_RATIO = 1.4;
  const MAX_ZOOM = 3;

  /**
   * Sets up one pattern instance.
   *
   * @param {HTMLElement} root - The pattern's outer element.
   */
  const init = (root) => {
    const wrapper = root.querySelector('.pb-image__wrapper');
    const image = wrapper && wrapper.querySelector('img');
    if (!image) {
      return;
    }

    const lens = document.createElement('span');
    lens.className = 'pb-pattern-loupe';
    lens.setAttribute('aria-hidden', 'true');
    wrapper.append(lens);

    let active = false;

    /**
     * Tells whether the shown photo is large enough to enlarge.
     *
     * @return {boolean} Whether the lens may show.
     */
    const canMagnify = () => {
      const { width } = image.getBoundingClientRect();
      return (
        window.matchMedia(FINE_POINTER).matches &&
        image.complete &&
        !!image.currentSrc &&
        width > 0 &&
        image.naturalWidth / width >= MIN_RATIO
      );
    };

    /**
     * Moves the lens to the pointer and shows that part of the photo.
     *
     * @param {PointerEvent} event - The pointer event.
     */
    const place = (event) => {
      const box = image.getBoundingClientRect();
      const shell = wrapper.getBoundingClientRect();
      const x = event.clientX - box.left;
      const y = event.clientY - box.top;
      const inside = x >= 0 && y >= 0 && x <= box.width && y <= box.height;
      lens.classList.toggle('pb-is-active', inside);
      if (!inside) {
        return;
      }

      const size = lens.offsetWidth;
      const zoom = Math.min(image.naturalWidth / box.width, MAX_ZOOM);
      // The lens sits at the inline start, which is the right edge in RTL.
      const rtl = getComputedStyle(wrapper).direction === 'rtl';
      const origin = rtl ? shell.width - size : 0;
      const moveX = box.left - shell.left + x - size / 2 - origin;
      const moveY = box.top - shell.top + y - size / 2;

      lens.style.transform = `translate(${moveX}px, ${moveY}px)`;
      lens.style.backgroundSize = `${box.width * zoom}px ${box.height * zoom}px`;
      lens.style.backgroundPosition = `${size / 2 - x * zoom}px ${size / 2 - y * zoom}px`;
    };

    wrapper.addEventListener('pointerenter', (event) => {
      if (event.pointerType === 'touch' || !canMagnify()) {
        return;
      }
      active = true;
      lens.style.backgroundImage = `url("${image.currentSrc.replace(/["\\]/g, '\\$&')}")`;
      place(event);
    });

    wrapper.addEventListener('pointermove', (event) => {
      if (active) {
        place(event);
      }
    });

    wrapper.addEventListener('pointerleave', () => {
      active = false;
      lens.classList.remove('pb-is-active');
    });
  };

  /**
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.pbPatternImageLoupe = {
    attach(context) {
      once(
        'pb-pattern-image-loupe',
        '.paragraphs-bundles.pb-image.pb-pattern--image-loupe',
        context,
      ).forEach(init);
    },
  };
})(Drupal, once);
