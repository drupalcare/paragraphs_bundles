/**
 * @file
 * Paragraph Bundle Alert.
 *
 * Supports: close button, countdown auto-close, date-range auto-hide,
 * and "don't show again today/session" via localStorage/sessionStorage.
 *
 * Filename:     paragraph-bundle-alert.js
 * Website:      https://www.flashwebcenter.com
 * Developer:    Alaa Haddad https://www.alaahaddad.com
 */
// phpcs:disable Drupal.NamingConventions.UpperCaseConstant
// phpcs:disable Generic.PHP.UpperCaseConstant
// phpcs:disable Squiz.WhiteSpace.OperatorSpacing

((Drupal, once) => {
  'use strict';

  const PARAGRAPHS_BUNDLES = 'paragraphs-bundles';
  const PB_ALERT = 'pb-alert';
  const PB_ALERT__CLOSE = 'pb-alert__close';
  const PB_ALERT__CONTENT = 'pb-alert__content';
  const PB_IS_CLOSED = 'pb-is-closed';

  const PARAGRAPHS_BUNDLES_PB_ALERT =
    `.${PARAGRAPHS_BUNDLES}.${PB_ALERT}`;

  const PB_ALERT_OWNED_CLOSE =
    `:scope > .pb-ui-inner .${PB_ALERT__CLOSE}`;
  const PB_ALERT_OWNED_CONTENT =
    `:scope > .pb-ui-inner > .pb-content__full.${PB_ALERT__CONTENT}`;
  const PB_ALERT_OWNED_COUNTDOWN_LABEL =
    `:scope [data-pb-countdown-label]`;

  const FOCUSABLE_SELECTOR =
    'a[href], button, input, textarea, select, [tabindex]:not([tabindex="-1"])';
  const DATA_PB_ALERT_TABINDEX = 'data-pb-alert-tabindex';

  const STORAGE_KEY_PREFIX = 'pb-alert-closed-';

  const ONCE_PB_PARAGRAPH_BUNDLE_ALERT =
    'pb-paragraph-bundle-alert';

  /** @type {WeakMap<HTMLElement, AbortController>} */
  const alertAbortControllers = new WeakMap();

  /** @type {WeakMap<HTMLElement, number[]>} */
  const alertTimeoutIds = new WeakMap();

  /**
   * @return {string}
   */
  const getTodayKey = () => {
    const d = new Date();
    return `${d.getUTCFullYear()}-${String(d.getUTCMonth() + 1).padStart(2, '0')}-${String(d.getUTCDate()).padStart(2, '0')}`;
  };

  /**
   * @param {HTMLElement} alertBox
   *
   * @return {boolean}
   */
  const isClosedByPersist = (alertBox) => {
    const id = alertBox.id || '';
    const persist = alertBox.getAttribute('data-close-persist') || 'none';
    if (persist === 'none') {
      return false;
    }
    if (persist === 'today') {
      const key = `${STORAGE_KEY_PREFIX}${id}-${getTodayKey()}`;
      try {
        return localStorage.getItem(key) === '1';
      }
      catch {
        return false;
      }
    }
    if (persist === 'session') {
      const key = `${STORAGE_KEY_PREFIX}${id}`;
      try {
        return sessionStorage.getItem(key) === '1';
      }
      catch {
        return false;
      }
    }
    return false;
  };

  /**
   * @param {HTMLElement} alertBox
   */
  const setClosedPersist = (alertBox) => {
    const id = alertBox.id || '';
    const persist = alertBox.getAttribute('data-close-persist') || 'none';
    try {
      if (persist === 'today') {
        const key = `${STORAGE_KEY_PREFIX}${id}-${getTodayKey()}`;
        localStorage.setItem(key, '1');
      }
      else if (persist === 'session') {
        const key = `${STORAGE_KEY_PREFIX}${id}`;
        sessionStorage.setItem(key, '1');
      }
    }
    catch {
      // Storage can be unavailable in private contexts; dismissal still works.
    }
  };

  /**
   * @param {HTMLElement} alertBox
   * @param {number} timeoutId
   */
  const trackTimeout = (alertBox, timeoutId) => {
    const existing = alertTimeoutIds.get(alertBox) ?? [];
    existing.push(timeoutId);
    alertTimeoutIds.set(alertBox, existing);
  };

  /**
   * @param {HTMLElement} alertBox
   * @param {number} delayMs
   * @param {() => void} callback
   */
  const scheduleTimeout = (alertBox, delayMs, callback) => {
    const timeoutId = window.setTimeout(callback, delayMs);
    trackTimeout(alertBox, timeoutId);
  };

  /**
   * @param {HTMLElement} alertBox
   */
  const clearAlertTimeouts = (alertBox) => {
    const timeoutIds = alertTimeoutIds.get(alertBox) ?? [];
    timeoutIds.forEach((timeoutId) => {
      window.clearTimeout(timeoutId);
    });
    alertTimeoutIds.delete(alertBox);
  };

  /**
   * @param {NodeListOf<Element>|Element[]} focusableElements
   * @param {boolean} enabled
   */
  const updateTabindex = (focusableElements, enabled) => {
    focusableElements.forEach((el) => {
      if (!(el instanceof HTMLElement)) {
        return;
      }

      if (!enabled) {
        if (!el.hasAttribute(DATA_PB_ALERT_TABINDEX)) {
          el.setAttribute(
            DATA_PB_ALERT_TABINDEX,
            el.getAttribute('tabindex') ?? '',
          );
        }
        el.setAttribute('tabindex', '-1');
        return;
      }

      if (!el.hasAttribute(DATA_PB_ALERT_TABINDEX)) {
        return;
      }

      const originalTabindex = el.getAttribute(DATA_PB_ALERT_TABINDEX);
      if (originalTabindex) {
        el.setAttribute('tabindex', originalTabindex);
      }
      else {
        el.removeAttribute('tabindex');
      }
      el.removeAttribute(DATA_PB_ALERT_TABINDEX);
    });
  };

  /**
   * @param {HTMLElement} alertBox
   * @param {HTMLButtonElement|null} closeButton
   * @param {NodeListOf<Element>} focusableElements
   * @param {boolean} announce
   */
  const closeAlert = (
    alertBox,
    closeButton,
    focusableElements,
    announce = false,
  ) => {
    clearAlertTimeouts(alertBox);
    alertBox.classList.add(PB_IS_CLOSED);
    alertBox.setAttribute('aria-hidden', 'true');
    alertBox.inert = true;
    if (closeButton) {
      closeButton.setAttribute('aria-expanded', 'false');
    }
    updateTabindex(focusableElements, false);
    if (announce && typeof Drupal.announce === 'function') {
      Drupal.announce(Drupal.t('Alert dismissed'), 'polite');
    }
  };

  /**
   * @param {HTMLElement} alertBox
   * @param {HTMLButtonElement|null} closeButton
   * @param {NodeListOf<Element>} focusableElements
   */
  const openAlert = (alertBox, closeButton, focusableElements) => {
    alertBox.classList.remove(PB_IS_CLOSED);
    alertBox.removeAttribute('aria-hidden');
    alertBox.inert = false;
    if (closeButton) {
      closeButton.setAttribute('aria-expanded', 'true');
    }
    updateTabindex(focusableElements, true);
  };

  /**
   * @param {HTMLElement} alertBox
   * @param {AbortSignal} signal
   */
  const initAlert = (alertBox, signal) => {
    const closeButton = alertBox.querySelector(PB_ALERT_OWNED_CLOSE);
    const paragraphContent = alertBox.querySelector(PB_ALERT_OWNED_CONTENT);
    const focusableElements = paragraphContent
      ? paragraphContent.querySelectorAll(FOCUSABLE_SELECTOR)
      : [];

    if (isClosedByPersist(alertBox)) {
      closeAlert(alertBox, closeButton, focusableElements);
      return;
    }

    if (alertBox.classList.contains(PB_IS_CLOSED)) {
      alertBox.setAttribute('aria-hidden', 'true');
      alertBox.inert = true;
      if (closeButton) {
        closeButton.setAttribute('aria-expanded', 'false');
      }
      updateTabindex(focusableElements, false);
    }
    else {
      alertBox.removeAttribute('aria-hidden');
      alertBox.inert = false;
      if (closeButton) {
        closeButton.setAttribute('aria-expanded', 'true');
      }
      updateTabindex(focusableElements, true);
    }

    const dismissalType = alertBox.getAttribute('data-dismissal-type') || 'none';

    if (dismissalType === 'date_range') {
      const endTs = parseInt(alertBox.getAttribute('data-date-range-end'), 10) || 0;
      if (endTs > 0) {
        const checkEnd = () => {
          if (signal.aborted) {
            return;
          }
          const nowSec = Math.floor(Date.now() / 1000);
          if (nowSec >= endTs) {
            closeAlert(alertBox, closeButton, focusableElements);
            return;
          }
          const delaySec = Math.min(endTs - nowSec, 30);
          scheduleTimeout(alertBox, delaySec * 1000, checkEnd);
        };
        checkEnd();
      }
    }

    let countdownSeconds = parseInt(
      alertBox.getAttribute('data-countdown-seconds'),
      10,
    ) || 0;
    const showCountdownLabel =
      alertBox.getAttribute('data-show-countdown-label') === '1';
    const countdownLabelEl = alertBox.querySelector(
      PB_ALERT_OWNED_COUNTDOWN_LABEL,
    );
    if (dismissalType === 'countdown' && countdownSeconds <= 0 && countdownLabelEl) {
      const fromLabel = parseInt(countdownLabelEl.textContent ?? '', 10);
      if (!Number.isNaN(fromLabel) && fromLabel > 0) {
        countdownSeconds = fromLabel;
      }
    }

    if (dismissalType === 'countdown' && countdownSeconds > 0) {
      let remaining = countdownSeconds;
      if (countdownLabelEl) {
        countdownLabelEl.textContent = String(remaining);
      }

      const tick = () => {
        if (signal.aborted) {
          return;
        }
        remaining -= 1;
        if (countdownLabelEl) {
          countdownLabelEl.textContent = String(remaining > 0 ? remaining : 0);
        }
        if (remaining <= 0) {
          closeAlert(alertBox, closeButton, focusableElements);
          return;
        }
        scheduleTimeout(alertBox, 1000, tick);
      };

      scheduleTimeout(alertBox, 1000, tick);
    }

    if (closeButton) {
      closeButton.addEventListener('click', () => {
        if (alertBox.classList.contains(PB_IS_CLOSED)) {
          openAlert(alertBox, closeButton, focusableElements);
        }
        else {
          setClosedPersist(alertBox);
          closeAlert(alertBox, closeButton, focusableElements, true);
        }
      }, { signal });
    }
  };

  /**
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.paragraphBundleAlert = {
    attach(context, settings) {
      once(ONCE_PB_PARAGRAPH_BUNDLE_ALERT, PARAGRAPHS_BUNDLES_PB_ALERT, context)
        .forEach((alertBox) => {
          const abortController = new AbortController();
          alertAbortControllers.set(alertBox, abortController);
          initAlert(alertBox, abortController.signal);
        });
    },

    /**
     * @param {Document|HTMLElement} context
     * @param {object} settings
     * @param {string} trigger
     */
    detach(context, settings, trigger) {
      if (trigger !== 'unload') {
        return;
      }

      once.remove(
        ONCE_PB_PARAGRAPH_BUNDLE_ALERT,
        PARAGRAPHS_BUNDLES_PB_ALERT,
        context,
      ).forEach((alertBox) => {
        const abortController = alertAbortControllers.get(alertBox);
        if (abortController) {
          abortController.abort();
          alertAbortControllers.delete(alertBox);
        }
        clearAlertTimeouts(alertBox);
      });
    },
  };
})(Drupal, once);
