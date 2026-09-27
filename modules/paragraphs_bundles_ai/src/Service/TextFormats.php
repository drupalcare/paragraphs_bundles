<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Service;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TypedData\ComplexDataDefinitionInterface;
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
 * LINE BREAKS ARE KEPT, SINCE 2026-09-27. The model writes plain text, and
 * `basic_html` — the usual default — has no `filter_autop`, so "three short
 * lines" rendered as one run-on line (measured on sts.local). Where the format
 * will not turn newlines into markup itself, the text is escaped and wrapped:
 * a blank line starts a paragraph, a single newline is a line break. A format
 * that escapes or auto-paragraphs is handed the text untouched, because it
 * already does exactly that.
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
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface|null $entityTypeManager
   *   Loads the chosen format, to ask whether it keeps line breaks itself.
   *   NULL leaves the text as it was written.
   * @param \Drupal\filter\FilterFormatRepositoryInterface|null $formatRepository
   *   NULL on Drupal 11.3, where this service does not exist yet, and on any
   *   site without filter.module — which this module does not depend on. The
   *   type hint is safe in both cases: PHP does not autoload a class to check
   *   NULL against a nullable parameter.
   */
  public function __construct(
    private readonly AccountInterface $currentUser,
    private readonly ?EntityTypeManagerInterface $entityTypeManager = NULL,
    private readonly ?FilterFormatRepositoryInterface $formatRepository = NULL,
  ) {}

  /**
   * {@inheritdoc}
   *
   * The generic is restated here rather than left to {@inheritdoc}: PHPStan
   * reported the missing type parameter against this file AND the interface
   * separately, so inheriting it was not enough to satisfy the analyser.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface<\Drupal\Core\Field\FieldItemInterface> $items
   *   The field being written to, as it currently stands.
   * @param string $text
   *   The words.
   *
   * @return array<string, string>|string
   *   An item array carrying the format, or the plain string when the field
   *   has no format to carry.
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
    if ($format === NULL) {
      return $text;
    }

    return ['value' => $this->keepsBreaks($format) ? $text : self::toMarkup($text), 'format' => $format];
  }

  /**
   * Whether a format already turns newlines into markup, or shows text as is.
   *
   * @param string $format
   *   Text format id.
   *
   * @return bool
   *   TRUE when the text can be stored exactly as written.
   */
  private function keepsBreaks(string $format): bool {
    if ($this->entityTypeManager === NULL || !$this->entityTypeManager->hasDefinition('filter_format')) {
      return TRUE;
    }

    $entity = $this->entityTypeManager->getStorage('filter_format')->load($format);

    if ($entity === NULL) {
      return TRUE;
    }

    // The stored configuration, not the plugin collection: it answers "is it
    // switched on" without instantiating every filter the format carries.
    $filters = $entity->get('filters');

    foreach (['filter_autop', 'filter_html_escape'] as $id) {
      if (is_array($filters) && is_array($filters[$id] ?? NULL) && !empty($filters[$id]['status'])) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Plain text as HTML: escaped, blank lines as paragraphs, newlines as breaks.
   *
   * @param string $text
   *   Plain text as the model wrote it.
   *
   * @return string
   *   Markup safe for any HTML format.
   */
  private static function toMarkup(string $text): string {
    $paragraphs = preg_split('/\R\s*\R/', trim(str_replace("\r\n", "\n", $text))) ?: [];
    $out = [];

    foreach ($paragraphs as $paragraph) {
      $lines = array_map(static fn(string $line): string => Html::escape(trim($line)), preg_split('/\R/', $paragraph) ?: []);
      $out[] = '<p>' . implode("<br>\n", array_filter($lines, static fn(string $line): bool => $line !== '')) . '</p>';
    }

    return implode("\n", $out);
  }

  /**
   * Whether this field's items have a format property at all.
   *
   * Asked of the FIELD rather than of a list of type names, because a site can
   * add a formatted field type this module has never heard of, and a hard-coded
   * list would silently mishandle it.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface<\Drupal\Core\Field\FieldItemInterface> $items
   *   The field.
   *
   * @return bool
   *   TRUE when its items carry a format.
   */
  private function carriesFormat(FieldItemListInterface $items): bool {
    $definition = $items->getFieldDefinition()->getItemDefinition();

    // getPropertyDefinitions() lives on ComplexDataDefinitionInterface, but
    // getItemDefinition() is typed to return the plainer DataDefinitionInterface.
    // Every field item definition in core is complex, so this guard has never
    // fired — and "has never fired" is not "cannot fire". A field type handing
    // back a simple definition would fatal here on a page being edited, rather
    // than answering the question actually being asked, which is only ever
    // "does this field carry a format?".
    if (!$definition instanceof ComplexDataDefinitionInterface) {
      return FALSE;
    }

    return array_key_exists('format', $definition->getPropertyDefinitions());
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
