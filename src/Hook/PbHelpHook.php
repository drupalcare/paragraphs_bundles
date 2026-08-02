<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Hook;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Markup;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\filter\FilterPluginManager;
use Drupal\filter\Plugin\FilterInterface;

/**
 * Help hook — renders README.md on the module's help page.
 *
 * Mirror of v1's `paragraphs_bundles_help()`. When the `markdown`
 * module is enabled the README is processed through it; otherwise the
 * raw text is wrapped in `<pre>`.
 */
final class PbHelpHook {

  public function __construct(
    private readonly ModuleExtensionList $moduleList,
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly FilterPluginManager $filterManager,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Implements hook_help().
   *
   * @param string $routeName
   *   Current route name.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   Current route match (unused here, accepted for hook contract).
   *
   * @return string|\Drupal\Component\Render\MarkupInterface|null
   *   Rendered help text, or NULL when the route doesn't match.
   */
  #[Hook('help')]
  public function help(string $routeName, RouteMatchInterface $routeMatch): string|MarkupInterface|null {
    if ($routeName !== 'help.page.paragraphs_bundles') {
      return NULL;
    }

    $path = $this->moduleList->getPath('paragraphs_bundles') . '/README.md';
    if (!is_file($path) || !is_readable($path)) {
      return NULL;
    }
    $text = (string) file_get_contents($path);

    if (!$this->moduleHandler->moduleExists('markdown')) {
      return Markup::create('<pre>' . Html::escape($text) . '</pre>');
    }

    $settings = $this->configFactory->get('markdown.settings')->getRawData();
    $filter = $this->filterManager->createInstance('markdown', ['settings' => $settings]);
    // createInstance() is typed object. A broken or shadowed markdown plugin
    // would otherwise fatal on the help page; fall back to escaped source.
    if (!$filter instanceof FilterInterface) {
      return Markup::create('<pre>' . Html::escape($text) . '</pre>');
    }
    return $filter->process($text, 'en')->getProcessedText();
  }

}
