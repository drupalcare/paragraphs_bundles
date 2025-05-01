/**
 * @file
 * Provides accordion functionality for paragraph bundle.
 *
 * This behavior handles expanding and collapsing accordion items,
 * ensures proper ARIA attributes for accessibility, and supports
 * multiple instances on the page.
 *
 * This follows Drupal best practices for behaviors in contrib modules.
 *
 * Filename:     paragraph-bundle-accordion.js
 * Website:      https://www.flashwebcenter.com
 * Developer:    Alaa Haddad https://www.alaahaddad.com.
 */
((Drupal, drupalSettings, once) => {
  'use strict';

  Drupal.behaviors.paragraphsAccordionBundle = {
    attach(context) {
      const accordionContainers = once('paragraphsAccordionBundle', '.pb__accor', context);

      accordionContainers.forEach((accordionContainer) => {

        /**
         * Helper to get accordion elements within a specific container.
         */
        const getElementsById = (id) => {
          const wrapper = accordionContainer.querySelectorAll(`#${id} > .pb__accor-wrap-btn-item`);
          const minusButton = accordionContainer.querySelector(`#${id} > .pb__ex-button > button.pb__minus`);
          const plusButton = accordionContainer.querySelector(`#${id} > .pb__ex-button > button.pb__plus`);
          return [wrapper, minusButton, plusButton];
        };

        /**
         * Update button tabindex based on visibility.
         */
        const updateTabindex = (button) => {
          if (window.getComputedStyle(button).display === 'none') {
            button.setAttribute('tabindex', '-1');
          }
          else {
            button.removeAttribute('tabindex');
          }
        };

        /**
         * Toggle all accordion items between active/inactive.
         */
        const toggleClassAndDisplay = (buttonSelector, addClass, removeClass, hideIndex, showIndex) => {
          once('toggleClassAndDisplay', buttonSelector, accordionContainer).forEach((button) => {
            button.addEventListener('click', (event) => {
              const classId = event.target.closest('.pb__accor').id;
              const [wrapper, minusButton, plusButton] = getElementsById(classId);

              wrapper.forEach((element) => {
                element.classList.remove(removeClass);
                element.classList.add(addClass);

                const isExpand = addClass === 'pb__active';
                const toggleButton = element.querySelector('.pb__accor-button');
                const contentPane = element.querySelector('.pb__accor-pane');

                if (toggleButton) {
                  toggleButton.setAttribute('aria-expanded', isExpand);
                }
                if (contentPane) {
                  contentPane.setAttribute('aria-hidden', !isExpand);
                }
              });

              if (minusButton) {
                minusButton.style.display = hideIndex === 1 ? 'none' : 'block';
                updateTabindex(minusButton);
              }
              if (plusButton) {
                plusButton.style.display = showIndex === 1 ? 'none' : 'block';
                updateTabindex(plusButton);
              }
            });
          });
        };

        /**
         * Toggle a single accordion item.
         */
        const toggleActiveState = (buttonSelector, activeClass, inactiveClass) => {
          once('toggleActiveState', buttonSelector, accordionContainer).forEach((button) => {
            button.addEventListener('click', (event) => {
              const element = event.target.closest('.pb__accor-wrap-btn-item');
              const toggleButton = element.querySelector('.pb__accor-button');
              const contentPane = element.querySelector('.pb__accor-pane');
              const plusMinus = element.querySelector('.pb__plus-minus');

              const isCurrentlyActive = element.classList.contains(activeClass);

              element.classList.toggle(activeClass, !isCurrentlyActive);
              element.classList.toggle(inactiveClass, isCurrentlyActive);

              if (toggleButton) {
                toggleButton.setAttribute('aria-expanded', String(!isCurrentlyActive));
              }
              if (contentPane) {
                contentPane.setAttribute('aria-hidden', String(isCurrentlyActive));
              }

              if (plusMinus) {
                const plus = plusMinus.querySelector('.pb__plus');
                const minus = plusMinus.querySelector('.pb__minus');

                if (plus && minus) {
                  plus.style.display = !isCurrentlyActive ? 'none' : 'block';
                  minus.style.display = !isCurrentlyActive ? 'block' : 'none';

                  plus.setAttribute('aria-hidden', String(!isCurrentlyActive));
                  minus.setAttribute('aria-hidden', String(isCurrentlyActive));
                }
              }

              // ADDED: Update URL hash using data-fragment, if available
              const slug = button.getAttribute('data-fragment');
              if (slug) {
                history.replaceState(null, '', `#${slug}`);
              }
            });
          });
        };

        /**
         * Expand or collapse all items in this accordion container.
         */
        once('expand-collapse-all', '.pb__ex-button > button', accordionContainer).forEach((button) => {
          button.addEventListener('click', () => {
            const isExpand = button.classList.contains('pb__plus');
            const accId = button.getAttribute('aria-controls');
            const elements = accordionContainer.querySelectorAll(`#${accId} .pb__accor-wrap-btn-item`);

            elements.forEach((element) => {
              const toggleButton = element.querySelector('.pb__accor-button');
              const contentPane = element.querySelector('.pb__accor-pane');
              const plusMinus = element.querySelector('.pb__plus-minus');

              element.classList.toggle('pb__active', isExpand);
              element.classList.toggle('pb__active-no', !isExpand);

              if (toggleButton) {
                toggleButton.setAttribute('aria-expanded', isExpand);
              }
              if (contentPane) {
                contentPane.setAttribute('aria-hidden', !isExpand);
              }

              if (plusMinus) {
                const plus = plusMinus.querySelector('.pb__plus');
                const minus = plusMinus.querySelector('.pb__minus');

                if (plus && minus) {
                  plus.style.display = isExpand ? 'none' : 'block';
                  minus.style.display = isExpand ? 'block' : 'none';
                  plus.setAttribute('aria-hidden', isExpand);
                  minus.setAttribute('aria-hidden', !isExpand);
                }
              }
            });
          });
        });

        // ADDED: Deep link support — open accordion item based on hash
        const hash = window.location.hash.replace('#', '');
        if (hash) {
          const button = accordionContainer.querySelector(`[data-fragment="${CSS.escape(hash)}"]`);
          if (button && button.classList.contains('pb__accor-button')) {
            button.click();
            button.scrollIntoView({ behavior: 'smooth', block: 'start' });
          }
        }

        // Initialize accordion interaction for this container.
        toggleActiveState('.pb__accor-button', 'pb__active', 'pb__active-no');
        toggleClassAndDisplay('.pb__ex-button>button.pb__plus', 'pb__active', 'pb__active-no', 2, 1);
        toggleClassAndDisplay('.pb__ex-button>button.pb__minus', 'pb__active-no', 'pb__active', 1, 2);

        // Optional log for debugging during development (remove for production).
        Drupal.debug && Drupal.debug('Paragraphs Accordion initialized for one container.');
      });
    }
  };
})(Drupal, drupalSettings, once);
