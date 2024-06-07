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
      let currBtn;
      let modalContent;
      let focusableElements;
      let firstFocusableElement;
      let lastFocusableElement;

      const showModal = (event) => {
        let modalBtn = event.currentTarget.id;
        let modalWin = `${modalBtn}-win`;

        // Close the currently opened window if any
        if (currWin) {
          hideModal();
        }

        // Show the new window
        currBtn = event.currentTarget;
        currBtn.setAttribute('aria-expanded', 'true');
        currWin = document.getElementById(modalWin);
        currWin.style.display = "block";
        currWin.setAttribute('aria-hidden', 'false');
        modalContent = currWin.querySelector('.pb-modal-content');

        // Focus management
        focusableElements = currWin.querySelectorAll('a, button, input, textarea, select, [tabindex]:not([tabindex="-1"])');
        firstFocusableElement = focusableElements[0];
        lastFocusableElement = focusableElements[focusableElements.length - 1];
        firstFocusableElement.focus();

        document.addEventListener('keydown', trapFocus);
        event.stopPropagation(); // Prevents the event from bubbling up
      };

      const hideModal = () => {
        if (currWin) {
          currWin.style.display = "none";
          currWin.setAttribute('aria-hidden', 'true');
          if (currBtn) {
            currBtn.setAttribute('aria-expanded', 'false');
            currBtn.focus(); // Return focus to the triggering element
          }
        }
        document.removeEventListener('keydown', trapFocus);
      };

      const trapFocus = (event) => {
        if (event.key === 'Escape') {
          hideModal();
        } else if (event.key === 'Tab') {
          // Trap focus within the modal
          if (event.shiftKey) { // Shift + Tab
            if (document.activeElement === firstFocusableElement) {
              lastFocusableElement.focus();
              event.preventDefault();
            }
          } else { // Tab
            if (document.activeElement === lastFocusableElement) {
              firstFocusableElement.focus();
              event.preventDefault();
            }
          }
        }
      };

      // Close modal if clicking on pb-modal-content but not on its child .b__modal__inner
      const closeModalOnContentClick = (event) => {
        if (modalContent && event.target === modalContent) {
          hideModal();
        }
      };

      let modalButtons = once('ParagraphBundleModal', '.pb-modal-button>button', context);
      modalButtons.forEach(button => button.addEventListener('click', showModal));

      let modalHeaders = once('ParagraphBundleModal', '.pb-modal-header>button', context);
      modalHeaders.forEach(header => header.addEventListener('click', hideModal));

      // Add event listener to the entire document to close modal when clicking on pb-modal-content
      document.addEventListener('click', closeModalOnContentClick, true);
    }
  };
})(Drupal, once);

