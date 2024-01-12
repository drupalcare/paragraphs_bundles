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
            pane.setAttribute('aria-hidden', 'true'); // Set aria-hidden attribute
          }
        });
      }

      // Function to remove active class from buttons
      function removeActiveClassFromButtons(buttons) {
        buttons.forEach(button => {
          if (button) {
            button.classList.remove('pb__active');
            button.setAttribute('aria-expanded', 'false'); // Set aria-expanded attribute
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
            button.setAttribute('aria-expanded', 'true');
          } else {
            console.warn(`Pane with ID ${paneId} not found.`);
          }
        });
      }

      const tabContainers = context.querySelectorAll('.pb__tabs-button > .pb__tab-button');
      tabContainers.forEach(container => {
        if (container) {
          handleTabClicks(container);
        }
      });

      // Activating the first child buttons
      const firstChildButtons = context.querySelectorAll('.pb__tabs-button > button:first-child');
      firstChildButtons.forEach(button => {
        if (button) {
          button.classList.add('pb__active');
          button.setAttribute('aria-expanded', 'true');
        }
      });
    }
  };
})(Drupal, drupalSettings, once);
