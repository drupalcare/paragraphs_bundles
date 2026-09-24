<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Service;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\paragraphs\ParagraphsTypeInterface;
use Drupal\paragraphs_bundles_ai\Model\BundleProfile;
use Drupal\paragraphs_bundles_ai\Model\FieldFact;

/**
 * Default bundle profiler.
 *
 * WHERE THE CONTENT / DISPLAY SPLIT COMES FROM, AND WHY NOT FROM THE FILES.
 * The obvious source is the module's own shipped
 * `core.entity_form_display.paragraph.*.default.yml`. This class reads the
 * INSTALLED form display instead, for a reason measured on sts.local on
 * 2026-09-15: the site's installed 3d_carousel display has two fields in no
 * group at all, while the shipped file groups both, and there is no pending
 * update to reconcile them. A site builder may also regroup fields entirely,
 * which is their right. The assistant has to describe the site a person is
 * looking at, not the site the module shipped.
 *
 * THE TWO SOURCES, IN ORDER, AND WHY THERE ARE TWO.
 * 1. The field groups `group_pb_tab_content` and `group_pb_tab_display`.
 *    Measured 2026-09-15 across all 40 installed bundles: those two names are
 *    universal wherever groups exist — zero bundles use a different name.
 * 2. The `pb_content_` / `pb_display_` name prefix, for fields the groups do
 *    not mention. Measured the same day: the prefix and the groups disagree
 *    on ZERO fields, so the fallback cannot contradict the first source; it
 *    only covers what the first source is silent about.
 *
 * Anything neither source explains is reported as unclassified rather than
 * assigned a tab. A wrong guess here puts a styling control in front of an
 * editor as content, and nothing downstream would catch it.
 */
final class BundleProfiler implements BundleProfilerInterface {

  /**
   * The field group holding the Content tab.
   */
  private const string GROUP_CONTENT = 'group_pb_tab_content';

  /**
   * The field group holding the Display tab.
   */
  private const string GROUP_DISPLAY = 'group_pb_tab_display';

  /**
   * Field types a person would recognise as the title of a section.
   */
  private const TITLE_TYPES = ['string', 'string_long', 'text', 'text_long'];

  /**
   * Constructs the profiler.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Loads paragraph types and form displays.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   Reads field definitions per bundle.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityFieldManagerInterface $entityFieldManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function profile(string $bundle): ?BundleProfile {
    $bundle = trim($bundle);

    if ($bundle === '') {
      return NULL;
    }

    $type = $this->entityTypeManager->getStorage('paragraphs_type')->load($bundle);

    // instanceof, not `=== NULL`. load() is typed ?EntityInterface, and
    // getDescription() further down is declared on ParagraphsTypeInterface,
    // not on EntityInterface — so the old check satisfied the analyser about
    // NULL while telling it nothing about the type, leaving a call to a method
    // the declared type does not have. Narrowing here fixes that and is
    // stricter for free: a storage handing back something that is not a
    // paragraphs type is refused, rather than fataling on the first method it
    // turns out not to implement.
    if (!$type instanceof ParagraphsTypeInterface) {
      return NULL;
    }

    $definitions = [];

    foreach ($this->entityFieldManager->getFieldDefinitions('paragraph', $bundle) as $name => $definition) {
      // Base fields — id, uuid, type, parent_id and the rest — are machinery.
      // They are on no tab and belong in no answer this class gives.
      if ($definition->getFieldStorageDefinition()->isBaseField()) {
        continue;
      }
      $definitions[(string) $name] = $definition;
    }

    $display = $this->formDisplay($bundle);
    $groups = $display === NULL ? [] : $display->getThirdPartySettings('field_group');

    $inContent = $this->fieldsUnder($groups, self::GROUP_CONTENT);
    $inDisplay = $this->fieldsUnder($groups, self::GROUP_DISPLAY);

    $content = [];
    $styling = [];
    $unclassified = [];

    foreach (array_keys($definitions) as $name) {
      if (isset($inContent[$name])) {
        $content[] = $name;
      }
      elseif (isset($inDisplay[$name])) {
        $styling[] = $name;
      }
      elseif (str_starts_with($name, 'pb_content_')) {
        $content[] = $name;
      }
      elseif (str_starts_with($name, 'pb_display_')) {
        $styling[] = $name;
      }
      else {
        $unclassified[] = $name;
      }
    }

    sort($content);
    sort($styling);
    sort($unclassified);

    $children = $this->childFields($definitions);
    $facts = [];

    foreach ($definitions as $name => $definition) {
      $storage = $definition->getFieldStorageDefinition();
      $limit = $storage->getSetting('max_length');
      $holdsSections = isset($children[$name]);

      $facts[$name] = new FieldFact(
        $name,
        (string) $definition->getLabel(),
        $definition->getType(),
        in_array($name, $content, TRUE) ? 'content' : (in_array($name, $styling, TRUE) ? 'display' : 'unknown'),
        $definition->isRequired(),
        is_numeric($limit) ? (int) $limit : NULL,
        $this->allowedValues($definition),
        // Every reference field, not only the section-holding ones. A media
        // reference names the media types it accepts in exactly the same
        // setting, and whatever fills a picture needs to know which.
        $holdsSections ? $children[$name] : $this->acceptedBundles($definition),
        $holdsSections,
        // The site builder's help text for this field. Usually empty, and worth
        // passing through precisely when it is not.
        trim(strip_tags((string) $definition->getDescription())),
        $this->targetType($definition),
      );
    }

    ksort($facts);

    return new BundleProfile(
      $bundle,
      (string) $type->label(),
      // A bundle whose Display tab holds nothing is styled through its parent.
      // Measured: the 4 section bundles, which are children of accordion, link,
      // slideshow and tabs.
      $styling !== [],
      $this->titleField($content, $definitions),
      $content,
      $styling,
      $children,
      $unclassified,
      $facts,
      // What the bundle IS, from its own config. PB ships a description for
      // nearly every bundle and nothing here ever read one.
      trim(strip_tags((string) $type->getDescription())),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function all(): array {
    $out = [];

    foreach (array_keys($this->entityTypeManager->getStorage('paragraphs_type')->loadMultiple()) as $id) {
      $profile = $this->profile((string) $id);

      if ($profile !== NULL) {
        $out[(string) $id] = $profile;
      }
    }

    ksort($out);

    return $out;
  }

  /**
   * {@inheritdoc}
   */
  public function parents(): array {
    $all = $this->all();
    $children = [];

    foreach ($all as $profile) {
      foreach ($profile->childFields as $accepts) {
        foreach ($accepts as $bundle) {
          $children[$bundle] = TRUE;
        }
      }
    }

    return array_diff_key($all, $children);
  }

