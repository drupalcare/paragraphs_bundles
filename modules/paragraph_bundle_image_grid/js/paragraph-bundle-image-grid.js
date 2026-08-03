/**
 * @file
 * Paragraph Bundle Image Grid.
 *
 * Lightbox gallery with focus trap, Escape dismiss, and grid item count class.
 *
 * Filename:     paragraph-bundle-image-grid.js
 * Website:      https://www.flashwebcenter.com
 * Developer:    Alaa Haddad https://www.alaahaddad.com
 */
// phpcs:disable Drupal.NamingConventions.UpperCaseConstant
// phpcs:disable Generic.PHP.UpperCaseConstant
// phpcs:disable Squiz.WhiteSpace.OperatorSpacing

((Drupal, once) => {
  const PARAGRAPHS_BUNDLES = 'paragraphs-bundles';
  const PB_IMAGE_GRID = 'pb-image-grid';
  const PB_IMAGE_GRID_SECTION = 'pb-image-grid-section';
  const PB_IMAGE_GRID__WRAPPER = 'pb-image-grid__wrapper';
  const PB_GRID__LIGHTBOX = 'pb-grid__lightbox';
  const PB_GRID__LIGHTBOX_CONTENT = 'pb-grid__lightbox-content';
  const PB_GRID__EXPAND = 'pb-grid__expand';
  const PB_GRID__CLOSE = 'pb-grid__close';
  const PB_MODAL__NEXT = 'pb-modal__next';
  const PB_MODAL__PREV = 'pb-modal__prev';

  const PARAGRAPHS_BUNDLES_PB_IMAGE_GRID_PB_IMAGE_GRID__WRAPPER = `.${PARAGRAPHS_BUNDLES}.${PB_IMAGE_GRID} .${PB_IMAGE_GRID__WRAPPER}`;
  const PARAGRAPHS_BUNDLES_PB_IMAGE_GRID_SECTION = `.${PARAGRAPHS_BUNDLES}.${PB_IMAGE_GRID_SECTION}`;
  const PB_IMAGE_GRID__WRAPPER_OWNED_PB_IMAGE_GRID_SECTION = `:scope > ${PARAGRAPHS_BUNDLES_PB_IMAGE_GRID_SECTION}`;

  const FOCUSABLE_SELECTOR = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled])',
    'textarea:not([disabled])',
    'select:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
  ].join(', ');

  const ONCE_PB_PARAGRAPH_BUNDLE_IMAGE_GRID_WRAPPER =
    'pb-paragraph-bundle-image-grid-wrapper';
  const ONCE_PB_PARAGRAPH_BUNDLE_IMAGE_GRID_EXPAND =
    'pb-paragraph-bundle-image-grid-expand';
  const ONCE_PB_PARAGRAPH_BUNDLE_IMAGE_GRID_NAV =
    'pb-paragraph-bundle-image-grid-nav';

  /** @type {WeakMap<HTMLElement, AbortController>} */
  const gridAbortControllers = new WeakMap();
  /** @type {WeakMap<HTMLElement, Map<HTMLElement, boolean>>} */
  const gridInertedElements = new WeakMap();

  /**
   * Reads a layout property so the browser flushes pending style changes.
   *
   * The read is the whole point and the value is discarded: without it the
   * opacity written next is coalesced with the one written before it and the
   * fade never runs.
   *
   * @param {HTMLElement} element - The element to measure.
   *
   * @return {number} The measured height, unused by callers.
   */
  const forceReflow = (element) => element.offsetHeight;

  /**
   * Inert page branches outside the open lightbox.
   *
   * @param {HTMLElement} dialog
   * @param {Map<HTMLElement, boolean>} inertedElements
   */
  const applyDialogInert = (dialog, inertedElements) => {
    const inertElement = (element) => {
      if (!inertedElements.has(element)) {
        inertedElements.set(element, element.inert);
      }
      element.inert = true;
    };

    let branch = dialog;

    while (branch.parentElement && branch.parentElement !== document.body) {
      const parent = branch.parentElement;
      // Bound per iteration so the callback closes over a constant rather
      // than over the reassigned loop variable.
      const current = branch;
      Array.from(parent.children).forEach((sibling) => {
        if (sibling !== current && sibling instanceof HTMLElement) {
          inertElement(sibling);
        }
      });
      branch = parent;
    }

    Array.from(document.body.children).forEach((child) => {
      if (child instanceof HTMLElement && !child.contains(dialog)) {
        inertElement(child);
      }
    });
  };

  /**
   * @param {Map<HTMLElement, boolean>} inertedElements
   */
  const removeDialogInert = (inertedElements) => {
    inertedElements.forEach((wasInert, element) => {
      element.inert = wasInert;
    });
    inertedElements.clear();
  };

  /**
   * @param {HTMLElement} wrapper
   * @param {HTMLElement} lightbox
   * @param {Map<HTMLElement, boolean>} [inertedElements]
   *
   * @return {void}
   */
  const closeLightbox = (wrapper, lightbox, inertedElements = null) => {
    lightbox.style.display = 'none';
    lightbox.setAttribute('aria-hidden', 'true');
    lightbox.removeAttribute('aria-modal');
    lightbox.inert = true;
    if (inertedElements) {
      removeDialogInert(inertedElements);
    }
    wrapper.querySelectorAll(`.${PB_GRID__EXPAND}`).forEach((button) => {
      button.setAttribute('aria-expanded', 'false');
    });
  };

  /**
   * @param {HTMLElement} wrapper
   */
  const initImageGridWrapper = (wrapper) => {
    const lightbox = wrapper.querySelector(`.${PB_GRID__LIGHTBOX}`);
    const modalContent = wrapper.querySelector(`.${PB_GRID__LIGHTBOX_CONTENT}`);
    if (
      !(lightbox instanceof HTMLElement) ||
      !(modalContent instanceof HTMLElement)
    ) {
      return;
    }

    lightbox.inert = true;

    let currentIndex = -1;
    /** @type {HTMLElement|null} */
    let lastFocusedElement = null;
    let items = Array.from(
      wrapper.querySelectorAll(
        PB_IMAGE_GRID__WRAPPER_OWNED_PB_IMAGE_GRID_SECTION,
      ),
    );
    const controller = new AbortController();
    const { signal } = controller;
    const inertedElements = new Map();
    gridAbortControllers.set(wrapper, controller);
    gridInertedElements.set(wrapper, inertedElements);

    /**
     * @return {void}
     */
    const updateItemCountClass = () => {
      items = Array.from(
        wrapper.querySelectorAll(
          PB_IMAGE_GRID__WRAPPER_OWNED_PB_IMAGE_GRID_SECTION,
        ),
      );
      const itemCount = items.length;
      wrapper.className = wrapper.className.replace(
        /\bpb-image-grid--count-\d+\b/g,
        '',
      );
      wrapper.classList.add(`pb-image-grid--count-${itemCount}`);
    };

    /**
     * @param {string} fullImageUrl
     *
     * @return {void}
     */
    const updateModalContent = (fullImageUrl) => {
      const clonedElement = items[currentIndex].cloneNode(true);
      if (!(clonedElement instanceof HTMLElement)) {
        return;
      }
      clonedElement.removeAttribute('id');
      clonedElement.querySelectorAll('[id]').forEach((element) => {
        element.removeAttribute('id');
      });
      clonedElement
        .querySelectorAll(`.${PB_GRID__EXPAND}`)
        .forEach((button) => {
          button.remove();
        });
      const imageElement = clonedElement.querySelector('img');
      if (imageElement instanceof HTMLImageElement) {
        imageElement.src = fullImageUrl;
        imageElement.removeAttribute('height');
        imageElement.removeAttribute('width');
      }
      modalContent.replaceChildren(clonedElement);
      forceReflow(modalContent);
      modalContent.style.opacity = '1';
      modalContent.classList.remove('pb-is-fade-in');
      requestAnimationFrame(() => modalContent.classList.add('pb-is-fade-in'));
    };

    /**
     * @param {Event} event
     *
     * @return {void}
     */
    const openLightbox = (event) => {
      const trigger = event.currentTarget;
      if (!(trigger instanceof HTMLElement)) {
        return;
      }

      const activeElement = document.activeElement;
      lastFocusedElement =
        activeElement instanceof HTMLElement ? activeElement : null;

      const section = trigger.closest(PARAGRAPHS_BUNDLES_PB_IMAGE_GRID_SECTION);
      if (!(section instanceof HTMLElement)) {
        return;
      }

      currentIndex = items.indexOf(section);
      if (currentIndex < 0) {
        return;
      }

      const fullImageUrl = section.getAttribute('data-full-image-url') || '';
      updateModalContent(fullImageUrl);
      trigger.setAttribute('aria-expanded', 'true');
      lightbox.setAttribute('aria-hidden', 'false');
      lightbox.setAttribute('aria-modal', 'true');
      lightbox.inert = false;
      lightbox.style.display = 'flex';
      applyDialogInert(lightbox, inertedElements);
      lightbox.focus();
      if (typeof Drupal.announce === 'function') {
        Drupal.announce(Drupal.t('Image grid lightbox opened'), 'polite');
      }
      event.stopPropagation();
    };

    /**
     * @param {Event} [event]
     *
     * @return {void}
     */
    const handleCloseLightbox = (event) => {
      closeLightbox(wrapper, lightbox, inertedElements);
      currentIndex = -1;
      if (lastFocusedElement) {
        lastFocusedElement.focus();
        lastFocusedElement = null;
      }
      if (typeof Drupal.announce === 'function') {
        Drupal.announce(Drupal.t('Image grid lightbox closed'), 'polite');
      }
      if (event) {
        event.stopPropagation();
      }
    };

    /**
     * @param {string} direction
     *
     * @return {void}
     */
    const navigateLightbox = (direction) => {
      if (items.length === 0) {
        return;
      }

      if (direction === 'next') {
        currentIndex = (currentIndex + 1) % items.length;
      } else {
        currentIndex = (currentIndex - 1 + items.length) % items.length;
      }

      const fullImageUrl =
        items[currentIndex].getAttribute('data-full-image-url') || '';
      updateModalContent(fullImageUrl);
    };

    /**
     * @param {KeyboardEvent} event
     *
     * @return {void}
     */
    const trapFocus = (event) => {
      if (lightbox.getAttribute('aria-hidden') === 'true') {
        return;
      }

      const focusableElements = lightbox.querySelectorAll(FOCUSABLE_SELECTOR);
      const firstElement = focusableElements[0];
      const lastElement = focusableElements[focusableElements.length - 1];

      if (event.key === 'Tab') {
        if (focusableElements.length === 0) {
          // Nothing tabbable inside the dialog: keep focus on the container
          // rather than dereferencing undefined first/last elements.
          event.preventDefault();
          lightbox.focus();
          return;
        }
        if (event.shiftKey && document.activeElement === firstElement) {
          lastElement.focus();
          event.preventDefault();
        } else if (!event.shiftKey && document.activeElement === lastElement) {
          firstElement.focus();
          event.preventDefault();
        }
      } else if (event.key === 'Escape') {
        handleCloseLightbox(event);
      }
    };

    once(
      ONCE_PB_PARAGRAPH_BUNDLE_IMAGE_GRID_EXPAND,
      `.${PB_GRID__EXPAND}`,
      wrapper,
    ).forEach((button) => {
      if (lightbox.id) {
        button.setAttribute('aria-controls', lightbox.id);
      }
      button.addEventListener('click', openLightbox, { signal });
    });

    once(
      ONCE_PB_PARAGRAPH_BUNDLE_IMAGE_GRID_NAV,
      `.${PB_MODAL__NEXT}, .${PB_MODAL__PREV}`,
      wrapper,
    ).forEach((button) => {
      button.addEventListener(
        'click',
        (event) => {
          const direction = button.classList.contains(PB_MODAL__NEXT)
            ? 'next'
            : 'prev';
          navigateLightbox(direction);
          event.stopPropagation();
        },
        { signal },
      );
    });

    wrapper.addEventListener(
      'click',
      (event) => {
        const target = event.target;
        if (!(target instanceof Element)) {
          return;
        }

        if (
          target.matches(`.${PB_GRID__CLOSE}`) ||
          target.closest(`.${PB_GRID__CLOSE}`) ||
          target === lightbox ||
          target === modalContent
        ) {
          handleCloseLightbox(event);
        }
      },
      { signal },
    );

    lightbox.addEventListener('keydown', trapFocus, { signal });

    updateItemCountClass();
  };

  /**
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.paragraphBundleImageGrid = {
    attach(context) {
      once(
        ONCE_PB_PARAGRAPH_BUNDLE_IMAGE_GRID_WRAPPER,
        PARAGRAPHS_BUNDLES_PB_IMAGE_GRID_PB_IMAGE_GRID__WRAPPER,
        context,
      ).forEach((wrapper) => {
        initImageGridWrapper(wrapper);
      });
    },

    /**
     * @param {Document|HTMLElement} context
     * @param {object} settings
     * @param {string} trigger
     */
    detach(context, settings, trigger) {
      if (trigger !== 'unload') {
        return;
      }

      once
        .remove(
          ONCE_PB_PARAGRAPH_BUNDLE_IMAGE_GRID_WRAPPER,
          PARAGRAPHS_BUNDLES_PB_IMAGE_GRID_PB_IMAGE_GRID__WRAPPER,
          context,
        )
        .forEach((wrapper) => {
          const controller = gridAbortControllers.get(wrapper);
          if (controller) {
            controller.abort();
            gridAbortControllers.delete(wrapper);
          }
          const inertedElements = gridInertedElements.get(wrapper);
          if (inertedElements) {
            gridInertedElements.delete(wrapper);
          }

          const lightbox = wrapper.querySelector(`.${PB_GRID__LIGHTBOX}`);
          if (lightbox instanceof HTMLElement) {
            closeLightbox(wrapper, lightbox, inertedElements);
          }
        });

      once.remove(
        ONCE_PB_PARAGRAPH_BUNDLE_IMAGE_GRID_EXPAND,
        `.${PB_GRID__EXPAND}`,
        context,
      );
      once.remove(
        ONCE_PB_PARAGRAPH_BUNDLE_IMAGE_GRID_NAV,
        `.${PB_MODAL__NEXT}, .${PB_MODAL__PREV}`,
        context,
      );
    },
  };
})(Drupal, once);
