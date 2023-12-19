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
    attach: function (context, settings) {
      // Utility function to get elements by ID
      const getElementsById = (id) => {
        const wrapper = context.querySelectorAll(`#${id} > .wrapper-accs-btn-pane`);
        const minusButton = context.querySelector(`#${id} > .ex-button > button.minus`);
        const plusButton = context.querySelector(`#${id} > .ex-button > button.plus`);
        return [wrapper, minusButton, plusButton];
      };

      // Function to toggle class and display
      const toggleClassAndDisplay = (buttonSelector, addClass, removeClass, hideIndex, showIndex) => {
        once('toggleClassAndDisplay', buttonSelector, context).forEach((button) => {
          button.addEventListener('click', function (event) {
            const classId = event.target.closest('.pb-acc').id;
            const [wrapper, minusButton, plusButton] = getElementsById(classId);
            wrapper.forEach((element) => {
              element.classList.remove(removeClass);
              element.classList.add(addClass);
            });
            minusButton.style.display = hideIndex === 1 ? "none" : "block";
            plusButton.style.display = showIndex === 1 ? "none" : "block";
          });
        });
      };

      // Function to toggle active state
      const toggleActiveState = (buttonSelector, activeClass, inactiveClass) => {
        once('toggleActiveState', buttonSelector, context).forEach((button) => {
          button.addEventListener('click', function (event) {
            const classId = event.target.closest('.wrapper-accs-btn-pane').id;
            const sectionId = event.target.closest('.pb-acc').id;
            const [wrapper, minusButton, plusButton] = getElementsById(sectionId);
            const element = context.getElementById(classId);
            const section = context.getElementById(sectionId);

            if (element.classList.contains(inactiveClass)) {
              element.classList.replace(inactiveClass, activeClass);
              Array.from(section.children).forEach((child) => {
                if (!child.classList.contains(inactiveClass) && minusButton) {
                  minusButton.style.display = "block";
                  plusButton.style.display = "none";
                }
              });
            } else {
              element.classList.replace(activeClass, inactiveClass);
              Array.from(section.children).forEach((child) => {
                if (!child.classList.contains(activeClass) && plusButton) {
                  plusButton.style.display = "block";
                  minusButton.style.display = "none";
                }
              });
            }
          });
        });
      };

      // Initialize toggle functions
      toggleActiveState('.pb-accs-button>button', 'pb-is-active', 'pb-not-active');
      toggleClassAndDisplay('.ex-button>button.plus', 'pb-is-active', 'pb-not-active', 2, 1);
      toggleClassAndDisplay('.ex-button>button.minus', 'pb-not-active', 'pb-is-active', 1, 2);
    }
  };
})(Drupal, drupalSettings, once);



