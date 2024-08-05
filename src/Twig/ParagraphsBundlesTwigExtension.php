<?php

namespace Drupal\paragraphs_bundles\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Class ParagraphsBundlesTwigExtension.
 *
 * Provides a custom Twig extension for decoding HTML entities.
 *
 * @package Drupal\paragraphs_bundles\Twig
 */
class ParagraphsBundlesTwigExtension extends AbstractExtension {

  /**
   * {@inheritdoc}
   */
  public function getFilters(): array {
    return [
      new TwigFilter('decode_entities', $this->decodeEntities(...)),
    ];
  }

  /**
   * Decode HTML entities.
   *
   * @param string $string
   *   The string to decode.
   *
   * @return string
   *   The decoded string.
   */
  public function decodeEntities(string $string): string {
    return html_entity_decode($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
  }

}
