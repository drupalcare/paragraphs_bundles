<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_patterns\Hook;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\paragraphs_bundles\Service\Help\PbReadmeHelpRendererInterface;

/**
 * Help hook: renders README.md on the module help page.
 */
final class PatternHelpHook {

  public function __construct(
    private readonly PbReadmeHelpRendererInterface $readmeHelp,
  ) {}

  /**
   * Implements hook_help().
   *
   * @param string $routeName
   *   Current route name.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   Current route match (unused).
   *
   * @return string|\Drupal\Component\Render\MarkupInterface|null
   *   Rendered help text, or NULL when the route does not match.
   */
  #[Hook('help')]
  public function help(string $routeName, RouteMatchInterface $routeMatch): string|MarkupInterface|null {
    return $this->readmeHelp->render($routeName, 'paragraphs_bundles_patterns');
  }

}
