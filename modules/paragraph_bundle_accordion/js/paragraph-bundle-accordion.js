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

((Drupal, drupalSettings, once) => {
  'use strict';

  Drupal.behaviors.paragraphsAccordionBundle = {
    attach(context) {
      const accordionContainers = once('paragraphsAccordionBundle', '.pb__accor', context);

      accordionContainers.forEach((accordionContainer) => {

        const togglePlusMinus = (plus, minus, isExpanded) => {
          if (!plus || !minus) return;
          plus.setAttribute('aria-hidden', String(isExpanded));
          minus.setAttribute('aria-hidden', String(!isExpanded));
        };

        const openAccordionItem = (button) => {
          const element = button.closest('.pb__accor-wrap-btn-item');
          if (!element || element.classList.contains('pb__active')) return;

          element.classList.add('pb__active');
          element.classList.remove('pb__active-no');

          const toggleButton = element.querySelector('.pb__accor-button');
          const contentPane = element.querySelector('.pb__accor-pane');
          const plus = element.querySelector('.pb__plus-minus .pb__plus');
          const minus = element.querySelector('.pb__plus-minus .pb__minus');

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

        const toggleActiveState = (buttonSelector, activeClass, inactiveClass) => {
          once('toggleActiveState', buttonSelector, accordionContainer).forEach((button) => {
            button.addEventListener('click', () => {
              const element = button.closest('.pb__accor-wrap-btn-item');
              if (!element) return;

              const isActive = element.classList.contains(activeClass);
              element.classList.toggle(activeClass, !isActive);
              element.classList.toggle(inactiveClass, isActive);

              const toggleButton = element.querySelector('.pb__accor-button');
              const contentPane = element.querySelector('.pb__accor-pane');
              const plus = element.querySelector('.pb__plus-minus .pb__plus');
              const minus = element.querySelector('.pb__plus-minus .pb__minus');

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
            });
          });
        };

        const toggleExpandCollapseAll = (buttonSelector) => {
          once('toggleExpandCollapseAll', buttonSelector, accordionContainer).forEach((button) => {
            button.addEventListener('click', () => {
              const wrapper = button.closest('.pb__ex-button');
              if (!wrapper) return;

              const isExpanding = wrapper.classList.contains('expand-all');
              const accordionItems = accordionContainer.querySelectorAll('.pb__accor-wrap-btn-item');

              accordionItems.forEach((item) => {
                const contentPane = item.querySelector('.pb__accor-pane');
                const toggleButton = item.querySelector('.pb__accor-button');
                const plus = item.querySelector('.pb__plus-minus .pb__plus');
                const minus = item.querySelector('.pb__plus-minus .pb__minus');

                if (isExpanding && !item.classList.contains('pb__active')) {
                  item.classList.add('pb__active');
                  item.classList.remove('pb__active-no');
                  toggleButton?.setAttribute('aria-expanded', 'true');
                  contentPane?.setAttribute('aria-hidden', 'false');
                  if (contentPane) {
                    Drupal.paragraphBundleAccordion.slideDown(contentPane);
                  }
                  togglePlusMinus(plus, minus, true);
                } else if (!isExpanding && item.classList.contains('pb__active')) {
                  item.classList.remove('pb__active');
                  item.classList.add('pb__active-no');
                  toggleButton?.setAttribute('aria-expanded', 'false');
                  contentPane?.setAttribute('aria-hidden', 'true');
                  if (contentPane) {
                    Drupal.paragraphBundleAccordion.slideUp(contentPane);
                  }
                  togglePlusMinus(plus, minus, false);
                }
              });

              wrapper.classList.toggle('expand-all', !isExpanding);
              wrapper.classList.toggle('collapse-all', isExpanding);
            });
          });
        };

        toggleActiveState('.pb__accor-button', 'pb__active', 'pb__active-no');
        toggleExpandCollapseAll('.pb__ex-button .pb__toggle-all');

        // Ensure only the first matching initially open pane is animated open on load
        // Open first item if the "pb--open-first" class is set on the accordion container.
        if (accordionContainer.classList.contains('pb--open-first')) {
          const firstButton = accordionContainer.querySelector('.pb__accor-button');
          if (firstButton) {
            openAccordionItem(firstButton);
          }
        } else {
          // Fallback: ensure any pre-opened item gets animated open.
          const initiallyOpenPane = accordionContainer.querySelector('.pb__accor-pane[aria-hidden="false"]');
          if (initiallyOpenPane) {
            Drupal.paragraphBundleAccordion.slideDown(initiallyOpenPane);
          }
        }

        if (window.location.hash) {
          const hash = decodeURIComponent(window.location.hash.slice(1));
          requestAnimationFrame(() => {
            const button = accordionContainer.querySelector(`.pb__accor-button[data-fragment="${CSS.escape(hash)}"]`);
            if (button) {
              openAccordionItem(button);
            }
          });
        }
      });
    }
  };
})(Drupal, drupalSettings, once);
