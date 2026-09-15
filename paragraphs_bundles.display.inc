<?php

/**
 * @file
 * Display tab form display helpers for paragraphs_bundles.
 *
 * Loaded from paragraphs_bundles.module so bundle sub-module update hooks
 * can call paragraphs_bundles_pb_nest_display_field_groups().
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */

declare(strict_types=1);

use Drupal\Core\Entity\Display\EntityFormDisplayInterface;

/**
 * A config entity property as an array, whatever get() actually returned.
 *
 * EntityDisplay::get() is typed mixed, and every caller here immediately
 * indexes the result. Normalising once means a property that is missing or
 * somehow not an array yields an empty array instead of a fatal on offset
 * access part-way through an upgrade, with the site left half-migrated.
 *
 * Lives here rather than in the .install because .module require_once's this
 * file, so it is available to runtime code and update hooks alike; the
 * .install is only ever loaded with the module already bootstrapped.
 *
 * @param mixed $value
 *   Raw return value of a config entity get().
 *
 * @return array<string, mixed>
 *   The value when it is an array, otherwise an empty array.
 */
function paragraphs_bundles_pb_array_prop(mixed $value): array {
  if (!is_array($value)) {
    return [];
  }
  $normalized = [];
  foreach ($value as $key => $item) {
    $normalized[(string) $key] = $item;
  }
  return $normalized;
}

/**
 * Loads a paragraph bundle's default form display.
 *
 * The storage returns EntityInterface, which has no get()/set(); narrowing to
 * the display interface here means a wrong or missing entity is a no-op
 * rather than a fatal in the middle of an update hook.
 *
 * @param string $bundle
 *   Paragraph type machine name.
 *
 * @return \Drupal\Core\Entity\Display\EntityFormDisplayInterface|null
 *   The default form display, or NULL when there is not one.
 */
function paragraphs_bundles_pb_load_form_display(string $bundle): ?EntityFormDisplayInterface {
  $display = \Drupal::entityTypeManager()
    ->getStorage('entity_form_display')
    ->load('paragraph.' . $bundle . '.default');

  return $display instanceof EntityFormDisplayInterface ? $display : NULL;
}

/**
 * Display tab nested field group definitions keyed by group machine name.
 *
 * Canonical field → group map for every PB bundle. Documented in
 * docs/planning/CURSOR-HANDOFF-PB-VVJ.md §6.1 — keep in sync when
 * adding bundle-specific pb_display_* fields.
 *
 * @return array<string, array{
 *   label: string,
 *   weight: int,
 *   open: bool,
 *   classes: string,
 *   fields: list<string>,
 *   }>
 *   Field group metadata keyed by group machine name.
 */
function paragraphs_bundles_pb_display_subgroup_definitions(): array {
  return [
    'group_pb_display_colors' => [
      'label' => 'Colors',
      'weight' => 10,
      'open' => TRUE,
      'classes' => 'pb__display-colors',
      'fields' => [
        'pb_display_bg_opacity',
        'pb_display_bg',
        'pb_display_bg_hover',
        'pb_display_text',
        'pb_display_text_hover',
        'pb_display_border_color',
        'pb_display_border_hover',
        'pb_display_link',
        'pb_display_link_hover',
      ],
    ],
    'group_pb_display_border' => [
      'label' => 'Border & radius',
      'weight' => 20,
      'open' => FALSE,
      'classes' => 'pb__display-border',
      'fields' => [
        'pb_display_border',
        'pb_display_border_radius',
      ],
    ],
    'group_pb_display_spacing' => [
      'label' => 'Spacing',
      'weight' => 30,
      'open' => FALSE,
      'classes' => 'pb__display-spacing',
      'fields' => [
        'pb_display_margin',
        'pb_display_padding',
      ],
    ],
    'group_pb_display_size' => [
      'label' => 'Size & effects',
      'weight' => 40,
      'open' => FALSE,
      'classes' => 'pb__display-size',
      'fields' => [
        'pb_display_width',
        'pb_display_shadow',
      ],
    ],
    'group_pb_display_advanced' => [
      'label' => 'Advanced',
      'weight' => 60,
      // Closed, like every other display group. Opening it by default was
      // tried and rejected by the maintainer: the Display tab is a stack of
      // collapsed sections, and one section standing open breaks that rhythm
      // for a field most authors never touch.
      'open' => FALSE,
      'classes' => 'pb__display-advanced',
      'fields' => [
        'pb_display_css_classes',
      ],
    ],
    'group_pb_display_extra' => [
      'label' => 'Additional',
      'weight' => 50,
      'open' => FALSE,
      'classes' => 'pb__display-extra',
      'fields' => [
        'pb_display_alert_start_date',
        'pb_display_alert_end_date',
        'pb_display_hero',
      ],
    ],
  ];
}

