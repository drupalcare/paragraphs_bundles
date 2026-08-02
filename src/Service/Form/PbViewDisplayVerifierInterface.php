<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Service\Form;

/**
 * Validates paragraph view display layout (v2 display-field contract).
 */
interface PbViewDisplayVerifierInterface {

  /**
   * Validates a view display export (YAML array or entity export).
   *
   * @param array<string, mixed> $viewDisplay
   *   View display `content`, `hidden`, etc.
   *
   * @return list<string>
   *   Human-readable errors; empty when valid.
   */
  public function verify(array $viewDisplay): array;

}
