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
        const wrapper = context.querySelectorAll(`#${id} > .pb__accor-wrap-btn-item`);
        const minusButton = context.querySelector(`#${id} > .pb__ex-button > button.pb__minus`);
        const plusButton = context.querySelector(`#${id} > .pb__ex-button > button.pb__plus`);
        return [wrapper, minusButton, plusButton];
      };
 const toggleClassAndDisplay = (buttonSelector, addClass, removeClass, hideIndex, showIndex) => {
      once('toggleClassAndDisplay', buttonSelector, context).forEach((button) => {
        button.addEventListener('click', function (event) {
          const classId = event.target.closest('.pb__accor').id;
          const [wrapper, minusButton, plusButton] = getElementsById(classId);
          wrapper.forEach((element) => {
            element.classList.remove(removeClass);
            element.classList.add(addClass);

            // Update aria-expanded and aria-hidden attributes
            const isExpanded = addClass === 'pb__active';
            element.setAttribute('aria-expanded', isExpanded);
            element.setAttribute('aria-hidden', !isExpanded);
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
          const classId = event.target.closest('.pb__accor-wrap-btn-item').id;
          const sectionId = event.target.closest('.pb__accor').id;
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

            // Update aria-expanded and aria-hidden attributes
            element.setAttribute('aria-expanded', 'true');
            section.setAttribute('aria-hidden', 'false');
          } else {
            element.classList.replace(activeClass, inactiveClass);
            Array.from(section.children).forEach((child) => {
              if (!child.classList.contains(activeClass) && plusButton) {
                plusButton.style.display = "block";
                minusButton.style.display = "none";
              }
            });

            // Update aria-expanded and aria-hidden attributes
            element.setAttribute('aria-expanded', 'false');
            section.setAttribute('aria-hidden', 'true');
          }
        });
      });
    };

    // Initialize toggle functions
    toggleActiveState('.pb__accor-btn>button', 'pb__active', 'pb__active-no');
    toggleClassAndDisplay('.pb__ex-button>button.pb__plus', 'pb__active', 'pb__active-no', 2, 1);
    toggleClassAndDisplay('.pb__ex-button>button.pb__minus', 'pb__active-no', 'pb__active', 1, 2);
  }
};
})(Drupal, drupalSettings, once);