/**
 * Applies optional Display tab group field moves after canonical assignment.
 *
 * Bundle sub-modules pass overrides when calling
 * paragraphs_bundles_pb_nest_display_field_groups().
 *
 * @param array<string, list<string>> $children_by_group
 *   Group id => field machine names (modified in place).
 * @param array<string, mixed> $content
 *   Form display content components, as config stores them.
 * @param array<string, array{remove?: list<string>, add?: list<string>}> $group_overrides
 *   Group id => remove/add field lists.
 */
function paragraphs_bundles_pb_apply_bundle_display_group_overrides(
  array &$children_by_group,
  array $content,
  array $group_overrides,
): void {
  foreach ($group_overrides as $group_id => $override) {
    $children = $children_by_group[$group_id] ?? [];
    foreach ($override['remove'] ?? [] as $field_name) {
      $children = array_values(array_filter(
        $children,
        static fn(string $name): bool => $name !== $field_name,
      ));
    }
    foreach ($override['add'] ?? [] as $field_name) {
      if (isset($content[$field_name]) && !in_array($field_name, $children, TRUE)) {
        $children[] = $field_name;
      }
    }
    if ($group_id === 'group_pb_display_extra') {
      $children = paragraphs_bundles_pb_sort_extra_field_names($children, $content);
    }
    $children_by_group[$group_id] = $children;
  }
}

/**
 * Flattens Display tab field group children into a field name list.
 *
 * @param array<string, mixed> $field_groups
 *   Field group definitions from the form display, as config stores them.
 * @param list<string> $children
 *   Child names from a field group.
 *
 * @return list<string>
 *   Field machine names in encounter order.
 */
function paragraphs_bundles_pb_flatten_display_group_children(array $field_groups, array $children): array {
  $fields = [];
  foreach ($children as $child) {
    if (!is_string($child) || $child === '') {
      continue;
    }
    $group = paragraphs_bundles_pb_array_prop($field_groups[$child] ?? NULL);
    if (isset($group['children']) && is_array($group['children'])) {
      $fields = array_merge(
        $fields,
        paragraphs_bundles_pb_flatten_display_group_children(
          $field_groups,
          array_values(array_filter($group['children'], 'is_string')),
        ),
      );
      continue;
    }
    $fields[] = $child;
  }
  return $fields;
}

/**
 * Widget weights for Colors group fields (Drupal sorts by weight, not list).
 *
 * Opacity must be 16 so it renders before bg (17) on v1-upgraded sites where
 * opacity was left at 18+.
 *
 * @return array<string, int>
 *   Field machine name => form display weight.
 */
function paragraphs_bundles_pb_display_color_weights(): array {
  return [
    'pb_display_bg_opacity' => 16,
    'pb_display_bg' => 17,
    'pb_display_bg_hover' => 18,
    'pb_display_text' => 19,
    'pb_display_text_hover' => 20,
    'pb_display_border_color' => 21,
    'pb_display_border_hover' => 22,
    'pb_display_link' => 23,
    'pb_display_link_hover' => 24,
  ];
}

