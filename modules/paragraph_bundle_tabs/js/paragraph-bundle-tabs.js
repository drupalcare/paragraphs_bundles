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
        panes.forEach(function(pane) {
          pane.style.display = 'none';
        });
      }

      // Function to remove active class from buttons
      function removeActiveClassFromButtons(buttons) {
        buttons.forEach(function(button) {
          button.classList.remove('pb-is-active');
        });
      }

      // Function to handle button click
      function handleButtonClick(button) {
        button.addEventListener('click', function() {
          const btnId = this.id;
          const paneId = `${btnId}-pane`;
          const currentPane = document.getElementById(paneId);
          const currentButton = document.getElementById(btnId);
          const parentId = `#pb-tab-${btnId.split('-')[2]}`;
          const tabPanes = document.querySelectorAll(`${parentId} .pb-tab-pane`);
          const tabButtons = document.querySelectorAll(`${parentId} .pb-tab-button`);
          hideAllPanes(tabPanes);
          removeActiveClassFromButtons(tabButtons);
          currentPane.style.display = 'block';
          currentButton.classList.add('pb-is-active');
        });
      }

      const buttons = context.querySelectorAll('.pb-tabs-button > .pb-tab-button');
      buttons.forEach(function(button) {
        handleButtonClick(button);
      });

      const firstChildButtons = context.querySelectorAll('.pb-tabs-button > button:first-child');
      firstChildButtons.forEach(function(button) {
        button.classList.add('pb-is-active');
      });
    }
  };
})(Drupal, drupalSettings, once);

