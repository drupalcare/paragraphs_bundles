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
            minusButton.style.display = hideIndex === 1 ? "none" : "block";
            plusButton.style.display = showIndex === 1 ? "none" : "block";
          });
        });
      };

      const toggleActiveState = (buttonSelector, activeClass, inactiveClass) => {
        once('toggleActiveState', buttonSelector, context).forEach((button) => {
          button.addEventListener('click', function(event) {
            const element = event.target.closest('.pb__accor-wrap-btn-item');
            const section = event.target.closest('.pb__accor'); // It should reference the individual panel's parent, not just any ancestor.
            const toggleButton = element.querySelector('.pb__accor-button');
            const contentPane = element.querySelector('.pb__accor-pane');

            const isCurrentlyActive = toggleButton.getAttribute('aria-expanded') === 'true';

            if (isCurrentlyActive) {
              element.classList.replace(activeClass, inactiveClass);
              toggleButton.setAttribute('aria-expanded', 'false');
              contentPane.setAttribute('aria-hidden', 'true');
            } else {
              element.classList.replace(inactiveClass, activeClass);
              toggleButton.setAttribute('aria-expanded', 'true');
              contentPane.setAttribute('aria-hidden', 'false');
            }

            // Ensure the correct display of expand/collapse all buttons
            const [_, minusButton, plusButton] = getElementsById(section.id);
            plusButton.style.display = isCurrentlyActive ? "block" : "none";
            minusButton.style.display = isCurrentlyActive ? "none" : "block";
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
          if (plusButton && minusButton) {
            plusButton.style.display = isExpand ? 'none' : 'block';
            minusButton.style.display = isExpand ? 'block' : 'none';
          }
        });
      });

      // Initialize toggle functions
      toggleActiveState('.pb__accor-btn>button', 'pb__active', 'pb__active-no');
      toggleClassAndDisplay('.pb__ex-button>button.pb__plus', 'pb__active', 'pb__active-no', 2, 1);
      toggleClassAndDisplay('.pb__ex-button>button.pb__minus', 'pb__active-no', 'pb__active', 1, 2);
    }
  };
})(Drupal, drupalSettings, once);