/**
 * Legacy v1 field_group machine names → v2 names.
 *
 * @return array<string, string>
 *   Old group id => current group id.
 */
function paragraphs_bundles_pb_legacy_field_group_renames(): array {
  return [
    'group_tabs_wrapper' => 'group_pb_tabs_wrapper',
    'group_content' => 'group_pb_tab_content',
    'group_display' => 'group_pb_tab_display',
  ];
}

/**
 * Normalizes one field_group reference (key, parent, or child name).
 */
function paragraphs_bundles_pb_normalize_group_reference(string $reference): string {
  $legacy = paragraphs_bundles_pb_legacy_field_group_renames();
  if (isset($legacy[$reference])) {
    return $legacy[$reference];
  }
  return str_replace('group_tab_', 'group_pb_tab_', $reference);
}

/**
 * Renames legacy v1 field_group keys (group_tab_* → group_pb_tab_*).
 *
 * @param array<string, mixed> $field_groups
 *   Field group definitions from a form display (by reference).
 *
 * @return bool
 *   TRUE when any group key, parent_name, or child reference changed.
 */
function paragraphs_bundles_pb_normalize_field_group_names(array &$field_groups): bool {
  $changed = FALSE;
  $renamed = [];
  foreach ($field_groups as $key => $group) {
    if (!is_string($key) || !is_array($group)) {
      continue;
    }
    $new_key = paragraphs_bundles_pb_normalize_group_reference($key);
    if ($new_key !== $key) {
      $changed = TRUE;
    }
    if (isset($group['parent_name']) && is_string($group['parent_name'])) {
      $new_parent = paragraphs_bundles_pb_normalize_group_reference($group['parent_name']);
      if ($new_parent !== $group['parent_name']) {
        $group['parent_name'] = $new_parent;
        $changed = TRUE;
      }
    }
    if (isset($group['children']) && is_array($group['children'])) {
      $group['children'] = array_map(
        static function (mixed $child) use (&$changed): mixed {
          if (!is_string($child)) {
            return $child;
          }
          $new_child = paragraphs_bundles_pb_normalize_group_reference($child);
          if ($new_child !== $child) {
            $changed = TRUE;
          }
          return $new_child;
        },
        $group['children'],
      );
    }
    $renamed[$new_key] = $group;
  }
  $field_groups = $renamed;
  return $changed;
}

/**
 * Removes a field from every field_group children list.
 *
 * @param array<string, mixed> $field_groups
 *   Field group definitions (by reference).
 * @param string $field_name
 *   Field machine name to remove.
 */
function paragraphs_bundles_pb_remove_field_from_field_groups(
  array &$field_groups,
  string $field_name,
): void {
  foreach ($field_groups as &$group) {
    if (!is_array($group) || !isset($group['children']) || !is_array($group['children'])) {
      continue;
    }
    $group['children'] = array_values(array_filter(
      $group['children'],
      static fn(mixed $child): bool => $child !== $field_name,
    ));
  }
  unset($group);
}

/**
 * Ensures link color widgets sit in Display → Colors on the form display.
 *
 * Idempotent surgical pass after nest — handles orphaned widgets and legacy
 * v1 tab group names.
 *
 * @param string $bundle
 *   Paragraph type machine name.
 */
