<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Service\Form;

/**
 * Validates Display tab field_group layout for paragraph bundle form displays.
 */
final class PbFormDisplayVerifier implements PbFormDisplayVerifierInterface {

  /**
   * {@inheritdoc}
   */
  public function verify(
    array $formDisplay,
    bool $expectsTitleFields,
    bool $rejectLegacyTabGroups = FALSE,
  ): array {
    $errors = [];
    $groups = $formDisplay['third_party_settings']['field_group'] ?? [];
    if (!is_array($groups)) {
      $errors[] = 'third_party_settings.field_group missing or invalid';
      return $errors;
    }

    if ($rejectLegacyTabGroups) {
      $legacy = [
        'group_tab_content',
        'group_tab_display',
        'group_tabs_wrapper',
        'group_content',
        'group_display',
      ];
      foreach (array_keys($groups) as $group_id) {
        if (is_string($group_id) && in_array($group_id, $legacy, TRUE)) {
          $errors[] = "Legacy v1 field group still present: {$group_id} (run drush updb)";
        }
      }
    }

    $content = $formDisplay['content'] ?? [];
    if (!is_array($content)) {
      $errors[] = 'content section missing';
      return $errors;
    }

    if ($expectsTitleFields) {
      $this->verifyContentTab($groups, $content, $errors);
      $this->verifyTitleDependencies($formDisplay, $errors);
    }

    $this->verifyDisplayTab($groups, $content, $errors);

    return $errors;
  }

  /**
   * Verifies Content tab field order and weights.
   *
   * @param array<string, mixed> $groups
   *   Field group definitions from the form display.
   * @param array<string, mixed> $content
   *   Form display content section.
   * @param list<string> $errors
   *   Collected validation errors (by reference).
   */
  private function verifyContentTab(array $groups, array $content, array &$errors): void {
    $contentChildren = $groups['group_pb_tab_content']['children'] ?? NULL;
    if (!is_array($contentChildren) || count($contentChildren) < 3) {
      $errors[] = 'group_pb_tab_content.children must list title fields first';
      return;
    }

    $titleBlock = [
      'pb_content_title',
      'pb_content_title_tag',
      'pb_content_title_alignment',
    ];
    for ($i = 0; $i < 3; $i++) {
      if (($contentChildren[$i] ?? NULL) !== $titleBlock[$i]) {
        $errors[] = 'Content tab: pb_content_title, pb_content_title_tag, and '
          . 'pb_content_title_alignment must be the first three children '
          . '(in that order). Found: '
          . implode(', ', array_slice($contentChildren, 0, 5));
        break;
      }
    }

    $titleWeights = [
      'pb_content_title' => 1,
      'pb_content_title_tag' => 2,
      'pb_content_title_alignment' => 3,
    ];
    foreach ($titleWeights as $field => $expected) {
      $actual = $content[$field]['weight'] ?? NULL;
      if ($actual !== $expected) {
        $errors[] = "content.{$field}.weight must be {$expected} (found "
          . var_export($actual, TRUE) . ')';
      }
    }

    foreach (array_slice($contentChildren, 3) as $child) {
      if (!is_string($child)) {
        continue;
      }
      if (str_starts_with($child, 'group_')) {
        $groupWeight = $groups[$child]['weight'] ?? NULL;
        if ($groupWeight !== NULL && $groupWeight <= 3) {
          $errors[] = "field_group.{$child}.weight must be > 3 (found "
            . "{$groupWeight}); Drupal sorts Content tab by weight";
        }
        continue;
      }
      $fieldWeight = $content[$child]['weight'] ?? NULL;
      if ($fieldWeight !== NULL && $fieldWeight <= 3) {
        $errors[] = "content.{$child}.weight must be > 3 (found {$fieldWeight}); "
          . 'bundle fields must not share weights 1–3 with title tag/alignment';
      }
    }
  }

  /**
   * Verifies title-related config dependencies are declared.
   *
   * @param array<string, mixed> $formDisplay
   *   Entity form display export array.
   * @param list<string> $errors
   *   Collected validation errors (by reference).
   */
  private function verifyTitleDependencies(array $formDisplay, array &$errors): void {
    $bundle = $formDisplay['bundle'] ?? NULL;
    if (!is_string($bundle) || $bundle === '') {
      return;
    }

    $deps = $formDisplay['dependencies']['config'] ?? [];
    if (!is_array($deps)) {
      $errors[] = 'dependencies.config missing';
      return;
    }

    foreach ([
      "field.field.paragraph.{$bundle}.pb_content_title_tag",
      "field.field.paragraph.{$bundle}.pb_content_title_alignment",
      "field.field.paragraph.{$bundle}.pb_display_link",
      "field.field.paragraph.{$bundle}.pb_display_link_hover",
    ] as $dep) {
      if (!in_array($dep, $deps, TRUE)) {
        $errors[] = "dependencies.config missing: {$dep}";
      }
    }
  }

