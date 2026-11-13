<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Hook;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs_bundles\Service\Picture\PbPictureBinderInterface;

/**
 * Lets a Pattern choose how the pictures inside its section are drawn.
 */
final class PbPictureBindingHook {

  public function __construct(
    private readonly PbPictureBinderInterface $binder,
  ) {}

  /**
   * Implements hook_entity_display_build_alter().
   *
   * Runs per paragraph, after its view display built the fields; the display
   * itself is never changed.
   *
   * @param array<string, mixed> $build
   *   The field render arrays.
   * @param array<string, mixed> $context
   *   The entity, view mode and display.
   */
  #[Hook('entity_display_build_alter')]
  public function entityDisplayBuildAlter(array &$build, array $context): void {
    $paragraph = $context['entity'] ?? NULL;
    $display = $context['display'] ?? NULL;
    if ($paragraph instanceof ParagraphInterface && $display instanceof EntityViewDisplayInterface) {
      $view_mode = is_string($context['view_mode'] ?? NULL) ? $context['view_mode'] : 'default';
      $this->binder->bind($build, $paragraph, $display, $view_mode);
    }
  }

}
