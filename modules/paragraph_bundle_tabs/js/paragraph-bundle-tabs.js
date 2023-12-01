/**
 * @file
 * Paragraph Bundle Tabs.
 *
 * Filename:     paragraph-bundle-tabs.js
 * Website:      https://www.flashwebcenter.com
 * Developer:    Alaa Haddad https://www.alaahaddad.com.
 */
((Drupal, settings, once) => {
  'use strict';

  // Function to hide all panes
  const hideAllPanes = panes => {
    panes.forEach(pane => pane.style.display = "none");
  };

  // Function to remove active class from buttons
  const removeActiveClassFromButtons = buttons => {
    buttons.forEach(button => button.classList.remove("pb-is-active"));
  };

  // Function to handle button click
  const handleButtonClick = button => {
    button.addEventListener('click', function() {
      const { id: btnId } = this;
      const paneId = `${btnId}-pane`;
      const currentPane = document.getElementById(paneId);
      const currentButton = document.getElementById(btnId);
      const parentId = `#pb-tab-${btnId.split('-')[2]}`;
      const tabPanes = document.querySelectorAll(`${parentId} .pb-tab-pane`);
      const tabButtons = document.querySelectorAll(`${parentId} .pb-tab-button`);
      hideAllPanes(tabPanes);
      removeActiveClassFromButtons(tabButtons);
      currentPane.style.display = "block";
      currentButton.classList.add("pb-is-active");
    });
  };

  // Main function
  const main = () => {
    const firstChildButtons = document.querySelectorAll('.pb-tabs-button>button:first-child');
    firstChildButtons.forEach(button => button.classList.add('pb-is-active'));
    const buttons = document.querySelectorAll('.pb-tabs-button>.pb-tab-button');
    buttons.forEach(button => handleButtonClick(button));
  };

  Drupal.behaviors.paragraphBundleTabs = {
    attach: (context, settings) => {
      main();
    }
  };
})(Drupal, drupalSettings, once);
