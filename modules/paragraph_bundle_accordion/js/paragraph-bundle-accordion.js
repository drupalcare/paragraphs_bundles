/**
 * @file
 * Paragraph Bundle Accordion.
 *
 * Filename:     paragraph-bundle-accordion.js
 * Website:      https://www.flashwebcenter.com
 * Developer:    Alaa Haddad https://www.alaahaddad.com.
 */
((Drupal, settings, once) => {

  'use strict';

  const getMyId = (classId) => {
    const wrapperAccsBtnPane = document.querySelectorAll(`#${classId} > .wrapper-accs-btn-pane`);
    const minusButton = document.querySelector(`#${classId} > .ex-button > button.minus`);
    const plusButton = document.querySelector(`#${classId} > .ex-button > button.plus`);
    return [wrapperAccsBtnPane, minusButton, plusButton];
  };

  const toggleClassAndDisplay = (buttonSelector, activeClass, inactiveClass, displayIndex1, displayIndex2) => {
    const buttons = document.querySelectorAll(buttonSelector);
    buttons.forEach((button) => {
      button.addEventListener('click', function() {
        const classId = this.parentElement.parentElement.id;
        const s = getMyId(classId);
        s[0].forEach((element) => {
          element.className = element.className.replace(inactiveClass, activeClass);
        });
        s[displayIndex1].style.display = "none";
        s[displayIndex2].style.display = "block";
      });
    });
  };

  const toggleActiveState = (buttonSelector, activeClass, inactiveClass) => {
    const buttons = document.querySelectorAll(buttonSelector);
    buttons.forEach((button) => {
      button.addEventListener('click', function() {
        const classId = this.parentElement.parentElement.id;
        const sectionId = this.parentElement.parentElement.parentElement.id;
        const x = getMyId(sectionId);
        const element = document.getElementById(classId);
        const section = document.getElementById(sectionId);
        if (element.classList.contains(inactiveClass)) {
          element.classList.replace(inactiveClass, activeClass);
          Array.from(section.children).forEach((child) => {
            if (!child.classList.contains(inactiveClass) && x[1]) {
              x[1].style.display = "block";
              x[2].style.display = "none";
            }
          });
        } else {
          element.classList.replace(activeClass, inactiveClass);
          Array.from(section.children).forEach((child) => {
            if (!child.classList.contains(activeClass) && x[2]) {
              x[2].style.display = "block";
              x[1].style.display = "none";
            }
          });
        }
      });
    });
  };

  Drupal.behaviors.paragraphsAccordionBundle = {
    attach: (settings) => {
      const loadingBlocks = document.querySelectorAll('[data-big-pipe-placeholder-id]').length;
      if (!loadingBlocks) {
        toggleActiveState('.pb-accs-button>button', 'pb-is-active', 'pb-not-active');
        toggleClassAndDisplay('.ex-button>button.plus', 'pb-is-active', 'pb-not-active', 2, 1);
        toggleClassAndDisplay('.ex-button>button.minus', 'pb-not-active', 'pb-is-active', 1, 2);
      }
    }
  };
})(Drupal, drupalSettings, once);
