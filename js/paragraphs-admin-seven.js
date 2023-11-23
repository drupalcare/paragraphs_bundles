/**
 * @file
 * W3CSS Paragraphs.
 *
 * Filename:     paragraphs-bundles-admin-claro.js
 * Website:      https://www.flashwebcenter.com
 * Developer:    Alaa Haddad https://www.alaahaddad.com.
 */
((Drupal) => {
  'use strict';

  function getColor(elements, y = 0) {

    elements.forEach(element => {

      let currVal = adjustValue(element.value, y);
      addClassToElementAndSibling(element, currVal);

    });
  }

  function adjustValue(value, y) {
    return y > 0 ? 'w3' + value.slice(y) : value;
  }

  function addClassToElementAndSibling(element, className) {

    element.parentElement.classList.add(className);
    element.nextElementSibling.classList.add(className);

  }

  const selectors = [
    ".field--name-paragraphs-bundles-node-bg",
    ".field--name-w3css-display-bg-color",
    ".field--name-w3css-display-hover-bg",
    ".field--name-w3css-display-text-color",
    ".field--name-w3css-display-hover-text",
    ".field--name-w3css-display-border-color",
    ".field--name-w3css-display-hover-border",
    ".field--name-w3css-display-active-item"
  ];

  const sliceValues = [0, 0, 8, 7, 13, 9, 15, 12];

  function applyColor() {

    for (let i = 0; i < selectors.length; i++) {

      if ('.w3css-tab-display .form-type--radio>.form-radio') {
        let x = document.querySelectorAll(`${selectors[i]} .form-type--radio>.form-radio`);
        getColor(x, sliceValues[i]);
      }

      if ('.w3css-tab-display .form-type-radio>.form-radio') {
        let x = document.querySelectorAll(`${selectors[i]} .form-type-radio>.form-radio`);
        getColor(x, sliceValues[i]);
      }

    }
  }

  Drupal.behaviors.w3cssParagraphsAdmin = {
    attach: function(settings) {

        const elements = document.querySelectorAll('.field--type-entity-reference-revisions');

        elements.forEach(element => {
          element.addEventListener('click', applyColor);
        });

        applyColor();

    }
  };

})(Drupal);
