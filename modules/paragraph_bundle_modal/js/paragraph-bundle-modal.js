/**
 * @file
 * Paragraph Bundle Modal.
 *
 * Modal open/close, focus trap, Escape, backdrop dismiss, and WCAG dialog
 * semantics (aria-modal, inert, Drupal.announce).
 *
 * Filename:     paragraph-bundle-modal.js
 * Website:      https://www.flashwebcenter.com
 * Developer:    Alaa Haddad https://www.alaahaddad.com
 */
// phpcs:disable Drupal.NamingConventions.UpperCaseConstant
// phpcs:disable Generic.PHP.UpperCaseConstant
// phpcs:disable Squiz.WhiteSpace.OperatorSpacing

((Drupal, once) => {
  const PARAGRAPHS_BUNDLES = 'paragraphs-bundles';
  const PB_MODAL = 'pb-modal';
  const PB_MODAL__DIALOG = 'pb-modal__dialog';
  const PB_MODAL__BUTTON = 'pb-modal__button';
  const PB_MODAL__CONTENT = 'pb-modal__content';
  const PB_MODAL__HEADER = 'pb-modal__header';
  const PB_MODAL__INNER = 'pb-modal__inner';
  const PB_UI_BTN = 'pb-ui-btn';
  const PB_IS_OPEN = 'pb-is-open';

  const PARAGRAPHS_BUNDLES_PB_MODAL = `.${PARAGRAPHS_BUNDLES}.${PB_MODAL}`;
  const PARAGRAPHS_BUNDLES_PB_MODAL_PB_MODAL__DIALOG = `${PARAGRAPHS_BUNDLES_PB_MODAL} > .${PB_MODAL__DIALOG}`;

  const PB_MODAL_OWNED_OPEN_BUTTON = `:scope > .${PB_MODAL__BUTTON} > .${PB_UI_BTN}`;
  const PB_MODAL__DIALOG_OWNED_CONTENT = `:scope > .${PB_MODAL__CONTENT}`;
  const PB_MODAL__DIALOG_OWNED_CLOSE = `:scope > .${PB_MODAL__CONTENT} > .${PB_MODAL__INNER} > .${PB_MODAL__HEADER} > .${PB_UI_BTN}`;
  const FOCUSABLE_SELECTOR = [
    'a[href]',
    'area[href]',
    'button:not([disabled])',
    'input:not([disabled])',
    'textarea:not([disabled])',
    'select:not([disabled])',
    'details > summary:first-of-type',
    '[tabindex]:not([tabindex="-1"])',
  ].join(', ');

  const ONCE_PB_PARAGRAPH_BUNDLE_MODAL_DIALOG =
    'pb-paragraph-bundle-modal-dialog';

  /** @type {WeakMap<HTMLElement, () => void>} */
  const modalCleanupHandlers = new WeakMap();

  /** @type {{ root: HTMLElement, hide: () => void }|null} */
  let openModalInstance = null;

  const isVisibleFocusable = (element) => {
    if (!(element instanceof HTMLElement)) {
      return false;
    }

    if (element.hidden || element.getAttribute('aria-hidden') === 'true') {
      return false;
    }

    const style = window.getComputedStyle(element);

    return (
      style.display !== 'none' &&
      style.visibility !== 'hidden' &&
      element.getClientRects().length > 0
    );
  };

  /**
   * Inert page branches outside the open dialog (sibling-inert pattern).
   *
   * @param {HTMLElement} dialog
   * @param {Map<HTMLElement, boolean>} inertedElements
   */
  const applyDialogInert = (dialog, inertedElements) => {
    const inertElement = (element) => {
      if (!inertedElements.has(element)) {
        inertedElements.set(element, element.inert);
      }
      element.inert = true;
    };

    let branch = dialog;

    while (branch.parentElement && branch.parentElement !== document.body) {
      const parent = branch.parentElement;
      // Bound per iteration so the callback closes over a constant rather
      // than over the reassigned loop variable.
      const current = branch;
      Array.from(parent.children).forEach((sibling) => {
        if (sibling !== current && sibling instanceof HTMLElement) {
          inertElement(sibling);
        }
      });
      branch = parent;
    }

    Array.from(document.body.children).forEach((child) => {
      if (child instanceof HTMLElement && !child.contains(dialog)) {
        inertElement(child);
      }
    });
  };

  /**
   * @param {Map<HTMLElement, boolean>} inertedElements
   */
  const removeDialogInert = (inertedElements) => {
    inertedElements.forEach((wasInert, element) => {
      element.inert = wasInert;
    });
    inertedElements.clear();
  };

  /**
   * @param {HTMLElement} dialog
   *
   * @return {string}
   */
  const getDialogAccessibleName = (dialog) => {
    const labelledBy = dialog.getAttribute('aria-labelledby');
    if (labelledBy) {
      const label = document.getElementById(labelledBy);
      const labelText = label?.textContent?.trim();
      if (labelText) {
        return labelText;
      }
    }

    const ariaLabel = dialog.getAttribute('aria-label')?.trim();
    if (ariaLabel) {
      return ariaLabel;
    }

    return Drupal.t('Dialog');
  };

  Drupal.behaviors.paragraphBundleModal = {
    attach(context) {
      const dialogs = once(
        ONCE_PB_PARAGRAPH_BUNDLE_MODAL_DIALOG,
        PARAGRAPHS_BUNDLES_PB_MODAL_PB_MODAL__DIALOG,
        context,
      );

      dialogs.forEach((dialog) => {
        const root = dialog.closest(PARAGRAPHS_BUNDLES_PB_MODAL);

        if (!(root instanceof HTMLElement)) {
          return;
        }

        const abortController = new AbortController();
        const { signal } = abortController;

        const openButton = root.querySelector(PB_MODAL_OWNED_OPEN_BUTTON);
        const modalContent = dialog.querySelector(
          PB_MODAL__DIALOG_OWNED_CONTENT,
        );
        const closeButton = dialog.querySelector(PB_MODAL__DIALOG_OWNED_CLOSE);

        if (!(openButton instanceof HTMLElement) || !modalContent) {
          return;
        }

        /** @type {AbortController|null} */
        let sessionController = null;
        /** @type {HTMLElement[]} */
        let focusableElements = [];
        /** @type {Map<HTMLElement, boolean>} */
        const inertedElements = new Map();
        /** @type {HTMLElement|undefined} */
        let firstFocusableElement;
        /** @type {HTMLElement|undefined} */
        let lastFocusableElement;

        const refreshFocusableElements = () => {
          focusableElements = Array.from(
            dialog.querySelectorAll(FOCUSABLE_SELECTOR),
          ).filter(isVisibleFocusable);
          firstFocusableElement = focusableElements[0];
          lastFocusableElement =
            focusableElements[focusableElements.length - 1];
        };

        const hideModal = () => {
          sessionController?.abort();
          sessionController = null;
          removeDialogInert(inertedElements);

          dialog.classList.remove(PB_IS_OPEN);
          dialog.removeAttribute('aria-modal');
          dialog.setAttribute('aria-hidden', 'true');
          openButton.setAttribute('aria-expanded', 'false');
          openButton.focus();

          Drupal.announce(Drupal.t('Dialog closed'), 'polite');

          if (openModalInstance?.root === root) {
            openModalInstance = null;
          }

          if (!openModalInstance) {
            document.documentElement.classList.remove('pb-u-modal-open');
          }
        };

        const trapFocus = (event) => {
          if (!dialog.classList.contains(PB_IS_OPEN)) {
            return;
          }

          if (event.key === 'Escape') {
            hideModal();
            return;
          }

          if (event.key !== 'Tab') {
            return;
          }

          if (focusableElements.length === 0) {
            event.preventDefault();
            dialog.focus();
            return;
          }

          if (!dialog.contains(document.activeElement)) {
            firstFocusableElement?.focus();
            event.preventDefault();
            return;
          }

          if (event.shiftKey) {
            if (document.activeElement === firstFocusableElement) {
              lastFocusableElement?.focus();
              event.preventDefault();
            }
          } else if (document.activeElement === lastFocusableElement) {
            firstFocusableElement?.focus();
            event.preventDefault();
          }
        };

        const closeModalOnBackdropClick = (event) => {
          if (event.target === dialog) {
            hideModal();
          }
        };

        const showModal = (event) => {
          event.stopPropagation();

          if (openModalInstance && openModalInstance.root !== root) {
            openModalInstance.hide();
          }

          sessionController?.abort();
          sessionController = new AbortController();
          const sessionSignal = sessionController.signal;

          dialog.classList.add(PB_IS_OPEN);
          dialog.setAttribute('aria-modal', 'true');
          dialog.removeAttribute('aria-hidden');
          openButton.setAttribute('aria-expanded', 'true');
          document.documentElement.classList.add('pb-u-modal-open');

          applyDialogInert(dialog, inertedElements);

          refreshFocusableElements();
          if (firstFocusableElement) {
            firstFocusableElement.focus();
          } else {
            dialog.focus();
          }

          Drupal.announce(
            Drupal.t('@title opened', {
              '@title': getDialogAccessibleName(dialog),
            }),
            'polite',
          );

          document.addEventListener('keydown', trapFocus, {
            signal: sessionSignal,
          });
          document.addEventListener('click', closeModalOnBackdropClick, {
            capture: true,
            signal: sessionSignal,
          });

          openModalInstance = { root, hide: hideModal };
        };

        openButton.addEventListener('click', showModal, { signal });
        closeButton?.addEventListener('click', hideModal, { signal });

        modalCleanupHandlers.set(dialog, () => {
          if (openModalInstance?.root === root) {
            hideModal();
          }
          abortController.abort();
        });
      });
    },

    detach(context, settings, trigger) {
      if (trigger !== 'unload') {
        return;
      }

      once
        .remove(
          ONCE_PB_PARAGRAPH_BUNDLE_MODAL_DIALOG,
          PARAGRAPHS_BUNDLES_PB_MODAL_PB_MODAL__DIALOG,
          context,
        )
        .forEach((dialog) => {
          modalCleanupHandlers.get(dialog)?.();
          modalCleanupHandlers.delete(dialog);
        });
    },
  };
})(Drupal, once);
