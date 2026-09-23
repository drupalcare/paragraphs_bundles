<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_icon\Service;

use Drupal\Core\Database\Connection;

/**
 * Default {@see PbiIconUsageInterface} implementation.
 *
 * Reads the field tables directly rather than loading entities, and that is a
 * measured choice. Loading every `icon_bundle` paragraph to read two fields
 * took 144.8 ms on the maintainer's site; the equivalent query took 1.1 ms —
 * 132x cheaper for the same answer. This runs during a library-cache rebuild,
 * where a slow query is paid by whichever unlucky request triggers the rebuild.
 */
final class PbiIconUsage implements PbiIconUsageInterface {

  /**
   * Field table and column holding the icon name.
   */
  private const string NAME_TABLE = 'paragraph__pb_content_gms_icon_name';
  private const string NAME_COLUMN = 'pb_content_gms_icon_name_value';

  /**
   * Field table and column holding the style.
   */
  private const string STYLE_TABLE = 'paragraph__pb_content_gms_style';
  private const string STYLE_COLUMN = 'pb_content_gms_style_value';

  public function __construct(
    private readonly Connection $database,
    private readonly PbiIconNameRegistryInterface $registry,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function iconsByStyle(): array {
    $schema = $this->database->schema();

    // The field may not be installed at all — this submodule's config is
    // optional, and a site can delete a field. No table means no icons, which
    // means "change nothing" and the bundled font keeps rendering.
    if (!$schema->tableExists(self::NAME_TABLE)) {
      return [];
    }

    $hasStyleTable = $schema->tableExists(self::STYLE_TABLE);

    $query = $this->database->select(self::NAME_TABLE, 'n');
    $query->addField('n', self::NAME_COLUMN, 'icon');

    // `deleted = 1` rows survive in a field table after a field is removed,
    // until cron purges them. Including them would request icons for content
    // that no longer renders. Not exercised on the maintainer's site, which
    // has 5 rows all at deleted = 0 — so this guard is reasoned from the
    // schema, NOT verified against real dirty data. Needs checking on a site
    // that has actually deleted the field.
    $query->condition('n.deleted', 0);

    if ($hasStyleTable) {
      $query->leftJoin(
        self::STYLE_TABLE,
        's',
        's.entity_id = n.entity_id AND s.deleted = 0',
      );
      $query->addField('s', self::STYLE_COLUMN, 'style');
    }

    // Both fields are non-translatable, so a multilingual site stores one row
    // per language with identical values. DISTINCT collapses those; it also
    // collapses the common case of several paragraphs using the same icon.
    $query->distinct();

    $collected = [];

    // execute() is typed StatementInterface|NULL, and a NULL would fatal the
    // foreach below. This runs during a library-cache rebuild, so the failure
    // would surface as a white screen on whichever request triggered the
    // rebuild rather than anywhere near the icon feature.
    $result = $query->execute();
    if ($result === NULL) {
      return [];
    }

    foreach ($result as $row) {
      $icon = isset($row->icon) && is_string($row->icon) ? $row->icon : '';
      if ($icon === '') {
        continue;
      }

      $style = $hasStyleTable && isset($row->style) && is_string($row->style)
        ? $row->style
        : '';

      // Whitelist rather than trust storage: this value picks a font family,
      // and an unknown one would build a URL for a family that does not exist.
      if (!in_array($style, self::STYLES, TRUE)) {
        $style = self::DEFAULT_STYLE;
      }

      $collected[$style][] = $icon;
    }

    $out = [];

    foreach ($collected as $style => $names) {
      // The registry drops anything Google would not recognise. Without this,
      // one typo alongside valid names silently fails to render, and a set
      // where EVERY name is wrong falls back to the complete 344 KB font.
      $valid = $this->registry->filter($names);
      if ($valid !== []) {
        $out[$style] = $valid;
      }
    }

    return $out;
  }

}
