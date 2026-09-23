<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Hook;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Markup;
use Drupal\Core\Template\Attribute;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs_bundles\Service\Bundle\BundleSluggerInterface;
use Drupal\paragraphs_bundles\Service\Css\PbCssFileManagerInterface;
use Drupal\paragraphs_bundles\Service\Css\PbDynamicCssBuilder;
use Drupal\paragraphs_bundles\Service\Css\PbDynamicCssBuilderInterface;
use Drupal\paragraphs_bundles\Service\Display\PbDisplayClassResolverInterface;
use Drupal\paragraphs_bundles\Utility\PbRenderArray;
use Psr\Log\LoggerInterface;

/**
 * Preprocess hook — exposes per-paragraph data to Twig.
 *
 * Sets extra variables on every paragraph render:
 *   - `pb_paragraph_id`: DOM id (`pb-{slug}-{id}`, one form for every bundle).
 *   - `pb_display_classes`: inner-wrapper classes from this bundle's display
 *     fields, in the bundle's declared field order.
 *   - `pb_dynamic_library`: library id when a dynamic CSS file exists,
 *     otherwise NULL (Twig must not attach a missing library).
 */
final class PbPreprocessHook {

  /**
   * Paragraph bundles owned by Paragraphs Bundles (cached per request).
   *
   * @var list<string>|null
   */
  private ?array $managedBundles = NULL;

  public function __construct(
    private readonly BundleSluggerInterface $slugger,
    private readonly PbCssFileManagerInterface $cssFiles,
    private readonly PbDynamicCssBuilderInterface $dynamicCss,
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly LoggerInterface $logger,
    private readonly PbDisplayClassResolverInterface $displayClasses,
    private readonly CacheBackendInterface $cache,
  ) {}

  /**
   * Implements hook_preprocess_paragraph().
   *
   * @param array<string, mixed> $variables
   *   Render variables (passed by reference by the dispatcher).
   */
  #[Hook('preprocess_paragraph')]
  public function preprocessParagraph(array &$variables): void {
    $paragraph = $variables['paragraph'] ?? NULL;
    if (!$paragraph instanceof ParagraphInterface) {
      return;
    }
    if (!in_array($paragraph->bundle(), $this->managedBundles(), TRUE)) {
      return;
    }
    // Seed the base classes in preprocess (not only the template) so they
    // survive a theme's paragraph-template override. The module's CSS and
    // per-paragraph dynamic CSS both hook onto `.paragraphs-bundles`, so
    // losing it silently breaks styling. Matches v1's belt + suspenders.
    $this->pinBaseClasses($variables);
    // Author-supplied classes go on the OUTER element, in preprocess for the
    // same reason as the base classes: a theme override must not lose them.
    $this->applyCustomClasses($variables, $paragraph);

    $slug = $this->slugger->slugify($paragraph->bundle());
    $id = (int) $paragraph->id();

    // The DOM id and the generated CSS scope come from one function, so a
    // template can never carry an id the stylesheet does not target.
    $variables['pb_paragraph_id'] = PbDynamicCssBuilder::outerElementId($slug, $id);
    // Inner-wrapper classes for this bundle's display fields. Every template
    // used to rebuild this with an identical five-line Twig loop over its own
    // copy of the field list; the list is a DOM contract and now has one home.
    $variables['pb_display_classes'] = $this->displayClasses->innerClasses($paragraph);
    $this->attachDynamicCss($variables, $paragraph, $slug, $id);
  }

  /**
   * Attaches the paragraph's dynamic CSS; self-heals a missing file.
   *
   * Fast path: when the per-paragraph CSS file exists, expose its cacheable
   * library id for the template to attach. Fallback: when the file is missing
   * (fresh deploy, wiped `public://` files, or content migrated without a
   * re-save) the named library isn't registered — regenerating the file here
   * can't be resolved by `attach_library` this request. So inline the generated
   * CSS in the page head instead, guaranteeing the paragraph never renders
   * unstyled. The cacheable file/library path resumes once the paragraph is
   * saved or `drush pb:regenerate-css` runs. Empty CSS needs nothing.
   *
   * @param array<string, mixed> $variables
   *   Paragraph preprocess variables (by reference).
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph being rendered.
   * @param string $slug
   *   Bundle slug.
   * @param int $id
   *   Paragraph id.
   */
  private function attachDynamicCss(
    array &$variables,
    ParagraphInterface $paragraph,
    string $slug,
    int $id,
  ): void {
    if ($this->cssFiles->exists($slug, $id)) {
      $variables['pb_dynamic_library'] = 'paragraphs_bundles/dynamic--' . $slug . '-' . $id;
      return;
    }

    $variables['pb_dynamic_library'] = NULL;
    $css = $this->dynamicCss->build($paragraph);
    if ($css === '') {
      return;
    }
    // Last-line guard before the only unescaped sink in the module.
    // Markup::create() asserts the string is already safe, so html_tag emits
    // it verbatim without Xss::filterAdmin(). Values are validated upstream
    // (colour shape at the field layer, structural characters stripped from
    // user vars), which means generated CSS can never legitimately contain
    // '<'. If one appears, an upstream guarantee has regressed — drop the
    // inline block rather than emit it, and leave a trace.
    if (str_contains($css, '<')) {
      $this->logger->error(
        'Refused to inline dynamic CSS for paragraph @id (@slug): generated source contained an unexpected "<". This indicates a sanitization regression upstream.',
        ['@id' => $id, '@slug' => $slug],
      );
      return;
    }
    PbRenderArray::append($variables, ['#attached', 'html_head'], [
      [
        '#type' => 'html_tag',
        '#tag' => 'style',
        '#value' => Markup::create($css),
        '#attributes' => ['data-pb-dynamic' => $slug . '-' . $id],
      ],
      'pb-dynamic-css-' . $slug . '-' . $id,
    ]);
  }

