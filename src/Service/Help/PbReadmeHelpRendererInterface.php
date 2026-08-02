<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Service\Help;

use Drupal\Component\Render\MarkupInterface;

/**
 * Renders a module's README.md as its help-page content.
 *
 * Every Paragraphs Bundles module implements `hook_help()` the same way, so the
 * implementation lives here once and each module's hook class is a delegation.
 */
interface PbReadmeHelpRendererInterface {

  /**
   * Renders README.md for a module, when the route is that module's help page.
   *
   * @param string $routeName
   *   Current route name, as passed to hook_help().
   * @param string $module
   *   Machine name of the module whose README should be rendered.
   *
   * @return string|\Drupal\Component\Render\MarkupInterface|null
   *   Rendered help text, or NULL when the route does not belong to $module or
   *   the module ships no readable README.md.
   */
  public function render(string $routeName, string $module): string|MarkupInterface|null;

}
