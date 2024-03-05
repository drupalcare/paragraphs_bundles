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
      const currModal = document.querySelector('.pb__grid-lightbox');
      const modalContent = currModal.querySelector('.pb__grid-lightbox-content');
      let currentIndex = -1; // This will be updated upon opening the modal
      let items = Array.from(document.querySelectorAll('.paragraph--type--image-grid-section-bundle')); // Ensure this is updated if items can change

      const updateItemCountClass = () => {
          const wrappers = document.querySelectorAll('.pb__image-grid-wrapper');
          wrappers.forEach(wrapper => {
              const itemCount = wrapper.children.length - 1;
              wrapper.className = wrapper.className.replace(/\bgrid-count-\d+\b/g, '');
              wrapper.classList.add(`grid-count-${itemCount}`);
          });
      };

      const updateModalContent = (fullImageUrl) => {
        const clonedElement = items[currentIndex].cloneNode(true);
        const imageElement = clonedElement.querySelector('img');
        if (imageElement) {
          imageElement.src = fullImageUrl;
          // Reset height and width attributes
          imageElement.removeAttribute('height');
          imageElement.removeAttribute('width');
        }

        modalContent.innerHTML = '';
        modalContent.appendChild(clonedElement);
        modalContent.offsetHeight; // Trigger reflow
        modalContent.style.opacity = '1';
        modalContent.classList.remove('fade-in');
        requestAnimationFrame(() => {
          modalContent.classList.add('fade-in');
        });
      };

      const openModal = (event) => {
        const paragraphContent = event.currentTarget.closest('.paragraph--type--image-grid-section-bundle');
        currentIndex = items.indexOf(paragraphContent);
        const fullImageUrl = paragraphContent.getAttribute('data-full-image-url');
        updateModalContent(fullImageUrl);
        currModal.setAttribute('aria-expanded', 'true');
        currModal.setAttribute('aria-hidden', 'false');
        currModal.style.display = 'block';
        event.stopPropagation();
      };

      const closeModal = (event) => {
        currModal.style.display = 'none';
        currModal.setAttribute('aria-hidden', 'true');
        currentIndex = -1; // Reset index
        if (event) event.stopPropagation();
      };

      const navigateModal = (direction) => {
        // Adjust currentIndex based on direction
        if (direction === 'next') {
          currentIndex = (currentIndex + 1) % items.length;
        } else if (direction === 'prev') {
          currentIndex = (currentIndex - 1 + items.length) % items.length;
        }
        const fullImageUrl = items[currentIndex].getAttribute('data-full-image-url');
        updateModalContent(fullImageUrl);
      };

      once('paragraphBundleExpand', '.pb__expand-grid', context).forEach(button => button.addEventListener('click', openModal));
      once('modalNavigation', '.pb__modal-next, .pb__modal-prev', context).forEach(button => button.addEventListener('click', event => {
        const direction = button.classList.contains('pb__modal-next') ? 'next' : 'prev';
        navigateModal(direction);
        event.stopPropagation();
      }));

      context.addEventListener('click', event => {
        if (event.target.matches('.pb__close-grid') || event.target.closest('.pb__close-grid') || event.target === modalContent) {
          closeModal(event);
        }
      });

      window.addEventListener('load', updateItemCountClass);

    }
  };
})(Drupal, once);

