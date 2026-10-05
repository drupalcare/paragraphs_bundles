<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles_apex\Unit;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\StringTranslation\PluralTranslatableMarkup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\paragraphs_bundles_apex\Hook\PbApexRequirementsHook;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * A deleted APEX mode must be reported, because nothing else reports it.
 *
 * 🚨 THE DEFECT THIS GUARDS PRODUCES NO SIGNAL WHATSOEVER.
 * `ApexResponsiveImageFormatter::viewElements()` returns an empty render array
 * for a mode it cannot load: the image vanishes from the page with no error, no
 * exception and no log entry. The three modes are config entities APEX ships a
 * delete form for, so this is an ordinary administrator action, not a corrupt
 * database.
 *
 * ⚠ AND THE OBVIOUS FIX WAS MEASURED AND REJECTED. Adding the mode to each
 * display's `dependencies.config` looks correct and silently reverts:
 * `EntityDisplayBase::calculateDependencies()` rebuilds that array on every
 * save, and APEX's formatter declares no dependency of its own. Measured on
 * a test site 2026-09-23 -- present before save(), gone after. That is why the
 * guard is a runtime check and not three YAML edits.
 *
 * WHY A UNIT TEST. This project's phpunit.xml.dist sweeps Unit directories
 * only, so a Kernel test here runs in CI and never on a developer's machine.
 * The contract belongs in the suite that actually runs on every change.
 *
 * ⚠ IT EXTENDS PHPUnit's TestCase, NOT Drupal\Tests\UnitTestCase, AND THAT IS
 * NOT A STYLE CHOICE. This suite runs with no Drupal bootstrap, so
 * `UnitTestCase` is not autoloadable here: referencing it does not fail this
 * class, it fails the ENTIRE suite at load time with exit 255. That happened
 * while writing this file. The translation stub below is hand-rolled for the
 * same reason -- it is the one thing `UnitTestCase` was wanted for.
 *
 * @group paragraphs_bundles_apex
 */
#[Group('paragraphs_bundles_apex')]
final class PbApexRequirementsTest extends TestCase {

  /**
   * The wide display's config id, as shipped.
   */
  private const WIDE = 'media.pb_media_image.pb_apex_wide';

  /**
   * A translation service that renders placeholders and needs no container.
   *
   * Mirrors what UnitTestCase::getStringTranslationStub() provides, without
   * the base class this suite cannot load.
   */
  private function translation(): TranslationInterface {
    return new class implements TranslationInterface {

      /**
       * {@inheritdoc}
       */
      public function translate($string, array $args = [], array $options = []) {
        return new TranslatableMarkup($string, $args, $options, $this);
      }

      /**
       * {@inheritdoc}
       */
      public function translateString(TranslatableMarkup $translated_string) {
        return strtr($translated_string->getUntranslatedString(), $translated_string->getArguments());
      }

      /**
       * {@inheritdoc}
       */
      public function formatPlural($count, $singular, $plural, array $args = [], array $options = []) {
        return new PluralTranslatableMarkup($count, $singular, $plural, $args, $options, $this);
      }

      /**
       * {@inheritdoc}
       *
       * Added to TranslationInterface in Drupal 12.0.0-beta1; an extra method
       * on this anonymous class is harmless on older cores.
       */
      public function selectPluralForm(int|float $count, string $string, ?string $langcode = NULL): string {
        return $string;
      }

    };
  }

