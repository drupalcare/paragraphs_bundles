/**
 * @file
 * Paragraph Bundle Parallax.
 *
 * Parallax scrolling with breakpoint gating, reduced-motion respect, and
 * scroll-reveal visibility on section text.
 *
 * Filename:     paragraph-bundle-parallax.js
 * Website:      https://www.flashwebcenter.com
 * Developer:    Alaa Haddad https://www.alaahaddad.com
 */
// phpcs:disable Drupal.NamingConventions.UpperCaseConstant
// phpcs:disable Generic.PHP.UpperCaseConstant
// phpcs:disable Squiz.WhiteSpace.OperatorSpacing

((Drupal, once) => {
  const PARAGRAPHS_BUNDLES = 'paragraphs-bundles';
  const PB_PARALLAX_SECTION = 'pb-parallax-section';
  const PB_PARALLAX__BG = 'pb-parallax__bg';
  const PB_IS_VISIBLE = 'pb-is-visible';
  const PARALLAX_CSS = 'parallax-css';

  const PARAGRAPHS_BUNDLES_PB_PARALLAX_SECTION = `.${PARAGRAPHS_BUNDLES}.${PB_PARALLAX_SECTION}`;
  const PB_PARALLAX_SECTION_OWNED_INNER = ':scope > .pb-ui-inner';

  const ONCE_PB_PARAGRAPH_BUNDLE_PARALLAX = 'pb-paragraph-bundle-parallax';

  /** @type {Set<HTMLElement>} */
  const activeSections = new Set();

  /** @type {AbortController|null} */
  let globalController = null;

  /** @type {boolean} */
  let ticking = false;

  /**
   * @return {boolean}
   */
  const prefersReducedMotion = () =>
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /**
   * @param {HTMLElement} element
   *
   * @return {boolean}
   */
  const shouldRunParallax = (element) => {
    if (prefersReducedMotion()) {
      return false;
    }

    const breakpoint = element.getAttribute('data-breakpoint');
    if (!breakpoint || breakpoint === 'all') {
      return true;
    }

    const bp = parseInt(breakpoint, 10);
    if (Number.isFinite(bp)) {
      return window.innerWidth > bp;
    }

    return true;
  };

  /**
   * @param {HTMLElement} element
   *
   * @return {boolean}
   */
  const isInViewport = (element) => {
    const rect = element.getBoundingClientRect();
    const windowHeight =
      window.innerHeight || document.documentElement.clientHeight;

    return rect.bottom > 0 && rect.top < windowHeight;
  };

  /**
   * @param {HTMLElement} element
   */
  const updateVisibility = (element) => {
    const inner = element.querySelector(PB_PARALLAX_SECTION_OWNED_INNER);
    if (!(inner instanceof HTMLElement)) {
      return;
    }

    if (isInViewport(element)) {
      inner.classList.add(PB_IS_VISIBLE);
    } else {
      inner.classList.remove(PB_IS_VISIBLE);
    }
  };

  /**
   * @param {HTMLElement} element
   */
  const resetBackgroundStyles = (element) => {
    const bgEl = element.querySelector(`.${PB_PARALLAX__BG}`);
    if (!(bgEl instanceof HTMLElement)) {
      return;
    }

    bgEl.style.transform = '';
    bgEl.style.filter = '';
    bgEl.style.opacity = '';
  };

  /**
   * @param {HTMLElement} element
   */
  const updateParallax = (element) => {
    updateVisibility(element);

    if (element.classList.contains(PARALLAX_CSS)) {
      return;
    }

    if (!shouldRunParallax(element)) {
      resetBackgroundStyles(element);
      return;
    }

    const bgEl = element.querySelector(`.${PB_PARALLAX__BG}`);
    if (!(bgEl instanceof HTMLElement)) {
      return;
    }

    if (!isInViewport(element)) {
      resetBackgroundStyles(element);
      return;
    }

    const speed =
      parseFloat(element.getAttribute('data-parallax-speed') || '') || 0.6;
    const effect = element.getAttribute('data-parallax-effect') || 'translate';

    const scrollPosition = window.pageYOffset;
    const windowHeight = window.innerHeight;
    const elementRect = element.getBoundingClientRect();
    const elementTop = elementRect.top + scrollPosition;
    const offset =
      (scrollPosition + windowHeight - elementTop) * speed -
      windowHeight * speed;

    bgEl.style.transform = '';
    bgEl.style.filter = '';
    bgEl.style.opacity = '';

    switch (effect) {
      case 'translate':
        bgEl.style.transform = `translateY(${offset}px)`;
        break;

      case 'blur': {
        const blurAmount = Math.min(Math.abs(offset) / 100, 10);
        bgEl.style.filter = `blur(${blurAmount}px)`;
        break;
      }

      case 'fade':
      case 'opacity': {
        const opacity = Math.max(1 - Math.abs(offset) / 1000, 0);
        bgEl.style.opacity = String(opacity);
        break;
      }

      case 'scale': {
        const scaleAmount = Math.max(1 + Math.abs(offset) / 1000, 1);
        bgEl.style.transform = `scale(${scaleAmount})`;
        break;
      }

      case 'rotate': {
        const rotateAmount = Math.max(-8, Math.min(8, offset / 10));
        bgEl.style.transform = `rotate(${rotateAmount}deg)`;
        break;
      }

      case 'legacy':
        break;

      default:
        bgEl.style.transform = `translateY(${offset}px)`;
        break;
    }
  };

  /**
   * @return {void}
   */
  const processAllElements = () => {
    activeSections.forEach((element) => {
      updateParallax(element);
    });
  };

  /**
   * @return {void}
   */
  const handleScroll = () => {
    if (ticking) {
      return;
    }

    window.requestAnimationFrame(() => {
      processAllElements();
      ticking = false;
    });
    ticking = true;
  };

  /**
   * @return {void}
   */
  const ensureGlobalListeners = () => {
    if (globalController) {
      return;
    }

    globalController = new AbortController();
    const { signal } = globalController;
    window.addEventListener('scroll', handleScroll, { passive: true, signal });
    window.addEventListener('resize', handleScroll, { passive: true, signal });
  };

  /**
   * @return {void}
   */
  const teardownGlobalListeners = () => {
    if (activeSections.size > 0) {
      return;
    }

    globalController?.abort();
    globalController = null;
    ticking = false;
  };

  /**
   * @param {HTMLElement} element
   */
  const registerSection = (element) => {
    activeSections.add(element);
    ensureGlobalListeners();
    updateParallax(element);
  };

  /**
   * @param {HTMLElement} element
   */
  const unregisterSection = (element) => {
    activeSections.delete(element);
    resetBackgroundStyles(element);
    const inner = element.querySelector(PB_PARALLAX_SECTION_OWNED_INNER);
    if (inner instanceof HTMLElement) {
      inner.classList.remove(PB_IS_VISIBLE);
    }
    teardownGlobalListeners();
  };

  /**
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.paragraphBundleParallax = {
    attach(context) {
      once(
        ONCE_PB_PARAGRAPH_BUNDLE_PARALLAX,
        PARAGRAPHS_BUNDLES_PB_PARALLAX_SECTION,
        context,
      ).forEach((element) => {
        registerSection(element);
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
          ONCE_PB_PARAGRAPH_BUNDLE_PARALLAX,
          PARAGRAPHS_BUNDLES_PB_PARALLAX_SECTION,
          context,
        )
        .forEach((element) => {
          unregisterSection(element);
        });
    },
  };
})(Drupal, once);
