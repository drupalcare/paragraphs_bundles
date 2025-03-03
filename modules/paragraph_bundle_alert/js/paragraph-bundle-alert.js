/**
 * @file
 * Paragraph Bundle Accordion.
 *
 * Filename:     paragraph-bundle-alert.js
 * Website:      https://www.flashwebcenter.com
 * Developer:    Alaa Haddad https://www.alaahaddad.com.
 */
((Drupal, once) => {
  'use strict';

  Drupal.behaviors.paragraphBundleAlert = {
    attach: function(context, settings) {
      const alertBoxes = once('paragraphBundleAlert', '.paragraph--type--alert-bundle', context);
      if (!alertBoxes.length) {
        return;
      }

      alertBoxes.forEach(alertBox => {
        const closeButton = alertBox.querySelector('.pb__alert-top.On > button');
        const paragraphContent = alertBox.querySelector('.pb__content-full');
        const focusableElements = paragraphContent.querySelectorAll('a, button, input, textarea, select');

        const updateTabindex = (state) => {
          focusableElements.forEach(element => {
            element.setAttribute('tabindex', state ? '0' : '-1');
          });
        };

        if (closeButton && paragraphContent) {
          // Initialize aria-hidden based on the presence of the alert-is-closed class
          if (alertBox.classList.contains('alert-is-closed')) {
            alertBox.setAttribute('aria-hidden', 'true');
            closeButton.setAttribute('aria-expanded', 'false');
            updateTabindex(false);
          } else {
            alertBox.removeAttribute('aria-hidden');
            closeButton.setAttribute('aria-expanded', 'true');
            updateTabindex(true);
          }

          closeButton.addEventListener('click', () => {
            // Toggle the visibility of the alert box using CSS class
            if (alertBox.classList.contains('alert-is-closed')) {
              alertBox.classList.remove('alert-is-closed');
              alertBox.removeAttribute('aria-hidden');
              closeButton.setAttribute('aria-expanded', 'true');
              updateTabindex(true);
            } else {
              alertBox.classList.add('alert-is-closed');
              alertBox.setAttribute('aria-hidden', 'true');
              closeButton.setAttribute('aria-expanded', 'false');
              updateTabindex(false);
            }
          });
        }
      });
    }
  };

})(Drupal, once);
