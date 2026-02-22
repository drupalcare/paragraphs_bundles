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
((Drupal, once) => {
  'use strict';

  const STORAGE_KEY_PREFIX = 'pb-alert-closed-';

  function getTodayKey() {
    const d = new Date();
    return `${d.getUTCFullYear()}-${String(d.getUTCMonth() + 1).padStart(2, '0')}-${String(d.getUTCDate()).padStart(2, '0')}`;
  }

  function isClosedByPersist(alertBox) {
    const id = alertBox.id || '';
    const persist = alertBox.getAttribute('data-close-persist') || 'none';
    if (persist === 'none') return false;
    if (persist === 'today') {
      const key = `${STORAGE_KEY_PREFIX}${id}-${getTodayKey()}`;
      return localStorage.getItem(key) === '1';
    }
    if (persist === 'session') {
      const key = `${STORAGE_KEY_PREFIX}${id}`;
      return sessionStorage.getItem(key) === '1';
    }
    return false;
  }

  function setClosedPersist(alertBox) {
    const id = alertBox.id || '';
    const persist = alertBox.getAttribute('data-close-persist') || 'none';
    if (persist === 'today') {
      const key = `${STORAGE_KEY_PREFIX}${id}-${getTodayKey()}`;
      localStorage.setItem(key, '1');
    } else if (persist === 'session') {
      const key = `${STORAGE_KEY_PREFIX}${id}`;
      sessionStorage.setItem(key, '1');
    }
  }

  function closeAlert(alertBox, closeButton, focusableElements) {
    alertBox.classList.add('alert-is-closed');
    alertBox.setAttribute('aria-hidden', 'true');
    if (closeButton) {
      closeButton.setAttribute('aria-expanded', 'false');
    }
    if (focusableElements && focusableElements.length) {
      focusableElements.forEach((el) => el.setAttribute('tabindex', '-1'));
    }
  }

  function openAlert(alertBox, closeButton, focusableElements) {
    alertBox.classList.remove('alert-is-closed');
    alertBox.removeAttribute('aria-hidden');
    if (closeButton) {
      closeButton.setAttribute('aria-expanded', 'true');
    }
    if (focusableElements && focusableElements.length) {
      focusableElements.forEach((el) => el.setAttribute('tabindex', '0'));
    }
  }

  Drupal.behaviors.paragraphBundleAlert = {
    attach(context, settings) {
      const alertBoxes = once('paragraphBundleAlert', '.paragraph--type--alert-bundle', context);
      if (!alertBoxes.length) {
        return;
      }

      alertBoxes.forEach((alertBox) => {
        const closeButton = alertBox.querySelector('button.pb__close-alert');
        const paragraphContent = alertBox.querySelector('.pb__content-full');
        const focusableElements = paragraphContent
          ? paragraphContent.querySelectorAll('a, button, input, textarea, select')
          : [];

        const updateTabindex = (state) => {
          focusableElements.forEach((el) => {
            el.setAttribute('tabindex', state ? '0' : '-1');
          });
        };

        // If user previously closed with "today" or "session", start closed.
        if (isClosedByPersist(alertBox)) {
          closeAlert(alertBox, closeButton, focusableElements);
          return;
        }

        // Initialize from server-rendered state (e.g. date range hidden).
        if (alertBox.classList.contains('alert-is-closed')) {
          alertBox.setAttribute('aria-hidden', 'true');
          if (closeButton) closeButton.setAttribute('aria-expanded', 'false');
          updateTabindex(false);
        } else {
          alertBox.removeAttribute('aria-hidden');
          if (closeButton) closeButton.setAttribute('aria-expanded', 'true');
          updateTabindex(true);
        }

        const dismissalType = alertBox.getAttribute('data-dismissal-type') || 'none';

        // Date range: auto-hide when end date arrives while page is open.
        if (dismissalType === 'date_range') {
          const endTs = parseInt(alertBox.getAttribute('data-date-range-end'), 10) || 0;
          if (endTs > 0) {
            const checkEnd = () => {
              const nowSec = Math.floor(Date.now() / 1000);
              if (nowSec >= endTs) {
                closeAlert(alertBox, closeButton, focusableElements);
                return;
              }
              // Re-check in 30 s or when end arrives, whichever is sooner.
              const delaySec = Math.min(endTs - nowSec, 30);
              window.setTimeout(checkEnd, delaySec * 1000);
            };
            checkEnd();
          }
        }

        // Countdown: auto-close after N seconds.
        let countdownSeconds = parseInt(alertBox.getAttribute('data-countdown-seconds'), 10) || 0;
        const showCountdownLabel = alertBox.getAttribute('data-show-countdown-label') === '1';
        const countdownLabelEl = alertBox.querySelector('[data-pb-countdown-label]');
        // Fallback: if data attribute is 0/missing but label shows a number (e.g. caching), use it.
        if (dismissalType === 'countdown' && countdownSeconds <= 0 && countdownLabelEl) {
          const fromLabel = parseInt(countdownLabelEl.textContent, 10);
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
            remaining -= 1;
            if (countdownLabelEl) {
              countdownLabelEl.textContent = String(remaining > 0 ? remaining : 0);
            }
            if (remaining <= 0) {
              closeAlert(alertBox, closeButton, focusableElements);
              return;
            }
            window.setTimeout(tick, 1000);
          };

          window.setTimeout(tick, 1000);
        }

        // Close button: toggle and optionally persist.
        if (closeButton) {
          closeButton.addEventListener('click', () => {
            if (alertBox.classList.contains('alert-is-closed')) {
              openAlert(alertBox, closeButton, focusableElements);
            } else {
              setClosedPersist(alertBox);
              closeAlert(alertBox, closeButton, focusableElements);
            }
          });
        }
      });
    },
  };
})(Drupal, once);
