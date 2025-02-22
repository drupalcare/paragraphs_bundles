/**
 * @file
 * Paragraph Bundle Icon.
 *
 * Filename:     paragraph-bundle-icon.js
 * Website:      https://www.flashwebcenter.com
 * Developer:    Alaa Haddad https://www.alaahaddad.com.
 */
(function (Drupal) {
  Drupal.behaviors.materialIconsLoader = {
    attach: function (context, settings) {
      if (context !== document) {
        return;
      }

      // Function to fade in the icons
      function fadeInIcons() {
        document.querySelectorAll('.icon-wrapper').forEach((iconWrapper) => {
          iconWrapper.classList.remove('material-icons-hidden');
        });
      }

      // Check if Font Loading API is available
      if (document.fonts && document.fonts.ready) {
        // Wait for the browser to load all fonts
        document.fonts.ready.then(() => {
          fadeInIcons();
        });
      } else {
        // Fallback in case fonts API is not supported
        setTimeout(() => {
          fadeInIcons();
        }, 500);
      }


    }
  };
})(Drupal);


