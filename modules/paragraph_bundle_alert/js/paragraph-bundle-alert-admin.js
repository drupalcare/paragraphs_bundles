/**
 * @file
 * Paragraph Bundle Alert – admin form: show/hide fields by Dismissal type,
 * and allow clearing date range fields.
 *
 * - None: hide date range and countdown fields.
 * - Date range: show Start date, End date; hide countdown fields.
 * - Countdown: show Countdown (seconds), Show countdown label; hide date range.
 *
 * "When user closes" is shown for all dismissal types.
 */
((Drupal, once) => {
  'use strict';

  const DISMISSAL_SELECT = 'select[name*="[pb_display_alert_dismissal_type]"]';
  const DATE_RANGE_FIELDS = [
    '.field--name-pb-display-alert-start-date',
    '.field--name-pb-display-alert-end-date',
  ];
  const COUNTDOWN_FIELDS = [
    '.field--name-pb-display-alert-countdown-sec',
    '.field--name-pb-display-alert-show-cdown-lbl',
  ];
  const DATE_RANGE_WRAPPER_SELECTOR = DATE_RANGE_FIELDS.join(', ');

  function toggleFields(container, value) {
    const dateWrappers = DATE_RANGE_FIELDS.map((sel) => container.querySelector(sel)).filter(Boolean);
    const countdownWrappers = COUNTDOWN_FIELDS.map((sel) => container.querySelector(sel)).filter(Boolean);

    if (value === 'date_range') {
      dateWrappers.forEach((el) => { el.style.display = ''; });
      countdownWrappers.forEach((el) => { el.style.display = 'none'; });
    } else if (value === 'countdown') {
      dateWrappers.forEach((el) => { el.style.display = 'none'; });
      countdownWrappers.forEach((el) => { el.style.display = ''; });
    } else {
      dateWrappers.forEach((el) => { el.style.display = 'none'; });
      countdownWrappers.forEach((el) => { el.style.display = 'none'; });
    }
  }

  function initDismissalToggle(select) {
    const container = select.closest('.pb__tab-display') || select.closest('.horizontal-tabs-pane') || select.closest('details');
    if (!container) return;

    const value = (select.value || 'none').trim();
    toggleFields(container, value);

    select.addEventListener('change', () => {
      toggleFields(container, (select.value || 'none').trim());
    });
  }

  /**
   * Get timestamp from a date-range field wrapper (date + time inputs).
   */
  function getTimestampFromDateField(wrapper) {
    const dateInput = wrapper.querySelector('input[type="date"]');
    const timeInput = wrapper.querySelector('input[type="time"]');
    if (!dateInput || !dateInput.value) return null;
    const dateStr = dateInput.value;
    const timeStr = (timeInput && timeInput.value) ? timeInput.value : '00:00:00';
    const ms = Date.parse(dateStr + 'T' + timeStr);
    return Number.isNaN(ms) ? null : ms;
  }

  /**
   * If dismissal is date_range, check start < end. Return true if invalid (block submit).
   */
  function isDateRangeInvalid(container) {
    const startWrapper = container.querySelector(DATE_RANGE_FIELDS[0]);
    const endWrapper = container.querySelector(DATE_RANGE_FIELDS[1]);
    if (!startWrapper || !endWrapper) return false;
    const startTs = getTimestampFromDateField(startWrapper);
    const endTs = getTimestampFromDateField(endWrapper);
    if (startTs == null || endTs == null) return false;
    return startTs >= endTs;
  }

  /**
   * Clear date range inputs (start + end) in this container.
   */
  function clearDateRangeInContainer(container) {
    DATE_RANGE_FIELDS.forEach((sel) => {
      const wrapper = container.querySelector(sel);
      if (!wrapper) return;
      wrapper.querySelectorAll('input[type="date"], input[type="time"]').forEach((input) => {
        input.value = '';
        input.dispatchEvent(new Event('change', { bubbles: true }));
        input.dispatchEvent(new Event('input', { bubbles: true }));
      });
    });
  }

  /**
   * Clear countdown inputs (seconds + show label checkbox) in this container.
   */
  function clearCountdownInContainer(container) {
    const secWrapper = container.querySelector(COUNTDOWN_FIELDS[0]);
    if (secWrapper) {
      const numInput = secWrapper.querySelector('input[type="number"]');
      if (numInput) {
        numInput.value = '';
        numInput.dispatchEvent(new Event('change', { bubbles: true }));
        numInput.dispatchEvent(new Event('input', { bubbles: true }));
      }
    }
    const lblWrapper = container.querySelector(COUNTDOWN_FIELDS[1]);
    if (lblWrapper) {
      const checkbox = lblWrapper.querySelector('input[type="checkbox"]');
      if (checkbox) {
        checkbox.checked = false;
        checkbox.dispatchEvent(new Event('change', { bubbles: true }));
      }
    }
  }

  function addSubmitClearHandler(form) {
    form.addEventListener('submit', (event) => {
      // When date range is selected, end date must be after start date.
      const invalidContainers = [];
      form.querySelectorAll(DISMISSAL_SELECT).forEach((select) => {
        const value = (select.value || 'none').trim();
        if (value !== 'date_range') return;
        const container = select.closest('.pb__tab-display') || select.closest('.horizontal-tabs-pane') || select.closest('details');
        if (container && isDateRangeInvalid(container)) {
          invalidContainers.push(container);
        }
      });
      if (invalidContainers.length > 0) {
        event.preventDefault();
        event.stopPropagation();
        alert(Drupal.t('End date must be after start date.'));
        return;
      }

      form.querySelectorAll(DISMISSAL_SELECT).forEach((select) => {
        const container = select.closest('.pb__tab-display') || select.closest('.horizontal-tabs-pane') || select.closest('details');
        if (!container) return;
        const value = (select.value || 'none').trim();
        if (value === 'countdown') {
          clearDateRangeInContainer(container);
        } else if (value === 'date_range') {
          clearCountdownInContainer(container);
        } else {
          clearDateRangeInContainer(container);
          clearCountdownInContainer(container);
        }
      });
    }, false);
  }

  function addClearButton(wrapper) {
    const dateInput = wrapper.querySelector('input[type="date"]');
    const timeInput = wrapper.querySelector('input[type="time"]');
    if (!dateInput && !timeInput) return;

    const label = wrapper.querySelector('.fieldset__label, .form-item__label');
    const labelText = (label && label.textContent) ? label.textContent.trim() : '';
    const clearLabel = Drupal.t('Clear');

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'button button--small paragraph-bundle-alert-clear-date';
    btn.textContent = clearLabel;
    btn.setAttribute('aria-label', clearLabel + (labelText ? ' ' + labelText : ''));

    btn.addEventListener('click', () => {
      if (dateInput) {
        dateInput.value = '';
        dateInput.dispatchEvent(new Event('change', { bubbles: true }));
        dateInput.dispatchEvent(new Event('input', { bubbles: true }));
      }
      if (timeInput) {
        timeInput.value = '';
        timeInput.dispatchEvent(new Event('change', { bubbles: true }));
        timeInput.dispatchEvent(new Event('input', { bubbles: true }));
      }
    });

    const fieldset = wrapper.querySelector('fieldset');
    const legend = wrapper.querySelector('.fieldset__legend, legend');
    if (fieldset && legend && legend.nextElementSibling) {
      legend.parentNode.insertBefore(btn, legend.nextElementSibling);
    } else if (fieldset) {
      fieldset.insertBefore(btn, fieldset.firstChild);
    } else {
      const first = wrapper.querySelector('.form-item, .form-datetime-wrapper');
      if (first) {
        first.parentNode.insertBefore(btn, first);
      } else {
        wrapper.insertBefore(btn, wrapper.firstChild);
      }
    }
  }

  Drupal.behaviors.paragraphBundleAlertAdmin = {
    attach(context) {
      once('paragraph-bundle-alert-admin', DISMISSAL_SELECT, context).forEach(initDismissalToggle);
      once('paragraph-bundle-alert-admin-clear', DATE_RANGE_WRAPPER_SELECTOR, context).forEach(addClearButton);

      // On submit: clear date range when countdown is chosen, clear countdown when date range is chosen (once per form).
      context.querySelectorAll(DISMISSAL_SELECT).forEach((select) => {
        const form = select.closest('form');
        if (form && !form.dataset.pbAlertSubmitAttached) {
          form.dataset.pbAlertSubmitAttached = '1';
          addSubmitClearHandler(form);
        }
      });
    },
  };
})(Drupal, once);
