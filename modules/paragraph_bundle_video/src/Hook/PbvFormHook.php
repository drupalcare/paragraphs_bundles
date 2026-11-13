<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_video\Hook;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\media\MediaInterface;
use Drupal\paragraph_bundle_video\PbVideo;

/**
 * Connects the save rules and the captions warning to the editor form.
 */
final class PbvFormHook {

  use StringTranslationTrait;

  /**
   * Implements hook_entity_bundle_field_info_alter().
   *
   * @param array<string, \Drupal\Core\Field\FieldDefinitionInterface> $fields
   *   The field definitions of the bundle.
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   * @param string $bundle
   *   The bundle.
   */
  #[Hook('entity_bundle_field_info_alter')]
  public function bundleFieldInfoAlter(array &$fields, EntityTypeInterface $entity_type, string $bundle): void {
    if ($entity_type->id() === 'paragraph' && $bundle === PbVideo::BUNDLE && isset($fields[PbVideo::FIELD_VIDEO])) {
      $fields[PbVideo::FIELD_VIDEO]->addConstraint('PbVideoRequirements');
    }
  }

  /**
   * Implements hook_field_widget_single_element_form_alter().
   *
   * Warns the editor when a self-hosted video has no captions. It is a
   * message, not a violation, so it never blocks the save.
   *
   * @param array<string, mixed> $element
   *   The widget element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array<string, mixed> $context
   *   The widget context.
   */
  #[Hook('field_widget_single_element_form_alter')]
  public function widgetAlter(array &$element, FormStateInterface $form_state, array $context): void {
    $items = $context['items'] ?? NULL;
    if (!$items instanceof FieldItemListInterface) {
      return;
    }
    $definition = $items->getFieldDefinition();
    if ($definition->getName() !== PbVideo::FIELD_VIDEO || $definition->getTargetBundle() !== PbVideo::BUNDLE) {
      return;
    }
    // The media library widget copies its selection into $items on every
    // rebuild, so this also follows a video chosen without saving.
    $media = $items->entity;
    if (!$media instanceof MediaInterface || $media->bundle() !== PbVideo::MEDIA_FILE) {
      return;
    }
    if (!$media->hasField(PbVideo::MEDIA_FIELD_CAPTIONS) || !$media->get(PbVideo::MEDIA_FIELD_CAPTIONS)->isEmpty()) {
      return;
    }
    $element['pb_video_captions_warning'] = [
      '#theme' => 'status_messages',
      '#message_list' => [
        'warning' => [
          $this->t('This video has no captions file. Add a captions file on the video if it has speech or other sound people need. The Player shows captions from that file.'),
        ],
      ],
      '#status_headings' => [
        'warning' => $this->t('Warning message'),
      ],
      '#weight' => -100,
    ];
  }

}
