<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Service;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\filter\FilterFormatRepositoryInterface;

/**
 * Default text formats helper.
 *
 * MEASURED, NOT REASONED. On sts.local, 2026-09-17, against a real stored
 * `pb_content_body`:
 *
 *   as stored           format: 'basic_html'
 *   after set('words')  format: NULL          <- the damage
 *   Paragraph::create() format: NULL          <- never set in the first place
 *
 * A NULL format does not render as "no filtering". `ProcessedText` substitutes
 * `filter.settings.fallback_format`, normally `plain_text`, so the markup the
 * person wrote is escaped and shown as source.
 *
 * THE EXISTING FORMAT WINS, ALWAYS. Rewording a section must not silently
 * promote it from Restricted HTML to Full HTML, nor demote it: the format was
 * a decision somebody made about that field, and rewriting the words is not a
 * reason to revisit it. Only when there is no existing value — a section being
 * created — does the person's own default format apply.
 */
final class TextFormats implements TextFormatsInterface {

  /**
   * Constructs the helper.
   *
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   Whose default format applies to a value that has none yet. Asking the
   *   user rather than the site is what keeps this from handing someone a
   *   format they are not permitted to use.
   * @param \Drupal\filter\FilterFormatRepositoryInterface|null $formatRepository
   *   NULL on Drupal 11.3, where this service does not exist yet, and on any
   *   site without filter.module — which this module does not depend on. The
   *   type hint is safe in both cases: PHP does not autoload a class to check
   *   NULL against a nullable parameter.
   */
  public function __construct(
    private readonly AccountInterface $currentUser,
    private readonly ?FilterFormatRepositoryInterface $formatRepository = NULL,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function valueFor(FieldItemListInterface $items, string $text): array|string {
    if (!$this->carriesFormat($items)) {
      return $text;
    }

    $existing = $items->isEmpty() ? NULL : $items->first()?->format;
    $format = is_string($existing) && $existing !== ''
      ? $existing
      : $this->defaultFormat();

    // No format available at all: write the plain string rather than an item
    // with a NULL format. Both render the same, and one of them is honest.
    return $format === NULL ? $text : ['value' => $text, 'format' => $format];
  }

  /**
   * Whether this field's items have a format property at all.
   *
   * Asked of the FIELD rather than of a list of type names, because a site can
   * add a formatted field type this module has never heard of, and a hard-coded
   * list would silently mishandle it.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The field.
   *
   * @return bool
   *   TRUE when its items carry a format.
   */
  private function carriesFormat(FieldItemListInterface $items): bool {
    $properties = $items->getFieldDefinition()
      ->getItemDefinition()
      ->getPropertyDefinitions();

    return array_key_exists('format', $properties);
  }

  /**
   * The current person's default text format, when there is one.
   *
   * @return string|null
   *   A format id, or NULL when none can be determined.
   */
  private function defaultFormat(): ?string {
    // The supported path, from Drupal 11.4.
    if ($this->formatRepository !== NULL) {
      $id = $this->formatRepository->getDefaultFormat($this->currentUser)->id();

      return is_string($id) && $id !== '' ? $id : NULL;
    }

    // THE 11.3 FALLBACK, and it is deliberate rather than left behind.
    // `filter_default_format()` is deprecated in 11.4 and removed in 13, but
    // this module declares `^11.3`, where the repository above does not exist.
    // Deleting this would break the older major the info.yml still promises.
    // Remove it the day that floor rises to ^11.4.
    //
    // It also belongs to filter.module, which this module does NOT depend on —
    // a field carrying a `format` property is a text field, and text.module
    // requires filter.module, so this line is only reached where it is loaded.
    // The guard stays anyway: a guarantee arrived at by reasoning is not one
    // the container enforces.
    if (!function_exists('filter_default_format')) {
      return NULL;
    }

    // phpcs:ignore Drupal.Semantics.FunctionD8Deprecated.DeprecatedFunctionUsed
    $format = filter_default_format($this->currentUser);

    return is_string($format) && $format !== '' ? $format : NULL;
  }

}