  /**
   * Paragraph bundle machine names owned by Paragraphs Bundles + sub-modules.
   *
   * Built once per request from each PB module's optional
   * `paragraphs.paragraphs_type.*.yml` config (v1 parity).
   *
   * @return list<string>
   *   Sorted, unique bundle ids.
   */
  private function managedBundles(): array {
    if ($this->managedBundles !== NULL) {
      return $this->managedBundles;
    }
    // The answer only changes when a PB module is installed or uninstalled,
    // but it used to be recomputed on every request — 27 directory scans to
    // learn something that had not changed. Key the cache on the module list
    // so an install or uninstall produces a different key and recomputes,
    // with no invalidation hook to forget to fire.
    $moduleNames = ['paragraphs_bundles'];
    foreach (array_keys($this->moduleHandler->getModuleList()) as $name) {
      if (str_starts_with($name, 'paragraph_bundle_')) {
        $moduleNames[] = $name;
      }
    }
    sort($moduleNames);
    $cid = 'paragraphs_bundles:managed_bundles:' . hash('xxh128', implode(',', $moduleNames));
    $cached = $this->cache->get($cid);
    if ($cached !== FALSE && is_array($cached->data)) {
      // Only trust the entry if every value is a bundle name. A cache written
      // by a different version of this code — or by anything else that lands
      // on the same cid — would otherwise be returned as `list<string>` and
      // fed straight into template suggestions. Recompute instead.
      $bundles = array_values(array_filter($cached->data, 'is_string'));
      if (count($bundles) === count($cached->data)) {
        $this->managedBundles = $bundles;
        return $bundles;
      }
    }

    $bundles = [];
    foreach (array_unique($moduleNames) as $moduleName) {
      $path = $this->moduleHandler->getModule($moduleName)->getPath();
      $optional = $path . '/config/optional';
      if (!is_dir($optional)) {
        continue;
      }
      $pattern = $optional . '/paragraphs.paragraphs_type.*.yml';
      foreach (glob($pattern) ?: [] as $file) {
        $base = basename($file);
        if (preg_match('#^paragraphs\.paragraphs_type\.(.+)\.yml$#', $base, $m)) {
          $bundles[] = $m[1];
        }
      }
    }
    $bundles = array_values(array_unique($bundles));
    sort($bundles);
    $this->cache->set($cid, $bundles);
    $this->managedBundles = $bundles;
    return $bundles;
  }

  /**
   * Seeds `paragraph` + `paragraphs-bundles` classes on the wrapper.
   *
   * Done in preprocess so the classes are present even when a theme overrides
   * the bundle template. Handles the attributes value as an Attribute object
   * or a plain array.
   *
   * @param array<string, mixed> $variables
   *   Paragraph preprocess variables (by reference).
   */
  private function pinBaseClasses(array &$variables): void {
    $attributes = $variables['attributes'] ?? NULL;
    if ($attributes instanceof Attribute) {
      $attributes->addClass('paragraph', 'paragraphs-bundles');
      return;
    }
    if (!is_array($attributes)) {
      $attributes = [];
    }
    $classes = $attributes['class'] ?? [];
    if (!is_array($classes)) {
      $classes = (array) $classes;
    }
    // Normalise before array_diff(): a theme can leave a Markup object in the
    // class list, and array_diff() casts every element to string — which is a
    // fatal on an array element, not a skipped one.
    $normalized = [];
    foreach ($classes as $class) {
      if (is_string($class)) {
        $normalized[] = $class;
      }
      elseif ($class instanceof \Stringable) {
        $normalized[] = (string) $class;
      }
    }
    $classes = array_values(
      array_diff($normalized, ['paragraph', 'paragraphs-bundles']),
    );
    array_unshift($classes, 'paragraph', 'paragraphs-bundles');
    $attributes['class'] = $classes;
    $variables['attributes'] = $attributes;
  }

  /**
   * Adds the author's `pb_display_css_classes` to the outer element.
   *
   * The point of the field is a class shared by every instance of a bundle,
   * so a site can write one rule and style them all. Sanitising happens in
   * the resolver; this only places what survived.
   *
   * @param array<string, mixed> $variables
   *   Paragraph preprocess variables (by reference).
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph being rendered.
   */
  private function applyCustomClasses(array &$variables, ParagraphInterface $paragraph): void {
    $classes = $this->displayClasses->customClasses($paragraph);
    if ($classes === []) {
      return;
    }
    $attributes = $variables['attributes'] ?? NULL;
    if ($attributes instanceof Attribute) {
      $attributes->addClass(...$classes);
      return;
    }
    if (!is_array($attributes)) {
      $attributes = [];
    }
    $existing = $attributes['class'] ?? [];
    if (!is_array($existing)) {
      $existing = (array) $existing;
    }
    // Same normalisation as pinBaseClasses(): a theme can leave a Markup
    // object here, and array_merge would carry it into the class list.
    $normalized = [];
    foreach ($existing as $class) {
      if (is_string($class)) {
        $normalized[] = $class;
      }
      elseif ($class instanceof \Stringable) {
        $normalized[] = (string) $class;
      }
    }
    foreach ($classes as $class) {
      if (!in_array($class, $normalized, TRUE)) {
        $normalized[] = $class;
      }
    }
    $attributes['class'] = $normalized;
    $variables['attributes'] = $attributes;
  }

}