  /**
   * Verifies Display tab nesting and Colors group field order.
   *
   * @param array<string, mixed> $groups
   *   Field group definitions from the form display.
   * @param array<string, mixed> $content
   *   Form display content section.
   * @param list<string> $errors
   *   Collected validation errors (by reference).
   */
  private function verifyDisplayTab(array $groups, array $content, array &$errors): void {
    $link_fields = ['pb_display_link', 'pb_display_link_hover'];
    $has_link = FALSE;
    foreach ($link_fields as $field_name) {
      if (isset($content[$field_name])) {
        $has_link = TRUE;
        break;
      }
    }
    if (!$has_link) {
      return;
    }

    $displayChildren = $groups['group_pb_tab_display']['children']
      ?? $groups['group_tab_display']['children']
      ?? $groups['group_display']['children']
      ?? [];
    if (!is_array($displayChildren)) {
      $errors[] = 'Display tab field group missing (group_pb_tab_display)';
      return;
    }

    foreach ($displayChildren as $child) {
      if (is_string($child) && in_array($child, $link_fields, TRUE)) {
        $errors[] = "Link color field orphaned on Display tab: {$child} "
          . '(must be inside group_pb_display_colors)';
      }
      if (is_string($child) && str_starts_with($child, 'pb_display_')) {
        $errors[] = "Flat display field under Display tab: {$child} "
          . '(must be inside a nested group)';
      }
    }

    $tabsChildren = $groups['group_pb_tabs_wrapper']['children']
      ?? $groups['group_tabs_wrapper']['children']
      ?? [];
    if (is_array($tabsChildren)) {
      foreach ($tabsChildren as $child) {
        if (is_string($child) && in_array($child, $link_fields, TRUE)) {
          $errors[] = "Link color field outside tabs: {$child}";
        }
      }
    }

    $colorChildren = $groups['group_pb_display_colors']['children'] ?? [];
    if (!is_array($colorChildren)) {
      $errors[] = 'group_pb_display_colors.children missing';
      return;
    }

    $borderHoverIndex = array_search('pb_display_border_hover', $colorChildren, TRUE);
    $linkIndex = array_search('pb_display_link', $colorChildren, TRUE);
    $linkHoverIndex = array_search('pb_display_link_hover', $colorChildren, TRUE);

    if ($linkIndex === FALSE) {
      $errors[] = 'pb_display_link missing from group_pb_display_colors.children';
    }
    if ($linkHoverIndex === FALSE) {
      $errors[] = 'pb_display_link_hover missing from group_pb_display_colors.children';
    }
    if ($linkIndex !== FALSE && $linkHoverIndex !== FALSE
      && $linkHoverIndex !== $linkIndex + 1) {
      $errors[] = 'pb_display_link_hover must immediately follow pb_display_link '
        . 'in Colors group';
    }
    if ($borderHoverIndex !== FALSE && $linkIndex !== FALSE
      && $linkIndex !== $borderHoverIndex + 1) {
      $errors[] = 'pb_display_link must immediately follow pb_display_border_hover '
        . 'in Colors group';
    }

    if (isset($content['pb_display_bg_opacity'], $content['pb_display_bg'])) {
      $opacityWeight = $content['pb_display_bg_opacity']['weight'] ?? 99;
      $bgWeight = $content['pb_display_bg']['weight'] ?? 99;
      if ($opacityWeight >= $bgWeight) {
        $errors[] = 'pb_display_bg_opacity weight must be less than pb_display_bg '
          . "(found opacity {$opacityWeight}, bg {$bgWeight})";
      }
    }

    $opacityIndex = array_search('pb_display_bg_opacity', $colorChildren, TRUE);
    $bgIndex = array_search('pb_display_bg', $colorChildren, TRUE);
    if ($opacityIndex !== FALSE && $bgIndex !== FALSE && $opacityIndex > $bgIndex) {
      $errors[] = 'pb_display_bg_opacity must appear before pb_display_bg in Colors group';
    }
  }

}
