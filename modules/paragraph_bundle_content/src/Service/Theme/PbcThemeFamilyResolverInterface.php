<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_content\Service\Theme;

/**
 * Resolves the active front-end theme into a PB content layout family.
 */
interface PbcThemeFamilyResolverInterface {

  /**
   * Theme family identifier for Solo and Solo sub-themes.
   */
  public const string FAMILY_SOLO = 'solo';

  /**
   * Theme family identifier for APEX Theme and apex_* sub-themes.
   */
  public const string FAMILY_APEX = 'apex';

  /**
   * Fallback when the active theme is neither Solo nor APEX.
   */
  public const string FAMILY_OTHER = 'other';

  /**
   * Resolve the layout family for the active theme.
   *
   * @return string
   *   One of {@see self::FAMILY_SOLO}, {@see self::FAMILY_APEX},
   *   or {@see self::FAMILY_OTHER}.
   */
  public function resolveActiveFamily(): string;

}
