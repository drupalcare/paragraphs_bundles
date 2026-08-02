/**
 * @file
 * Provides accordion functionality for paragraph bundle.
 *
 * This behavior handles expanding and collapsing accordion items,
 * ensures proper ARIA attributes for accessibility, and supports
 * multiple instances on the page.
 *
 * Follows Drupal 11 contrib best practices.
 *
 * Filename:     paragraph-bundle-accordion.js
 * Website:      https://www.flashwebcenter.com
 * Developer:    Alaa Haddad https://www.alaahaddad.com
 */
// phpcs:disable Drupal.NamingConventions.UpperCaseConstant
// phpcs:disable Generic.PHP.UpperCaseConstant
// phpcs:disable Squiz.WhiteSpace.OperatorSpacing

((Drupal, once) => {
  'use strict';

  const PARAGRAPHS_BUNDLES = 'paragraphs-bundles';
  const PB_ACCORDION = 'pb-accordion';
  const PB_ACCORDION__SECTIONS = 'pb-accordion__sections';
  const PB_ACCORDION__ITEM = 'pb-accordion__item';
  const PB_ACCORDION__BUTTON = 'pb-accordion__button';
  const PB_ACCORDION__PANE = 'pb-accordion__pane';
  const PB_ACCORDION__TOGGLE_ALL = 'pb-accordion__toggle-all';
  const PB_GRID__EXPAND_BUTTON = 'pb-grid__expand-button';
  const PB_ICON_TOGGLE = 'pb-icon-toggle';
  const PB_ICON_TOGGLE__PLUS = 'pb-icon-toggle__plus';
  const PB_ICON_TOGGLE__MINUS = 'pb-icon-toggle__minus';
  const PB_IS_ACTIVE = 'pb-is-active';
  const PB_IS_INACTIVE = 'pb-is-inactive';
  const PB_IS_EXPAND_ALL = 'pb-is-expand-all';
  const PB_IS_COLLAPSE_ALL = 'pb-is-collapse-all';
  const PB_U_OPEN_FIRST = 'pb-u-open-first';

  /** `.paragraphs-bundles.pb-accordion` */
  const PARAGRAPHS_BUNDLES_PB_ACCORDION =
    `.${PARAGRAPHS_BUNDLES}.${PB_ACCORDION}`;

  /** `.paragraphs-bundles.pb-accordion .pb-accordion__sections` */
  const PARAGRAPHS_BUNDLES_PB_ACCORDION_PB_ACCORDION__SECTIONS =
    `${PARAGRAPHS_BUNDLES_PB_ACCORDION} .${PB_ACCORDION__SECTIONS}`;

  /** Direct accordion items owned by one sections host. */
  const PB_ACCORDION__SECTIONS_OWNED_ITEM =
    `:scope > .${PB_ACCORDION__ITEM}`;

  /** Section toggle buttons owned by one sections host. */
  const PB_ACCORDION__SECTIONS_OWNED_BUTTON =
    `:scope > .${PB_ACCORDION__ITEM} > .${PB_ACCORDION__BUTTON}`;

  /** Expand/collapse-all control owned by one sections host. */
  const PB_ACCORDION__SECTIONS_OWNED_TOGGLE_ALL =
    `:scope > .${PB_GRID__EXPAND_BUTTON} .${PB_ACCORDION__TOGGLE_ALL}`;

  const ONCE_PB_PARAGRAPH_BUNDLE_ACCORDION_SECTIONS =
    'pb-paragraph-bundle-accordion-sections';
  const ONCE_PB_PARAGRAPH_BUNDLE_ACCORDION_TOGGLE =
    'pb-paragraph-bundle-accordion-toggle';
  const ONCE_PB_PARAGRAPH_BUNDLE_ACCORDION_EXPAND_ALL =
    'pb-paragraph-bundle-accordion-expand-all';

  /** @type {WeakMap<HTMLElement, AbortController>} */
  const widgetAbortControllers = new WeakMap();

  /**
   * Drupal behavior for accordion functionality.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.paragraphsAccordionBundle = {
    attach(context, settings) {
      const accordionContainers = once(
        ONCE_PB_PARAGRAPH_BUNDLE_ACCORDION_SECTIONS,
        PARAGRAPHS_BUNDLES_PB_ACCORDION_PB_ACCORDION__SECTIONS,
        context,
      );

      accordionContainers.forEach((accordionContainer) => {
        const abortController = new AbortController();
        widgetAbortControllers.set(accordionContainer, abortController);
        const { signal } = abortController;

        const paragraphsBundlesPbAccordion = accordionContainer.closest(
          PARAGRAPHS_BUNDLES_PB_ACCORDION,
        );
        if (!paragraphsBundlesPbAccordion) {
          return;
        }

        /**
         * Returns true when the item is a direct child of this widget root.
         *
         * @param {HTMLElement} item - Accordion item element.
         * @return {boolean}
         */
        const isOwnedItem = (item) => item.parentElement === accordionContainer;

        /**
         * Toggles the visibility of plus/minus icons.
         *
         * @param {HTMLElement|null} plus - The plus icon element.
         * @param {HTMLElement|null} minus - The minus icon element.
         * @param {boolean} isExpanded - Whether the accordion item is expanded.
         */
        const togglePlusMinus = (plus, minus, isExpanded) => {
          if (!plus || !minus) {
            return;
          }
          plus.setAttribute('aria-hidden', String(isExpanded));
          minus.setAttribute('aria-hidden', String(!isExpanded));
        };

        /**
         * Returns plus/minus icons owned by a section button.
         *
         * @param {HTMLElement|null} button - Accordion button element.
         * @return {{plus: HTMLElement|null, minus: HTMLElement|null}}
         *   Owned icon elements.
         */
        const getOwnedIcons = (button) => ({
          plus: button?.querySelector(
            `.${PB_ICON_TOGGLE} .${PB_ICON_TOGGLE__PLUS}`,
          ) ?? null,
          minus: button?.querySelector(
            `.${PB_ICON_TOGGLE} .${PB_ICON_TOGGLE__MINUS}`,
          ) ?? null,
        });

        /**
         * Opens a specific accordion item.
         *
         * @param {HTMLElement} button - The accordion button to activate.
         */
        const openAccordionItem = (button) => {
          const element = button.closest(`.${PB_ACCORDION__ITEM}`);
          if (
            !element
            || !isOwnedItem(element)
            || element.classList.contains(PB_IS_ACTIVE)
          ) {
            return;
          }

          element.classList.add(PB_IS_ACTIVE);
          element.classList.remove(PB_IS_INACTIVE);

          const toggleButton = element.querySelector(
            `:scope > .${PB_ACCORDION__BUTTON}`,
          );
          const contentPane = element.querySelector(
            `:scope > .${PB_ACCORDION__PANE}`,
          );
          const { plus, minus } = getOwnedIcons(toggleButton);

          toggleButton?.setAttribute('aria-expanded', 'true');
          contentPane?.setAttribute('aria-hidden', 'false');
          if (contentPane) {
            Drupal.paragraphBundleAccordion.slideDown(contentPane);
          }
          togglePlusMinus(plus, minus, true);

          button.scrollIntoView({ behavior: 'smooth', block: 'start' });

          const slug = button.getAttribute('data-fragment');
          if (slug) {
            history.replaceState(null, '', `#${slug}`);
          }
        };

        /**
         * Sets up toggle functionality for accordion buttons.
         */
        const toggleActiveState = () => {
          once(
            ONCE_PB_PARAGRAPH_BUNDLE_ACCORDION_TOGGLE,
            PB_ACCORDION__SECTIONS_OWNED_BUTTON,
            accordionContainer,
          ).forEach((button) => {
            button.addEventListener('click', () => {
              const element = button.closest(`.${PB_ACCORDION__ITEM}`);
              if (!element || !isOwnedItem(element)) {
                return;
              }

              const isActive = element.classList.contains(PB_IS_ACTIVE);
              element.classList.toggle(PB_IS_ACTIVE, !isActive);
              element.classList.toggle(PB_IS_INACTIVE, isActive);

              const toggleButton = element.querySelector(
                `:scope > .${PB_ACCORDION__BUTTON}`,
              );
              const contentPane = element.querySelector(
                `:scope > .${PB_ACCORDION__PANE}`,
              );
              const { plus, minus } = getOwnedIcons(toggleButton);

              toggleButton?.setAttribute('aria-expanded', String(!isActive));
              contentPane?.setAttribute('aria-hidden', String(isActive));

              if (contentPane) {
                if (!isActive) {
                  Drupal.paragraphBundleAccordion.slideDown(contentPane);
                } else {
                  Drupal.paragraphBundleAccordion.slideUp(contentPane);
                }
              }
              togglePlusMinus(plus, minus, !isActive);

              const slug = button.getAttribute('data-fragment');
              if (slug) {
                history.replaceState(null, '', `#${slug}`);
              }
            }, { signal });
          });
        };

        /**
         * Sets up expand/collapse all functionality.
         */
        const toggleExpandCollapseAll = () => {
          once(
            ONCE_PB_PARAGRAPH_BUNDLE_ACCORDION_EXPAND_ALL,
            PB_ACCORDION__SECTIONS_OWNED_TOGGLE_ALL,
            accordionContainer,
          ).forEach((button) => {
            button.addEventListener('click', () => {
              const wrapper = button.closest(`.${PB_GRID__EXPAND_BUTTON}`);
              if (!wrapper || wrapper.parentElement !== accordionContainer) {
                return;
              }

              const isExpanding = wrapper.classList.contains(PB_IS_EXPAND_ALL);
              const accordionItems = accordionContainer.querySelectorAll(
                PB_ACCORDION__SECTIONS_OWNED_ITEM,
              );

              accordionItems.forEach((item) => {
                const contentPane = item.querySelector(
                  `:scope > .${PB_ACCORDION__PANE}`,
                );
                const toggleButton = item.querySelector(
                  `:scope > .${PB_ACCORDION__BUTTON}`,
                );
                const { plus, minus } = getOwnedIcons(toggleButton);

                if (isExpanding && !item.classList.contains(PB_IS_ACTIVE)) {
                  item.classList.add(PB_IS_ACTIVE);
                  item.classList.remove(PB_IS_INACTIVE);
                  toggleButton?.setAttribute('aria-expanded', 'true');
                  contentPane?.setAttribute('aria-hidden', 'false');
                  if (contentPane) {
                    Drupal.paragraphBundleAccordion.slideDown(contentPane);
                  }
                  togglePlusMinus(plus, minus, true);
                }
                else if (
                  !isExpanding
                  && item.classList.contains(PB_IS_ACTIVE)
                ) {
                  item.classList.remove(PB_IS_ACTIVE);
                  item.classList.add(PB_IS_INACTIVE);
                  toggleButton?.setAttribute('aria-expanded', 'false');
                  contentPane?.setAttribute('aria-hidden', 'true');
                  if (contentPane) {
                    Drupal.paragraphBundleAccordion.slideUp(contentPane);
                  }
                  togglePlusMinus(plus, minus, false);
                }
              });

              wrapper.classList.toggle(PB_IS_EXPAND_ALL, !isExpanding);
              wrapper.classList.toggle(PB_IS_COLLAPSE_ALL, isExpanding);
              button.setAttribute('aria-expanded', String(isExpanding));
            }, { signal });
          });
        };

        toggleActiveState();
        toggleExpandCollapseAll();

        if (paragraphsBundlesPbAccordion.classList.contains(PB_U_OPEN_FIRST)) {
          const firstButton = accordionContainer.querySelector(
            PB_ACCORDION__SECTIONS_OWNED_BUTTON,
          );
          if (firstButton) {
            openAccordionItem(firstButton);
          }
        }
        else {
          accordionContainer
            .querySelectorAll(PB_ACCORDION__SECTIONS_OWNED_ITEM)
            .forEach((item) => {
              const initiallyOpenPane = item.querySelector(
                `:scope > .${PB_ACCORDION__PANE}[aria-hidden="false"]`,
              );
              if (initiallyOpenPane) {
                Drupal.paragraphBundleAccordion.slideDown(initiallyOpenPane);
              }
            });
        }

        if (window.location.hash) {
          const hash = decodeURIComponent(window.location.hash.slice(1));
          requestAnimationFrame(() => {
            const buttons = accordionContainer.querySelectorAll(
              PB_ACCORDION__SECTIONS_OWNED_BUTTON,
            );
            const button = Array.from(buttons).find(
              (candidate) => candidate.getAttribute('data-fragment') === hash,
            );
            if (button) {
              openAccordionItem(button);
            }
          });
        }
      });
    },

    detach(context, settings, trigger) {
      if (trigger !== 'unload') {
        return;
      }

      once.remove(
        ONCE_PB_PARAGRAPH_BUNDLE_ACCORDION_SECTIONS,
        PARAGRAPHS_BUNDLES_PB_ACCORDION_PB_ACCORDION__SECTIONS,
        context,
      ).forEach((accordionContainer) => {
        widgetAbortControllers.get(accordionContainer)?.abort();
        once.remove(
          ONCE_PB_PARAGRAPH_BUNDLE_ACCORDION_TOGGLE,
          PB_ACCORDION__SECTIONS_OWNED_BUTTON,
          accordionContainer,
        );
        once.remove(
          ONCE_PB_PARAGRAPH_BUNDLE_ACCORDION_EXPAND_ALL,
          PB_ACCORDION__SECTIONS_OWNED_TOGGLE_ALL,
          accordionContainer,
        );
      });
    },
  };
})(Drupal, once);
