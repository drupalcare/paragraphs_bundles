/**
 * @file
 * Paragraph Bundle Tabs.
 *
 * Tab functionality with keyboard navigation, ARIA support, URL hash navigation,
 * and horizontal or vertical layouts.
 *
 * Follows Drupal 11 contrib best practices.
 *
 * Filename:     paragraph-bundle-tabs.js
 * Website:      https://www.flashwebcenter.com
 * Developer:    Alaa Haddad https://www.alaahaddad.com
 */
// phpcs:disable Drupal.NamingConventions.UpperCaseConstant
// phpcs:disable Generic.PHP.UpperCaseConstant
// phpcs:disable Squiz.WhiteSpace.OperatorSpacing

((Drupal, once) => {
  const PARAGRAPHS_BUNDLES = 'paragraphs-bundles';
  const PB_TABS = 'pb-tabs';
  const PB_TABS__TABSET = 'pb-tabs__tabset';
  const PB_TABS__TABLIST = 'pb-tabs__tablist';
  const PB_TABS__TAB = 'pb-tabs__tab';
  const PB_TABS__PANELS = 'pb-tabs__panels';
  const PB_TABS__PANEL = 'pb-tabs__panel';
  const PB_IS_ACTIVE = 'pb-is-active';

  /** `.paragraphs-bundles.pb-tabs` */
  const PARAGRAPHS_BUNDLES_PB_TABS = `.${PARAGRAPHS_BUNDLES}.${PB_TABS}`;

  /** `.paragraphs-bundles.pb-tabs .pb-tabs__tabset` */
  const PARAGRAPHS_BUNDLES_PB_TABS_PB_TABS__TABSET = `${PARAGRAPHS_BUNDLES_PB_TABS} .${PB_TABS__TABSET}`;

  /** Tab buttons owned by one tabset host. */
  const PB_TABS__TABSET_OWNED_TAB = `:scope > .${PB_TABS__TABLIST} > .${PB_TABS__TAB}`;

  /** Tab panels owned by one tabset host. */
  const PB_TABS__TABSET_OWNED_PANEL = `:scope > .${PB_TABS__PANELS} > .${PB_TABS__PANEL}`;

  const ONCE_PB_PARAGRAPH_BUNDLE_TABS_TABSET =
    'pb-paragraph-bundle-tabs-tabset';
  const ONCE_PB_PARAGRAPH_BUNDLE_TABS_TAB = 'pb-paragraph-bundle-tabs-tab';
  const ONCE_PB_PARAGRAPH_BUNDLE_TABS_GLOBAL_HASH =
    'pb-paragraph-bundle-tabs-global-hash';

  /** @type {WeakMap<HTMLElement, AbortController>} */
  const tabsetAbortControllers = new WeakMap();

  /** @type {AbortController|null} */
  let globalHashAbortController = null;

  /**
   * Drupal behavior for tabs functionality.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.paragraphBundleTabs = {
    attach(context, settings) {
      const tabsets = once(
        ONCE_PB_PARAGRAPH_BUNDLE_TABS_TABSET,
        PARAGRAPHS_BUNDLES_PB_TABS_PB_TABS__TABSET,
        context,
      );

      tabsets.forEach((tabset) => {
        const abortController = new AbortController();
        tabsetAbortControllers.set(tabset, abortController);
        const { signal } = abortController;

        const tabButtons = tabset.querySelectorAll(PB_TABS__TABSET_OWNED_TAB);
        const tabPanels = tabset.querySelectorAll(PB_TABS__TABSET_OWNED_PANEL);

        if (tabButtons.length === 0 || tabPanels.length === 0) {
          return;
        }

        /**
         * Hides all panels owned by this tabset.
         */
        const hideAllPanels = () => {
          tabPanels.forEach((panel) => {
            panel.style.display = 'none';
            panel.setAttribute('aria-hidden', 'true');
          });
        };

        /**
         * Clears active state from all owned tab buttons.
         */
        const removeActiveFromTabs = () => {
          tabButtons.forEach((button) => {
            button.classList.remove(PB_IS_ACTIVE);
            button.setAttribute('tabindex', '-1');
            button.setAttribute('aria-selected', 'false');
            button.setAttribute('aria-expanded', 'false');
          });
        };

        /**
         * Activates a tab and its corresponding panel.
         *
         * @param {HTMLButtonElement} button - Tab control to activate.
         */
        const activateTab = (button) => {
          if (button.parentElement?.parentElement !== tabset) {
            return;
          }

          const paneId = `${button.id}-pane`;
          const currentPanel = tabset.querySelector(`#${CSS.escape(paneId)}`);

          if (
            !currentPanel ||
            currentPanel.parentElement?.parentElement !== tabset
          ) {
            return;
          }

          hideAllPanels();
          removeActiveFromTabs();

          currentPanel.style.display = 'flex';
          currentPanel.setAttribute('aria-hidden', 'false');

          button.classList.add(PB_IS_ACTIVE);
          button.removeAttribute('tabindex');
          button.setAttribute('aria-selected', 'true');
          button.setAttribute('aria-expanded', 'true');
          button.focus();

          const slug = button.getAttribute('data-fragment') || button.id;
          window.history.replaceState(null, '', `#${slug}`);
        };

        /**
         * Handles keyboard navigation between owned tabs.
         *
         * @param {KeyboardEvent} event - Keyboard event.
         * @param {number} currentIndex - Index of the focused tab.
         */
        const handleKeyboardNavigation = (event, currentIndex) => {
          // Vertical tablists navigate with Up/Down, horizontal with Left/Right
          // (WAI-ARIA APG tabs pattern).
          const isVertical = tabset.classList.contains('pb-u-vertical-tabs');
          const nextKey = isVertical ? 'ArrowDown' : 'ArrowRight';
          const prevKey = isVertical ? 'ArrowUp' : 'ArrowLeft';
          let newIndex;

          switch (event.key) {
            case nextKey:
              newIndex = (currentIndex + 1) % tabButtons.length;
              break;

            case prevKey:
              newIndex =
                (currentIndex - 1 + tabButtons.length) % tabButtons.length;
              break;

            case 'Home':
              newIndex = 0;
              break;

            case 'End':
              newIndex = tabButtons.length - 1;
              break;

            default:
              return;
          }

          event.preventDefault();
          tabButtons.forEach((tabButton) => {
            tabButton.setAttribute('tabindex', '-1');
          });
          tabButtons[newIndex].setAttribute('tabindex', '0');
          tabButtons[newIndex].focus();
        };

        once(
          ONCE_PB_PARAGRAPH_BUNDLE_TABS_TAB,
          PB_TABS__TABSET_OWNED_TAB,
          tabset,
        ).forEach((button, index) => {
          button.addEventListener(
            'click',
            () => {
              activateTab(button);
            },
            { signal },
          );

          button.addEventListener(
            'keydown',
            (event) => {
              handleKeyboardNavigation(event, index);
            },
            { signal },
          );
        });

        const hash = window.location.hash.replace('#', '');
        if (hash) {
          const button = Array.from(tabButtons).find(
            (candidate) =>
              candidate.id === hash ||
              candidate.getAttribute('data-fragment') === hash,
          );
          if (button) {
            activateTab(button);
            return;
          }
        }

        if (tabPanels[0]) {
          tabPanels[0].style.display = 'flex';
          tabPanels[0].setAttribute('aria-hidden', 'false');
        }
      });

      // The selector must be an Element: once() resolves a Document to an empty
      // list, which would silently never bind this listener.
      once(
        ONCE_PB_PARAGRAPH_BUNDLE_TABS_GLOBAL_HASH,
        document.documentElement,
      ).forEach(() => {
        globalHashAbortController?.abort();
        globalHashAbortController = new AbortController();
        const { signal } = globalHashAbortController;

        document.addEventListener(
          'click',
          (event) => {
            const anchor = event.target.closest('a[href^="#"]');
            if (!anchor) {
              return;
            }

            const linkHash = anchor.getAttribute('href').slice(1);
            if (!linkHash) {
              return;
            }

            const scopedTabsets = document.querySelectorAll(
              PARAGRAPHS_BUNDLES_PB_TABS_PB_TABS__TABSET,
            );

            let tabActivated = false;

            scopedTabsets.forEach((tabset) => {
              const ownedTabs = tabset.querySelectorAll(
                PB_TABS__TABSET_OWNED_TAB,
              );
              const button = Array.from(ownedTabs).find(
                (candidate) =>
                  candidate.id === linkHash ||
                  candidate.getAttribute('data-fragment') === linkHash,
              );

              if (button) {
                event.preventDefault();
                button.click();
                const reduceMotion = window.matchMedia(
                  '(prefers-reduced-motion: reduce)',
                ).matches;
                button.scrollIntoView({
                  behavior: reduceMotion ? 'auto' : 'smooth',
                  block: 'start',
                });
                tabActivated = true;
              }
            });

            if (tabActivated) {
              window.history.replaceState(null, '', `#${linkHash}`);
            }
          },
          { signal },
        );
      });
    },

    detach(context, settings, trigger) {
      if (trigger !== 'unload') {
        return;
      }

      once
        .remove(
          ONCE_PB_PARAGRAPH_BUNDLE_TABS_TABSET,
          PARAGRAPHS_BUNDLES_PB_TABS_PB_TABS__TABSET,
          context,
        )
        .forEach((tabset) => {
          tabsetAbortControllers.get(tabset)?.abort();
          tabsetAbortControllers.delete(tabset);
          once.remove(
            ONCE_PB_PARAGRAPH_BUNDLE_TABS_TAB,
            PB_TABS__TABSET_OWNED_TAB,
            tabset,
          );
        });

      once.remove(
        ONCE_PB_PARAGRAPH_BUNDLE_TABS_GLOBAL_HASH,
        document.documentElement,
      );
      globalHashAbortController?.abort();
      globalHashAbortController = null;
    },
  };
})(Drupal, once);
