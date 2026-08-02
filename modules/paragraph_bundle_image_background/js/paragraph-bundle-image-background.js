/**
 * @file
 * Paragraph Bundle Image Background.
 *
 * Swaps large/small background images at a configured viewport breakpoint.
 *
 * Filename:     paragraph-bundle-image-background.js
 * Website:      https://www.flashwebcenter.com
 * Developer:    Alaa Haddad https://www.alaahaddad.com.
 */
// phpcs:disable Drupal.NamingConventions.UpperCaseConstant
// phpcs:disable Generic.PHP.UpperCaseConstant
// phpcs:disable Squiz.WhiteSpace.OperatorSpacing

((Drupal, once) => {
  'use strict';

  const PARAGRAPHS_BUNDLES = 'paragraphs-bundles';
  const PB_IMAGE_BACKGROUND = 'pb-image-background';

  const PARAGRAPHS_BUNDLES_PB_IMAGE_BACKGROUND =
    `.${PARAGRAPHS_BUNDLES}.${PB_IMAGE_BACKGROUND}`;

  const ONCE_PB_PARAGRAPH_BUNDLE_IMAGE_BACKGROUND =
    'pb-paragraph-bundle-image-background';

  /** @type {WeakMap<HTMLElement, AbortController>} */
  const imageBackgroundAbortControllers = new WeakMap();

  /**
   * @param {HTMLElement} paragraph
   */
  const applyBackgroundImage = (paragraph) => {
    const breakpoint = parseInt(paragraph.dataset.breakpoint || '0', 10);
    const imageBig = paragraph.dataset.imageBig;
    const imageSmall = paragraph.dataset.imageSmall || imageBig;

    if (!imageBig) {
      paragraph.style.backgroundImage = '';
      return;
    }

    if (window.innerWidth <= breakpoint && breakpoint !== 0) {
      paragraph.style.backgroundImage = `url(${imageSmall})`;
    }
    else {
      paragraph.style.backgroundImage = `url(${imageBig})`;
    }
  };

  /**
   * @param {HTMLElement} paragraph
   * @param {AbortSignal} signal
   */
  const initImageBackground = (paragraph, signal) => {
    applyBackgroundImage(paragraph);
    window.addEventListener('resize', () => {
      applyBackgroundImage(paragraph);
    }, { signal });
  };

  /**
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.paragraphBundleImageBackground = {
    attach(context) {
      once(
        ONCE_PB_PARAGRAPH_BUNDLE_IMAGE_BACKGROUND,
        PARAGRAPHS_BUNDLES_PB_IMAGE_BACKGROUND,
        context,
      ).forEach((paragraph) => {
        const abortController = new AbortController();
        imageBackgroundAbortControllers.set(paragraph, abortController);
        initImageBackground(paragraph, abortController.signal);
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

      once.remove(
        ONCE_PB_PARAGRAPH_BUNDLE_IMAGE_BACKGROUND,
        PARAGRAPHS_BUNDLES_PB_IMAGE_BACKGROUND,
        context,
      ).forEach((paragraph) => {
        const abortController = imageBackgroundAbortControllers.get(paragraph);
        if (abortController) {
          abortController.abort();
          imageBackgroundAbortControllers.delete(paragraph);
        }
        paragraph.style.backgroundImage = '';
      });
    },
  };
})(Drupal, once);
