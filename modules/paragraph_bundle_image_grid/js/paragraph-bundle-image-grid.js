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
    attach: (context) => {
      // Scope to each individual `.pb__image-grid-wrapper`.
      once('paragraphBundleGrid', '.pb__image-grid-wrapper', context).forEach((slideshowContainer) => {
        const currModal = slideshowContainer.querySelector('.pb__grid-lightbox');
        const modalContent = currModal.querySelector('.pb__grid-lightbox-content');
        let currentIndex = -1;
        let items = Array.from(slideshowContainer.querySelectorAll('.paragraph--type--image-grid-section-bundle'));
        let lastFocusedElement = null;
        // Set grid count class dynamically.
        const updateItemCountClass = () => {
          const itemCount = items.length;
          slideshowContainer.className = slideshowContainer.className.replace(/\bgrid-count-\d+\b/g, '');
          slideshowContainer.classList.add(`grid-count-${itemCount}`);
        };
        // Populate the modal with the current item.
        const updateModalContent = (fullImageUrl) => {
          const clonedElement = items[currentIndex].cloneNode(true);
          const imageElement = clonedElement.querySelector('img');
          if (imageElement) {
            imageElement.src = fullImageUrl;
            imageElement.removeAttribute('height');
            imageElement.removeAttribute('width');
          }
          modalContent.innerHTML = '';
          modalContent.appendChild(clonedElement);
          // Trigger fade-in animation.
          modalContent.offsetHeight; // Force reflow.
          modalContent.style.opacity = '1';
          modalContent.classList.remove('fade-in');
          requestAnimationFrame(() => modalContent.classList.add('fade-in'));
        };
        // Open the modal when a grid item button is clicked.
        const openModal = (event) => {
          lastFocusedElement = document.activeElement;
          const paragraphContent = event.currentTarget.closest('.paragraph--type--image-grid-section-bundle');
          currentIndex = items.indexOf(paragraphContent);
          const fullImageUrl = paragraphContent.getAttribute('data-full-image-url');
          updateModalContent(fullImageUrl);
          event.currentTarget.setAttribute('aria-expanded', 'true');
          currModal.setAttribute('aria-hidden', 'false');
          currModal.style.display = 'block';
          currModal.focus();
          document.addEventListener('keydown', trapFocus);
          event.stopPropagation();
        };
        // Close the modal.
        const closeModal = (event) => {
          currModal.style.display = 'none';
          currModal.setAttribute('aria-hidden', 'true');
          slideshowContainer.querySelectorAll('.pb__expand-grid').forEach(button => button.setAttribute('aria-expanded', 'false'));
          currentIndex = -1;
          if (lastFocusedElement) {
            lastFocusedElement.focus();
          }
          document.removeEventListener('keydown', trapFocus);
          if (event) event.stopPropagation();
        };
        // Navigate to next/previous image.
        const navigateModal = (direction) => {
          if (direction === 'next') {
            currentIndex = (currentIndex + 1) % items.length;
          } else if (direction === 'prev') {
            currentIndex = (currentIndex - 1 + items.length) % items.length;
          }
          const fullImageUrl = items[currentIndex].getAttribute('data-full-image-url');
          updateModalContent(fullImageUrl);
        };
        // Handle keyboard focus trapping within the modal.
        const trapFocus = (event) => {
          const focusableElements = currModal.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
          const firstElement = focusableElements[0];
          const lastElement = focusableElements[focusableElements.length - 1];
          if (event.key === 'Tab') {
            if (event.shiftKey && document.activeElement === firstElement) {
              lastElement.focus();
              event.preventDefault();
            } else if (!event.shiftKey && document.activeElement === lastElement) {
              firstElement.focus();
              event.preventDefault();
            }
          } else if (event.key === 'Escape') {
            closeModal(event);
          }
        };
        // Attach event listeners within this slideshow only.
        once('paragraphBundleExpand', '.pb__expand-grid', slideshowContainer).forEach(button => {
          button.addEventListener('click', openModal);
        });
        once('modalNavigation', '.pb__modal-next, .pb__modal-prev', slideshowContainer).forEach(button => {
          button.addEventListener('click', (event) => {
            const direction = button.classList.contains('pb__modal-next') ? 'next' : 'prev';
            navigateModal(direction);
            event.stopPropagation();
          });
        });
        // Close modal when clicking close button or background.
        slideshowContainer.addEventListener('click', (event) => {
          if (event.target.matches('.pb__close-grid') ||
            event.target.closest('.pb__close-grid') ||
            event.target === modalContent) {
            closeModal(event);
          }
        });
        // Set initial item count class.
        updateItemCountClass();
      });
    }
  };
})(Drupal, once);