function paragraphs_bundles_pb_ensure_link_fields_in_colors_group(string $bundle): void {
  $fd = paragraphs_bundles_pb_load_form_display($bundle);
  if (!$fd) {
    return;
  }

  $content = paragraphs_bundles_pb_array_prop($fd->get('content'));
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

  $third = paragraphs_bundles_pb_array_prop($fd->get('third_party_settings'));
  if (empty($third['field_group']) || !is_array($third['field_group'])) {
    paragraphs_bundles_pb_nest_display_field_groups($bundle);
    return;
  }

  $field_groups = paragraphs_bundles_pb_array_prop($third['field_group']);
  paragraphs_bundles_pb_normalize_field_group_names($field_groups);

  foreach ($link_fields as $field_name) {
    paragraphs_bundles_pb_remove_field_from_field_groups($field_groups, $field_name);
  }

  if (!isset($field_groups['group_pb_display_colors'])) {
    $third['field_group'] = $field_groups;
    $fd->set('third_party_settings', $third);
    $fd->save();
    paragraphs_bundles_pb_nest_display_field_groups($bundle);
    return;
  }

  $colors_group = paragraphs_bundles_pb_array_prop($field_groups['group_pb_display_colors'] ?? NULL);
  $colors = $colors_group['children'] ?? [];
  if (!is_array($colors)) {
    $colors = [];
  }
  $colors = array_values(array_filter(
    $colors,
    static fn(mixed $child): bool => is_string($child)
      && !in_array($child, $link_fields, TRUE),
  ));

  $border_hover = array_search('pb_display_border_hover', $colors, TRUE);
  if ($border_hover !== FALSE) {
    array_splice($colors, (int) $border_hover + 1, 0, $link_fields);
  }
  else {
    $after_bg = array_search('pb_display_bg', $colors, TRUE);
    if ($after_bg !== FALSE) {
      array_splice($colors, (int) $after_bg + 1, 0, $link_fields);
    }
    else {
      foreach ($link_fields as $field_name) {
        if (isset($content[$field_name])) {
          $colors[] = $field_name;
        }
      }
    }
  }
  $colors_group['children'] = $colors;
  $field_groups['group_pb_display_colors'] = $colors_group;

  $tab_display = paragraphs_bundles_pb_array_prop($field_groups['group_pb_tab_display'] ?? NULL);
  if (isset($tab_display['children']) && is_array($tab_display['children'])) {
    $children = array_values(array_filter(
      $tab_display['children'],
      static fn(mixed $child): bool => is_string($child)
        && !in_array($child, $link_fields, TRUE),
    ));
    if (!in_array('group_pb_display_colors', $children, TRUE)) {
      array_unshift($children, 'group_pb_display_colors');
    }
    $tab_display['children'] = $children;
    $field_groups['group_pb_tab_display'] = $tab_display;
  }

  $tabs_wrapper = paragraphs_bundles_pb_array_prop($field_groups['group_pb_tabs_wrapper'] ?? NULL);
  if (isset($tabs_wrapper['children']) && is_array($tabs_wrapper['children'])) {
    $tabs_wrapper['children'] = array_values(array_filter(
      $tabs_wrapper['children'],
      static fn(mixed $child): bool => is_string($child)
        && !in_array($child, $link_fields, TRUE),
    ));
    $field_groups['group_pb_tabs_wrapper'] = $tabs_wrapper;
  }

  $third['field_group'] = $field_groups;
  $fd->set('third_party_settings', $third);
  $fd->save();
}

/**
 * Sort bucket for Additional display fields grouped by widget type.
 *
 * Lower values render first. Within a bucket, original order is kept.
 *
 * @param string $widget_type
 *   Field widget plugin ID from the form display.
 *
 * @return int
 *   Sort bucket (10 = selects, 20 = colors, 30 = numbers, …).
 */
function paragraphs_bundles_pb_display_extra_widget_bucket(string $widget_type): int {
  return match ($widget_type) {
    'options_select', 'options_buttons' => 10,
    'color_text_widget' => 20,
    'number', 'string_textfield' => 30,
    'boolean_checkbox' => 40,
    'datetime_default', 'datetime_datelist' => 50,
    'range_number_widget' => 60,
    'image_image', 'media_library_widget' => 70,
    default => 80,
  };
}

/**
 * Sorts Additional display field names by widget type.
 *
 * @param list<string> $field_names
 *   Field machine names in the Additional group.
 * @param array<string, mixed> $content
 *   Form display content components, as config stores them.
 *
 * @return list<string>
 *   Field names sorted selects → colors → numbers → checkboxes → …
 */
