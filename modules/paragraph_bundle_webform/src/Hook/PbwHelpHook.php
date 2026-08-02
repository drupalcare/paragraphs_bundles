<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_webform\Hook;

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
 * Help hook — renders README.md on the module help page.
 */
final class PbwHelpHook {

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
   *   Current route match (unused).
   *
   * @return string|\Drupal\Component\Render\MarkupInterface|null
   *   Rendered help text, or NULL when the route does not match.
   */
  #[Hook('help')]
  public function help(string $routeName, RouteMatchInterface $routeMatch): string|MarkupInterface|null {
    if ($routeName !== 'help.page.paragraph_bundle_webform') {
      return NULL;
    }

    $path = $this->moduleList->getPath('paragraph_bundle_webform') . '/README.md';
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
