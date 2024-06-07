/**
 * @file
 * Paragraph Bundle Tabs.
 *
 * Filename:     paragraph-bundle-tabs.js
 * Website:      https://www.flashwebcenter.com
 * Developer:    Alaa Haddad https://www.alaahaddad.com.
 */
((Drupal, drupalSettings, once) => {
  'use strict';

  Drupal.behaviors.paragraphBundleTabs = {
    attach: function (context, settings) {
      // Function to hide all panes
      function hideAllPanes(panes) {
        panes.forEach(pane => {
          if (pane) {
            pane.style.display = 'none';
            pane.setAttribute('aria-hidden', 'true');
          }
        });
      }

      // Function to remove active class from buttons
      function removeActiveClassFromButtons(buttons) {
        buttons.forEach(button => {
          if (button) {
            button.classList.remove('pb__active');
            button.setAttribute('tabindex', '-1');
            button.setAttribute('aria-selected', 'false');
            button.setAttribute('aria-expanded', 'false');
          }
        });
      }

      // Function to handle tab clicks with event delegation
      function handleTabClicks(tabContainer) {
        tabContainer.addEventListener('click', event => {
          const button = event.target.closest('.pb__tab-button');
          if (!button) return;

          const btnId = button.id;
          const paneId = `${btnId}-pane`;
          const currentPane = document.getElementById(paneId);
          const parentId = `#pb__tab-${btnId.split('-')[1]}`;
          const tabPanes = document.querySelectorAll(`${parentId} .pb__tab-pane`);
          const tabButtons = document.querySelectorAll(`${parentId} .pb__tab-button`);

          if (currentPane) {
            hideAllPanes(tabPanes);
            removeActiveClassFromButtons(tabButtons);
            currentPane.style.display = 'block';
            currentPane.setAttribute('aria-hidden', 'false');
            button.classList.add('pb__active');
            button.removeAttribute('tabindex');
            button.setAttribute('aria-selected', 'true');
            button.setAttribute('aria-expanded', 'true');
            button.focus();
          } else {
            console.warn(`Pane with ID ${paneId} not found.`);
          }
        });
      }

      // Function to handle keyboard navigation
      function handleKeyboardNavigation(tabButtons) {
        tabButtons.forEach((tab, index) => {
          tab.addEventListener('keydown', (event) => {
            const currentIndex = index;
            let newIndex;

            switch (event.key) {
              case 'ArrowRight':
                newIndex = (currentIndex + 1) % tabButtons.length;
                break;
              case 'ArrowLeft':
                newIndex = (currentIndex - 1 + tabButtons.length) % tabButtons.length;
                break;
              case 'Home':
                newIndex = 0;
                break;
              case 'End':
                newIndex = tabButtons.length - 1;
                break;
              default:
                return;
            }

            tabButtons.forEach(t => t.setAttribute('tabindex', '-1'));
            tabButtons[newIndex].setAttribute('tabindex', '0');
            tabButtons[newIndex].focus();
            event.preventDefault();
          });
        });
      }

      const tabContainers = once('paragraphBundleTabs', '.pb__tabs-bundle', context);
      tabContainers.forEach(container => {
        const tabButtons = container.querySelectorAll('.pb__tab-button');
        const tabPanes = container.querySelectorAll('.pb__tab-pane');

        // Activate the first tab and pane by default
        if (tabButtons.length > 0) {
          tabButtons[0].classList.add('pb__active');
          tabButtons[0].setAttribute('aria-selected', 'true');
          tabButtons[0].setAttribute('aria-expanded', 'true');
          tabButtons[0].setAttribute('tabindex', '0');
          if (tabPanes.length > 0) {
            tabPanes[0].style.display = 'block';
            tabPanes[0].setAttribute('aria-hidden', 'false');
          }
        }

        // Initialize tabindex for other buttons
        tabButtons.forEach((button, index) => {
          if (!button.classList.contains('pb__active')) {
            button.setAttribute('tabindex', '-1');
          }

          // Attach click event listener
          button.addEventListener('click', handleTabClicks.bind(null, container));
        });

        // Handle keyboard navigation
        handleKeyboardNavigation(tabButtons);
      });
    }
  };
})(Drupal, drupalSettings, once);