function paragraphs_bundles_pb_sort_extra_field_names(array $field_names, array $content): array {
  $indexed = [];
  foreach ($field_names as $index => $field_name) {
    if (!is_string($field_name) || $field_name === '') {
      continue;
    }
    $component = paragraphs_bundles_pb_array_prop($content[$field_name] ?? NULL);
    $widget_type = isset($component['type']) && is_scalar($component['type'])
      ? (string) $component['type']
      : '';
    $indexed[] = [
      'name' => $field_name,
      'bucket' => paragraphs_bundles_pb_display_extra_widget_bucket($widget_type),
      'index' => $index,
    ];
  }
  usort($indexed, static function (array $a, array $b): int {
    $cmp = $a['bucket'] <=> $b['bucket'];
    return $cmp !== 0 ? $cmp : $a['index'] <=> $b['index'];
  });
  return array_map(static fn(array $row): string => $row['name'], $indexed);
}

/**
 * Re-sorts Additional display fields on a bundle form display.
 *
 * @param string $bundle
 *   Paragraph type machine name.
 */
function paragraphs_bundles_pb_sort_display_extra_fields(string $bundle): void {
  $fd = paragraphs_bundles_pb_load_form_display($bundle);
  if (!$fd) {
    return;
  }

  $third = paragraphs_bundles_pb_array_prop($fd->get('third_party_settings'));
  $field_groups = paragraphs_bundles_pb_array_prop($third['field_group'] ?? NULL);
  $extra_group = paragraphs_bundles_pb_array_prop($field_groups['group_pb_display_extra'] ?? NULL);
  $children = $extra_group['children'] ?? [];
  if (!is_array($children) || $children === []) {
    return;
  }

  $content = paragraphs_bundles_pb_array_prop($fd->get('content'));
  $sorted = paragraphs_bundles_pb_sort_extra_field_names(
    array_values(array_filter($children, 'is_string')),
    $content,
  );
  if ($sorted === $children) {
    return;
  }

  $extra_group['children'] = $sorted;
  $field_groups['group_pb_display_extra'] = $extra_group;
  $third['field_group'] = $field_groups;
  $fd->set('third_party_settings', $third);
  $fd->save();
}

/**
 * Nests Display tab fields into collapsible field_group sections.
 *
 * Idempotent — safe to run after link wiring or color re-order passes.
 *
 * @param string $bundle
 *   Paragraph type machine name.
 * @param array<string, array{remove?: list<string>, add?: list<string>}> $group_overrides
 *   Optional bundle-specific field moves (from the bundle sub-module).
 */
