<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Service\Theme;

/**
 * Resolves which PB theme-bridge library matches the active front theme.
 */
interface PbThemeBridgeServiceInterface {

  /**
   * Bridge identifier for library suffix (`theme-bridge-{id}`).
   */
  public const string BRIDGE_APEX = 'apex';

  /**
   * Bridge identifier for Solo and Solo sub-themes.
   */
  public const string BRIDGE_SOLO = 'solo';

  /**
   * Bridge identifier for the W3CSS theme and its sub-themes.
   */
  public const string BRIDGE_W3CSS = 'w3css';

  /**
   * Bridge identifier for themes without Solo/APEX/W3CSS in the base chain.
   */
  public const string BRIDGE_NEUTRAL = 'neutral';

  /**
   * Resolve bridge id from the active theme hierarchy.
   *
   * @return string
   *   One of {@see self::BRIDGE_APEX}, {@see self::BRIDGE_SOLO},
   *   {@see self::BRIDGE_W3CSS} or {@see self::BRIDGE_NEUTRAL}.
   */
  public function resolveBridgeId(): string;

  /**
   * Drupal library machine name (without extension prefix).
   *
   * @return string
   *   e.g. `theme-bridge-apex`.
   */
  public function resolveLibraryName(): string;

}
