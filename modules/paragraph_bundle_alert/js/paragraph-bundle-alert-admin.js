/**
 * @file
 * Paragraph Bundle Alert - admin form: show/hide fields by Dismissal type,
 * and allow clearing date range fields.
 *
 * - None: hide date range and countdown fields.
 * - Date range: show Start date, End date; hide countdown fields.
 * - Countdown: show Countdown (seconds), Show countdown label; hide date range.
 *
 * "When user closes" is shown for all dismissal types.
 *
 * Also seeds the background colour when an Alert state is chosen, so an editor
 * who picks a state but forgets the colour still gets the USWDS tint. Seeding
 * is NON-DESTRUCTIVE: a colour the editor typed is never overwritten.
 */
// phpcs:disable Drupal.NamingConventions.UpperCaseConstant
// phpcs:disable Generic.PHP.UpperCaseConstant
// phpcs:disable Squiz.WhiteSpace.OperatorSpacing
((Drupal, once) => {
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
    const dateWrappers = DATE_RANGE_FIELDS.map((sel) =>
      container.querySelector(sel),
    ).filter(Boolean);
    const countdownWrappers = COUNTDOWN_FIELDS.map((sel) =>
      container.querySelector(sel),
    ).filter(Boolean);

    if (value === 'date_range') {
      dateWrappers.forEach((el) => {
        el.style.display = '';
      });
      countdownWrappers.forEach((el) => {
        el.style.display = 'none';
      });
    } else if (value === 'countdown') {
      dateWrappers.forEach((el) => {
        el.style.display = 'none';
      });
      countdownWrappers.forEach((el) => {
        el.style.display = '';
      });
    } else {
      dateWrappers.forEach((el) => {
        el.style.display = 'none';
      });
      countdownWrappers.forEach((el) => {
        el.style.display = 'none';
      });
    }
  }

  function initDismissalToggle(select) {
    const container =
      select.closest('.pb__tab-display') ||
      select.closest('.horizontal-tabs-pane') ||
      select.closest('details');
    if (!container) {
      return;
    }

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
    if (!dateInput || !dateInput.value) {
      return null;
    }
    const dateStr = dateInput.value;
    const timeStr = timeInput && timeInput.value ? timeInput.value : '00:00:00';
    const ms = Date.parse(`${dateStr}T${timeStr}`);
    return Number.isNaN(ms) ? null : ms;
  }

  /**
   * If dismissal is date_range, check start < end. Return true if invalid (block submit).
   */
  function isDateRangeInvalid(container) {
    const startWrapper = container.querySelector(DATE_RANGE_FIELDS[0]);
    const endWrapper = container.querySelector(DATE_RANGE_FIELDS[1]);
    if (!startWrapper || !endWrapper) {
      return false;
    }
    const startTs = getTimestampFromDateField(startWrapper);
    const endTs = getTimestampFromDateField(endWrapper);
    if (startTs == null || endTs == null) {
      return false;
    }
    return startTs >= endTs;
  }

  /**
   * Clear date range inputs (start + end) in this container.
   */
  function clearDateRangeInContainer(container) {
    DATE_RANGE_FIELDS.forEach((sel) => {
      const wrapper = container.querySelector(sel);
      if (!wrapper) {
        return;
      }
      wrapper
        .querySelectorAll('input[type="date"], input[type="time"]')
        .forEach((input) => {
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
    form.addEventListener(
      'submit',
      (event) => {
        // When date range is selected, end date must be after start date.
        const invalidContainers = [];
        form.querySelectorAll(DISMISSAL_SELECT).forEach((select) => {
          const value = (select.value || 'none').trim();
          if (value !== 'date_range') {
            return;
          }
          const container =
            select.closest('.pb__tab-display') ||
            select.closest('.horizontal-tabs-pane') ||
            select.closest('details');
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
          const container =
            select.closest('.pb__tab-display') ||
            select.closest('.horizontal-tabs-pane') ||
            select.closest('details');
          if (!container) {
            return;
          }
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
      },
      false,
    );
  }

  function addClearButton(wrapper) {
    const dateInput = wrapper.querySelector('input[type="date"]');
    const timeInput = wrapper.querySelector('input[type="time"]');
    if (!dateInput && !timeInput) {
      return;
    }

    const label = wrapper.querySelector('.fieldset__label, .form-item__label');
    const labelText =
      label && label.textContent ? label.textContent.trim() : '';
    const clearLabel = Drupal.t('Clear');

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'button button--small paragraph-bundle-alert-clear-date';
    btn.textContent = clearLabel;
    btn.setAttribute(
      'aria-label',
      clearLabel + (labelText ? ` ${labelText}` : ''),
    );

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

  // --- Alert state seeds the background colour -----------------------------

  const STATE_SELECT = 'select[name*="[pb_display_alert_state]"]';

  // The background tints seeded into pb_display_bg. The icon and left bar use
  // the matching 700 step in CSS. Kept in sync with the colour block in
  // paragraph-bundle-alert.css - PbAlertStateContractTest checks every state
  // has a rule there, but the exact hex pairing lives in these two files.
  const STATE_TINTS = {
    success: '#ecfdf5',
    info: '#eff6ff',
    warning: '#fffbeb',
    error: '#fef2f2',
  };

  const isUntouchedPreset = (value) => {
    const v = (value || '').trim().toLowerCase();
    if (v === '') {
      return true;
    }
    return Object.values(STATE_TINTS).some((tint) => tint === v);
  };

  /**
   * The background input belonging to the SAME paragraph as this select.
   *
   * Scoping by closest('.paragraphs-subform') and then querySelector() is
   * wrong, and wrong in a way that silently writes to the wrong entity: an
   * alert's nested body paragraphs render their own subforms INSIDE the
   * alert's, and pb_content_alert_body is a Content field, so a child's
   * pb_display_bg appears in the DOM *before* the alert's own Display field.
   * The descendant search found the child and coloured that instead.
   *
   * Drupal's form names carry the answer exactly. For an alert at
   *   apex_sections[0][subform][pb_display_alert_state]
   * its own background is
   *   apex_sections[0][subform][pb_display_bg][0][value]
   * - the same prefix. A nested child's is one or more [subform] levels
   * deeper, so an exact prefix match can never reach it.
   */
  function ownBackgroundInput(select) {
    const suffix = '[pb_display_alert_state]';
    const name = select.name || '';
    if (!name.endsWith(suffix)) {
      return null;
    }
    const prefix = name.slice(0, -suffix.length);
    const form = select.closest('form') || document;
    return form.querySelector(
      `input[name="${prefix}[pb_display_bg][0][value]"]`,
    );
  }

  function seedBackgroundColour(select) {
    const input = ownBackgroundInput(select);
    if (!input) {
      return;
    }
    const state = (select.value || '').trim();
    const tint = STATE_TINTS[state];
    if (!tint) {
      // Back to "- None -": leave whatever is there. Clearing it would throw
      // away a colour the editor may have chosen deliberately.
      return;
    }
    // Only fill when the field is empty, or still holds another state's tint -
    // i.e. it is demonstrably untouched. A colour the editor typed themselves
    // never equals a preset, so it is never destroyed.
    if (!isUntouchedPreset(input.value)) {
      return;
    }
    input.value = tint;
    // Notify the colour widget's own listeners (swatch preview, validation).
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
  }

  function initStateSeeding(select) {
    // Deliberately NOT seeded on load: that would write a value into every
    // existing alert the moment its form is opened.
    select.addEventListener('change', () => seedBackgroundColour(select));
  }

  Drupal.behaviors.paragraphBundleAlertAdmin = {
    attach(context) {
      once('paragraph-bundle-alert-admin', DISMISSAL_SELECT, context).forEach(
        initDismissalToggle,
      );
      once(
        'paragraph-bundle-alert-admin-state',
        STATE_SELECT,
        context,
      ).forEach(initStateSeeding);
      once(
        'paragraph-bundle-alert-admin-clear',
        DATE_RANGE_WRAPPER_SELECTOR,
        context,
      ).forEach(addClearButton);

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
