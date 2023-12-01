/**
 * @file
 * Paragraph Bundle Modal.
 *
 * Filename:     paragraph-bundle-modal.js
 * Website:      https://www.flashwebcenter.com
 * Developer:    Alaa Haddad https://www.alaahaddad.com.
 */
((Drupal, once) => {
  'use strict';
  Drupal.behaviors.ParagraphBundleModal = {
    attach: (context, settings) => {
      let currWin;
      const showModal = (event) => {
        let modalBtn = event.currentTarget.id;
        let modalWin = `${modalBtn}-win`;
        currWin = document.getElementById(modalWin);
        currWin.style.display = "block";
        event.stopPropagation(); // Prevents the event from bubbling up
      };
      const hideModal = () => {
        if (currWin) {
          currWin.style.display = "none";
        }
      };
      let modalButtons = once('.pb-modal-button', '.pb-modal-button>button', context);
      modalButtons.forEach(button => button.addEventListener('click', showModal));
      let modalHeaders = once('.pb-modal-header', '.pb-modal-header>button', context);
      modalHeaders.forEach(header => header.addEventListener('click', hideModal));

      // Add event listener to the entire document
      document.addEventListener('click', hideModal);
    }
  };
})(Drupal, once);
