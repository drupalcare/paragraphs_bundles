/**
 * @file
 * Paragraph Bundle Tabs.
 *
 * Filename:     paragraph-bundle-image-grid.js
 * Website:      https://www.flashwebcenter.com
 * Developer:    Alaa Haddad https://www.alaahaddad.com.
 */
((Drupal, once) => {
  'use strict';

  Drupal.behaviors.paragraphBundleImageGridModal = {
    attach: (context, settings) => {
      let currModal;
      let currBtn;
      let currentIndex = -1; // Index of the currently shown item
      let items; // Collection of items

      const updateModalContent = (index) => {
        const modalContent = currModal.querySelector('.pb__grid-lightbox-content');
        modalContent.classList.remove('fade-in', 'fade-out');
        modalContent.style.opacity = '0';
        modalContent.innerHTML = '';
        modalContent.appendChild(items[index].cloneNode(true));
        setTimeout(() => {
          modalContent.style.opacity = '';
          modalContent.classList.add('fade-in');
        }, 10);
      };

      const openModal = (event) => {
        const paragraphContent = event.currentTarget.closest('.paragraph--type--image-grid-section-bundle');
        items = Array.from(document.querySelectorAll('.paragraph--type--image-grid-section-bundle')); // Collect all items
        currentIndex = items.indexOf(paragraphContent);

        // Modal setup
        const modal = document.querySelector('.pb__grid-lightbox');
        currModal = modal;
        currBtn = event.currentTarget;
        currBtn.style.display = 'none';

        updateModalContent(currentIndex);

        currBtn.setAttribute('aria-expanded', 'true');
        currModal.setAttribute('aria-hidden', 'false');
        currModal.style.display = 'block';

        event.stopPropagation();
      };

      const closeModal = (event) => {
        if (currModal) {
          currModal.style.display = 'none';
          currModal.setAttribute('aria-hidden', 'true');
          if (currBtn) {
            currBtn.setAttribute('aria-expanded', 'false');
            currBtn.style.display = 'block';
          }
          currModal = null;
          currBtn = null;
          currentIndex = -1;
        }
        if (event) event.stopPropagation();
      };

      const navigateModal = (direction) => {
        const itemsLength = items.length; // Total number of items

        if (direction === 'next') {
          currentIndex = (currentIndex + 1) % itemsLength;
        } else if (direction === 'prev') {
          currentIndex = (currentIndex - 1 + itemsLength) % itemsLength;
        }

        updateModalContent(currentIndex);
      };


      once('paragraphBundleExpand', '.pb__expand-grid', context).forEach(button => {
        button.addEventListener('click', openModal);
      });

      once('modalNavigation', '.pb__modal-next, .pb__modal-prev', context).forEach(button => {
        button.addEventListener('click', (event) => {
          const direction = button.classList.contains('pb__modal-next') ? 'next' : 'prev';
          navigateModal(direction);
          event.stopPropagation();
        });
      });

      context.addEventListener('click', (event) => {
        const closeBtnClicked = event.target.matches('.pb__close-grid') || event.target.closest('.pb__close-grid');
        if (closeBtnClicked) {
          closeModal(event);
          return;
        }

        if (currModal && event.target === currModal) {
          closeModal();
        }
      }, true);
    }
  };
})(Drupal, once);

