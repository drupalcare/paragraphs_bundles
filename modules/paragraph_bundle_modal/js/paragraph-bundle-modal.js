/**
 * @file
 * W3CSS Paragraphs Modal.
 *
 * Filename:     w3css-paragraphs-modal.js
 * Website:      https://www.flashwebcenter.com
 * Developer:    Alaa Haddad https://www.alaahaddad.com.
 */
((Drupal, once) => {
  'use strict';

  Drupal.behaviors.w3cssParagraphsModal = {
    attach: (context, settings) => {
      let currWin;

      const showModal = function () {

        let modalBtn = this.id;
        let modalWin = `${modalBtn}-win`;
        currWin = document.getElementById(modalWin);
        currWin.style.display = "block";
      };

      const hideModal = function () {
        currWin.style.display = "none";
      };

      let modalButtons = once('.pb-modal-button', '.pb-modal-button>button', context);
      modalButtons.forEach(button => button.addEventListener('click', showModal));

      let modalHeaders = once('.pb-modal-header', '.pb-modal-header>button', context);
      modalHeaders.forEach(header => header.addEventListener('click', hideModal));
    }

      //click any where to close any submenu.
      // document.addEventListener('click', (event) => {
      //   if (event.target == loginBlock) {
      //     closeSearchHandler();
      //   }
      // });

  }

})(Drupal, once);
