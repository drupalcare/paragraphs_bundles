/**
 * @file
 * Paragraph Bundle Image Background.
 *
 * Filename:     paragraph-bundle-image-background.js
 * Website:      https://www.flashwebcenter.com
 * Developer:    Alaa Haddad https://www.alaahaddad.com.
 */
((Drupal, once) => {
  'use strict';
  Drupal.behaviors.paragraphImageBackground = {
    attach: (context) => {
      once('paragraphImageBackground', '.paragraph--type--image-background-bundle', context).forEach((paragraph) => {
        const breakpoint = parseInt(paragraph.dataset.breakpoint || 0, 10);
        const imageBig = paragraph.dataset.imageBig;
        const imageSmall = paragraph.dataset.imageSmall || imageBig;

        function checkBreakpoint() {
          if (window.innerWidth <= breakpoint && breakpoint !== 0) {
            paragraph.style.backgroundImage = `url(${imageSmall})`;
          } else {
            paragraph.style.backgroundImage = `url(${imageBig})`;
          }
        }

        checkBreakpoint();
        window.addEventListener('resize', checkBreakpoint);
      });
    }
  };
})(Drupal, once);

