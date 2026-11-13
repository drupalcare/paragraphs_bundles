<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_video\Plugin\Validation\Constraint;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\media\MediaInterface;
use Drupal\paragraph_bundle_video\PbVideo;
use Drupal\paragraphs\ParagraphInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Refuses a remote video in Background mode and a video without a poster.
 */
final class PbVideoRequirementsConstraintValidator extends ConstraintValidator {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    if (!$value instanceof FieldItemListInterface || $value->isEmpty()) {
      return;
    }
    $paragraph = $value->getEntity();
    if (!$paragraph instanceof ParagraphInterface || $paragraph->bundle() !== PbVideo::BUNDLE) {
      return;
    }
    $media = $value->entity;
    if (!$media instanceof MediaInterface) {
      return;
    }

    $background = $paragraph->hasField(PbVideo::FIELD_MODE)
      && $paragraph->get(PbVideo::FIELD_MODE)->value === PbVideo::MODE_BACKGROUND;
    if ($background && $media->bundle() === PbVideo::MEDIA_REMOTE) {
      $this->context->addViolation((string) $this->t('Background mode needs an uploaded video file. Choose an uploaded video, or change the mode to Player.'));
    }

    if ($media->bundle() === PbVideo::MEDIA_FILE
      && $paragraph->hasField(PbVideo::FIELD_POSTER)
      && $paragraph->get(PbVideo::FIELD_POSTER)->isEmpty()) {
      $this->context->addViolation((string) $this->t('An uploaded video needs a poster image.'));
    }
  }

}