  /**
   * The default form display for a bundle, when it has one.
   *
   * @param string $bundle
   *   Paragraph bundle machine name.
   *
   * @return \Drupal\Core\Entity\Display\EntityFormDisplayInterface|null
   *   The display, or NULL when the site has none stored — in which case the
   *   name prefix is the only source left, and it is enough.
   */
  private function formDisplay(string $bundle): ?EntityFormDisplayInterface {
    $display = $this->entityTypeManager
      ->getStorage('entity_form_display')
      ->load('paragraph.' . $bundle . '.default');

    return $display instanceof EntityFormDisplayInterface ? $display : NULL;
  }

  /**
   * Every field under a group, following nested groups down.
   *
   * PB nests three deep — a tabs wrapper holds the two tabs, and the Display
   * tab holds Colors, Spacing, Border, Size and Advanced, which hold the
   * fields. Reading one level finds group names and no fields at all.
   *
   * @param array<string, mixed> $groups
   *   The field_group third-party settings.
   * @param string $name
   *   Group to walk from.
   *
   * @return array<string, true>
   *   Field machine names, as a set.
   */
  private function fieldsUnder(array $groups, string $name): array {
    $out = [];
    $queue = [$name];
    $seen = [];

    while ($queue !== []) {
      $current = array_shift($queue);

      // A malformed config could point two groups at each other. Walking it
      // would hang the request rather than fail, which is the worst shape a
      // bug can take here.
      if (isset($seen[$current])) {
        continue;
      }
      $seen[$current] = TRUE;

      // Third-party settings are `mixed` all the way down, so both levels are
      // narrowed rather than indexed blind: PHPStan at the level this project
      // runs rejects the shorter version, and it is right to — a group written
      // by another module need not have the shape PB's have.
      $group = $groups[$current] ?? [];

      if (!is_array($group)) {
        continue;
      }

      $children = $group['children'] ?? [];

      if (!is_array($children)) {
        continue;
      }

      foreach ($children as $child) {
        $child = (string) $child;

        if (str_starts_with($child, 'group_')) {
          $queue[] = $child;
          continue;
        }

        $out[$child] = TRUE;
      }
    }

    return $out;
  }