  /**
   * Builds the hook against an imaginary site.
   *
   * @param array<string, string> $displayModes
   *   Display config id => the APEX mode its formatter names.
   * @param list<string> $existingModes
   *   The APEX mode ids that still exist on this imaginary site.
   * @param bool $modeTypeExists
   *   FALSE simulates apex_responsive_image being mid-uninstall, where the
   *   entity type is gone and getStorage() would throw.
   *
   * @return \Drupal\paragraphs_bundles_apex\Hook\PbApexRequirementsHook
   *   The hook under test.
   */
  private function hook(array $displayModes, array $existingModes, bool $modeTypeExists = TRUE): PbApexRequirementsHook {
    $displayStorage = $this->createMock(EntityStorageInterface::class);
    $displayStorage->method('load')->willReturnCallback(
      function (string $id) use ($displayModes): ?EntityViewDisplayInterface {
        if (!isset($displayModes[$id])) {
          return NULL;
        }
        $display = $this->createMock(EntityViewDisplayInterface::class);
        $display->method('getComponent')->willReturn([
          'type' => 'apex_responsive_image',
          'settings' => ['apex_responsive_image_mode' => $displayModes[$id]],
        ]);
        return $display;
      },
    );

    $modeStorage = $this->createMock(EntityStorageInterface::class);
    $modeStorage->method('load')->willReturnCallback(
      static fn (string $id): ?object => in_array($id, $existingModes, TRUE) ? (object) ['id' => $id] : NULL,
    );

    $etm = $this->createMock(EntityTypeManagerInterface::class);
    $etm->method('hasDefinition')->willReturn($modeTypeExists);
    $etm->method('getStorage')->willReturnCallback(
      static fn (string $type): EntityStorageInterface => $type === 'entity_view_display' ? $displayStorage : $modeStorage,
    );

    return new PbApexRequirementsHook($etm, $this->translation());
  }

  /**
   * A healthy site reports nothing, so the status report stays quiet.
   *
   * This is the control. Without it a hook that always returned a row would
   * pass the failure case below and be useless.
   */
  public function testEveryModePresentReportsNothing(): void {
    $hook = $this->hook(
      [self::WIDE => 'apex_hero'],
      ['apex_hero', 'apex_content', 'apex_card'],
    );

    self::assertSame([], $hook->runtimeRequirements());
  }

  /**
   * A deleted mode is reported as an error, naming the display and the mode.
   */
  public function testDeletedModeIsReportedAsAnError(): void {
    $hook = $this->hook(
      [self::WIDE => 'apex_hero'],
      ['apex_content', 'apex_card'],
    );

    $requirements = $hook->runtimeRequirements();

    self::assertArrayHasKey(
      'paragraphs_bundles_apex_modes',
      $requirements,
      'A display naming a mode that does not exist was not reported at all — which is the silent failure this hook exists to prevent.',
    );

    $row = $requirements['paragraphs_bundles_apex_modes'];

    self::assertSame(
      RequirementSeverity::Error,
      $row['severity'],
      'The row must be an Error: the page is missing content and nothing else says so.',
    );

    $items = $row['description']['#items'] ?? [];
    self::assertCount(1, $items);
    self::assertStringContainsString(self::WIDE, (string) $items[0], 'The report does not name the affected display.');
    self::assertStringContainsString('apex_hero', (string) $items[0], 'The report does not name the missing mode.');
  }

  /**
   * Several deleted modes are all reported, not just the first.
   */
  public function testEveryAffectedDisplayIsListed(): void {
    $hook = $this->hook(
      [
        self::WIDE => 'apex_hero',
        'media.pb_media_image.pb_apex_card' => 'apex_card',
      ],
      [],
    );

    $items = $hook->runtimeRequirements()['paragraphs_bundles_apex_modes']['description']['#items'] ?? [];

    self::assertCount(2, $items, 'Only some of the broken displays were reported.');
  }

  /**
   * A display this module does not ship is never inspected.
   *
   * The hook reports on its own three displays. Reporting on a display some
   * other module owns would be this module speaking for config it does not
   * control.
   */
  public function testForeignDisplayIsIgnored(): void {
    $hook = $this->hook(
      ['media.something_else.default' => 'apex_hero'],
      [],
    );

    self::assertSame([], $hook->runtimeRequirements());
  }

  /**
   * A missing entity type reports nothing rather than fatalling.
   *
   * During an uninstall of apex_responsive_image the mode entity type is gone
   * before this module is, and getStorage() would throw. A requirements hook
   * that fatals takes the status report down with it.
   */
  public function testMissingEntityTypeIsNotFatal(): void {
    $hook = $this->hook([self::WIDE => 'apex_hero'], [], FALSE);

    self::assertSame([], $hook->runtimeRequirements());
  }

}
