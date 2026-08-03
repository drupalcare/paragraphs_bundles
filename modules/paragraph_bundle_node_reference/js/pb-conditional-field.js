/**
 * @file
 * Conditional NF reference fields for node_reference_bundle admin form.
 */
// phpcs:disable Drupal.NamingConventions.UpperCaseConstant
// phpcs:disable Generic.PHP.UpperCaseConstant
// phpcs:disable Squiz.WhiteSpace.OperatorSpacing
(function (Drupal, once) {
  const FIELD_SELECTORS = {
    vm_title: '.field--name-pb-content-nf-title-only',
    vm_teaser_text: '.field--name-pb-content-nf-teaser-text',
    vm_slideshow: '.field--name-pb-content-nf-slideshow',
    vm_3d_carousel: '.field--name-pb-content-nf-3d-carousel',
  };

  /**
   * Show the NF field matching the view mode; hide and optionally clear others.
   *
   * @param {HTMLElement} context
   *   Behavior context root.
   * @param {HTMLSelectElement} viewModeSelect
   *   View mode select element.
   * @param {boolean} clearHidden
   *   Whether to clear values on hidden fields.
   */
  function toggleFields(context, viewModeSelect, clearHidden) {
    const value = viewModeSelect.value;

    Object.values(FIELD_SELECTORS).forEach((selector) => {
      const fieldContainer = context.querySelector(selector);
      if (!fieldContainer) {
        return;
      }
      fieldContainer.style.display = 'none';
      if (clearHidden) {
        const inputOrSelect = fieldContainer.querySelector('input, select');
        if (inputOrSelect) {
          inputOrSelect.value = '';
        }
      }
    });

    const selectedFieldContainer = context.querySelector(
      FIELD_SELECTORS[value],
    );
    if (selectedFieldContainer) {
      selectedFieldContainer.style.display = 'block';
    }
  }

  /**
   * Detect populated NF fields and sync the view mode select on first attach.
   *
   * @param {HTMLElement} context
   *   Behavior context root.
   * @param {HTMLSelectElement} viewModeSelect
   *   View mode select element.
   */
  function syncViewModeFromFields(context, viewModeSelect) {
    let selectedOption = 'vm_title';

    Object.entries(FIELD_SELECTORS).forEach(([key, selector]) => {
      const fieldContainer = context.querySelector(selector);
      if (!fieldContainer) {
        return;
      }
      const inputOrSelect = fieldContainer.querySelector('input, select');
      if (inputOrSelect && inputOrSelect.value) {
        selectedOption = key;
      }
    });

    viewModeSelect.value = selectedOption;
    toggleFields(context, viewModeSelect, false);
  }

  Drupal.behaviors.pbNodeReferenceConditionalFields = {
    attach(context) {
      once(
        'pbNodeReferenceConditionalFields',
        '.field--name-pb-content-view-mode select',
        context,
      ).forEach((viewModeSelect) => {
        syncViewModeFromFields(context, viewModeSelect);
        viewModeSelect.addEventListener('change', () => {
          toggleFields(context, viewModeSelect, true);
        });
      });
    },
  };
})(Drupal, once);
