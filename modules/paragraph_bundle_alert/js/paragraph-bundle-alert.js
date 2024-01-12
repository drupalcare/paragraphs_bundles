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
        const paragraph = alertBox.querySelector('.paragraph');

        if (closeButton && paragraph) {
          closeButton.addEventListener('click', () => {
            // Toggle the visibility of the alert box using CSS class
            if (alertBox.classList.contains('alert-is-closed')) {
              // Show the alert and update aria attributes
              paragraph.removeAttribute('aria-hidden');
              alertBox.classList.remove('alert-is-closed');
              closeButton.setAttribute('aria-expanded', 'true');
            } else {
              // Hide the alert and update aria attributes
              paragraph.setAttribute('aria-hidden', 'true');
              alertBox.classList.add('alert-is-closed');
              closeButton.setAttribute('aria-expanded', 'false');
            }
          });
        }
      });
    }
  };

})(Drupal, once);