  /**
   * The field a person would call the title.
   *
   * NINE FIELD NAMES MEAN THIS ACROSS THE SHIPPED BUNDLES, measured from
   * config/optional: `pb_content_title` on 32 of them, and then
   * `pb_content_slideshow_title`, `pb_content_modal_title`,
   * `pb_content_accordion_title`, `pb_content_tab_name` and others on the rest.
   * THREE BUNDLES HAVE NONE — 3d_carousel, image_background and
   * parallax_section — so NULL is a real answer, not a failure.
   *
   * The match is anchored at the END of the name on purpose: `pb_content_title`
   * is the title, while `pb_content_title_tag` and `pb_content_title_alignment`
   * are settings about the title and would both win a substring test.
   *
   * @param list<string> $content
   *   Content-tab field names, sorted.
   * @param array<string, \Drupal\Core\Field\FieldDefinitionInterface> $definitions
   *   All non-base field definitions on the bundle.
   *
   * @return string|null
   *   The field name, or NULL when the bundle has no title.
   */
  private function titleField(array $content, array $definitions): ?string {
    // The overwhelming majority case, and exact rather than inferred.
    if (in_array('pb_content_title', $content, TRUE)) {
      return 'pb_content_title';
    }

    foreach ($content as $name) {
      if (preg_match('/(^|_)(title|name|heading)$/', $name) !== 1) {
        continue;
      }
      if (!in_array($definitions[$name]->getType(), self::TITLE_TYPES, TRUE)) {
        continue;
      }

      return $name;
    }

    return NULL;
  }

  /**
   * The fields that hold child sections, and what each accepts.
   *
   * THE EMPTY-MEANS-ALL TRAP, which `TargetResolver` documents at length and
   * which applies identically here: Drupal stores "any bundle is allowed" as an
   * EMPTY `target_bundles`, not as a list of everything. An empty list in the
   * returned array therefore means ANY, never NONE.
   *
   * `negate` turns the list into an exclusion. It is rare, and rather than
   * invent a third shape this returns "any" for it — a superset. Nothing is
   * written on the strength of this answer: `SectionWriter` re-checks the real
   * setting before it saves, so a superset costs a refusal, never a bad write.
   *
   * @param array<string, \Drupal\Core\Field\FieldDefinitionInterface> $definitions
   *   All non-base field definitions on the bundle.
   *
   * @return array<string, list<string>>
   *   Field name => accepted bundles, empty list meaning any.
   */
  private function childFields(array $definitions): array {
    $out = [];

    foreach ($definitions as $name => $definition) {
      // Both halves matter: entity_reference_revisions is also used for things
      // that are not paragraphs, so the target type is checked rather than
      // inferred from the field type.
      if ($definition->getType() !== 'entity_reference_revisions') {
        continue;
      }
      if (($definition->getSettings()['target_type'] ?? NULL) !== 'paragraph') {
        continue;
      }

      $out[$name] = $this->acceptedBundles($definition);
    }

    ksort($out);

    return $out;
  }

  /**
   * The bundles one reference field accepts.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $definition
   *   The field.
   *
   * @return list<string>
   *   Bundle machine names, or an empty list meaning any bundle.
   */
  private function acceptedBundles(FieldDefinitionInterface $definition): array {
    $settings = $definition->getSettings()['handler_settings'] ?? [];

    if (!is_array($settings)) {
      return [];
    }

    $targets = $settings['target_bundles'] ?? [];

    if (!is_array($targets) || $targets === [] || !empty($settings['negate'])) {
      return [];
    }

    $out = array_map(strval(...), array_keys($targets));
    sort($out);

    return $out;
  }

  /**
   * The entity type a reference field points at.
   *
   * READ FROM THE SETTING, NEVER INFERRED FROM THE FIELD NAME. `pb_content_
   * image` is a reference to media and `pb_content_webform` is a reference to
   * a webform; both are plain `entity_reference`, and a name-based guess would
   * be right until a site renamed something.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $definition
   *   The field.
   *
   * @return string
   *   The target entity type id, or an empty string when this is not a
   *   reference field at all.
   */
  private function targetType(FieldDefinitionInterface $definition): string {
    if (!in_array($definition->getType(), ['entity_reference', 'entity_reference_revisions'], TRUE)) {
      return '';
    }

    return (string) ($definition->getSettings()['target_type'] ?? '');
  }

  /**
   * The keys a list field accepts, when it is one.
   *
   * THE KEYS, NOT THE LABELS. A list field stores the key and displays the
   * label, and a caller writing "Heading 2" into a field whose key is `h2`
   * produces a value the field does not have. Handing over the keys is the
   * difference between a choice and a guess.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $definition
   *   The field.
   *
   * @return list<string>
   *   Allowed keys, or an empty list for a field with no fixed set.
   */
  private function allowedValues(FieldDefinitionInterface $definition): array {
    $allowed = $definition->getFieldStorageDefinition()->getSetting('allowed_values');

    if (!is_array($allowed) || $allowed === []) {
      return [];
    }

    return array_map(strval(...), array_keys($allowed));
  }

}
