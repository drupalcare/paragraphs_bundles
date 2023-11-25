/**
 * @file
 * Paragraph Bundle Tabs.
 *
 * Filename:     paragraph-bundle-tabs.js
 * Website:      https://www.flashwebcenter.com
 * Developer:    Alaa Haddad https://www.alaahaddad.com.
 */
((Drupal, once) => {
  'use strict';
  const firstChildButtons = document.querySelectorAll('.pb-tabs-button>button:first-child');
  firstChildButtons.forEach(button => button.classList.add('pb-is-active'));

  Drupal.behaviors.paragraphBundleTabs = {
    attach: (context, settings) => {
      const hideAllPanes = panes => panes.forEach(pane => pane.style.display = "none");

      const removeActiveClassFromButtons = buttons => buttons.forEach(button => button.classList.remove("pb-is-active"));

      const buttons = document.querySelectorAll('.pb-tabs-button>.pb-tab-button');

      buttons.forEach(button => {

        button.addEventListener('click', () => {

          const { id: btnId } = button;
          const pneId = `${btnId}-pane`;
          const currPne = document.getElementById(pneId);
          const currBtn = document.getElementById(btnId);
          const pId = `#pb-tab-${btnId.split('-')[2]}`;
          const tabPanes = document.querySelectorAll(`${pId} .pb-tab-pane`);
          const tabButtons = document.querySelectorAll(`${pId} .pb-tab-button`);

          hideAllPanes(tabPanes);

          removeActiveClassFromButtons(tabButtons);

          currPne.style.display = "block";
          currBtn.classList.add("pb-is-active");

        });

      });

    }

  }

})(Drupal, once);

