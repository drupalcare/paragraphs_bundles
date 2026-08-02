/**
 * @file
 * Paragraph Bundle Icon.
 *
 * Reveals GMS icon wrappers after font files load.
 *
 * Filename:     paragraph-bundle-icon.js
 * Website:      https://www.flashwebcenter.com
 * Developer:    Alaa Haddad https://www.alaahaddad.com.
 */
// phpcs:disable Drupal.NamingConventions.UpperCaseConstant
// phpcs:disable Generic.PHP.UpperCaseConstant
// phpcs:disable Squiz.WhiteSpace.OperatorSpacing

((Drupal, once) => {
  'use strict';

  const PARAGRAPHS_BUNDLES = 'paragraphs-bundles';
  const PB_ICON = 'pb-icon';
  const PARAGRAPHS_BUNDLES_PB_ICON = `.${PARAGRAPHS_BUNDLES}.${PB_ICON}`;
  const ONCE_PB_PARAGRAPH_BUNDLE_ICON = 'pb-paragraph-bundle-icon';
  const MATERIAL_ICONS_HIDDEN = 'material-icons-hidden';
  const ICON_WRAPPER = 'icon-wrapper';

  /** @type {WeakMap<HTMLElement, AbortController>} */
  const iconAbortControllers = new WeakMap();

  /**
   * @param {HTMLElement} paragraph
   */
  const fadeInIcons = (paragraph) => {
    paragraph.querySelectorAll(`.${ICON_WRAPPER}.${MATERIAL_ICONS_HIDDEN}`).forEach(
      (iconWrapper) => {
        if (iconWrapper.closest(PARAGRAPHS_BUNDLES_PB_ICON) !== paragraph) {
          return;
        }
        iconWrapper.classList.remove(MATERIAL_ICONS_HIDDEN);
      },
    );
  };

  /**
   * @param {HTMLElement} paragraph
   * @param {AbortSignal} signal
   */
  const initIconFonts = (paragraph, signal) => {
    const reveal = () => {
      if (!signal.aborted) {
        fadeInIcons(paragraph);
      }
    };

    if (document.fonts?.ready) {
      document.fonts.ready.then(reveal).catch(reveal);
      return;
    }

    const timeoutId = window.setTimeout(reveal, 500);
    signal.addEventListener('abort', () => {
      window.clearTimeout(timeoutId);
    }, { once: true });
  };

  /**
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.paragraphBundleIcon = {
    attach(context) {
      once(
        ONCE_PB_PARAGRAPH_BUNDLE_ICON,
        PARAGRAPHS_BUNDLES_PB_ICON,
        context,
      ).forEach((paragraph) => {
        const abortController = new AbortController();
        iconAbortControllers.set(paragraph, abortController);
        initIconFonts(paragraph, abortController.signal);
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
        ONCE_PB_PARAGRAPH_BUNDLE_ICON,
        PARAGRAPHS_BUNDLES_PB_ICON,
        context,
      ).forEach((paragraph) => {
        const abortController = iconAbortControllers.get(paragraph);
        if (abortController) {
          abortController.abort();
          iconAbortControllers.delete(paragraph);
        }
      });
    },
  };
})(Drupal, once);
