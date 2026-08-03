/**
 * @file
 * Paragraph Bundle Card.
 *
 * Adds pb-u-image-top when the right column holds an image section and the
 * left does not (mobile image-first stacking).
 *
 * Filename:     paragraph-bundle-card.js
 * Website:      https://www.flashwebcenter.com
 * Developer:    Alaa Haddad https://www.alaahaddad.com
 */
// phpcs:disable Drupal.NamingConventions.UpperCaseConstant
// phpcs:disable Generic.PHP.UpperCaseConstant
// phpcs:disable Squiz.WhiteSpace.OperatorSpacing

((Drupal, once) => {
  const PARAGRAPHS_BUNDLES = 'paragraphs-bundles';
  const PB_CARD = 'pb-card';
  const PB_CARD_TWO_COLUMNS = 'pb-card-two-columns';
  const PB_CARD_THREE_COLUMNS = 'pb-card-three-columns';
  const PB_CARD_IMAGE_SECTION = 'pb-card-image-section';
  const PB_CARD_TWO_COLUMNS__LEFT = 'pb-card-two-columns__left';
  const PB_CARD_TWO_COLUMNS__RIGHT = 'pb-card-two-columns__right';
  const PB_U_IMAGE_TOP = 'pb-u-image-top';

  const PARAGRAPHS_BUNDLES_PB_CARD_PB_CARD_TWO_COLUMNS = `.${PARAGRAPHS_BUNDLES}.${PB_CARD}.${PB_CARD_TWO_COLUMNS}`;
  const PARAGRAPHS_BUNDLES_PB_CARD_PB_CARD_TWO_COLUMNS_PB_CARD_TWO_COLUMNS = `${PARAGRAPHS_BUNDLES_PB_CARD_PB_CARD_TWO_COLUMNS} .${PB_CARD_TWO_COLUMNS}`;
  const PARAGRAPHS_BUNDLES_PB_CARD_PB_CARD_IMAGE_SECTION = `.${PARAGRAPHS_BUNDLES}.${PB_CARD}.${PB_CARD_IMAGE_SECTION}`;
  const PB_CARD_TWO_COLUMNS_PB_CARD_THREE_COLUMNS = `.${PB_CARD_TWO_COLUMNS}, .${PB_CARD_THREE_COLUMNS}`;

  const PB_CARD_TWO_COLUMNS_OWNED_PB_CARD_TWO_COLUMNS__LEFT = `:scope > .${PB_CARD_TWO_COLUMNS__LEFT}`;
  const PB_CARD_TWO_COLUMNS_OWNED_PB_CARD_TWO_COLUMNS__RIGHT = `:scope > .${PB_CARD_TWO_COLUMNS__RIGHT}`;

  const ONCE_PB_PARAGRAPH_BUNDLE_CARD_IMAGE_TOP =
    'pb-paragraph-bundle-card-image-top';

  /**
   * Checks for image sections that belong to the current card grid, ignoring
   * image sections inside nested card layouts.
   *
   * @param {HTMLElement} column
   *   Card column wrapper.
   * @param {HTMLElement} grid
   *   Owning two-column grid.
   *
   * @return {boolean}
   *   TRUE when the column contains an owned image section.
   */
  const columnHasOwnedImageSection = (column, grid) =>
    Array.from(
      column.querySelectorAll(PARAGRAPHS_BUNDLES_PB_CARD_PB_CARD_IMAGE_SECTION),
    ).some(
      (imageSection) =>
        imageSection.closest(PB_CARD_TWO_COLUMNS_PB_CARD_THREE_COLUMNS) ===
        grid,
    );

  /**
   * @param {HTMLElement} grid
   *   Two-column card grid.
   */
  const applyImageTop = (grid) => {
    const leftColumn = grid.querySelector(
      PB_CARD_TWO_COLUMNS_OWNED_PB_CARD_TWO_COLUMNS__LEFT,
    );
    const rightColumn = grid.querySelector(
      PB_CARD_TWO_COLUMNS_OWNED_PB_CARD_TWO_COLUMNS__RIGHT,
    );
    if (!leftColumn || !rightColumn) {
      return;
    }

    const rightHasImage = columnHasOwnedImageSection(rightColumn, grid);
    const leftHasImage = columnHasOwnedImageSection(leftColumn, grid);

    if (rightHasImage && !leftHasImage) {
      grid.classList.add(PB_U_IMAGE_TOP);
    } else {
      grid.classList.remove(PB_U_IMAGE_TOP);
    }
  };

  /**
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.paragraphBundleCard = {
    attach(context, settings) {
      once(
        ONCE_PB_PARAGRAPH_BUNDLE_CARD_IMAGE_TOP,
        PARAGRAPHS_BUNDLES_PB_CARD_PB_CARD_TWO_COLUMNS_PB_CARD_TWO_COLUMNS,
        context,
      ).forEach((grid) => {
        applyImageTop(grid);
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

      once
        .remove(
          ONCE_PB_PARAGRAPH_BUNDLE_CARD_IMAGE_TOP,
          PARAGRAPHS_BUNDLES_PB_CARD_PB_CARD_TWO_COLUMNS_PB_CARD_TWO_COLUMNS,
          context,
        )
        .forEach((grid) => {
          grid.classList.remove(PB_U_IMAGE_TOP);
        });
    },
  };
})(Drupal, once);
