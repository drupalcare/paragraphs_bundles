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

  const closeAlert = document.querySelector('.pb__close-alert');

  const closeBlockHandler = (pId) => {
    const alertContent = document.getElementById(pId);
    if (alertContent) { // Check if element exists
      alertContent.setAttribute('aria-expanded', 'false');
      alertContent.setAttribute('aria-hidden', 'true');
      alertContent.style.display = 'none';
    }
  };

  Drupal.behaviors.paragraphBundleAlert = {
    attach: function(settings) {
      closeAlert?.addEventListener('click', function(event) {
        let closestParent = event.target.closest('[id]');
        if (closestParent) { // Ensure closestParent is not null
          closeBlockHandler(closestParent.id);
        }
      });
    }
  };

})(Drupal, once);

