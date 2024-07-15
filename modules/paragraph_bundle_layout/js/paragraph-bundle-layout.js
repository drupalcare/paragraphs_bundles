/**
 * @file
 * Paragraph Bundle Layout.
 *
 * Filename:     paragraph-bundle-layout.js
 * Website:      https://www.flashwebcenter.com
 * Developer:    Alaa Haddad https://www.alaahaddad.com.
 */
((Drupal, drupalSettings, once) => {
  'use strict';

  Drupal.behaviors.paragraphBundleLayout = {
    attach: (context, settings) => {
      once('paragraphBundleLayout', '.pb__two-columns>div, .pb__three-columns>div', context).forEach(element => {
        if (element.children.length > 1) {
          element.classList.add('plus-one');
        }
      });
    }
  };

})(Drupal, drupalSettings, once);
