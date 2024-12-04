/**
 * @file
 * Paragraph Bundle Accordion.
 *
 * Filename:     paragraph-bundle-accordion.js
 * Website:      https://www.flashwebcenter.com
 * Developer:    Alaa Haddad https://www.alaahaddad.com.
 */
((Drupal, drupalSettings, once) => {
  'use strict';

  Drupal.behaviors.paragraphsAccordionBundle = {
    attach: function(context, settings) {
      // Utility function to get elements by ID
      const getElementsById = (id) => {
        const wrapper = context.querySelectorAll(`#${id} > .pb__accor-wrap-btn-item`);
        const minusButton = context.querySelector(`#${id} > .pb__ex-button > button.pb__minus`);
        const plusButton = context.querySelector(`#${id} > .pb__ex-button > button.pb__plus`);
        return [wrapper, minusButton, plusButton];
      };

      // Function to update the tabindex based on visibility
      const updateTabindex = (button) => {
        if (window.getComputedStyle(button).display === 'none') {
          button.setAttribute('tabindex', '-1');
        } else {
          button.removeAttribute('tabindex');
        }
      };

      const toggleClassAndDisplay = (buttonSelector, addClass, removeClass, hideIndex, showIndex) => {
        once('toggleClassAndDisplay', buttonSelector, context).forEach((button) => {
          button.addEventListener('click', function(event) {
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
              minusButton.style.display = hideIndex === 1 ? "none" : "block";
              updateTabindex(minusButton);
            }
            if (plusButton) {
              plusButton.style.display = showIndex === 1 ? "none" : "block";
              updateTabindex(plusButton);
            }
          });
        });
      };

      const toggleActiveState = (buttonSelector, activeClass, inactiveClass) => {
        once('toggleActiveState', buttonSelector, context).forEach((button) => {
          button.addEventListener('click', function (event) {
            const element = event.target.closest('.pb__accor-wrap-btn-item');
            const toggleButton = element.querySelector('.pb__accor-button');
            const contentPane = element.querySelector('.pb__accor-pane');
            const plusMinus = element.querySelector('.pb__plus-minus'); // Target plus-minus container

            const isCurrentlyActive = toggleButton.getAttribute('aria-expanded') === 'true';

            // Update classes and ARIA attributes for the button
            element.classList.toggle(activeClass, !isCurrentlyActive);
            element.classList.toggle(inactiveClass, isCurrentlyActive);
            toggleButton.setAttribute('aria-expanded', String(!isCurrentlyActive));
            if (contentPane) {
              contentPane.setAttribute('aria-hidden', String(isCurrentlyActive));
            }

            // Update plus/minus visibility and aria-hidden attributes
            if (plusMinus) {
              const plus = plusMinus.querySelector('.pb__plus');
              const minus = plusMinus.querySelector('.pb__minus');
              if (plus && minus) {
                plus.style.display = isCurrentlyActive ? 'block' : 'none';
                minus.style.display = isCurrentlyActive ? 'none' : 'block';

                // Explicitly set aria-hidden for plus/minus spans
                plus.setAttribute('aria-hidden', String(!isCurrentlyActive));
                minus.setAttribute('aria-hidden', String(isCurrentlyActive));
              }
            }
          });
        });
      };

      once('expand-collapse-all', '.pb__ex-button > button', context).forEach((button) => {
        button.addEventListener('click', () => {
          const isExpand = button.classList.contains('pb__plus');
          const accId = button.getAttribute('aria-controls');
          const elements = context.querySelectorAll(`#${accId} .pb__accor-wrap-btn-item`);

          elements.forEach(element => {
            const toggleButton = element.querySelector('.pb__accor-button');
            if (toggleButton) {
              toggleButton.setAttribute('aria-expanded', isExpand);
            }
            const contentPane = element.querySelector('.pb__accor-pane');
            if (contentPane) {
              contentPane.setAttribute('aria-hidden', !isExpand);
            }
            element.classList.toggle('pb__active', isExpand);
            element.classList.toggle('pb__active-no', !isExpand);
          });

          // Update the display states of the global expand/collapse buttons
          const plusButton = context.querySelector(`#${accId} .pb__plus`);
          const minusButton = context.querySelector(`#${accId} .pb__minus`);
          if (plusButton) {
            plusButton.style.display = isExpand ? 'none' : 'block';
            updateTabindex(plusButton);
          }
          if (minusButton) {
            minusButton.style.display = isExpand ? 'block' : 'none';
            updateTabindex(minusButton);
          }
        });
      });

      // Initialize toggle functions
      toggleActiveState('.pb__accor-button', 'pb__active', 'pb__active-no');
      toggleClassAndDisplay('.pb__ex-button>button.pb__plus', 'pb__active', 'pb__active-no', 2, 1);
      toggleClassAndDisplay('.pb__ex-button>button.pb__minus', 'pb__active-no', 'pb__active', 1, 2);
    }
  };
})(Drupal, drupalSettings, once);
