<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Service\Form;

/**
 * Validates paragraph form display field_group layout (Display tab contract).
 */
interface PbFormDisplayVerifierInterface {

  /**
   * Validates a form display export (YAML array or entity export).
   *
   * @param array<string, mixed> $formDisplay
   *   Form display `content`, `third_party_settings`, `dependencies`, etc.
   * @param bool $expectsTitleFields
   *   TRUE when the bundle ships pb_content_title (full Content tab contract).
   * @param bool $rejectLegacyTabGroups
   *   TRUE on active config: fail when v1 group_tab_* names remain.
   *
   * @return list<string>
   *   Human-readable errors; empty when valid.
   */
  public function verify(
    array $formDisplay,
    bool $expectsTitleFields,
    bool $rejectLegacyTabGroups = FALSE,
  ): array;

}
