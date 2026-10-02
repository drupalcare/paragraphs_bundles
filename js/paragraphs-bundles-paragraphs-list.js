/**
 * @file
 * Paragraphs List: confirm before the bulk delete submits, and the Columns
 * control that hides and shows table columns.
 */

(function (Drupal, once) {
  'use strict';

  const STORAGE_KEY = 'pbParagraphsListHiddenColumns';

  /**
   * Reads the hidden column ids; never throws when storage is blocked.
   *
   * @return {string[]}
   *   The hidden column ids, empty when nothing is stored or storage fails.
   */
  function readHidden() {
    try {
      const stored = JSON.parse(window.localStorage.getItem(STORAGE_KEY) || '[]');
      return Array.isArray(stored) ? stored.filter(function (id) {
        return typeof id === 'string';
      }) : [];
    }
    catch (e) {
      return [];
    }
  }

  /**
   * Remembers the hidden column ids; silently skipped when storage fails.
   *
   * @param {string[]} hidden
   *   The hidden column ids.
   */
  function writeHidden(hidden) {
    try {
      window.localStorage.setItem(STORAGE_KEY, JSON.stringify(hidden));
    }
    catch (e) {
      // Storage is blocked or full: the choice simply is not remembered.
    }
  }

  /**
   * Shows or hides every cell of one column.
   *
   * @param {HTMLTableElement} table
   *   The table.
   * @param {string} column
   *   The column id.
   * @param {boolean} hidden
   *   Whether the column is hidden.
   */
  function applyColumn(table, column, hidden) {
    table.querySelectorAll('[data-pb-col="' + column + '"]').forEach(function (cell) {
      cell.hidden = hidden;
    });
  }

  /**
   * Asks for confirmation when "Delete Selected" is clicked.
   *
   * Cancelling the dialog stops the submit, so nothing is deleted.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.pbParagraphsListConfirm = {
    attach: function (context) {
      once('pb-paragraphs-list-confirm', '[data-pb-paragraphs-list-delete]', context).forEach(function (button) {
        button.addEventListener('click', function (event) {
          if (!window.confirm(Drupal.t('Are you sure you want to delete the selected paragraphs?'))) {
            event.preventDefault();
          }
        });
      });
    }
  };

  /**
   * Builds the Columns checkboxes: real checkboxes in a fieldset, always shown.
   *
   * Without JavaScript they never appear and every column shows. The select
   * column, the PID and Edit have no checkbox, so they stay visible.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.pbParagraphsListColumns = {
    attach: function (context) {
      once('pb-paragraphs-list-columns', '#paragraphs-table', context).forEach(function (table) {
        const heads = Array.from(table.querySelectorAll('thead th[data-pb-col]'));
        const anchor = table.closest('.pb-paragraphs-list__scroll') || table;
        if (!heads.length || !anchor.parentNode) {
          return;
        }

        const hidden = readHidden();
        // Always visible, built here so that without JavaScript (where the
        // boxes would do nothing) they do not appear at all.
        const box = document.createElement('fieldset');
        box.className = 'pb-paragraphs-list__columns';
        const legend = document.createElement('legend');
        legend.className = 'visually-hidden';
        legend.textContent = Drupal.t('Show columns');
        box.appendChild(legend);

        heads.forEach(function (th, index) {
          const column = th.getAttribute('data-pb-col');
          const name = th.getAttribute('data-pb-col-label') || column;
          const id = 'pb-paragraphs-list-col-' + index;
          const label = document.createElement('label');
          label.setAttribute('for', id);
          const checkbox = document.createElement('input');
          checkbox.type = 'checkbox';
          checkbox.id = id;
          checkbox.checked = hidden.indexOf(column) === -1;
          label.appendChild(checkbox);
          label.appendChild(document.createTextNode(name));
          box.appendChild(label);

          applyColumn(table, column, !checkbox.checked);
          checkbox.addEventListener('change', function () {
            const now = readHidden().filter(function (item) {
              return item !== column;
            });
            if (!checkbox.checked) {
              now.push(column);
            }
            writeHidden(now);
            applyColumn(table, column, !checkbox.checked);
            Drupal.announce(checkbox.checked
              ? Drupal.t('Column @name shown', {'@name': name})
              : Drupal.t('Column @name hidden', {'@name': name}));
          });
        });

        const bar = anchor.parentNode.querySelector('.pb-paragraphs-list__bar');
        if (bar) {
          bar.appendChild(box);
        }
        else {
          anchor.parentNode.insertBefore(box, anchor);
        }
      });
    }
  };

})(Drupal, once);
