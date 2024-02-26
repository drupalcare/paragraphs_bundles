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
      once('pb-card-image-top', '.pb__card-two-columns', context).forEach(container => {
        const rightCardContainsImage = container.querySelector('.pb-card-two-right .paragraph--type--card-image-section-bundle');
        const leftCardContainsImage = container.querySelector('.pb-card-two-left .paragraph--type--card-image-section-bundle');

        // Check if rightCard contains an image section and leftCard does not contain an image section
        if (rightCardContainsImage && !leftCardContainsImage) {
          // Add the 'pb-image-top' class to the parent element
          container.classList.add('pb-image-top');
        }
      });
    }
  };

})(Drupal, drupalSettings, once);