function paragraphs_bundles_pb_nest_display_field_groups(
  string $bundle,
  array $group_overrides = [],
): void {
  $color_weights = paragraphs_bundles_pb_display_color_weights();

  $fd = paragraphs_bundles_pb_load_form_display($bundle);
  if (!$fd) {
    return;
  }

  $content = paragraphs_bundles_pb_array_prop($fd->get('content'));
  foreach ($color_weights as $field_name => $weight) {
    if (isset($content[$field_name])) {
      $component = paragraphs_bundles_pb_array_prop($content[$field_name]);
      $component['weight'] = $weight;
      $content[$field_name] = $component;
    }
  }
  $fd->set('content', $content);

  $third = paragraphs_bundles_pb_array_prop($fd->get('third_party_settings'));
  if (empty($third['field_group']) || !is_array($third['field_group'])) {
    $fd->save();
    return;
  }

  $field_groups = paragraphs_bundles_pb_array_prop($third['field_group']);
  paragraphs_bundles_pb_normalize_field_group_names($field_groups);
  $third['field_group'] = $field_groups;
  $fd->set('third_party_settings', $third);

  $tab_display = paragraphs_bundles_pb_array_prop($field_groups['group_pb_tab_display'] ?? NULL);
  $tab_children = $tab_display['children'] ?? [];
  if (!is_array($tab_children) || $tab_children === []) {
    $fd->save();
    return;
  }

  $display_fields = paragraphs_bundles_pb_flatten_display_group_children(
    $field_groups,
    array_values(array_filter($tab_children, 'is_string')),
  );
  // Widgets added by link wiring may exist in content but not yet appear in
  // the Display tab tree (typical v1→v2 upgrade). Include them so nest assigns
  // them to the Colors group.
  foreach (array_keys($content) as $field_name) {
    if (!is_string($field_name) || !str_starts_with($field_name, 'pb_display_')) {
      continue;
    }
    if (!in_array($field_name, $display_fields, TRUE)) {
      $display_fields[] = $field_name;
    }
  }
  foreach ($group_overrides as $override) {
    foreach ($override['add'] ?? [] as $field_name) {
      if (isset($content[$field_name]) && !in_array($field_name, $display_fields, TRUE)) {
        $display_fields[] = $field_name;
      }
    }
  }
  $display_fields = array_values(array_unique($display_fields));
  $present = array_fill_keys($display_fields, TRUE);

  $definitions = paragraphs_bundles_pb_display_subgroup_definitions();
  $assigned = [];
  foreach ($definitions as $group_id => $definition) {
    foreach ($definition['fields'] as $field_name) {
      $assigned[$field_name] = $group_id;
    }
  }

  $extra_fields = [];
  foreach ($display_fields as $field_name) {
    if (isset($assigned[$field_name])) {
      continue;
    }
    if (str_starts_with($field_name, 'pb_display_')) {
      $extra_fields[] = $field_name;
    }
  }

  $display_group_ids = [];
  $children_by_group = [];
  foreach ($definitions as $group_id => $definition) {
    $children = array_values(array_filter(
      $definition['fields'],
      static fn(string $field_name): bool => isset($present[$field_name])
    ));
    if ($group_id === 'group_pb_display_extra') {
      $children = array_values(array_unique(array_merge($children, $extra_fields)));
    }
    $children_by_group[$group_id] = $children;
  }

  paragraphs_bundles_pb_apply_bundle_display_group_overrides(
    $children_by_group,
    $content,
    $group_overrides,
  );

  foreach ($definitions as $group_id => $definition) {
    $children = $children_by_group[$group_id] ?? [];
    if ($group_id === 'group_pb_display_extra') {
      $children = paragraphs_bundles_pb_sort_extra_field_names($children, $content);
    }
    if ($children === []) {
      unset($field_groups[$group_id]);
      continue;
    }

    $field_groups[$group_id] = [
      'children' => $children,
      'label' => $definition['label'],
      'region' => 'content',
      'parent_name' => 'group_pb_tab_display',
      'weight' => $definition['weight'],
      'format_type' => 'details',
      'format_settings' => [
        'classes' => $definition['classes'],
        'show_empty_fields' => FALSE,
        'id' => '',
        'label_as_html' => FALSE,
        'open' => $definition['open'],
        'description' => '',
        'required_fields' => TRUE,
      ],
    ];
    $display_group_ids[] = $group_id;
  }

  foreach (array_keys($field_groups) as $group_id) {
    if (!str_starts_with($group_id, 'group_pb_display_')) {
      continue;
    }
    if (!in_array($group_id, $display_group_ids, TRUE)) {
      unset($field_groups[$group_id]);
    }
  }

  usort($display_group_ids, static function (string $a, string $b) use ($definitions): int {
    return $definitions[$a]['weight'] <=> $definitions[$b]['weight'];
  });
  $tab_display['children'] = $display_group_ids;
  $field_groups['group_pb_tab_display'] = $tab_display;

  $third['field_group'] = $field_groups;
  $fd->set('third_party_settings', $third);
  $fd->save();
}

/**
 * Ensures Display tab color fields are ordered base then hover per group.
 *
 * @param string $bundle
 *   Paragraph type machine name.
 */
function paragraphs_bundles_pb_order_display_color_fields(string $bundle): void {
  paragraphs_bundles_pb_nest_display_field_groups($bundle);
}
