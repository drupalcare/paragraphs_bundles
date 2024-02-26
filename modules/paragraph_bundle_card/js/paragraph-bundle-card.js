/**
 * @file
 * Paragraph Bundle Card.
 *
 * Filename:     paragraph-bundle-card.js
 * Website:      https://www.flashwebcenter.com
 * Developer:    Alaa Haddad https://www.alaahaddad.com.
 */

((Drupal, drupalSettings, once) => {
  'use strict';

  Drupal.behaviors.pbCard = {
    attach: (context, settings) => {
      // Use 'once' to ensure this behavior is applied only once per element.
      once('pb-card-image-top', '.pb-card-two-right', context).forEach(rightCard => {
        // Check if rightCard contains an element with the specific class
        if (rightCard.querySelector('.paragraph--type--card-image-section-bundle')) {
          // Add the 'pb-image-top' class to the parent element
          rightCard.closest('.pb__card-two-columns').classList.add('pb-image-top');
        }
      });
    }
  };

})(Drupal, drupalSettings, once);

