<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Service\Help;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Render\Markup;
use Drupal\filter\FilterPluginManager;
use Drupal\filter\Plugin\FilterInterface;

/**
 * Renders a module's README.md as its help-page content.
 *
 * Mirror of v1's `paragraphs_bundles_help()`. When the `markdown` module is
 * enabled the README is processed through it; otherwise the raw text is
 * escaped and wrapped in `<pre>`.
 */
final class PbReadmeHelpRenderer implements PbReadmeHelpRendererInterface {

  public function __construct(
    private readonly ModuleExtensionList $moduleList,
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly FilterPluginManager $filterManager,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function render(string $routeName, string $module): string|MarkupInterface|null {
    if ($routeName !== 'help.page.' . $module) {
      return NULL;
    }

    $path = $this->moduleList->getPath($module) . '/README.md';
    if (!is_file($path) || !is_readable($path)) {
      return NULL;
    }
    $text = (string) file_get_contents($path);

    if (!$this->moduleHandler->moduleExists('markdown')) {
      return $this->escaped($text);
    }

    $settings = $this->configFactory->get('markdown.settings')->getRawData();
    $filter = $this->filterManager->createInstance('markdown', ['settings' => $settings]);
    // createInstance() is documented to return object, not FilterInterface. A
    // broken or shadowed markdown plugin would otherwise fatal on the help
    // page; fall back to escaped source.
    if (!$filter instanceof FilterInterface) {
      return $this->escaped($text);
    }
    return $filter->process($text, 'en')->getProcessedText();
  }

  /**
   * Wraps raw README source in a preformatted block, escaped.
   *
   * @param string $text
   *   Raw README source.
   *
   * @return string|\Drupal\Component\Render\MarkupInterface
   *   Safe markup. Markup::create() hands back a plain string for empty input.
   */
  private function escaped(string $text): string|MarkupInterface {
    return Markup::create('<pre>' . Html::escape($text) . '</pre>');
  }

}
