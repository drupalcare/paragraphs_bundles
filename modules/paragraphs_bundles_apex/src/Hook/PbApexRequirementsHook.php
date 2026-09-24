<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_apex\Hook;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Reports shipped displays whose APEX responsive mode no longer exists.
 *
 * 🚨 THE FAILURE THIS EXISTS TO MAKE VISIBLE IS COMPLETELY SILENT.
 * `ApexResponsiveImageFormatter::viewElements()` returns an EMPTY render array
 * when the mode it is configured with does not load — read 2026-09-23:
 *
 * @code
 * $mode = $storage->load($modeId);
 * if (!$mode instanceof ApexResponsiveImageModeInterface) {
 *   return $elements;
 * }
 * @endcode
 *
 * No exception, no watchdog entry, no markup. The image is simply gone from the
 * page, and nothing anywhere says why. The three modes this bridge names —
 * `apex_hero`, `apex_content`, `apex_card` — are CONFIG ENTITIES that APEX
 * ships a delete form and a delete route for, so an administrator can remove
 * one through the UI without ever being told what depends on it.
 *
 * ⚠ WHY THIS IS NOT FIXED IN THE SHIPPED YAML, WHICH WAS THE OBVIOUS IDEA.
 * The natural fix is to add the mode to each display's `dependencies.config`,
 * so Drupal's own dependency machinery reacts when it is deleted. MEASURED on
 * sts.local 2026-09-23 and it does not work: `ConfigEntityBase::preSave()`
 * calls `calculateDependencies()`, and `EntityDisplayBase::calculateDependencies()`
 * (core, lines 278-305) REBUILDS the array from the bundle, the field configs
 * and the view mode, plus whatever each formatter plugin declares. A
 * hand-added entry was present before `save()` and GONE after it. Editing the
 * YAML would look right on a fresh install and revert the first time an
 * administrator touched that display — a fix that silently undoes itself is
 * worse than none.
 *
 * ⚠ THE ROOT CAUSE IS IN APEX AND IS NOT OURS TO PATCH.
 * `ApexResponsiveImageFormatter` does not override `calculateDependencies()`,
 * so it never declares the mode entity it depends on. That belongs to APEX's
 * maintainer. This hook is Paragraphs Bundles making the consequence visible in
 * the one place a site operator will see it.
 *
 * Severity is Error rather than Warning on purpose: the outcome is missing
 * content on the public site with no other signal anywhere, which is the exact
 * class of failure this module was written to prevent.
 */
final class PbApexRequirementsHook {

  use StringTranslationTrait;

  /**
   * The displays this module ships, by config entity id.
   *
   * Hard-coded rather than discovered: these three are the module's entire
   * product, and a display this module did not ship is not its business to
   * report on.
   */
  private const array DISPLAYS = [
    'media.pb_media_image.pb_apex_wide',
    'media.pb_media_image.pb_apex_narrow',
    'media.pb_media_image.pb_apex_card',
  ];

  /**
   * The field inside those displays that carries the APEX formatter.
   */
  private const string IMAGE_FIELD = 'pb_image_field';

  /**
   * The formatter plugin id whose setting names an APEX mode.
   */
  private const string FORMATTER = 'apex_responsive_image';

  /**
   * The config entity type holding APEX's responsive modes.
   */
  private const string MODE_ENTITY_TYPE = 'apex_responsive_image_mode';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    TranslationInterface $stringTranslation,
  ) {
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * Implements hook_runtime_requirements().
   *
   * @return array<string, array<string, mixed>>
   *   Requirements rows, empty when every shipped display resolves its mode.
   */
  #[Hook('runtime_requirements')]
  public function runtimeRequirements(): array {
    $missing = $this->missingModes();
    if ($missing === []) {
      return [];
    }

    $items = [];
    foreach ($missing as $displayId => $modeId) {
      $items[] = $this->t('@display names the APEX mode @mode, which does not exist.', [
        '@display' => $displayId,
        '@mode' => $modeId,
      ]);
    }

    return [
      'paragraphs_bundles_apex_modes' => [
        'title' => $this->t('Paragraphs Bundles APEX responsive modes'),
        'value' => $this->formatPlural(
          count($missing),
          '1 display names an APEX mode that no longer exists',
          '@count displays name an APEX mode that no longer exists',
        ),
        'description' => [
          '#theme' => 'item_list',
          '#items' => $items,
          '#prefix' => '<p>' . $this->t('Images in these displays render as nothing at all. The APEX formatter returns an empty render array for a mode it cannot load, with no error and no log entry, so the picture disappears from the page silently. Restore the mode, or point the display at one that exists.') . '</p>',
        ],
        'severity' => RequirementSeverity::Error,
      ],
    ];
  }

  /**
   * Shipped displays whose configured APEX mode cannot be loaded.
   *
   * @return array<string, string>
   *   Display config id => the missing mode id. Empty when all resolve.
   */
  private function missingModes(): array {
    // During an uninstall of apex_responsive_image the entity type is gone
    // before this module is, and getStorage() would throw. A requirements hook
    // that fatals is worse than one that reports nothing.
    if (!$this->entityTypeManager->hasDefinition(self::MODE_ENTITY_TYPE)) {
      return [];
    }

    $displayStorage = $this->entityTypeManager->getStorage('entity_view_display');
    $modeStorage = $this->entityTypeManager->getStorage(self::MODE_ENTITY_TYPE);

    $missing = [];

    foreach (self::DISPLAYS as $displayId) {
      $display = $displayStorage->load($displayId);
      if (!$display instanceof EntityViewDisplayInterface) {
        continue;
      }

      $component = $display->getComponent(self::IMAGE_FIELD);
      if (!is_array($component) || ($component['type'] ?? '') !== self::FORMATTER) {
        continue;
      }

      $modeId = $component['settings']['apex_responsive_image_mode'] ?? '';
      if (!is_string($modeId) || $modeId === '') {
        continue;
      }

      if ($modeStorage->load($modeId) === NULL) {
        $missing[$displayId] = $modeId;
      }
    }

    return $missing;
  }

}
